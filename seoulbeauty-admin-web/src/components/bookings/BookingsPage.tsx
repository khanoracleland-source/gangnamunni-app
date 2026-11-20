"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon, { type IconName } from "../Icon";
import { Notice } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { showUnitToast } from "../treatment-units/UnitToast";
import BookingModal from "./BookingModal";
import {
  assignBookingDoctor, changeBookingStatus, downloadBookingsExcel, fetchBookingDetail, fetchBookingHospitals, fetchBookings, fetchMe,
  type ApiResult, type BookingAction, type BookingDetail, type BookingHospital, type BookingList, type BookingRow, type Me, type WriteResult,
} from "@/lib/api";
import { BOOKING_STATUSES, EMPTY_FILTERS, dayBefore, isBookingStatus, pageButtons, queryKey, todayIso, toQuery, type BookingStatus, type Filters } from "@/lib/bookings";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useLocale, useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
const STATUS_ICON: Record<BookingStatus, IconName> = { pending: "clock", hospital_checking: "search", confirmed: "check", completed: "clipboard", cancelled: "close", refunded: "refresh", no_show: "ban" };
const stKey = (s: string) => `bk.st.${s}` as MessageKey;
const actKey = (a: BookingAction) => `bk.act.${a}` as MessageKey;
const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

type Failure = Extract<WriteResult<unknown>, { ok: false }>;
/** The sentence for a refused change. */
function failureKey(f: Failure): MessageKey {
  if (f.status === 409) return "bk.err.conflict";
  // The write switch can be turned off while the page is open: that 404 does not come with "not_found".
  if (f.status === 404) return f.error === "not_found" ? "bk.err.notFound" : "bk.err.unavailable";
  if (f.status === 422) return f.error === "doctor_not_found" ? "bk.err.doctor" : "bk.err.noDoctor";
  if (f.status === 503) return "bk.err.unavailable";
  return "bk.err.generic";
}

function StatusBadge({ status }: { status: string }) {
  const t = useT();
  if (!isBookingStatus(status)) return <span className="bk-st bk-st--refunded">{status}</span>;
  return (
    <span className={`bk-st bk-st--${status}`}>
      <Icon name={STATUS_ICON[status]} size={13} />
      {t(stKey(status))}
    </span>
  );
}

/** Booking management: the list with status counts, filters, details, the status flow, doctor assignment and the Excel download. */
export default function BookingsPage() {
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
        if (!res.data.features.bookings) {
          setErrorKind("bookingsOff");
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
  if (phase === "loading") screen = <LoadingScreen text={t("bk.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="super" />;
  else if (phase === "error") screen = <ErrorScreen kind={errorKind} onRetry={() => { setPhase("loading"); setAttempt((n) => n + 1); }} retrying={false} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("bk.title")} description={t("bk.subtitle")} screen={screen} actions={phase === "ready" ? <ExcelButton /> : undefined}>
      {phase === "ready" && me ? <Board me={me} /> : null}
    </AdminShell>
  );
}

// The header button lives outside the board; it reaches the board's dialog through this hook.
let openExcel: (() => void) | null = null;
function ExcelButton() {
  const t = useT();
  return (
    <button type="button" className="btn btn--ghost bk-excel" onClick={() => openExcel?.()}>
      <Icon name="sheet" size={16} />
      {t("bk.excel")}
    </button>
  );
}

/** One booking's details, read again when `tick` changes. undefined while it is being read. */
function useBookingDetail(id: number | null, tick: number): ApiResult<BookingDetail> | undefined {
  const [got, setGot] = useState<{ id: number; res: ApiResult<BookingDetail> } | null>(null);
  // Closing or switching to another booking forgets the old answer, so a reopened panel never shows an old status.
  useEffect(() => () => setGot(null), [id]);
  // A change (tick) reads the booking again but keeps the old answer on screen until the new one arrives.
  useEffect(() => {
    if (id === null) return;
    const c = new AbortController();
    void fetchBookingDetail(id, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      setGot({ id, res });
    });
    return () => c.abort();
  }, [id, tick]);
  return id !== null && got?.id === id ? got.res : undefined;
}

