"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { Notice, Pill, Section } from "../hospitals/parts";
import { showUnitToast } from "../treatment-units/UnitToast";
import DoctorEditDialog, { type DoctorDialogResult } from "./DoctorEditDialog";
import { LevelMark } from "./DoctorTreatmentsTab";
import DoctorPhoto from "./DoctorPhoto";
import HospitalCombo from "../payments/HospitalCombo";
import DoctorReorder from "./DoctorReorder";
import { fetchDoctorHospitals, fetchDoctors, fetchMe, type DoctorCard, type DoctorHospitalOption, type DoctorListData, type DoctorListQuery, type DoctorStatusFilter, type Me } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { DOCTOR_SEARCH_MAX, doctorsHref, hasDoctorFilters, levelLabelKey, levelSummary, parseDoctorQuery, statusLabelKey } from "@/lib/doctors";
import { formatNumber } from "@/lib/format";
import { pageWindow } from "@/lib/hospitals";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
type State = { phase: Phase; errorKind: ErrorKind; me: Me | null; list: DoctorListData | null; stale: boolean; settled: string };
const INITIAL: State = { phase: "loading", errorKind: "load", me: null, list: null, stale: false, settled: "" };
const STATUSES: DoctorStatusFilter[] = ["all", "active", "inactive", "resigned"];

