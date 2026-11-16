"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useRef, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { Notice, Section } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, NotFoundScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { ackArchive, fetchArchiveItem, fetchArchiveList, fetchMe, type ArchiveDetail as ArchiveDetailData, type Me } from "@/lib/api";
import { announceArchiveDue } from "@/lib/archive";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatDayTime, formatNumber } from "@/lib/format";
import { failureKey } from "@/lib/hospitalEdit";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "unavailable" | "forbidden" | "redirecting" | "notFound" | "error";

type State = {
  phase: Phase;
  errorKind: ErrorKind;
  me: Me | null;
  item: ArchiveDetailData | null;
  settled: number;
};

const INITIAL: State = { phase: "loading", errorKind: "load", me: null, item: null, settled: -1 };

/** Stored hospital columns that have a name in the hospital pages; any other column is listed under its stored name. */
const FIELD_KEYS = new Map<string, MessageKey>([
  ["name", "h.f.name"],
  ["owner_name", "h.f.owner"],
  ["business_number", "h.f.businessNumber"],
  ["phone", "h.f.phone"],
  ["email", "h.f.email"],
  ["address", "h.f.address"],
  ["introduction", "h.f.intro"],
  ["website", "h.f.website"],
  ["categories", "h.tags.categories"],
  ["badges", "h.tags.badges"],
  ["ad_grade", "h.status.grade"],
  ["treatment_tags", "h.tags.treatments"],
  ["subway_names", "h.tags.subways"],
  ["sns_url", "h.f.sns"],
  ["kakao_channel_url", "h.f.kakao"],
]);
const snake = (field: string): string => field.replace(/[A-Z]/g, (c) => `_${c.toLowerCase()}`);

