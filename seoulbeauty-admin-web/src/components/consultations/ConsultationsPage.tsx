"use client";

import { useEffect, useState, useSyncExternalStore, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { DetailSheet, DetailView } from "./ConsultationDetail";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { fetchConsultationHospitals, fetchConsultations, fetchMe, type ApiResult, type ConsultationHospital, type ConsultationList, type ConsultationRow, type Me } from "@/lib/api";
import { pageButtons } from "@/lib/bookings";
import { CONSULTATION_STATUSES, EMPTY_FILTERS, TYPE_KEYS, isConsultationStatus, isStale, queryKey, tagValue, toQuery, type ConsultationStatus, type Filters } from "@/lib/consultations";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);
const stKey = (s: string) => `cs.st.${s}` as MessageKey;

const WIDE = "(min-width: 1280px)";
/** True from 1280px up, where the detail sits beside the list; below it opens as a sheet. */
function useWide(): boolean {
  return useSyncExternalStore(
    (cb) => { const m = window.matchMedia(WIDE); m.addEventListener("change", cb); return () => m.removeEventListener("change", cb); },
    () => window.matchMedia(WIDE).matches,
    () => false,
  );
}

/** "2026-11-24 10:00:00" -> "11.24 10:00" (the stored time, not converted). */
function shortTime(value: string): string {
  const m = /^\d{4}-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value);
  return m ? `${m[1]}.${m[2]} ${m[3]}:${m[4]}` : "—";
}

function StatusPill({ status }: { status: string }) {
  const t = useT();
  return <span className={`cs-st cs-st--${isConsultationStatus(status) ? status : "unknown"}`}>{isConsultationStatus(status) ? t(stKey(status)) : status}</span>;
}

/** Consultation requests: the queue of applications with status tabs, search and the hospital filter. */
export default function ConsultationsPage() {
  const t = useT();
  const [phase, setPhase] = useState<Phase>("loading");
  const [errorKind, setErrorKind] = useState<ErrorKind>("load");
  const [me, setMe] = useState<Me | null>(null);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    void (async () => {
      const res = await fetchMe(controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setMe(res.data);
        if (!res.data.features.consultations) {
          setErrorKind("consultationsOff");
          setPhase("error");
        } else setPhase("ready");
        return;
      }
      if (res.status === 401) {
        setPhase("redirecting");
        leaveToLogin();
      } else if (res.status === 403) setPhase("forbidden");
      else {
        setErrorKind(res.status === 404 ? "disabled" : res.status === 503 ? "session" : "load");
        setPhase("error");
      }
    })();
    return () => controller.abort();
  }, [attempt]);

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("cs.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={errorKind} onRetry={() => { setPhase("loading"); setAttempt((n) => n + 1); }} retrying={false} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("cs.title")} description={t("cs.subtitle")} screen={screen}>
      {phase === "ready" && me ? <Board me={me} /> : null}
    </AdminShell>
  );
}

