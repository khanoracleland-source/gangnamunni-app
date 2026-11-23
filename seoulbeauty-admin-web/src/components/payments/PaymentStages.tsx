"use client";

import { useEffect, useId, useRef, useState, type KeyboardEvent } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { Pager } from "./PaymentsTables";
import {
  fetchPaymentBoard, fetchPaymentOverview, fetchPaymentSection, type ApiResult, type BoardBox, type BoardPeriod, type PaymentBoard, type PaymentOverview, type PaymentSection, type SectionRow, type SectionStage,
} from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";
import { bpToPercent, methodKey } from "@/lib/paymentFees";
import { monthDay, monthDayTime, won, wonOrDash } from "@/lib/payments";

const STAGES: SectionStage[] = ["held", "counted", "received"];
const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

/** A "?" that explains a figure; it opens on hover, focus or press and closes with Escape. */
function Help({ text, label }: { text: string; label: string }) {
  const id = useId();
  const [open, setOpen] = useState(false);
  return (
    <span className="pay-help" onMouseLeave={() => setOpen(false)}>
      <button type="button" className="pay-help__btn" aria-label={label} aria-expanded={open} aria-describedby={open ? id : undefined} onClick={() => setOpen(true)} onMouseEnter={() => setOpen(true)} onBlur={() => setOpen(false)} onKeyDown={(e) => { if (e.key === "Escape") setOpen(false); }}>?</button>
      {open ? <span id={id} role="tooltip" className="pay-help__tip">{text}</span> : null}
    </span>
  );
}