/** One deleted hospital: the sealed financial summary, the stored information, and the one-month notice. */
export default function HospitalArchiveDetail({ id }: { id: string }) {
  const t = useT();
  const searchParams = useSearchParams();
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  const [acking, setAcking] = useState(false);
  const [ackError, setAckError] = useState<MessageKey | null>(null);
  const halted = useRef(false);
  const archiveId = /^[1-9]\d{0,9}$/.test(id) ? Number(id) : 0;
  const busy = state.settled !== attempt && state.phase !== "forbidden" && state.phase !== "redirecting";

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const [me, item] = await Promise.all([
        fetchMe(controller.signal),
        archiveId > 0 ? fetchArchiveItem(archiveId, controller.signal) : Promise.resolve({ ok: false as const, status: 404 }),
      ]);
      if (controller.signal.aborted) return;
      const statuses = [me, item].map((r) => (r.ok ? 200 : r.status));

      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      if (statuses.includes(403) || (me.ok && me.data.admin.scope !== "all")) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok && !me.data.features.archive) {
        setState({ ...INITIAL, phase: "error", errorKind: "archiveOff", me: me.data, settled: attempt });
        return;
      }
      if (me.ok && item.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, item: item.data, settled: attempt });
        return;
      }
      if (!me.ok) {
        const kind: ErrorKind = me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load";
        setState({ ...INITIAL, phase: "error", errorKind: kind, settled: attempt });
        return;
      }
      const status = item.ok ? 200 : item.status;
      if (status === 404) setState({ ...INITIAL, phase: "notFound", me: me.data, settled: attempt });
      else if (status === 503) setState({ ...INITIAL, phase: "unavailable", me: me.data, settled: attempt });
      else setState({ ...INITIAL, phase: "error", errorKind: "load", me: me.data, settled: attempt });
    };
    void run();
    return () => controller.abort();
  }, [archiveId, attempt]);

  const retry = () => setAttempt((n) => n + 1);
  const { phase, me, item } = state;

  const ack = async () => {
    if (!item || acking) return;
    setAcking(true);
    setAckError(null);
    const res = await ackArchive(item.id);
    setAcking(false);
    if (res.ok) {
      const list = await fetchArchiveList(1, 10);
      if (list.ok) announceArchiveDue(list.data.dueCount);
      retry();
      return;
    }
    if (res.status === 401) {
      halted.current = true;
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    setAckError(failureKey(res.status));
  };

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("ha.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="super" />;
  else if (phase === "notFound") screen = <NotFoundScreen backHref="/hospital-archive" backLabel={t("ha.back")} />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={busy} />;

  const actions = (
    <div className="page-actions">
      <Link className="btn btn--ghost" href="/hospital-archive">{t("ha.back")}</Link>
    </div>
  );
  const money = (n: number) => t("hs.money", { n: formatNumber(n) });

  return (
    <AdminShell
      admin={me?.admin ?? null}
      features={me?.features ?? null}
      title={item ? item.name || t("h.noName") : t("ha.d.title")}
      description={t("ha.d.desc")}
      actions={actions}
      screen={screen}
    >
      {phase === "unavailable" ? (
        <section className="state state--inline" role="status">
          <div className="state__in">
            <div className="state__ring"><Icon name="unplug" size={22} /></div>
            <h2 className="state__h">{t("ha.unavailable.title")}</h2>
            <p>{t("ha.unavailable.body")}</p>
            <div className="state__acts">
              <button type="button" className="btn btn--primary" onClick={retry} disabled={busy}>{t("state.retry")}</button>
            </div>
          </div>
        </section>
      ) : null}
      {item ? (
        <>
          <div role="status">
            {searchParams.get("deleted") === "1" ? <Notice icon="check">{t("hd.flash.deleted")}</Notice> : null}
          </div>
          {item.noticeDue ? (
            <Notice
              tone="warn"
              icon="alert"
              action={
                <button type="button" className="btn btn--primary" onClick={ack} disabled={acking}>
                  {acking ? t("h.act.working") : t("ha.d.ack")}
                </button>
              }
            >
              {t("ha.d.due")}
            </Notice>
          ) : item.noticeAckedAt ? (
            <Notice icon="check">{t("ha.d.acked", { at: formatDayTime(item.noticeAckedAt) })}</Notice>
          ) : null}
          {ackError ? <Notice tone="warn" icon="alert">{t(ackError)}</Notice> : null}

          <Section id="sec-a-sum" title={t("ha.d.sum.title")} desc={t("ha.d.sum.desc")}>
            <dl className="panel dl dl--3">
              <Field label={t("ha.d.hospitalId")}>{item.hospitalId}</Field>
              <Field label={t("ha.d.deletedAt")}>{formatDayTime(item.deletedAt)}</Field>
              <Field label={t("ha.d.by")}><span translate="no">{item.deletedByName || "—"}</span></Field>
              <Field label={t("ha.d.bookings")}>{t("hs.bookingsValue", { n: formatNumber(item.bookingsTotal) })}</Field>
              <Field label={t("ha.d.cancelled")}>{t("hs.bookingsValue", { n: formatNumber(item.bookingsCancelled) })}</Field>
              <Field label={t("ha.d.paid")}>{money(item.salesPaid)}</Field>
              <Field label={t("ha.d.refunded")}>{money(item.salesRefunded)}</Field>
              <Field label={t("ha.d.net")}>{money(item.salesNet)}</Field>
            </dl>
          </Section>

          <Section id="sec-a-snap" title={t("ha.d.snap.title")} desc={t("ha.d.snap.desc")}>
            {item.snapshot.hospital.length > 0 ? (
              <dl className="panel dl">
                {item.snapshot.hospital.map((f) => {
                  const key = FIELD_KEYS.get(snake(f.key));
                  return (
                    <Field key={f.key} label={key ? t(key) : f.key} wide={f.value.length > 60}>
                      <span className="prewrap" translate="no">{f.value.trim() || "—"}</span>
                    </Field>
                  );
                })}
              </dl>
            ) : (
              <p className="panel panel--empty">{t("ha.d.snap.empty")}</p>
            )}
            <p className="archive__counts">{t("ha.d.counts", { images: formatNumber(item.snapshot.images), treatments: formatNumber(item.snapshot.treatments) })}</p>
          </Section>

          <Section id="sec-a-doctors" title={t("ha.d.doctors")} desc={t("ha.d.snap.desc")}>
            {item.snapshot.doctors.length > 0 ? (
              <ul className="panel archive__doctors">
                {item.snapshot.doctors.map((d, i) => (
                  <li key={`${d.id}-${i}`} className="archive__doctor">
                    <span className="archive__dname" translate="no">{d.name || t("h.noName")}</span>
                    {d.position ? <span className="archive__dpos" translate="no">{d.position}</span> : null}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="panel panel--empty">{t("ha.d.doctors.empty")}</p>
            )}
          </Section>
        </>
      ) : null}
    </AdminShell>
  );
}

function Field({ label, wide, children }: { label: string; wide?: boolean; children: ReactNode }) {
  return (
    <div className={wide ? "dl__item dl__item--wide" : "dl__item"}>
      <dt>{label}</dt>
      <dd>{children}</dd>
    </div>
  );
}