function Board({ me }: { me: Me }) {
  const t = useT();
  const own = me.admin.scope === "own";
  const wide = useWide();

  const [filters, setFilters] = useState<Filters>(EMPTY_FILTERS);
  const [qInput, setQInput] = useState("");
  const [page, setPage] = useState(1);
  const [tick, setTick] = useState(0);
  const [hospitals, setHospitals] = useState<ConsultationHospital[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [got, setGot] = useState<{ key: string; res: ApiResult<ConsultationList> } | null>(null);

  const query = toQuery(filters, page);
  const key = `${queryKey(query)}#${tick}`;
  useEffect(() => {
    const c = new AbortController();
    void fetchConsultations(query, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      setGot({ key, res });
      // The server settles on a page that exists; follow it.
      if (res.ok && res.data.page !== query.page) setPage(res.data.page);
    });
    return () => c.abort();
    // `query` is rebuilt from `filters` and `page` on every render; `key` stands for it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  useEffect(() => {
    const c = new AbortController();
    void fetchConsultationHospitals(c.signal).then((res) => {
      if (!c.signal.aborted && res.ok) setHospitals(res.data);
    });
    return () => c.abort();
  }, []);

  // The search text is applied a moment after typing stops.
  useEffect(() => {
    if (qInput.trim() === filters.q.trim()) return;
    const id = window.setTimeout(() => {
      setFilters((f) => ({ ...f, q: qInput }));
      setPage(1);
    }, 350);
    return () => window.clearTimeout(id);
  }, [qInput, filters.q]);

  // The last answer stays on screen while the next one is read.
  const list = got?.res.ok ? got.res.data : null;
  const loadingList = got?.key !== key;
  const failed = got?.key === key && !got.res.ok;
  const refresh = () => setTick((n) => n + 1);
  const set = (patch: Partial<Filters>) => {
    setFilters((f) => ({ ...f, ...patch }));
    setPage(1);
  };
  const reset = () => {
    setFilters(EMPTY_FILTERS);
    setQInput("");
    setPage(1);
  };
  const dirty = JSON.stringify(filters) !== JSON.stringify(EMPTY_FILTERS) || qInput !== "";

  const split = wide && !!list && list.items.length > 0;
  const stats = list?.stats;
  const rows = list?.items ?? [];
  const hospitalName = own ? (rows[0]?.hospitalName ?? hospitals[0]?.name ?? "") : "";
  const tabs: { key: "" | ConsultationStatus; label: string; n: number | null }[] = [
    { key: "", label: t("cs.tab.all"), n: stats?.total ?? null },
    ...CONSULTATION_STATUSES.map((s) => ({ key: s, label: t(stKey(s)), n: stats ? stats[s] : null })),
  ];

  return (
    <div className="cs-page">
      {own && hospitalName ? <p className="cs-scope"><Icon name="hospital" size={14} />{hospitalName}</p> : null}

      <div className="cs-bar">
        <div className="cs-tabs" role="tablist" aria-label={t("cs.tabs")}>
          {tabs.map((tab) => (
            <button key={tab.key || "all"} type="button" role="tab" className="cs-tab" aria-selected={filters.status === tab.key} onClick={() => set({ status: tab.key })}>
              {tab.label}
              {tab.n !== null ? <span className={tab.key === "pending" && tab.n > 0 ? "cs-tab__n cs-tab__n--warn" : "cs-tab__n"}>{formatNumber(tab.n)}</span> : null}
            </button>
          ))}
        </div>
        <form className="cs-tools" role="search" onSubmit={(e) => e.preventDefault()}>
          <label className="cs-search">
            <span className="sr-only">{t("cs.f.search")}</span>
            <Icon name="search" size={16} />
            <input className="input" type="search" maxLength={60} placeholder={t(own ? "cs.f.searchOwn" : "cs.f.searchPh")} value={qInput} onChange={(e) => setQInput(e.target.value)} />
          </label>
          {!own ? (
            <label>
              <span className="sr-only">{t("cs.f.hospital")}</span>
              <select className="sel cs-hosp" value={filters.hospitalId} onChange={(e) => set({ hospitalId: Number(e.target.value) })}>
                <option value={0}>{t("cs.f.hospitalAll")}</option>
                {hospitals.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
              </select>
            </label>
          ) : null}
        </form>
      </div>

      {failed ? (
        <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={refresh}>{t("state.retry")}</button>}>{t("cs.err.list")}</Notice>
      ) : null}

      {got === null || (!list && loadingList) ? (
        <div className="cs-list is-skel" role="status" aria-label={t("cs.loading")}>
          {[0, 1, 2, 3, 4].map((i) => (
            <div key={i} className="cs-row cs-row--skel" aria-hidden="true"><span className="cs-sk cs-sk--m" /><span className="cs-sk cs-sk--l" /></div>
          ))}
        </div>
      ) : null}

      {list && rows.length === 0 && !loadingList ? (
        <div className="cs-none">
          <Icon name={dirty ? "search" : "headset"} size={28} />
          <h2>{t(dirty ? "cs.none.title" : "cs.empty.title")}</h2>
          <p>{t(dirty ? "cs.none.body" : "cs.empty.body")}</p>
          {dirty ? <button type="button" className="btn btn--ghost" onClick={reset}>{t("cs.f.reset")}</button> : null}
        </div>
      ) : null}

      <div className={split ? "cs-split" : undefined}>
      {list && rows.length > 0 ? (
        <div className={loadingList ? "cs-list cs-list--busy" : "cs-list"}>
          <div className="cs-list__meta" aria-live="polite">{t("cs.count", { n: formatNumber(list.total) })}</div>
          <ul className="cs-rows" aria-label={t("cs.list")}>
            {rows.map((r, i) => (
              <li key={r.id} style={{ "--i": i } as React.CSSProperties}>
                <Row row={r} selected={selectedId === r.id} showHospital={!own} onSelect={() => setSelectedId(r.id)} />
              </li>
            ))}
          </ul>
          <nav className="pager cs-pager" aria-label={t("cs.pager.label")}>
            {list.totalPages > 1 ? (
              <>
                <button type="button" className="pager__btn pager__prev" disabled={list.page === 1} onClick={() => setPage(list.page - 1)} aria-label={t("cs.prev")}><Icon name="chevron" size={16} /></button>
                {pageButtons(list.page, list.totalPages).map((n, i) => n === null ? (
                  <span key={`gap${i}`} className="muted" aria-hidden="true">…</span>
                ) : (
                  <button key={n} type="button" className="pager__btn" aria-current={n === list.page ? "page" : undefined} aria-label={t("cs.page", { n: String(n) })} onClick={() => setPage(n)}>{n}</button>
                ))}
                <button type="button" className="pager__btn" disabled={list.page === list.totalPages} onClick={() => setPage(list.page + 1)} aria-label={t("cs.next")}><Icon name="chevron" size={16} /></button>
              </>
            ) : null}
            <p className="pager__info">{t("cs.pager", { from: String((list.page - 1) * list.perPage + 1), to: String(Math.min(list.page * list.perPage, list.total)), total: String(list.total), size: String(list.perPage) })}</p>
          </nav>
        </div>
      ) : null}
      {split ? (
        <aside className="cs-panel" aria-label={t("cs.d.title")}>
          {selectedId !== null ? <DetailView id={selectedId} own={own} mode="panel" onClose={() => setSelectedId(null)} /> : (
            <div className="cs-panel__idle"><Icon name="headset" size={26} /><b>{t("cs.d.idle.title")}</b><p>{t("cs.d.idle.body")}</p></div>
          )}
        </aside>
      ) : null}
      </div>
      {!wide && selectedId !== null ? <DetailSheet id={selectedId} own={own} onClose={() => setSelectedId(null)} /> : null}
    </div>
  );
}