/** This week / this month: how much of the chosen hospital is 보류, 반영 and 입금. */
export function PeriodBoard({ hospitalId, tick, onLeave, onRange }: { hospitalId: number; tick: number; onLeave?: () => void; onRange?: (r: { from: string; to: string }) => void }) {
  const t = useT();
  const [period, setPeriod] = useState<BoardPeriod>("week");
  const [got, setGot] = useState<{ key: string; res: ApiResult<PaymentBoard> } | null>(null);
  const key = `${hospitalId}|${period}|${tick}`;
  useEffect(() => {
    const c = new AbortController();
    void fetchPaymentBoard(hospitalId, period, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) { (onLeave ?? leaveToLogin)(); return; }
      setGot({ key, res });
    });
    return () => c.abort();
    // `key` stands for the inputs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);
  const loading = got?.key !== key;
  const board = got?.res.ok ? got.res.data : null;
  const failed = got?.key === key && !got.res.ok;
  const boardFrom = board?.from;
  const boardTo = board?.to;
  useEffect(() => {
    if (boardFrom && boardTo) onRange?.({ from: boardFrom, to: boardTo });
    // The callback only stores the range.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [boardFrom, boardTo]);
  const box = (s: SectionStage): BoardBox | null => (board ? board[s] : null);
  return (
    <section className={`pay-board${loading ? " bk-busy" : ""}`} aria-label={t("pay.board.title")} aria-busy={loading}>
      <div className="pay-board__head">
        <h2 className="pay-board__title">{t("pay.board.title")}</h2>
        <div className="pay-seg" role="group" aria-label={t("pay.board.period")}>
          {(["week", "month"] as const).map((p) => (
            <button key={p} type="button" className="pay-seg__btn" aria-pressed={period === p} onClick={() => setPeriod(p)}>{t(p === "week" ? "pay.board.week" : "pay.board.month")}</button>
          ))}
        </div>
        {board ? <span className="muted pay-board__range">{board.from} ~ {board.to}</span> : null}
      </div>
      {failed ? <Notice tone="warn" icon="alert">{t("pay.err.list")}</Notice> : null}
      <div className="pay-boxes">
        {STAGES.map((s) => {
          const b = box(s);
          return (
            <div key={s} className={`pay-box pay-box--${s}`}>
              <div className="pay-box__label">{t(`pay.stage3.${s}` as never)}<Help label={t("pay.help.label")} text={t(`pay.help.${s}` as never)} /></div>
              <div className="pay-box__value">{b ? won(b.amount) : "—"}<small>원</small></div>
              <div className="muted pay-box__sub">{b ? t("pay.board.count", { n: won(b.count) }) : ""}{b && b.noFee > 0 ? ` · ${t("pay.board.noFee", { n: String(b.noFee) })}` : ""}</div>
              <div className="muted pay-box__note">{t(`pay.board.note.${s}` as never)}</div>
            </div>
          );
        })}
      </div>
    </section>
  );
}

const rate = (r: SectionRow): string => (r.feeRateBp === null ? "" : `${bpToPercent(r.feeRateBp)}%`);

/** One row of a section as a table row; the columns differ per section. */
function Cells({ stage, r, own }: { stage: SectionStage; r: SectionRow; own: boolean }) {
  const t = useT();
  const visit = `${monthDay(r.visitDate)} ${r.visitTime}`;
  const dash = <span className="muted">—</span>;
  return (
    <>
      <td><span className="pay-ell" title={r.patientName}>{r.patientName || "—"}</span></td>
      {!own ? <td><span className="pay-ell" title={r.hospitalName}>{r.hospitalName}</span></td> : null}
      <td><span className="pay-ell" title={r.treatmentName}>{r.treatmentName || "—"}</span></td>
      <td className="pay-nowrap">{visit}</td>
      {stage === "held" ? <td className="pay-r">{won(r.amount)}</td> : null}
      {stage !== "held" ? <td className="pay-nowrap">{stage === "counted" ? monthDayTime(r.paidAt) : monthDay(r.payoutDate ?? "")}</td> : null}
      {stage !== "held" ? <td className="pay-r">{won(r.amount)}</td> : null}
      {stage !== "held" ? <td className="pay-nowrap">{r.payMethod ? `${t(methodKey(r.payMethod))} ${rate(r)}` : dash}</td> : null}
      {stage !== "held" ? <td className="pay-r">{r.fee === null ? dash : wonOrDash(r.fee)}</td> : null}
      {stage !== "held" ? <td className="pay-r pay-strong">{r.settlement === null ? dash : won(r.settlement)}</td> : null}
      {stage === "counted" ? <td className="pay-nowrap">{r.payoutDate ? monthDay(r.payoutDate) : t("pay.col.afterTreatment")}</td> : null}
    </>
  );
}

function Head({ stage, own }: { stage: SectionStage; own: boolean }) {
  const t = useT();
  return (
    <tr>
      <th scope="col">{t("pay.col.patient")}</th>
      {!own ? <th scope="col">{t("pay.col.hospital")}</th> : null}
      <th scope="col">{t("pay.col.treatment")}</th>
      <th scope="col">{t("pay.col.visitAt")}</th>
      {stage === "held" ? <th scope="col" className="pay-r">{t("pay.col.amount")}</th> : null}
      {stage !== "held" ? <th scope="col">{t(stage === "counted" ? "pay.col.paidAt2" : "pay.col.payoutDay")}</th> : null}
      {stage !== "held" ? <th scope="col" className="pay-r">{t("pay.col.paid")}</th> : null}
      {stage !== "held" ? <th scope="col">{t("pay.col.method")}</th> : null}
      {stage !== "held" ? <th scope="col" className="pay-r">{t("pay.col.fee")}</th> : null}
      {stage !== "held" ? <th scope="col" className="pay-r">{t("pay.col.settlement")}</th> : null}
      {stage === "counted" ? <th scope="col">{t("pay.col.payoutDue")}</th> : null}
    </tr>
  );
}

export type SectionFilters = { hospitalId: number; from: string; to: string; q: string };

/** The three sections: 보류, 반영 and 입금, each a list of bookings with its own columns. Dates are sent only once the user chose them. */
export function Sections({ filters, datesTouched, range, own, tick, onLeave }: { filters: SectionFilters; datesTouched: boolean; range: { from: string; to: string } | null; own: boolean; tick: number; onLeave?: () => void }) {
  const t = useT();
  const [stage, setStage] = useState<SectionStage>("held");
  const [page, setPage] = useState(1);
  const [retry, setRetry] = useState(0);
  const [got, setGot] = useState<{ key: string; res: ApiResult<PaymentSection> } | null>(null);
  const refs = useRef<(HTMLButtonElement | null)[]>([]);
  // Until the user picks dates, the lists follow the week or month chosen on the board above.
  const from = datesTouched ? filters.from : (range?.from ?? "");
  const to = datesTouched ? filters.to : (range?.to ?? "");
  const waiting = !datesTouched && !range;
  const key = `${stage}|${filters.hospitalId}|${from}|${to}|${filters.q.trim()}|${page}|${tick}|${retry}`;

  // A new filter or section starts again at the first page.
  const filterKey = `${stage}|${filters.hospitalId}|${from}|${to}|${filters.q.trim()}`;
  const [seen, setSeen] = useState(filterKey);
  if (seen !== filterKey) { setSeen(filterKey); setPage(1); }

  useEffect(() => {
    if (waiting) return;
    const c = new AbortController();
    void fetchPaymentSection({ stage, hospitalId: filters.hospitalId, from, to, q: filters.q, page }, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) { (onLeave ?? leaveToLogin)(); return; }
      setGot({ key, res });
      if (res.ok && res.data.page !== page) setPage(res.data.page);
    });
    return () => c.abort();
    // `key` stands for the inputs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const onKey = (e: KeyboardEvent<HTMLDivElement>) => {
    const i = STAGES.indexOf(stage);
    let n = i;
    if (e.key === "ArrowRight") n = (i + 1) % 3;
    else if (e.key === "ArrowLeft") n = (i + 2) % 3;
    else return;
    e.preventDefault();
    setStage(STAGES[n]);
    refs.current[n]?.focus();
  };

  const loading = got?.key !== key;
  // While another section loads, the previous one's rows are not shown under the new columns.
  const data = got?.res.ok && got.res.data.stage === stage ? got.res.data : null;
  const failed = got?.key === key && !got.res.ok;
  return (
    <section className="pay-list">
      <div className="pay-tabs" role="tablist" aria-label={t("pay.tabs")} onKeyDown={onKey}>
        {STAGES.map((s, i) => (
          <button key={s} ref={(el) => { refs.current[i] = el; }} type="button" role="tab" id={`pay-stage-${s}`} aria-selected={stage === s} aria-controls="pay-stage-panel" tabIndex={stage === s ? 0 : -1} className="pay-tab" onClick={() => setStage(s)}>
            {t(`pay.stage3.${s}` as never)}
          </button>
        ))}
      </div>
      <div id="pay-stage-panel" role="tabpanel" aria-labelledby={`pay-stage-${stage}`} className={loading ? "bk-busy" : undefined} aria-busy={loading}>
        <p className="muted pay-stage-note">{t(`pay.help.${stage}` as never)}</p>
        {failed ? (
          <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={() => setRetry((n) => n + 1)}>{t("pay.err.retry")}</button>}>{t("pay.err.list")}</Notice>
        ) : null}
        {data ? (
          <>
            <p className="pay-totals">
              {t("pay.sec.totals", { n: won(data.total), amount: won(stage === "held" ? data.totals.received : data.totals.settlement) })}
              {stage !== "held" ? ` · ${t("pay.col.fee")} ${won(data.totals.fee)}원` : ""}
              {stage !== "held" && data.totals.noFee > 0 ? ` · ${t("pay.board.noFee", { n: String(data.totals.noFee) })}` : ""}
            </p>
            {data.items.length === 0 ? (
              <div className="bk-none"><Icon name="search" size={28} /><h2>{t("pay.sec.empty.title")}</h2><p>{t("pay.sec.empty.body")}</p></div>
            ) : (
              <>
                <div className="pay-tbl-wrap">
                  <table className="tbl pay-tbl pay-tbl--stage">
                    <thead><Head stage={stage} own={own} /></thead>
                    <tbody>{data.items.map((r) => <tr key={r.id}><Cells stage={stage} r={r} own={own} /></tr>)}</tbody>
                  </table>
                </div>
                <ul className="pay-cards">
                  {data.items.map((r) => (
                    <li key={r.id} className="bk-card">
                      <div className="bk-card__top"><span className="muted pay-nowrap">{monthDay(r.visitDate)} {r.visitTime}</span>{stage !== "held" && r.settlement !== null ? <strong>{won(r.settlement)}원</strong> : null}</div>
                      <p className="bk-card__name pay-card__name">{r.patientName || "—"}</p>
                      <dl className="bk-dl">
                        {!own ? <><dt>{t("pay.col.hospital")}</dt><dd>{r.hospitalName}</dd></> : null}
                        <dt>{t("pay.col.treatment")}</dt><dd>{r.treatmentName || "—"}</dd>
                        <dt>{t("pay.col.visitAt")}</dt><dd>{monthDay(r.visitDate)} {r.visitTime}</dd>
                        {stage === "held" ? <><dt>{t("pay.col.amount")}</dt><dd className="pay-r">{won(r.amount)}</dd></> : (
                          <>
                            <dt>{t(stage === "counted" ? "pay.col.paidAt2" : "pay.col.payoutDay")}</dt><dd>{stage === "counted" ? monthDayTime(r.paidAt) : monthDay(r.payoutDate ?? "")}</dd>
                            <dt>{t("pay.col.paid")}</dt><dd className="pay-r">{won(r.amount)}</dd>
                            <dt>{t("pay.col.method")}</dt><dd>{r.payMethod ? `${t(methodKey(r.payMethod))} ${rate(r)}` : "—"}</dd>
                            <dt>{t("pay.col.fee")}</dt><dd className="pay-r">{r.fee === null ? "—" : wonOrDash(r.fee)}</dd>
                            <dt>{t("pay.col.settlement")}</dt><dd className="pay-r">{r.settlement === null ? "—" : won(r.settlement)}</dd>
                            {stage === "counted" ? <><dt>{t("pay.col.payoutDue")}</dt><dd>{r.payoutDate ? monthDay(r.payoutDate) : t("pay.col.afterTreatment")}</dd></> : null}
                          </>
                        )}
                      </dl>
                    </li>
                  ))}
                </ul>
                <Pager list={data} onPage={setPage} />
              </>
            )}
          </>
        ) : failed ? null : <p className="muted" role="status">{t("pay.loading")}</p>}
      </div>
    </section>
  );
}

