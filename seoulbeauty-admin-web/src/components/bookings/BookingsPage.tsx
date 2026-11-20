"use client";

import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon, { type IconName } from "../Icon";
import { Notice } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { showUnitToast } from "../treatment-units/UnitToast";
import BookingModal from "./BookingModal";
import { fetchMe, type Me } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useLocale, useT } from "@/lib/locale";
import {
  BOOKING_STATUSES, EMPTY_FILTERS, MOCK_BOOKINGS, MOCK_DOCTORS, MOCK_HOSPITALS, MOCK_TODAY, PAGE_SIZE,
  actionsFor, applyFilters, countByStatus, targetOf,
  type Action, type BookingStatus, type Filters, type MockBooking,
} from "@/lib/bookingsMock";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
const STATUS_ICON: Record<BookingStatus, IconName> = { pending: "clock", hospital_checking: "search", confirmed: "check", completed: "clipboard", cancelled: "close", refunded: "refresh", no_show: "ban" };
const hospitalName = (id: number) => MOCK_HOSPITALS.find((h) => h.id === id)?.name ?? "";
const stKey = (s: string) => `bk.st.${s}` as MessageKey;
const actKey = (a: Action) => `bk.act.${a}` as MessageKey;

function StatusBadge({ status }: { status: BookingStatus }) {
  const t = useT();
  return (
    <span className={`bk-st bk-st--${status}`}>
      <Icon name={STATUS_ICON[status]} size={13} />
      {t(stKey(status))}
    </span>
  );
}

/** Draft of the bookings screen (variant B): the layout, the dialogs and the status flow on sample data. Nothing is sent to the server. */
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
        setPhase("ready");
        return;
      }
      if (res.status === 401) {
        setPhase("redirecting");
        window.location.assign(PHP_ADMIN_URL);
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

  const own = me?.admin.scope === "own";
  // The draft shows the first sample hospital as "the" hospital of a hospital account.
  const scopeHospital = own ? MOCK_HOSPITALS[0].id : null;

  return (
    <AdminShell
      admin={me?.admin ?? null}
      features={me?.features ?? null}
      title={t("bk.title")}
      description={t("bk.subtitle")}
      screen={screen}
      actions={phase === "ready" ? <ExcelButton /> : undefined}
    >
      {phase === "ready" ? <Board scopeHospital={scopeHospital} /> : null}
    </AdminShell>
  );
}

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