/** Every doctor the admin may see: three counts, filters, cards, pages; opens the edit dialog and the order screen. */
export default function DoctorsPage() {
  const t = useT();
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryString = searchParams.toString();
  const query = useMemo(() => parseDoctorQuery(new URLSearchParams(queryString)), [queryString]);
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  const [hospitals, setHospitals] = useState<DoctorHospitalOption[]>([]);
  const [editId, setEditId] = useState(0);
  const [reorder, setReorder] = useState(false);
  const meRef = useRef<Me | null>(null);
  const halted = useRef(false);

  const requestId = `${doctorsHref(query)}#${attempt}`;
  const busy = state.settled !== requestId && state.phase !== "forbidden" && state.phase !== "redirecting";

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const known = meRef.current;
      const [me, list] = await Promise.all([known ? Promise.resolve({ ok: true as const, data: known }) : fetchMe(controller.signal), fetchDoctors(query, controller.signal)]);
      if (controller.signal.aborted) return;
      const statuses = [me, list].map((r) => (r.ok ? 200 : r.status));
      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      if (statuses.includes(403)) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok) meRef.current = me.data;
      if (me.ok && !me.data.features.doctors) {
        setState({ ...INITIAL, phase: "error", errorKind: "doctorsOff", me: me.data, settled: requestId });
        return;
      }
      if (me.ok && list.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, list: list.data, stale: false, settled: requestId });
        return;
      }
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !list.ok && list.status === 404 ? "doctorsOff" : !list.ok && list.status === 503 ? "session" : "load";
      setState((s) =>
        s.list && s.phase === "ready" && kind !== "disabled" && kind !== "doctorsOff"
          ? { ...s, stale: true, settled: requestId }
          : { ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : s.me, settled: requestId },
      );
    };
    void run();
    return () => controller.abort();
  }, [query, attempt, requestId]);

  // The hospital choices (the filter, the edit box, the order screen): fetched once.
  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchDoctorHospitals(controller.signal);
      if (!controller.signal.aborted && res.ok) setHospitals(res.data);
    };
    void run();
    return () => controller.abort();
  }, []);

  const go = (patch: Partial<DoctorListQuery>) => router.push(doctorsHref({ ...query, page: 1, ...patch }));
  const retry = () => setAttempt((n) => n + 1);

  const { phase, me, list, stale } = state;
  const isSuper = me?.admin.role === "super_admin";
  const scope = list?.scope ?? me?.admin.scope ?? "all";
  const canWrite = me?.features.doctorsWrite === true;
  const canOrder = canWrite && me?.features.doctorsOrder === true;
  const canDelete = canWrite && me?.features.doctorsDelete === true && isSuper;
  const reordering = canOrder && reorder;
  const addHospital = scope === "own" ? (me?.admin.hospitalId ?? 0) : query.hospitalId;
  const canAdd = me?.features.hospitalsDoctors === true;

  const done = (r: DoctorDialogResult) => {
    setEditId(0);
    const key = r.kind === "saved" ? "d.ed.saved" : r.kind === "retired" ? "d.rt.done" : r.kind === "deactivated" ? "d.ac.offDone" : r.kind === "activated" ? "d.ac.onDone" : "d.dl.done";
    showUnitToast({ tone: "ok", message: t(key, { name: r.name }), closeLabel: t("m.close") });
    retry();
  };

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("d.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={busy} />;

  const actions =
    me && list ? (
      <>
        {canOrder ? (
          <button type="button" className={reordering ? "btn btn--primary reo-toggle" : "btn btn--ghost reo-toggle"} onClick={() => { setReorder((on) => !on); if (reordering) retry(); }}>
            {t(reordering ? "d.reorder.end" : "d.reorder.start")}
          </button>
        ) : null}
        {canAdd && addHospital > 0 ? (
          <Link className="btn btn--primary" href={`/hospitals/${addHospital}`}>
            <Icon name="plus" size={16} />
            {t("d.add")}
          </Link>
        ) : null}
      </>
    ) : null;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("d.title")} description={t("d.desc")} screen={screen} actions={actions}>
      {scope === "own" ? <Notice>{t("d.scope.own")}</Notice> : null}
      {list ? (
        <>
          <ul className="dc-stats" role="group" aria-label={t("d.stats.group")}>
            <StatTile label={t("d.stats.total")} value={t("d.stats.person", { n: formatNumber(list.stats.total) })} />
            {scope === "own" ? null : <StatTile label={t("d.stats.hospitals")} value={t("d.stats.place", { n: formatNumber(list.stats.hospitals) })} />}
            <StatTile label={t("d.stats.active")} value={t("d.stats.person", { n: formatNumber(list.stats.active) })} />
          </ul>
          {canAdd && addHospital <= 0 && !reordering ? <p className="dc-addhint">{t("d.add.pick")}</p> : null}

          {reordering ? (
            <DoctorReorder
              key={`${scope}-${query.hospitalId}`}
              hospitals={hospitals}
              initialHospitalId={scope === "own" ? (me?.admin.hospitalId ?? 0) : query.hospitalId}
              fixedHospital={scope === "own"}
              onExit={() => { setReorder(false); retry(); }}
            />
          ) : (
            <>
              <div className="dc-filters">
                {isSuper ? (
                  <div className="dc-f-hospital">
                    <HospitalCombo
                      key={hospitals.length}
                      hospitals={hospitals}
                      value={query.hospitalId}
                      onChange={(id) => { if (id !== query.hospitalId) go({ hospitalId: id }); }}
                      label={t("d.f.hospital")}
                      placeholder={t("d.hc.searchAll")}
                      clearLabel={t("d.hc.clear")}
                      noneLabel={t("d.hc.none")}
                      allLabel={t("d.f.hospitalAll")}
                    />
                  </div>
                ) : null}
                <SearchForm key={query.search} initial={query.search} onSearch={(search) => go({ search })} />
                <div className="field dc-f-status">
                  <span className="field__label" id="dc-status-l">{t("d.f.status")}</span>
                  <div className="seg" role="group" aria-labelledby="dc-status-l">
                    {STATUSES.map((s) => (
                      <button key={s} type="button" aria-pressed={query.status === s} onClick={() => query.status !== s && go({ status: s })}>
                        {t(statusLabelKey(s))}
                      </button>
                    ))}
                  </div>
                </div>
              </div>

              <Section id="sec-d-list" title={t("d.list.count", { n: formatNumber(list.total) })} desc="">
                {stale ? (
                  <Notice tone="warn" icon="alert" action={<button type="button" className="txtbtn" onClick={retry} disabled={busy}>{t("state.retry")}</button>}>
                    {t("d.list.stale")}
                  </Notice>
                ) : null}
                <div className="list-head">
                  <p className="list-head__count" role="status" aria-live="polite">{busy ? t("d.list.busy") : t("d.list.count", { n: formatNumber(list.total) })}</p>
                  {hasDoctorFilters(query) ? <Link className="txtbtn" href="/doctors">{t("d.f.reset")}</Link> : null}
                </div>
                {list.items.length === 0 ? (
                  <div className="empty">
                    <Icon name="people" size={22} />
                    <p className="reo-state__t">{t(hasDoctorFilters(query) ? "d.none.title" : "d.empty.title")}</p>
                    <p>{t(hasDoctorFilters(query) ? "d.none.body" : "d.empty.body")}</p>
                  </div>
                ) : (
                  <ul className={busy ? "dc-cards is-busy" : "dc-cards"}>
                    {list.items.map((d) => (
                      <Card key={d.id} d={d} showHospital onOpen={() => setEditId(d.id)} canEdit={canWrite} />
                    ))}
                  </ul>
                )}
                <Pager query={query} total={list.total} />
              </Section>
            </>
          )}
        </>
      ) : null}
      {editId > 0 ? (
        <DoctorEditDialog key={editId} doctorId={editId} hospitals={hospitals} isSuper={isSuper} canWrite={canWrite} canDelete={canDelete} onDone={done} onClose={() => setEditId(0)} />
      ) : null}
    </AdminShell>
  );
}

function StatTile({ label, value }: { label: string; value: string }) {
  return (
    <li className="dc-stat">
      <span className="dc-stat__label">{label}</span>
      <span className="dc-stat__value">{value}</span>
    </li>
  );
}