function Board({ me }: { me: Me }) {
  const t = useT();
  const own = me.admin.scope === "own";
  const canWrite = me.features.bookingsWrite;
  const closeLabel = t("m.close");

  const [filters, setFilters] = useState<Filters>(EMPTY_FILTERS);
  const [qInput, setQInput] = useState("");
  const [page, setPage] = useState(1);
  const [tick, setTick] = useState(0);
  const [hospitals, setHospitals] = useState<BookingHospital[]>([]);
  const [busyId, setBusyId] = useState<number | null>(null);
  // A flag that a second click in the same moment cannot miss (the state above only changes after the next render).
  const busyRef = useRef(false);
  const [confirmRow, setConfirmRow] = useState<BookingRow | null>(null);
  const [cancelRow, setCancelRow] = useState<BookingRow | null>(null);
  const [assignRow, setAssignRow] = useState<BookingRow | null>(null);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [excelOpen, setExcelOpen] = useState(false);
  const [got, setGot] = useState<{ key: string; res: ApiResult<BookingList> } | null>(null);

  const query = toQuery(filters, page);
  const key = `${queryKey(query)}#${tick}`;
  useEffect(() => {
    const c = new AbortController();
    void fetchBookings(query, c.signal).then((res) => {
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
    void fetchBookingHospitals(c.signal).then((res) => {
      if (!c.signal.aborted && res.ok) setHospitals(res.data);
    });
    return () => c.abort();
  }, []);

  useEffect(() => {
    openExcel = () => setExcelOpen(true);
    return () => {
      openExcel = null;
    };
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
  const detail = useBookingDetail(detailId, tick);
  const assignDetail = useBookingDetail(assignRow ? assignRow.id : null, tick);

  const set = (patch: Partial<Filters>) => {
    setFilters((f) => {
      const next = { ...f, ...patch };
      // A start after the end would be refused by the server: the date that was not just typed gives way.
      if (next.from && next.to && next.from > next.to) {
        if (patch.from !== undefined) next.to = "";
        else next.from = "";
      }
      return next;
    });
    setPage(1);
  };
  const reset = () => {
    setFilters(EMPTY_FILTERS);
    setQInput("");
    setPage(1);
  };
  const dirty = JSON.stringify(filters) !== JSON.stringify(EMPTY_FILTERS) || qInput !== "";
  const toast = (tone: "ok" | "error" | "info", message: string) => showUnitToast({ tone, message, closeLabel });
  const refresh = () => setTick((n) => n + 1);
  const fail = (res: Failure) => {
    if (res.status === 401) {
      leaveToLogin();
      return;
    }
    toast("error", t(failureKey(res)));
    // A conflict or a missing booking means the list on screen is out of date.
    if (res.status === 409 || res.status === 404) refresh();
  };

  const move = async (row: BookingRow, action: BookingAction, cancelReason = "") => {
    if (busyRef.current) return;
    busyRef.current = true;
    setBusyId(row.id);
    const res = await changeBookingStatus(row.id, action, cancelReason);
    busyRef.current = false;
    setBusyId(null);
    if (res.ok) {
      toast("ok", t("bk.toast.moved", { id: String(row.id), status: t(stKey(res.data.status)) }));
      refresh();
    } else fail(res);
  };
  const run = (row: BookingRow, action: BookingAction) => {
    if (busyId !== null || !canWrite) return;
    if (action === "confirm") setConfirmRow(row);
    else if (action === "cancel") setCancelRow(row);
    else void move(row, action);
  };
  const assign = async (row: BookingRow, doctorId: number, name: string) => {
    setAssignRow(null);
    if (busyRef.current) return;
    busyRef.current = true;
    setBusyId(row.id);
    const res = await assignBookingDoctor(row.id, doctorId);
    busyRef.current = false;
    setBusyId(null);
    if (res.ok) {
      toast("ok", doctorId ? t("bk.toast.doctor", { id: String(row.id), name }) : t("bk.toast.doctorOff", { id: String(row.id) }));
      refresh();
    } else fail(res);
  };

  const stats = list?.stats;
  const today = todayIso();
  const statTiles: { key: string; label: string; value: number; pressed: boolean; onClick: () => void; icon?: IconName }[] = [
    { key: "all", label: t("bk.stat.all"), value: stats?.total ?? 0, pressed: !filters.status && !filters.from && !filters.to, onClick: () => set({ status: "", from: "", to: "" }) },
    ...(["pending", "hospital_checking", "confirmed", "completed", "cancelled"] as const).map((s) => ({
      key: s, label: t(stKey(s)), value: stats?.[s] ?? 0, pressed: filters.status === s, icon: STATUS_ICON[s], onClick: () => set({ status: filters.status === s ? "" : s }),
    })),
    { key: "today", label: t("bk.stat.today"), value: stats?.today ?? 0, pressed: filters.from === today && filters.to === today, icon: "calendar", onClick: () => (filters.from === today && filters.to === today ? set({ from: "", to: "" }) : set({ from: today, to: today })) },
  ];

  const hospitalName = own ? (list?.items[0]?.hospitalName ?? hospitals[0]?.name ?? "") : "";
  const rows = list?.items ?? [];
  const actionButtons = (r: BookingRow) => {
    if (!r.actions.length) return <span className="muted">—</span>;
    return r.actions.map((a) => (
      <button key={a} type="button" className={`btn btn--sm ${a === "cancel" ? "btn--ghost btn--reject" : "btn--primary"}`} disabled={busyId !== null || !canWrite} aria-label={`${t(actKey(a))} #${r.id}`} onClick={() => run(r, a)}>
        {busyId === r.id && a !== "cancel" ? t("bk.working") : t(actKey(a))}
      </button>
    ));
  };

  return (
    <div className="bk-page">
      {!canWrite ? <Notice tone="info" icon="lock">{t("bk.readOnly")}</Notice> : null}
      {own && hospitalName ? <p className="bk-chip"><Icon name="hospital" size={14} />{hospitalName}</p> : null}

      <ul className="stats bk-stats" aria-label={t("bk.stats")}>
        {statTiles.map((s) => (
          <li key={s.key} className={s.key === "all" ? "bk-stats__all" : undefined}>
            <button type="button" className="stat stat--btn" aria-pressed={s.pressed} onClick={s.onClick}>
              <span className="stat__label bk-stat__label">{s.icon ? <Icon name={s.icon} size={13} /> : null}{s.label}</span>
              <span className="stat__value">{formatNumber(s.value)}</span>
            </button>
          </li>
        ))}
      </ul>

      <form className="filters bk-filters" role="search" onSubmit={(e) => e.preventDefault()}>
        {!own ? (
          <label className="field field--hosp"><span className="field__label">{t("bk.f.hospital")}</span>
            <select className="sel sel--field" value={filters.hospitalId} onChange={(e) => set({ hospitalId: Number(e.target.value) })}>
              <option value={0}>{t("bk.f.hospitalAll")}</option>
              {hospitals.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
            </select>
          </label>
        ) : null}
        <label className="field"><span className="field__label">{t("bk.f.status")}</span>
          <select className="sel sel--field" value={filters.status} onChange={(e) => set({ status: isBookingStatus(e.target.value) ? e.target.value : "" })}>
            <option value="">{t("bk.f.statusAll")}</option>
            {BOOKING_STATUSES.map((s) => <option key={s} value={s}>{t(stKey(s))}</option>)}
          </select>
        </label>
        <label className="field"><span className="field__label">{t("bk.f.from")}</span>
          <input className="input" type="date" value={filters.from} max={filters.to || undefined} onChange={(e) => set({ from: e.target.value })} />
        </label>
        <label className="field"><span className="field__label">{t("bk.f.to")}</span>
          <input className="input" type="date" value={filters.to} min={filters.from || undefined} onChange={(e) => set({ to: e.target.value })} />
        </label>
        <label className="field bk-filters__q"><span className="field__label">{t("bk.f.search")}</span>
          <input className="input" type="search" maxLength={60} placeholder={t("bk.f.searchPh")} value={qInput} onChange={(e) => setQInput(e.target.value)} />
        </label>
        <button type="button" className="btn btn--ghost bk-filters__reset" disabled={!dirty} onClick={reset}>{t("bk.f.reset")}</button>
      </form>

      {got === null ? <p className="muted" role="status">{t("bk.loading")}</p> : null}

      {failed ? (
        <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={refresh}>{t("state.retry")}</button>}>{t("bk.err.list")}</Notice>
      ) : null}

      {list && rows.length === 0 && !loadingList ? (
        <div className="bk-none">
          <Icon name="search" size={28} />
          <h2>{t(dirty ? "bk.none.title" : "bk.empty.title")}</h2>
          <p>{t(dirty ? "bk.none.body" : "bk.empty.body")}</p>
          {dirty ? <button type="button" className="btn btn--ghost" onClick={reset}>{t("bk.none.reset")}</button> : null}
        </div>
      ) : null}

      {list && rows.length > 0 ? (
        <div className={loadingList ? "bk-busy" : undefined} aria-busy={loadingList}>
          <div className="bk-tbl-wrap">
            <table className="tbl bk-tbl">
              <colgroup>
                <col className="bk-c-id" /><col className="bk-c-name" /><col className="bk-c-phone" />
                {!own ? <col className="bk-c-hosp" /> : null}
                <col className="bk-c-tr" /><col className="bk-c-date" /><col className="bk-c-time" /><col className="bk-c-photo" /><col className="bk-c-st" /><col className="bk-c-doc" /><col className="bk-c-act" />
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">#</th><th scope="col">{t("bk.col.name")}</th><th scope="col">{t("bk.col.phone")}</th>
                  {!own ? <th scope="col">{t("bk.col.hospital")}</th> : null}
                  <th scope="col">{t("bk.col.treatment")}</th><th scope="col">{t("bk.col.date")}</th><th scope="col">{t("bk.col.time")}</th>
                  <th scope="col">{t("bk.col.photos")}</th><th scope="col" className="bk-th-st">{t("bk.col.status")}</th><th scope="col">{t("bk.col.doctor")}</th><th scope="col">{t("bk.col.actions")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id} className={busyId === r.id ? "is-busy" : undefined}>
                    <td className="bk-num">{r.id}</td>
                    <td><button type="button" className="bk-name" title={r.name} onClick={() => setDetailId(r.id)}>{r.name}</button></td>
                    <td className="bk-num bk-nowrap">{r.phone}</td>
                    {!own ? <td><span className="bk-ell" title={r.hospitalName}>{r.hospitalName}</span></td> : null}
                    <td><span className="bk-ell" title={r.treatment}>{r.treatment}</span></td>
                    <td className="bk-num"><DateText date={r.date} /></td>
                    <td className="bk-num">{r.time}</td>
                    <td>{r.photoCount ? <span className="bk-photos"><Icon name="image" size={14} />{r.photoCount}</span> : <span className="muted">—</span>}</td>
                    <td className="bk-td-st"><StatusBadge status={r.status} /></td>
                    <td><DoctorCell r={r} canWrite={canWrite} onAssign={() => setAssignRow(r)} /></td>
                    <td><div className="bk-acts">{actionButtons(r)}</div></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <ul className="bk-cards">
            {rows.map((r) => (
              <li key={r.id} className={`bk-card${busyId === r.id ? " is-busy" : ""}`}>
                <div className="bk-card__top"><span className="bk-num muted">#{r.id}</span><StatusBadge status={r.status} /></div>
                <button type="button" className="bk-name bk-card__name" onClick={() => setDetailId(r.id)}>{r.name}</button>
                <dl className="bk-dl">
                  {!own ? <><dt>{t("bk.col.hospital")}</dt><dd>{r.hospitalName}</dd></> : null}
                  <dt>{t("bk.col.treatment")}</dt><dd>{r.treatment}</dd>
                  <dt>{t("bk.col.date")}</dt><dd className="bk-num"><DateText date={r.date} /> {r.time}</dd>
                  <dt>{t("bk.col.phone")}</dt><dd className="bk-num">{r.phone}</dd>
                  <dt>{t("bk.col.doctor")}</dt><dd><DoctorCell r={r} canWrite={canWrite} onAssign={() => setAssignRow(r)} /></dd>
                  <dt>{t("bk.col.photos")}</dt><dd>{r.photoCount ? <span className="bk-photos"><Icon name="image" size={14} />{r.photoCount}</span> : "—"}</dd>
                </dl>
                {r.actions.length ? (
                  <div className="bk-card__acts">
                    {actionButtons(r)}
                    <button type="button" className="btn btn--ghost bk-card__doc" disabled={!canWrite || busyId !== null} onClick={() => setAssignRow(r)}>{t("bk.doctor.change")}</button>
                  </div>
                ) : null}
              </li>
            ))}
          </ul>

          <nav className="pager" aria-label={t("bk.pager.label")}>
            {list.totalPages > 1 ? (
              <>
                <button type="button" className="pager__btn pager__prev" disabled={list.page === 1} onClick={() => setPage(list.page - 1)} aria-label={t("bk.prev")}><Icon name="chevron" size={16} /></button>
                {pageButtons(list.page, list.totalPages).map((n, i) => n === null ? (
                  <span key={`gap${i}`} className="muted" aria-hidden="true">…</span>
                ) : (
                  <button key={n} type="button" className="pager__btn" aria-current={n === list.page ? "page" : undefined} aria-label={t("bk.page", { n: String(n) })} onClick={() => setPage(n)}>{n}</button>
                ))}
                <button type="button" className="pager__btn" disabled={list.page === list.totalPages} onClick={() => setPage(list.page + 1)} aria-label={t("bk.next")}><Icon name="chevron" size={16} /></button>
              </>
            ) : null}
            <p className="pager__info">{t("bk.pager", { from: String((list.page - 1) * list.perPage + 1), to: String(Math.min(list.page * list.perPage, list.total)), total: String(list.total), size: String(list.perPage) })}</p>
          </nav>
        </div>
      ) : null}

      <ConfirmModal row={confirmRow} onClose={() => setConfirmRow(null)} onOk={(r) => { setConfirmRow(null); void move(r, "confirm"); }} />
      <CancelModal row={cancelRow} onClose={() => setCancelRow(null)} onOk={(r, reason) => { setCancelRow(null); void move(r, "cancel", reason); }} />
      <AssignModal row={assignRow} detail={assignDetail} onClose={() => setAssignRow(null)} onPick={(r, id, name) => void assign(r, id, name)} />
      <DetailDrawer id={detailId} detail={detail} own={own} canWrite={canWrite} onClose={() => setDetailId(null)} onAct={(r, a) => { setDetailId(null); run(r, a); }} />
      <ExcelModal open={excelOpen} own={own} hospitals={hospitals} onClose={() => setExcelOpen(false)} />
    </div>
  );
}

function DateText({ date }: { date: string }) {
  const locale = useLocale();
  const [y, m, d] = date.split("-").map(Number);
  if (!y || !m || !d) return <>{date}</>;
  const wd = new Date(y, m - 1, d).toLocaleDateString(locale, { weekday: "short" });
  return <>{`${String(m).padStart(2, "0")}.${String(d).padStart(2, "0")} (${wd})`}</>;
}

function DoctorCell({ r, canWrite, onAssign }: { r: BookingRow; canWrite: boolean; onAssign: () => void }) {
  const t = useT();
  const editable = canWrite && r.actions.length > 0;
  if (!r.doctorName) return editable ? <button type="button" className="bk-link" onClick={onAssign} aria-label={`${t("bk.doctor.assign")} #${r.id}`}>{t("bk.doctor.assign")}</button> : <span className="muted">{t("bk.doctor.none")}</span>;
  return (
    <span className="bk-doc">
      <span className="bk-ell" title={r.doctorName}>{r.doctorName}</span>
      {editable ? <button type="button" className="ibtn bk-doc__edit" onClick={onAssign} aria-label={`${t("bk.doctor.change")} #${r.id}`} title={t("bk.doctor.change")}><Icon name="edit" size={16} /></button> : null}
    </span>
  );
}

function DialogHead({ title, onClose }: { title: string; onClose: () => void }) {
  const t = useT();
  return (
    <div className="dialog__head">
      <h2 className="dialog__title">{title}</h2>
      <button type="button" className="ibtn dialog__close" onClick={onClose} aria-label={t("m.close")}><Icon name="close" /></button>
    </div>
  );
}

function ConfirmModal({ row, onClose, onOk }: { row: BookingRow | null; onClose: () => void; onOk: (r: BookingRow) => void }) {
  const t = useT();
  return (
    <BookingModal open={!!row} label={t("bk.confirm.title")} onClose={onClose}>
      {row ? (
        <div className="dialog__box">
          <DialogHead title={t("bk.confirm.title")} onClose={onClose} />
          <p className="dialog__text dialog__text--strong" translate="no">#{row.id} · {row.name}</p>
          <p className="dialog__text">{t("bk.confirm.body")}</p>
          <div className="dialog__err"><Notice tone="warn" icon="alert">{t("bk.confirm.warn")}</Notice></div>
          <div className="dialog__foot bk-foot">
            <button type="button" className="btn btn--ghost" onClick={onClose} data-autofocus="">{t("bk.common.cancel")}</button>
            <button type="button" className="btn btn--primary" onClick={() => onOk(row)}>{t("bk.confirm.ok")}</button>
          </div>
        </div>
      ) : null}
    </BookingModal>
  );
}

function CancelModal({ row, onClose, onOk }: { row: BookingRow | null; onClose: () => void; onOk: (r: BookingRow, reason: string) => void }) {
  const t = useT();
  return (
    <BookingModal open={!!row} label={t("bk.cancel.title")} onClose={onClose}>
      {row ? <CancelBody row={row} onClose={onClose} onOk={onOk} /> : null}
    </BookingModal>
  );
}

// The body is mounted fresh every time the dialog opens, so its fields always start empty.
function CancelBody({ row, onClose, onOk }: { row: BookingRow; onClose: () => void; onOk: (r: BookingRow, reason: string) => void }) {
  const t = useT();
  const [reason, setReason] = useState("");
  return (
    <div className="dialog__box">
      <DialogHead title={t("bk.cancel.title")} onClose={onClose} />
      <p className="dialog__text dialog__text--strong" translate="no">#{row.id} · {row.name}</p>
      <label className="field"><span className="field__label">{t("bk.cancel.reason")}</span>
        <textarea className="bk-textarea" maxLength={200} rows={4} placeholder={t("bk.cancel.reasonPh")} value={reason} onChange={(e) => setReason(e.target.value)} aria-describedby="bk-count" data-autofocus="" />
      </label>
      <span id="bk-count" className="bk-count">{reason.length} / 200</span>
      <div className="dialog__err"><Notice tone="warn" icon="alert">{t("bk.cancel.warn")}</Notice></div>
      <div className="dialog__foot bk-foot">
        <button type="button" className="btn btn--ghost" onClick={onClose}>{t("bk.cancel.back")}</button>
        <button type="button" className="btn bk-danger" onClick={() => onOk(row, reason.trim())}>{t("bk.cancel.ok")}</button>
      </div>
    </div>
  );
}

function AssignModal({ row, detail, onClose, onPick }: { row: BookingRow | null; detail: ApiResult<BookingDetail> | undefined; onClose: () => void; onPick: (r: BookingRow, doctorId: number, name: string) => void }) {
  const t = useT();
  return (
    <BookingModal open={!!row} label={t("bk.assign.title")} onClose={onClose}>
      {row ? <AssignBody row={row} detail={detail} onClose={onClose} onPick={onPick} /> : null}
    </BookingModal>
  );
}

function AssignBody({ row, detail, onClose, onPick }: { row: BookingRow; detail: ApiResult<BookingDetail> | undefined; onClose: () => void; onPick: (r: BookingRow, doctorId: number, name: string) => void }) {
  const t = useT();
  const [pick, setPick] = useState<number | null>(null);
  const doctors = detail?.ok ? detail.data.doctors : [];
  const chosen = pick ?? row.doctorId;
  return (
    <div className="dialog__box">
      <DialogHead title={t("bk.assign.title")} onClose={onClose} />
      <p className="dialog__text dialog__text--strong" translate="no">#{row.id} · {row.name}</p>
      {detail === undefined ? <p className="dialog__text" role="status">{t("bk.detail.loading")}</p> : null}
      {detail && !detail.ok ? <Notice tone="warn" icon="alert">{t("bk.detail.fail")}</Notice> : null}
      {detail?.ok && doctors.length === 0 ? <p className="dialog__text">{t("bk.assign.none")}</p> : null}
      {doctors.length ? (
        <ul className="bk-list">
          {doctors.map((d, i) => (
            <li key={d.id}><label className="bk-list__row"><input type="radio" name="bk-doc" checked={chosen === d.id} onChange={() => setPick(d.id)} data-autofocus={i === 0 ? "" : undefined} /><span>{d.name}</span></label></li>
          ))}
        </ul>
      ) : null}
      <div className="dialog__err"><Notice tone="info" icon="info">{t("bk.assign.info")}</Notice></div>
      {row.doctorId ? <button type="button" className="bk-link bk-link--danger" onClick={() => onPick(row, 0, "")}>{t("bk.assign.clear")}</button> : null}
      <div className="dialog__foot bk-foot">
        <button type="button" className="btn btn--ghost" onClick={onClose}>{t("bk.common.cancel")}</button>
        <button type="button" className="btn btn--primary" disabled={!chosen || chosen === row.doctorId} onClick={() => { const d = doctors.find((x) => x.id === chosen); if (d) onPick(row, d.id, d.name); }}>{t("bk.assign.ok")}</button>
      </div>
    </div>
  );
}

function DetailDrawer({ id, detail, own, canWrite, onClose, onAct }: { id: number | null; detail: ApiResult<BookingDetail> | undefined; own: boolean; canWrite: boolean; onClose: () => void; onAct: (r: BookingRow, a: BookingAction) => void }) {
  const t = useT();
  const ok = detail?.ok ? detail.data : null;
  const b = ok?.booking;
  return (
    <BookingModal open={id !== null} label={id !== null ? t("bk.detail.title", { id: String(id) }) : ""} kind="drawer" onClose={onClose}>
      {id !== null ? (
        <div className="bk-drawer">
          <header className="bk-drawer__head">
            <div>
              <p className="muted">{t("bk.detail.title", { id: String(id) })}</p>
              <h2 className="bk-drawer__name">{b?.name ?? ""}</h2>
              {b ? <StatusBadge status={b.status} /> : null}
            </div>
            <button type="button" className="ibtn bk-drawer__close" onClick={onClose} aria-label={t("m.close")} data-autofocus=""><Icon name="close" /></button>
          </header>
          <div className="bk-drawer__body">
            {detail === undefined ? <p role="status" className="muted">{t("bk.detail.loading")}</p> : null}
            {detail && !detail.ok ? <Notice tone="warn" icon="alert">{t(detail.status === 404 ? "bk.err.notFound" : "bk.detail.fail")}</Notice> : null}
            {ok && b ? (
              <>
                <dl className="bk-dl bk-dl--wide">
                  <dt>{t("bk.col.phone")}</dt><dd className="bk-num">{b.phone}</dd>
                  {!own ? <><dt>{t("bk.col.hospital")}</dt><dd>{b.hospitalName}</dd></> : null}
                  <dt>{t("bk.col.treatment")}</dt><dd>{b.treatment}</dd>
                  <dt>{t("bk.col.date")}</dt><dd className="bk-num"><DateText date={b.date} /> {b.time}</dd>
                  <dt>{t("bk.col.doctor")}</dt><dd>{b.doctorName || t("bk.doctor.none")}</dd>
                  <dt>{t("bk.detail.received")}</dt><dd className="bk-num">{b.receivedAt}</dd>
                </dl>
                {b.memo ? <section><h3>{t("bk.detail.memo")}</h3><p>{b.memo}</p></section> : null}
                {b.cancelReason ? <section><h3>{t("bk.detail.reason")}</h3><p>{b.cancelReason}</p></section> : null}
                {ok.answers.length ? (
                  <section><h3>{t("bk.detail.answers")}</h3>
                    <dl className="bk-qa">{ok.answers.map((x, i) => <div key={i}><dt>{x.question}</dt><dd>{x.yes ? t("bk.yes") : t("bk.no")}</dd></div>)}</dl>
                  </section>
                ) : null}
                {ok.photos.length ? (
                  <section><h3>{t("bk.detail.photos")}</h3>
                    <ul className="bk-thumbs">
                      {ok.photos.map((u, i) => (
                        // eslint-disable-next-line @next/next/no-img-element -- photos come from the PHP site's own upload folder
                        <li key={`${i}:${u}`} className="bk-thumb"><img src={u} alt="" loading="lazy" referrerPolicy="no-referrer" /></li>
                      ))}
                    </ul>
                  </section>
                ) : null}
              </>
            ) : null}
          </div>
          <footer className="bk-drawer__foot">
            {b && b.actions.length === 0 ? <p className="muted bk-drawer__final">{t("bk.detail.final")}</p> : null}
            <button type="button" className="btn btn--ghost" onClick={onClose}>{t("bk.detail.close")}</button>
            {b ? b.actions.map((a) => (
              <button key={a} type="button" disabled={!canWrite} className={a === "cancel" ? "btn btn--ghost btn--reject" : "btn btn--primary"} onClick={() => onAct(b, a)}>{t(actKey(a))}</button>
            )) : null}
          </footer>
        </div>
      ) : null}
    </BookingModal>
  );
}

function ExcelModal({ open, own, hospitals, onClose }: { open: boolean; own: boolean; hospitals: BookingHospital[]; onClose: () => void }) {
  const t = useT();
  const [busy, setBusy] = useState(false);
  return (
    <BookingModal open={open} label={t("bk.xl.title")} kind="sheet" onClose={onClose} locked={busy}>
      {open ? <ExcelBody own={own} hospitals={hospitals} onClose={onClose} onBusy={setBusy} /> : null}
    </BookingModal>
  );
}

function ExcelBody({ own, hospitals, onClose, onBusy }: { own: boolean; hospitals: BookingHospital[]; onClose: () => void; onBusy: (b: boolean) => void }) {
  const t = useT();
  const [all, setAll] = useState(true);
  const [picked, setPicked] = useState<number[]>([]);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [status, setStatus] = useState<BookingStatus | "">("");
  const [phase, setPhase] = useState<"idle" | "busy" | "empty" | "needRange" | "pickOne" | "tooMany" | "fail">("idle");
  // The dialog may close (and unmount) while the file is being made; nothing is done to a gone dialog.
  const alive = useRef(true);
  useEffect(() => {
    alive.current = true;
    return () => {
      alive.current = false;
      onBusy(false);
    };
  }, [onBusy]);

  const preset = (days: number | "month") => {
    const end = todayIso();
    setFrom(days === "month" ? `${end.slice(0, 8)}01` : dayBefore(end, days));
    setTo(end);
    setPhase("idle");
  };
  const go = async () => {
    if (!from || !to || from > to) { setPhase("needRange"); return; }
    if (!own && !all && picked.length === 0) { setPhase("pickOne"); return; }
    setPhase("busy");
    onBusy(true);
    const res = await downloadBookingsExcel({ hospitalIds: own || all ? [] : picked, from, to, status });
    if (!alive.current) return;
    onBusy(false);
    if (res.ok) {
      const url = URL.createObjectURL(res.blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = res.filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 10000);
      onClose();
      showUnitToast({ tone: "ok", message: t("bk.xl.done"), closeLabel: t("m.close") });
      return;
    }
    if (res.status === 401) { leaveToLogin(); return; }
    setPhase(res.status === 404 ? "empty" : res.status === 400 ? "needRange" : res.status === 413 ? "tooMany" : "fail");
  };
  const busy = phase === "busy";

  return (
    <div className="dialog__box bk-xl">
      <div className="dialog__head"><h2 className="dialog__title">{t("bk.xl.title")}</h2><button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("m.close")}><Icon name="close" /></button></div>
      <fieldset className="bk-xl__sec" disabled={busy}>
        <legend>{t("bk.xl.hospital")}</legend>
        {own ? <p className="bk-chip"><Icon name="hospital" size={14} />{hospitals[0]?.name ?? ""}</p> : (
          <>
            <div className="seg">
              <button type="button" aria-pressed={all} onClick={() => setAll(true)}>{t("bk.xl.hospitalAll")}</button>
              <button type="button" aria-pressed={!all} onClick={() => setAll(false)}>{t("bk.xl.hospitalPick")}</button>
            </div>
            {!all ? (
              <ul className="bk-list">
                {hospitals.map((h) => (
                  <li key={h.id}><label className="bk-list__row"><input type="checkbox" checked={picked.includes(h.id)} onChange={(e) => setPicked((p) => (e.target.checked ? [...p, h.id] : p.filter((x) => x !== h.id)))} /><span>{h.name}</span></label></li>
                ))}
              </ul>
            ) : null}
          </>
        )}
      </fieldset>
      <fieldset className="bk-xl__sec" disabled={busy}>
        <legend>{t("bk.xl.period")}</legend>
        <div className="bk-xl__dates">
          <input className="input" type="date" aria-label={t("bk.f.from")} value={from} onChange={(e) => { setFrom(e.target.value); setPhase("idle"); }} />
          <input className="input" type="date" aria-label={t("bk.f.to")} value={to} onChange={(e) => { setTo(e.target.value); setPhase("idle"); }} />
        </div>
        <div className="bk-chips">
          <button type="button" className="bk-chipbtn" onClick={() => preset(7)}>{t("bk.xl.week")}</button>
          <button type="button" className="bk-chipbtn" onClick={() => preset(30)}>{t("bk.xl.month")}</button>
          <button type="button" className="bk-chipbtn" onClick={() => preset(90)}>{t("bk.xl.quarter")}</button>
          <button type="button" className="bk-chipbtn" onClick={() => preset("month")}>{t("bk.xl.thisMonth")}</button>
        </div>
      </fieldset>
      <fieldset className="bk-xl__sec" disabled={busy}>
        <legend>{t("bk.xl.status")}</legend>
        <div className="bk-chips">
          <button type="button" className="bk-chipbtn" aria-pressed={status === ""} onClick={() => setStatus("")}>{t("bk.xl.statusAll")}</button>
          {BOOKING_STATUSES.map((s) => <button key={s} type="button" className="bk-chipbtn" aria-pressed={status === s} onClick={() => setStatus(s)}>{t(stKey(s))}</button>)}
        </div>
      </fieldset>
      <div aria-live="polite">{busy ? <Notice tone="info" icon="refresh">{t("bk.xl.preparing")}</Notice> : null}</div>
      {phase === "pickOne" ? <Notice tone="warn" icon="alert">{t("bk.xl.pickOne")}</Notice> : null}
      {phase === "empty" ? <Notice tone="warn" icon="alert">{t("bk.xl.empty")}</Notice> : null}
      {phase === "needRange" ? <Notice tone="warn" icon="alert">{t("bk.xl.needRange")}</Notice> : null}
      {phase === "tooMany" ? <Notice tone="warn" icon="alert">{t("bk.xl.tooMany")}</Notice> : null}
      {phase === "fail" ? <Notice tone="warn" icon="alert">{t("bk.xl.fail")}</Notice> : null}
      <div className="dialog__foot bk-foot">
        <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("bk.common.cancel")}</button>
        <button type="button" className="btn btn--primary" onClick={() => void go()} disabled={busy}><Icon name="sheet" size={16} />{busy ? t("bk.xl.preparing") : t("bk.xl.go")}</button>
      </div>
    </div>
  );
}