/** One application: who asked, what about, and how long it has waited. */
function Row({ row, selected, showHospital, onSelect }: { row: ConsultationRow; selected: boolean; showHospital: boolean; onSelect: () => void }) {
  const t = useT();
  const treatment = tagValue(row.tags, "관심 시술");
  const typeKey = TYPE_KEYS[row.type];
  const late = row.status === "pending" && isStale(row.createdAt);
  const cls = ["cs-row", row.status === "pending" ? "is-pend" : "", selected ? "is-sel" : ""].filter(Boolean).join(" ");
  return (
    <button type="button" className={cls} aria-current={selected ? "true" : undefined} onClick={onSelect}>
      <span className="cs-row__id">#{row.id}</span>
      <span className="cs-row__who"><b>{row.name || "—"}</b><span className="cs-row__ph">{row.phone}</span></span>
      <span className="cs-row__st"><StatusPill status={row.status} /></span>
      <span className="cs-row__meta">
        {showHospital && row.hospitalName ? <span className="cs-h">{row.hospitalName}</span> : null}
        {typeKey ? <span className="cs-t">{t(typeKey)}</span> : null}
        {treatment ? <span className="cs-t">{treatment}</span> : null}
        {row.preview ? <span className="cs-x">{row.preview}</span> : null}
      </span>
      <span className="cs-row__side">
        {row.photoCount > 0 ? <span className="cs-row__photos" role="img" aria-label={t("cs.photos", { n: String(row.photoCount) })}><Icon name="image" size={13} />{row.photoCount}</span> : null}
        <time className={late ? "is-stale" : undefined} dateTime={row.createdAt.replace(" ", "T")}>{shortTime(row.createdAt)}{late ? <span className="sr-only"> {t("cs.late")}</span> : null}</time>
      </span>
    </button>
  );
}