/** Per payment method and per payout Sunday, for the chosen hospital and dates (collapsed by default). */
export function Totals({ hospitalId, from, to, tick }: { hospitalId: number; from: string; to: string; tick: number }) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const [got, setGot] = useState<{ key: string; res: ApiResult<PaymentOverview> } | null>(null);
  const key = `${hospitalId}|${from}|${to}|${tick}`;
  useEffect(() => {
    if (!open) return;
    const c = new AbortController();
    void fetchPaymentOverview({ hospitalId, from, to }, c.signal).then((res) => { if (!c.signal.aborted) setGot({ key, res }); });
    return () => c.abort();
    // `key` stands for the inputs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, open]);
  const data = got?.key === key && got.res.ok ? got.res.data : null;
  return (
    <details className="pay-details" onToggle={(e) => setOpen((e.currentTarget as HTMLDetailsElement).open)}>
      <summary>{t("pay.totals.title")}</summary>
      {open && !data ? <p className="muted" role="status">{t("pay.loading")}</p> : null}
      {data ? (
        <div className="pay-totals-grid">
          <div>
            <h3>{t("pay.totals.byMethod")}</h3>
            <table className="pay-gtbl">
              <thead><tr><th scope="col">{t("pay.col.method")}</th><th scope="col" className="pay-r">{t("pay.col.payments")}</th><th scope="col" className="pay-r">{t("pay.col.paid")}</th><th scope="col" className="pay-r">{t("pay.col.fee")}</th><th scope="col" className="pay-r">{t("pay.col.settlement")}</th></tr></thead>
              <tbody>
                {data.methods.map((m) => <tr key={m.method || "unknown"}><th scope="row">{t(methodKey(m.method))}</th><td className="pay-r">{won(m.payments)}</td><td className="pay-r">{won(m.received - m.refunded)}</td><td className="pay-r">{wonOrDash(m.fee)}</td><td className="pay-r">{wonOrDash(m.settlement)}</td></tr>)}
              </tbody>
            </table>
          </div>
          <div>
            <h3>{t("pay.totals.byWeek")}</h3>
            {data.weeks.length === 0 ? <p className="muted">{t("pay.sec.empty.title")}</p> : (
              <table className="pay-gtbl">
                <thead><tr><th scope="col">{t("pay.col.payoutDay")}</th><th scope="col" className="pay-r">{t("pay.col.payments")}</th><th scope="col" className="pay-r">{t("pay.col.fee")}</th><th scope="col" className="pay-r">{t("pay.col.settlement")}</th></tr></thead>
                <tbody>{data.weeks.map((w) => <tr key={w.payoutDate}><th scope="row">{w.payoutDate}</th><td className="pay-r">{won(w.payments)}</td><td className="pay-r">{wonOrDash(w.fee)}</td><td className="pay-r">{won(w.settlement)}</td></tr>)}</tbody>
              </table>
            )}
          </div>
        </div>
      ) : null}
    </details>
  );
}
