"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { showUnitToast } from "../treatment-units/UnitToast";
import type { ApiResult } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { minuteOf } from "@/lib/members";
import { notificationsHref, typeKey, writeErrorKey } from "@/lib/notifications";
import { cancelScheduled, fetchScheduled, type ScheduledItem, type ScheduledList } from "@/lib/notificationsApi";

const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);
const STATUS: Record<ScheduledItem["status"], MessageKey> = { pending: "nt.sc.st.pending", running: "nt.sc.st.running", done: "nt.sc.st.done", failed: "nt.sc.st.failed", cancelled: "nt.sc.st.cancelled" };
const KNOWN_ERRORS: Record<string, MessageKey> = { "missed: too late to send": "nt.sc.err.missed", "bad payload": "nt.sc.err.bad_payload", no_recipients: "nt.err.no_recipients", nobody_allowed: "nt.err.nobody_allowed" };

/** The planned sends: when, who planned it, what it says and what became of it; a waiting one can be cancelled. */
export default function ScheduledTab({ enabled, onChanged }: { enabled: boolean; onChanged: () => void }) {
  const t = useT();
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<{ key: number; res: ApiResult<ScheduledList> } | null>(null);
  const [asking, setAsking] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!enabled) return;
    const c = new AbortController();
    void fetchScheduled(c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      setGot({ key: tick, res });
    });
    return () => c.abort();
  }, [enabled, tick]);

  if (!enabled) return <Notice icon="info">{t("nt.sc.off")}</Notice>;
  const loading = got?.key !== tick;
  const list = got?.res.ok ? got.res.data : null;
  const failed = got !== null && !got.res.ok;

  const cancel = async (id: number) => {
    if (busy) return;
    setBusy(true);
    setError(null);
    const res = await cancelScheduled(id);
    setBusy(false);
    if (res.ok) {
      setAsking(null);
      showUnitToast({ tone: "ok", message: t("nt.sc.ok"), closeLabel: t("nt.close") });
      setTick((n) => n + 1);
      onChanged();
      return;
    }
    if (res.status === 401) return leaveToLogin();
    setError(t(writeErrorKey(res)));
    setAsking(null);
    setTick((n) => n + 1);
    onChanged();
  };

  const target = (p: ScheduledItem): string => {
    const base = (["all", "recent_7days", "event_entrants", "specific_event", "individual", "selected"].includes(p.target) ? t(`nt.target.${p.target}` as MessageKey) : p.target);
    return p.target === "selected" && p.memberCount !== null ? `${base} ${p.memberCount}` : base;
  };
  const reason = (p: ScheduledItem): string => (p.error === "" ? "" : KNOWN_ERRORS[p.error] ? t(KNOWN_ERRORS[p.error]) : p.error);
  const actions = (p: ScheduledItem) =>
    p.status === "pending" ? (
      asking === p.id ? (
        <span className="nt-ask">
          <span>{t("nt.sc.ask")}</span>
          <button type="button" className="btn btn--ghost" onClick={() => setAsking(null)} disabled={busy}>{t("nt.sc.no")}</button>
          <button type="button" className="btn btn--primary" onClick={() => void cancel(p.id)} disabled={busy} aria-busy={busy}>{t("nt.sc.yes")}</button>
        </span>
      ) : (
        <button type="button" className="btn btn--ghost" onClick={() => setAsking(p.id)}>{t("nt.sc.cancel")}</button>
      )
    ) : p.sendId !== null ? (
      <Link className="btn btn--ghost" href={notificationsHref("history", p.sendId)} replace scroll={false}>{t("nt.sc.view")}</Link>
    ) : null;

  return (
    <div className="nt-sc">
      <div className="nt-toolbar">
        <p className="nt-toolbar__n">{list ? t("nt.h.count", { n: list.items.length }) : " "}</p>
        <button type="button" className="btn btn--ghost" onClick={() => setTick((n) => n + 1)} disabled={loading}><Icon name="refresh" size={16} /> {t("nt.h.refresh")}</button>
      </div>
      {error ? <Notice tone="warn" icon="alert">{error}</Notice> : null}
      {failed ? <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost" onClick={() => setTick((n) => n + 1)}>{t("nt.h.retry")}</button>}>{t("nt.h.failed")}</Notice> : null}
      {list && !list.available ? <Notice tone="warn" icon="alert">{t("nt.sc.unavailable")}</Notice> : null}
      {loading && !list ? <div className="nt-skel" aria-hidden="true"><i /><i /><i /></div> : null}
      {list && list.available && list.items.length === 0 ? <p className="nt-empty">{t("nt.sc.empty")}</p> : null}
      {list && list.items.length > 0 ? (
        <div className="nt-plan" role="table" aria-label={t("nt.tab.scheduled")} aria-busy={loading}>
          <div className="nt-plan__head" role="row">
            <span role="columnheader">{t("nt.sc.time")}</span><span role="columnheader" className="nt-plan__adm">{t("nt.sc.admin")}</span><span role="columnheader">{t("nt.sc.status")}</span>
            <span role="columnheader">{t("nt.sc.type")}</span><span role="columnheader">{t("nt.sc.title")}</span><span role="columnheader">{t("nt.sc.act")}</span>
          </div>
          {list.items.map((p) => (
            <div key={p.id} className="nt-pl" role="row">
              <span className="nt-pl__time" role="cell">{minuteOf(p.runAt)}<small className="nt-pl__by">{p.adminUsername}</small></span>
              <span className="nt-plan__adm" role="cell">{p.adminUsername}</span>
              <span role="cell"><span className={`nt-st nt-st--${p.status}`}>{t(STATUS[p.status])}</span></span>
              <span className="nt-pl__type" role="cell">{t(typeKey(p.type))}</span>
              <span className="nt-pl__body" role="cell">
                <strong>{p.title}</strong>
                <small>{target(p)}{p.nightPolicy === "delay" ? ` · ${t("nt.sc.delay")}` : ""}</small>
                {p.status === "failed" && reason(p) !== "" ? <small className="nt-pl__err">{reason(p)}</small> : null}
              </span>
              <span className="nt-pl__act" role="cell">{actions(p)}</span>
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}