function Board({ scopeHospital }: { scopeHospital: number | null }) {
  const t = useT();
  const [rows, setRows] = useState<MockBooking[]>(MOCK_BOOKINGS);
  const [filters, setFilters] = useState<Filters>(EMPTY_FILTERS);
  const [page, setPage] = useState(1);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [confirmId, setConfirmId] = useState<number | null>(null);
  const [cancelId, setCancelId] = useState<number | null>(null);
  const [assignId, setAssignId] = useState<number | null>(null);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [excelOpen, setExcelOpen] = useState(false);
  const closeLabel = t("m.close");

  useEffect(() => {
    openExcel = () => setExcelOpen(true);
    return () => {
      openExcel = null;
    };
  }, []);

  const filtered = useMemo(() => applyFilters(rows, filters, scopeHospital), [rows, filters, scopeHospital]);
  const counts = useMemo(() => countByStatus(rows, filters.hospitalId, scopeHospital), [rows, filters.hospitalId, scopeHospital]);
  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const current = Math.min(page, pages);
  const visible = filtered.slice((current - 1) * PAGE_SIZE, current * PAGE_SIZE);
  const byId = (id: number | null) => rows.find((r) => r.id === id) ?? null;
  const set = (patch: Partial<Filters>) => {
    setFilters((f) => ({ ...f, ...patch }));
    setPage(1);
  };
  const dirty = JSON.stringify(filters) !== JSON.stringify(EMPTY_FILTERS);

  const timers = useRef<number[]>([]);
  useEffect(() => {
    const list = timers.current;
    return () => list.forEach((x) => window.clearTimeout(x));
  }, []);
  const move = (id: number, action: Action, cancelReason = "") => {
    setBusyId(id);
    timers.current.push(window.setTimeout(() => {
      const status = targetOf(action);
      setRows((all) => all.map((r) => (r.id === id ? { ...r, status, cancelReason: action === "cancel" ? cancelReason : r.cancelReason, doctor: action === "confirm" && !r.doctor ? (MOCK_DOCTORS[r.hospitalId]?.[0] ?? null) : r.doctor } : r)));
      setBusyId(null);
      showUnitToast({ tone: "ok", message: t("bk.toast.moved", { id: String(id), status: t(stKey(status)) }), closeLabel });
    }, 450));
  };
  const run = (id: number, action: Action) => {
    // One change at a time: a second one waits until the first has finished.
    if (busyId !== null) return;
    if (action === "confirm") setConfirmId(id);
    else if (action === "cancel") setCancelId(id);
    else move(id, action);
  };
  const assign = (id: number, doctor: string | null) => {
    setRows((all) => all.map((r) => (r.id === id ? { ...r, doctor } : r)));
    showUnitToast({ tone: "ok", message: doctor ? t("bk.toast.doctor", { id: String(id), name: doctor }) : t("bk.toast.doctorOff", { id: String(id) }), closeLabel });
  };

  const statTiles: { key: string; label: string; value: number; pressed: boolean; onClick: () => void; icon?: IconName }[] = [
    { key: "all", label: t("bk.stat.all"), value: counts.all, pressed: !filters.status && !filters.from && !filters.to, onClick: () => set({ status: "", from: "", to: "" }) },
    ...(["pending", "hospital_checking", "confirmed", "completed", "cancelled"] as BookingStatus[]).map((s) => ({
      key: s, label: t(stKey(s)), value: counts.by[s] ?? 0, pressed: filters.status === s, icon: STATUS_ICON[s], onClick: () => set({ status: filters.status === s ? "" : s }),
    })),
    { key: "today", label: t("bk.stat.today"), value: counts.today, pressed: filters.from === MOCK_TODAY && filters.to === MOCK_TODAY, icon: "calendar", onClick: () => (filters.from === MOCK_TODAY && filters.to === MOCK_TODAY ? set({ from: "", to: "" }) : set({ from: MOCK_TODAY, to: MOCK_TODAY })) },
  ];

  const actionButtons = (r: MockBooking) => {
    const acts = actionsFor(r.status);
    if (!acts.length) return <span className="muted">—</span>;
    return acts.map((a) => (
      <button key={a} type="button" className={`btn btn--sm ${a === "cancel" ? "btn--ghost btn--reject" : "btn--primary"}`} disabled={busyId !== null} aria-label={`${t(actKey(a))} #${r.id}`} onClick={() => run(r.id, a)}>
        {busyId === r.id && a !== "cancel" ? t("bk.working") : t(actKey(a))}
      </button>
    ));
  };

  return (
    <div className="bk-page">
      <Notice tone="info" icon="info">{t("bk.draft")}</Notice>
      {scopeHospital ? <p className="bk-chip"><Icon name="hospital" size={14} />{hospitalName(scopeHospital)}</p> : null}

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
        {!scopeHospital ? (
          <label className="field field--hosp"><span className="field__label">{t("bk.f.hospital")}</span>
            <select className="sel sel--field" value={filters.hospitalId} onChange={(e) => set({ hospitalId: Number(e.target.value) })}>
              <option value={0}>{t("bk.f.hospitalAll")}</option>
              {MOCK_HOSPITALS.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
            </select>
          </label>
        ) : null}
        <label className="field"><span className="field__label">{t("bk.f.status")}</span>
          <select className="sel sel--field" value={filters.status} onChange={(e) => set({ status: e.target.value as BookingStatus | "" })}>
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
          <input className="input" type="search" maxLength={60} placeholder={t("bk.f.searchPh")} value={filters.q} onChange={(e) => set({ q: e.target.value })} />
        </label>
        <button type="button" className="btn btn--ghost bk-filters__reset" disabled={!dirty} onClick={() => set({ ...EMPTY_FILTERS })}>{t("bk.f.reset")}</button>
      </form>

      {visible.length === 0 ? (
        <div className="bk-none">
          <Icon name="search" size={28} />
          <h2>{t(rows.length ? "bk.none.title" : "bk.empty.title")}</h2>
          <p>{t(rows.length ? "bk.none.body" : "bk.empty.body")}</p>
          {dirty ? <button type="button" className="btn btn--ghost" onClick={() => set({ ...EMPTY_FILTERS })}>{t("bk.none.reset")}</button> : null}
        </div>
      ) : (
        <>
          <div className="bk-tbl-wrap">
            <table className="tbl bk-tbl">
              <colgroup>
                <col className="bk-c-id" /><col className="bk-c-name" /><col className="bk-c-phone" />
                {!scopeHospital ? <col className="bk-c-hosp" /> : null}
                <col className="bk-c-tr" /><col className="bk-c-date" /><col className="bk-c-time" /><col className="bk-c-photo" /><col className="bk-c-st" /><col className="bk-c-doc" /><col className="bk-c-act" />
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">#</th><th scope="col">{t("bk.col.name")}</th><th scope="col">{t("bk.col.phone")}</th>
                  {!scopeHospital ? <th scope="col">{t("bk.col.hospital")}</th> : null}
                  <th scope="col">{t("bk.col.treatment")}</th><th scope="col">{t("bk.col.date")}</th><th scope="col">{t("bk.col.time")}</th>
                  <th scope="col">{t("bk.col.photos")}</th><th scope="col" className="bk-th-st">{t("bk.col.status")}</th><th scope="col">{t("bk.col.doctor")}</th><th scope="col">{t("bk.col.actions")}</th>
                </tr>
              </thead>
              <tbody>
                {visible.map((r) => (
                  <tr key={r.id} className={busyId === r.id ? "is-busy" : undefined}>
                    <td className="bk-num">{r.id}</td>
                    <td><button type="button" className="bk-name" title={r.name} onClick={() => setDetailId(r.id)}>{r.name}</button></td>
                    <td className="bk-num bk-nowrap">{r.phone}</td>
                    {!scopeHospital ? <td className="bk-ell" title={hospitalName(r.hospitalId)}>{hospitalName(r.hospitalId)}</td> : null}
                    <td className="bk-ell" title={r.treatment}>{r.treatment}</td>
                    <td className="bk-num"><DateText date={r.date} /></td>
                    <td className="bk-num">{r.time}</td>
                    <td>{r.photos ? <span className="bk-photos"><Icon name="image" size={14} />{r.photos}</span> : <span className="muted">—</span>}</td>
                    <td className="bk-td-st"><StatusBadge status={r.status} /></td>
                    <td><DoctorCell r={r} onAssign={() => setAssignId(r.id)} /></td>
                    <td><div className="bk-acts">{actionButtons(r)}</div></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <ul className="bk-cards">
            {visible.map((r) => (
              <li key={r.id} className={`bk-card${busyId === r.id ? " is-busy" : ""}`}>
                <div className="bk-card__top"><span className="bk-num muted">#{r.id}</span><StatusBadge status={r.status} /></div>
                <button type="button" className="bk-name bk-card__name" onClick={() => setDetailId(r.id)}>{r.name}</button>
                <dl className="bk-dl">
                  {!scopeHospital ? <><dt>{t("bk.col.hospital")}</dt><dd>{hospitalName(r.hospitalId)}</dd></> : null}
                  <dt>{t("bk.col.treatment")}</dt><dd>{r.treatment}</dd>
                  <dt>{t("bk.col.date")}</dt><dd className="bk-num"><DateText date={r.date} /> {r.time}</dd>
                  <dt>{t("bk.col.phone")}</dt><dd className="bk-num">{r.phone}</dd>
                  <dt>{t("bk.col.doctor")}</dt><dd><DoctorCell r={r} onAssign={() => setAssignId(r.id)} /></dd>
                  <dt>{t("bk.col.photos")}</dt><dd>{r.photos ? <span className="bk-photos"><Icon name="image" size={14} />{r.photos}</span> : "—"}</dd>
                </dl>
                {actionsFor(r.status).length ? (
                  <div className="bk-card__acts">
                    {actionButtons(r)}
                    <button type="button" className="btn btn--ghost bk-card__doc" onClick={() => setAssignId(r.id)}>{t("bk.doctor.change")}</button>
                  </div>
                ) : null}
              </li>
            ))}
          </ul>

          <nav className="pager" aria-label={t("bk.pager.label")}>
            {pages > 1 ? (
              <>
                <button type="button" className="pager__btn pager__prev" disabled={current === 1} onClick={() => setPage(current - 1)} aria-label={t("bk.prev")}><Icon name="chevron" size={16} /></button>
                {Array.from({ length: pages }, (_, i) => i + 1).map((n) => (
                  <button key={n} type="button" className="pager__btn" aria-current={n === current ? "page" : undefined} aria-label={t("bk.page", { n: String(n) })} onClick={() => setPage(n)}>{n}</button>
                ))}
                <button type="button" className="pager__btn" disabled={current === pages} onClick={() => setPage(current + 1)} aria-label={t("bk.next")}><Icon name="chevron" size={16} /></button>
              </>
            ) : null}
            <p className="pager__info">{t("bk.pager", { from: String((current - 1) * PAGE_SIZE + 1), to: String(Math.min(current * PAGE_SIZE, filtered.length)), total: String(filtered.length), size: String(PAGE_SIZE) })}</p>
          </nav>
        </>
      )}

      <ConfirmModal booking={byId(confirmId)} onClose={() => setConfirmId(null)} onOk={(id) => { setConfirmId(null); move(id, "confirm"); }} />
      <CancelModal booking={byId(cancelId)} onClose={() => setCancelId(null)} onOk={(id, reason) => { setCancelId(null); move(id, "cancel", reason); }} />
      <AssignModal booking={byId(assignId)} onClose={() => setAssignId(null)} onPick={(id, name) => { setAssignId(null); assign(id, name); }} />
      <DetailDrawer booking={byId(detailId)} onClose={() => setDetailId(null)} showHospital={!scopeHospital} onAct={(id, a) => { setDetailId(null); run(id, a); }} />
      <ExcelModal open={excelOpen} scopeHospital={scopeHospital} rows={rows} onClose={() => setExcelOpen(false)} />
    </div>
  );
}

function DateText({ date }: { date: string }) {
  const locale = useLocale();
  const [y, m, d] = date.split("-").map(Number);
  const wd = new Date(y, m - 1, d).toLocaleDateString(locale, { weekday: "short" });
  return <>{`${String(m).padStart(2, "0")}.${String(d).padStart(2, "0")} (${wd})`}</>;
}

function DoctorCell({ r, onAssign }: { r: MockBooking; onAssign: () => void }) {
  const t = useT();
  const final = actionsFor(r.status).length === 0;
  if (!r.doctor) return final ? <span className="muted">{t("bk.doctor.none")}</span> : <button type="button" className="bk-link" onClick={onAssign} aria-label={`${t("bk.doctor.assign")} #${r.id}`}>{t("bk.doctor.assign")}</button>;
  return (
    <span className="bk-doc">
      <span className="bk-ell" title={r.doctor}>{r.doctor}</span>
      {final ? null : <button type="button" className="ibtn bk-doc__edit" onClick={onAssign} aria-label={`${t("bk.doctor.change")} #${r.id}`} title={t("bk.doctor.change")}><Icon name="edit" size={16} /></button>}
    </span>
  );
}

function ConfirmModal({ booking, onClose, onOk }: { booking: MockBooking | null; onClose: () => void; onOk: (id: number) => void }) {
  const t = useT();
  return (
    <BookingModal open={!!booking} label={t("bk.confirm.title")} onClose={onClose}>
      {booking ? (
        <div className="dialog__box">
          <div className="dialog__head"><h2 className="dialog__title">{t("bk.confirm.title")}</h2><button type="button" className="ibtn dialog__close" onClick={onClose} aria-label={t("m.close")}><Icon name="close" /></button></div>
          <p className="dialog__text dialog__text--strong" translate="no">#{booking.id} · {booking.name}</p>
          <p className="dialog__text">{t("bk.confirm.body")}</p>
          <div className="dialog__err"><Notice tone="warn" icon="alert">{t("bk.confirm.warn")}</Notice></div>
          <div className="dialog__foot bk-foot">
            <button type="button" className="btn btn--ghost" onClick={onClose} data-autofocus="">{t("bk.common.cancel")}</button>
            <button type="button" className="btn btn--primary" onClick={() => onOk(booking.id)}>{t("bk.confirm.ok")}</button>
          </div>
        </div>
      ) : null}
    </BookingModal>
  );
}

function CancelModal({ booking, onClose, onOk }: { booking: MockBooking | null; onClose: () => void; onOk: (id: number, reason: string) => void }) {
  const t = useT();
  return (
    <BookingModal open={!!booking} label={t("bk.cancel.title")} onClose={onClose}>
      {booking ? <CancelBody booking={booking} onClose={onClose} onOk={onOk} /> : null}
    </BookingModal>
  );
}

// The body is mounted fresh every time the dialog opens, so its fields always start empty.
function CancelBody({ booking, onClose, onOk }: { booking: MockBooking; onClose: () => void; onOk: (id: number, reason: string) => void }) {
  const t = useT();
  const [reason, setReason] = useState("");
  return (
    <>
      {(
        <div className="dialog__box">
          <div className="dialog__head"><h2 className="dialog__title">{t("bk.cancel.title")}</h2><button type="button" className="ibtn dialog__close" onClick={onClose} aria-label={t("m.close")}><Icon name="close" /></button></div>
          <p className="dialog__text dialog__text--strong" translate="no">#{booking.id} · {booking.name}</p>
          <label className="field"><span className="field__label">{t("bk.cancel.reason")}</span>
            <textarea className="bk-textarea" maxLength={200} rows={4} placeholder={t("bk.cancel.reasonPh")} value={reason} onChange={(e) => setReason(e.target.value)} aria-describedby="bk-count" data-autofocus="" />
          </label>
          <span id="bk-count" className="bk-count">{reason.length} / 200</span>
          <div className="dialog__err"><Notice tone="warn" icon="alert">{t("bk.cancel.warn")}</Notice></div>
          <div className="dialog__foot bk-foot">
            <button type="button" className="btn btn--ghost" onClick={onClose}>{t("bk.cancel.back")}</button>
            <button type="button" className="btn bk-danger" onClick={() => onOk(booking.id, reason.trim())}>{t("bk.cancel.ok")}</button>
          </div>
        </div>
      )}
    </>
  );
}

function AssignModal({ booking, onClose, onPick }: { booking: MockBooking | null; onClose: () => void; onPick: (id: number, doctor: string | null) => void }) {
  const t = useT();
  return (
    <BookingModal open={!!booking} label={t("bk.assign.title")} onClose={onClose}>
      {booking ? <AssignBody booking={booking} onClose={onClose} onPick={onPick} /> : null}
    </BookingModal>
  );
}

function AssignBody({ booking, onClose, onPick }: { booking: MockBooking; onClose: () => void; onPick: (id: number, doctor: string | null) => void }) {
  const t = useT();
  const [pick, setPick] = useState<string | null>(booking.doctor);
  const doctors = MOCK_DOCTORS[booking.hospitalId] ?? [];
  return (
    <>
      {(
        <div className="dialog__box">
          <div className="dialog__head"><h2 className="dialog__title">{t("bk.assign.title")}</h2><button type="button" className="ibtn dialog__close" onClick={onClose} aria-label={t("m.close")}><Icon name="close" /></button></div>
          <p className="dialog__text dialog__text--strong" translate="no">#{booking.id} · {booking.name}</p>
          <ul className="bk-list">
            {doctors.map((d, i) => (
              <li key={d}><label className="bk-list__row"><input type="radio" name="bk-doc" checked={pick === d} onChange={() => setPick(d)} data-autofocus={i === 0 ? "" : undefined} /><span>{d}</span></label></li>
            ))}
          </ul>
          <div className="dialog__err"><Notice tone="info" icon="info">{t("bk.assign.info")}</Notice></div>
          {booking.doctor ? <button type="button" className="bk-link bk-link--danger" onClick={() => onPick(booking.id, null)}>{t("bk.assign.clear")}</button> : null}
          <div className="dialog__foot bk-foot">
            <button type="button" className="btn btn--ghost" onClick={onClose}>{t("bk.common.cancel")}</button>
            <button type="button" className="btn btn--primary" disabled={!pick || pick === booking.doctor} onClick={() => pick && onPick(booking.id, pick)}>{t("bk.assign.ok")}</button>
          </div>
        </div>
      )}
    </>
  );
}

function DetailDrawer({ booking, showHospital, onClose, onAct }: { booking: MockBooking | null; showHospital: boolean; onClose: () => void; onAct: (id: number, a: Action) => void }) {
  const t = useT();
  const acts = booking ? actionsFor(booking.status) : [];
  return (
    <BookingModal open={!!booking} label={booking ? t("bk.detail.title", { id: String(booking.id) }) : ""} kind="drawer" onClose={onClose}>
      {booking ? (
        <div className="bk-drawer">
          <header className="bk-drawer__head">
            <div>
              <p className="muted">{t("bk.detail.title", { id: String(booking.id) })}</p>
              <h2 className="bk-drawer__name">{booking.name}</h2>
              <StatusBadge status={booking.status} />
            </div>
            <button type="button" className="ibtn bk-drawer__close" onClick={onClose} aria-label={t("m.close")} data-autofocus=""><Icon name="close" /></button>
          </header>
          <div className="bk-drawer__body">
            <dl className="bk-dl bk-dl--wide">
              <dt>{t("bk.col.phone")}</dt><dd className="bk-num">{booking.phone}</dd>
              {showHospital ? <><dt>{t("bk.col.hospital")}</dt><dd>{hospitalName(booking.hospitalId)}</dd></> : null}
              <dt>{t("bk.col.treatment")}</dt><dd>{booking.treatment}</dd>
              <dt>{t("bk.col.date")}</dt><dd className="bk-num"><DateText date={booking.date} /> {booking.time}</dd>
              <dt>{t("bk.col.doctor")}</dt><dd>{booking.doctor ?? t("bk.doctor.none")}</dd>
              <dt>{t("bk.detail.received")}</dt><dd className="bk-num">{booking.receivedAt}</dd>
            </dl>
            {booking.memo ? <section><h3>{t("bk.detail.memo")}</h3><p>{booking.memo}</p></section> : null}
            {booking.cancelReason ? <section><h3>{t("bk.detail.reason")}</h3><p>{booking.cancelReason}</p></section> : null}
            <section><h3>{t("bk.detail.answers")}</h3>
              <dl className="bk-qa">{booking.answers.map((x) => <div key={x.q}><dt>{x.q}</dt><dd>{x.a}</dd></div>)}</dl>
            </section>
            {booking.photos ? (
              <section><h3>{t("bk.detail.photos")}</h3>
                <ul className="bk-thumbs">{Array.from({ length: booking.photos }, (_, i) => <li key={i} className="bk-thumb"><Icon name="image" size={20} /></li>)}</ul>
              </section>
            ) : null}
          </div>
          <footer className="bk-drawer__foot">
            {acts.length === 0 ? <p className="muted bk-drawer__final">{t("bk.detail.final")}</p> : null}
            <button type="button" className="btn btn--ghost" onClick={onClose}>{t("bk.detail.close")}</button>
            {acts.map((a) => (
              <button key={a} type="button" className={a === "cancel" ? "btn btn--ghost btn--reject" : "btn btn--primary"} onClick={() => onAct(booking.id, a)}>{t(actKey(a))}</button>
            ))}
          </footer>
        </div>
      ) : null}
    </BookingModal>
  );
}

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

function ExcelModal({ open, scopeHospital, rows, onClose }: { open: boolean; scopeHospital: number | null; rows: MockBooking[]; onClose: () => void }) {
  const t = useT();
  const [excelBusy, setExcelBusy] = useState(false);
  return (
    <BookingModal open={open} label={t("bk.xl.title")} kind="sheet" onClose={onClose} locked={excelBusy}>
      {open ? <ExcelBody scopeHospital={scopeHospital} rows={rows} onClose={onClose} onBusy={setExcelBusy} /> : null}
    </BookingModal>
  );
}

function ExcelBody({ scopeHospital, rows, onClose, onBusy }: { scopeHospital: number | null; rows: MockBooking[]; onClose: () => void; onBusy: (b: boolean) => void }) {
  const t = useT();
  const [all, setAll] = useState(true);
  const [picked, setPicked] = useState<number[]>([]);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [status, setStatus] = useState<BookingStatus | "">("");
  const [phase, setPhase] = useState<"idle" | "busy" | "empty" | "needRange" | "pickOne">("idle");
  const timer = useRef<number | null>(null);
  useEffect(() => () => {
    if (timer.current) window.clearTimeout(timer.current);
    onBusy(false);
  }, [onBusy]);

  const preset = (days: number | "month") => {
    const [ty, tm, td] = MOCK_TODAY.split("-").map(Number);
    const end = new Date(ty, tm - 1, td);
    const start = new Date(ty, tm - 1, td);
    if (days === "month") start.setDate(1);
    else start.setDate(end.getDate() - days);
    setFrom(iso(start));
    setTo(iso(end));
    setPhase("idle");
  };
  const go = () => {
    if (!from || !to) { setPhase("needRange"); return; }
    if (!scopeHospital && !all && picked.length === 0) { setPhase("pickOne"); return; }
    const ids = scopeHospital ? [scopeHospital] : all ? [] : picked;
    const n = rows.filter((r) => (ids.length ? ids.includes(r.hospitalId) : true) && r.date >= from && r.date <= to && (status ? r.status === status : true)).length;
    if (n === 0) { setPhase("empty"); return; }
    setPhase("busy");
    onBusy(true);
    timer.current = window.setTimeout(() => {
      onBusy(false);
      onClose();
      showUnitToast({ tone: "info", message: t("bk.toast.excel"), closeLabel: t("m.close") });
    }, 900);
  };
  const busy = phase === "busy";

  return (
    <>
      {(
        <div className="dialog__box bk-xl">
          <div className="dialog__head"><h2 className="dialog__title">{t("bk.xl.title")}</h2><button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("m.close")}><Icon name="close" /></button></div>
          <fieldset className="bk-xl__sec" disabled={busy}>
            <legend>{t("bk.xl.hospital")}</legend>
            {scopeHospital ? <p className="bk-chip"><Icon name="hospital" size={14} />{hospitalName(scopeHospital)}</p> : (
              <>
                <div className="seg">
                  <button type="button" aria-pressed={all} onClick={() => setAll(true)}>{t("bk.xl.hospitalAll")}</button>
                  <button type="button" aria-pressed={!all} onClick={() => setAll(false)}>{t("bk.xl.hospitalPick")}</button>
                </div>
                {!all ? (
                  <ul className="bk-list">
                    {MOCK_HOSPITALS.map((h) => (
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
          <div className="dialog__foot bk-foot">
            <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("bk.common.cancel")}</button>
            <button type="button" className="btn btn--primary" onClick={go} disabled={busy}><Icon name="sheet" size={16} />{busy ? t("bk.xl.preparing") : t("bk.xl.go")}</button>
          </div>
        </div>
      )}
    </>
  );
}