function SearchForm({ initial, onSearch }: { initial: string; onSearch: (search: string) => void }) {
  const t = useT();
  const [value, setValue] = useState(initial);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    onSearch(value.trim().slice(0, DOCTOR_SEARCH_MAX));
  };
  return (
    <form className="dc-f-search" role="search" onSubmit={submit}>
      <label className="field field--grow">
        <span className="field__label">{t("d.f.search")}</span>
        <input className="input" type="search" name="search" value={value} maxLength={DOCTOR_SEARCH_MAX} placeholder={t("d.f.searchPh")} autoComplete="off" enterKeyHint="search" onChange={(e) => setValue(e.target.value)} />
      </label>
      <button type="submit" className="btn btn--primary">{t("d.f.submit")}</button>
    </form>
  );
}

function Card({ d, showHospital, canEdit, onOpen }: { d: DoctorCard; showHospital: boolean; canEdit: boolean; onOpen: () => void }) {
  const t = useT();
  const name = d.name || t("d.card.noName");
  const shown = d.treatments.slice(0, 3);
  const wideMore = d.treatments.length - 3;
  const narrowMore = d.treatments.length - 2;
  const resigned = d.status === "resigned";
  const levels = levelSummary(d.levelCounts);
  return (
    <li className={resigned ? "dc-card is-resigned" : d.status === "inactive" ? "dc-card is-inactive" : "dc-card"}>
      <div className="dc-card__top">
        <DoctorPhoto name={name} url={d.photoUrl} />
        <div className="dc-card__id">
          <div className="dc-card__nameRow">
            <h3 className="dc-card__name" title={name} translate="no">{name}</h3>
            <Pill tone={resigned ? "off" : d.status === "inactive" ? "warn" : "on"}>{t(resigned ? "d.status.resigned" : d.status === "inactive" ? "d.status.inactive" : "d.status.active")}</Pill>
          </div>
          <p className="dc-card__spec">{d.specialty || t("d.card.noSpecialty")}</p>
        </div>
      </div>
      {showHospital ? <p className="dc-card__hosp" title={d.hospitalName}>{d.hospitalName}</p> : null}
      {shown.length === 0 ? (
        <p className="dc-card__none">{t("d.card.noTreat")}</p>
      ) : (
        <ul className="chips dc-chips">
          {shown.map((x, i) => (
            <li key={`${i}-${x}`} className={i === 2 ? "chip dc-chip--3" : "chip"}>{x}</li>
          ))}
          {wideMore > 0 ? <li className="chip chip--more dc-more--wide">{t("d.card.more", { n: wideMore })}</li> : null}
          {narrowMore > 0 ? <li className="chip chip--more dc-more--narrow">{t("d.card.more", { n: narrowMore })}</li> : null}
        </ul>
      )}
      {levels.length > 0 ? (
        <ul className="dc-dist" aria-label={t("d.ex.lvSummary")}>
          {levels.map((x) => (
            <li key={x.level} className="dc-dist__item">
              <LevelMark level={x.level} />
              {t(levelLabelKey(x.level))} <b>{x.n}</b>
            </li>
          ))}
        </ul>
      ) : null}
      <div className="dc-card__foot">
        {!resigned && d.displayOrder !== null ? <span className="dc-card__order">{t("d.card.order", { n: d.displayOrder })}</span> : <span />}
        <button type="button" className="btn btn--ghost dc-card__btn" onClick={onOpen} aria-label={`${t(canEdit && !resigned ? "d.card.edit" : "d.card.view")}: ${name}`}>
          {t(canEdit && !resigned ? "d.card.edit" : "d.card.view")}
        </button>
      </div>
    </li>
  );
}

function Pager({ query, total }: { query: DoctorListQuery; total: number }) {
  const t = useT();
  const pages = Math.ceil(total / query.perPage);
  if (pages <= 1) return null;
  const page = Math.min(query.page, pages);
  const href = (p: number) => doctorsHref({ ...query, page: p });
  return (
    <nav className="pager" aria-label={t("d.pager.label")}>
      {page > 1 ? (
        <Link className="pager__btn" href={href(page - 1)} scroll={false} aria-label={t("d.pager.prev")} title={t("d.pager.prev")}>
          <Icon name="chevron" size={16} className="pager__prev" />
        </Link>
      ) : null}
      {pageWindow(page, pages).map((p) => (
        <Link key={p} className="pager__btn" href={href(p)} scroll={false} aria-current={p === page ? "page" : undefined} aria-label={t("d.pager.page", { n: p })}>
          {p}
        </Link>
      ))}
      {page < pages ? (
        <Link className="pager__btn" href={href(page + 1)} scroll={false} aria-label={t("d.pager.next")} title={t("d.pager.next")}>
          <Icon name="chevron" size={16} />
        </Link>
      ) : null}
      <span className="pager__info">{t("d.pager.info", { page: formatNumber(page), pages: formatNumber(pages) })}</span>
    </nav>
  );
}
