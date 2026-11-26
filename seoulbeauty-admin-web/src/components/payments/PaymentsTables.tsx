"use client";

import { useEffect, useRef, useState, type KeyboardEvent } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import {
  fetchPaymentsByHospital, fetchPaymentsByTreatment, type ApiResult, type PaymentHospitalSums, type PaymentList, type PaymentRow, type PaymentSums,
  type PaymentTable, type PaymentTreatmentSums,
} from "@/lib/api";
import { pageButtons } from "@/lib/bookings";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";
import { monthDay, monthDayTime, won, wonOrDash } from "@/lib/payments";

export type Tab = "list" | "hospitals" | "treatments" | "refunds";

/** The three views of the same filters: the payments, the hospitals and the treatments (hospital accounts have no hospitals view). */
export function Tabs({ tab, own, onTab, withoutList = false }: { tab: Tab; own: boolean; onTab: (t: Tab) => void; withoutList?: boolean }) {
  const t = useT();
  const tabs: Tab[] = (own ? ["list", "treatments", "refunds"] : ["list", "hospitals", "treatments", "refunds"]).filter((k) => !(withoutList && k === "list")) as Tab[];
  const refs = useRef<(HTMLButtonElement | null)[]>([]);
  const onKey = (e: KeyboardEvent<HTMLDivElement>) => {
    const i = tabs.indexOf(tab);
    let next = i;
    if (e.key === "ArrowRight") next = (i + 1) % tabs.length;
    else if (e.key === "ArrowLeft") next = (i + tabs.length - 1) % tabs.length;
    else if (e.key === "Home") next = 0;
    else if (e.key === "End") next = tabs.length - 1;
    else return;
    e.preventDefault();
    onTab(tabs[next]);
    refs.current[next]?.focus();
  };
  return (
    <div className="pay-tabs" role="tablist" aria-label={t("pay.tabs")} onKeyDown={onKey}>
      {tabs.map((k, i) => (
        <button key={k} ref={(el) => { refs.current[i] = el; }} type="button" role="tab" id={`pay-tab-${k}`} aria-selected={tab === k} aria-controls="pay-panel" tabIndex={tab === k ? 0 : -1} className="pay-tab" onClick={() => onTab(k)}>
          {t(`pay.tab.${k}` as never)}
        </button>
      ))}
    </div>
  );
}

function Stage({ row }: { row: PaymentRow }) {
  const t = useT();
  const partial = row.stage !== "refunded" && row.refundedAmount > 0;
  const cls = row.stage === "recognized" ? "completed" : row.stage === "refunded" ? "refunded" : "pending";
  return (
    <span className="pay-stage">
      <span className={`bk-st bk-st--${cls}`}>{t(`pay.stage.${row.stage}` as never)}</span>
      {partial ? <span className="bk-st bk-st--refunded">{t("pay.stage.partial")}</span> : null}
      {row.needsAttention ? <span className="bk-st bk-st--cancelled"><Icon name="alert" size={13} />{t("pay.stage.attention")}</span> : null}
    </span>
  );
}

export function Pager({ list, onPage }: { list: { page: number; perPage: number; total: number; totalPages: number }; onPage: (n: number) => void }) {
  const t = useT();
  const from = (list.page - 1) * list.perPage + 1;
  const to = Math.min(list.page * list.perPage, list.total);
  return (
    <nav className="pager" aria-label={t("pay.pager.label")}>
      {list.totalPages > 1 ? (
        <>
          <button type="button" className="pager__btn pager__prev" disabled={list.page === 1} onClick={() => onPage(list.page - 1)} aria-label={t("pay.prev")}><Icon name="chevron" size={16} /></button>
          {pageButtons(list.page, list.totalPages).map((n, i) => n === null ? (
            <span key={`gap${i}`} className="muted" aria-hidden="true">…</span>
          ) : (
            <button key={n} type="button" className="pager__btn" aria-current={n === list.page ? "page" : undefined} aria-label={t("pay.page", { n: String(n) })} onClick={() => onPage(n)}>{n}</button>
          ))}
          <button type="button" className="pager__btn" disabled={list.page === list.totalPages} onClick={() => onPage(list.page + 1)} aria-label={t("pay.next")}><Icon name="chevron" size={16} /></button>
        </>
      ) : null}
      <p className="pager__info">{t("pay.count", { from: String(from), to: String(to), total: String(list.total) })}</p>
    </nav>
  );
}

/** The ledger rows: a table from 921px wide, cards below. */
export function PaymentRows({ list, loading, own, dirty, onPage, onReset, onOpen }: { list: PaymentList; loading: boolean; own: boolean; dirty: boolean; onPage: (n: number) => void; onReset: () => void; onOpen: (id: number) => void }) {
  const t = useT();
  const rows = list.items;
  if (rows.length === 0) {
    return (
      <div className="bk-none">
        <Icon name="search" size={28} />
        <h2>{t(dirty ? "pay.none.title" : "pay.empty.title")}</h2>
        <p>{t(dirty ? "pay.none.body" : "pay.empty.body")}</p>
        {dirty ? <button type="button" className="btn btn--ghost" onClick={onReset}>{t("pay.none.reset")}</button> : null}
      </div>
    );
  }
  return (
    <div className={loading ? "bk-busy" : undefined} aria-busy={loading}>
      <div className="pay-tbl-wrap">
        <table className={`tbl pay-tbl${own ? " pay-tbl--own" : ""}`}>
          <colgroup>
            <col style={{ width: 104 }} /><col style={{ width: 104 }} />
            {!own ? <col style={{ width: 132 }} /> : null}
            <col style={{ width: 140 }} /><col style={{ width: 72 }} /><col style={{ width: 88 }} /><col style={{ width: 76 }} /><col style={{ width: 96 }} /><col style={{ width: 72 }} />
          </colgroup>
          <thead>
            <tr>
              <th scope="col">{t("pay.col.paidAt")}</th><th scope="col">{t("pay.col.patient")}</th>
              {!own ? <th scope="col">{t("pay.col.hospital")}</th> : null}
              <th scope="col">{t("pay.col.treatment")}</th><th scope="col">{t("pay.col.visit")}</th>
              <th scope="col" className="pay-r">{t("pay.col.amount")}</th><th scope="col" className="pay-r">{t("pay.col.refunded")}</th><th scope="col">{t("pay.col.status")}</th><th scope="col"><span className="sr-only">{t("pay.rf.col.action")}</span></th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td className="pay-nowrap">{monthDayTime(r.paidAt)}</td>
                <td><span className="pay-ell" title={r.patientName}>{r.patientName || "—"}</span></td>
                {!own ? <td><span className="pay-ell" title={r.hospitalName}>{r.hospitalName}</span></td> : null}
                <td><span className="pay-ell" title={r.treatmentName}>{r.treatmentName || "—"}</span></td>
                <td className="pay-nowrap">{monthDay(r.visitDate)}</td>
                <td className="pay-r">{won(r.amount)}</td>
                <td className="pay-r">{wonOrDash(r.refundedAmount)}</td>
                <td><Stage row={r} /></td>
                <td><button type="button" className="btn btn--ghost btn--sm" onClick={() => onOpen(r.id)}>{t("pay.rf.open")}</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <ul className="pay-cards">
        {rows.map((r) => (
          <li key={r.id} className="bk-card">
            <div className="bk-card__top"><span className="muted pay-nowrap">{monthDayTime(r.paidAt)}</span><Stage row={r} /></div>
            <p className="bk-card__name pay-card__name">{r.patientName || "—"}</p>
            <dl className="bk-dl">
              {!own ? <><dt>{t("pay.col.hospital")}</dt><dd>{r.hospitalName}</dd></> : null}
              <dt>{t("pay.col.treatment")}</dt><dd>{r.treatmentName || "—"}</dd>
              <dt>{t("pay.col.visit")}</dt><dd>{monthDay(r.visitDate)} {r.visitTime}</dd>
              <dt>{t("pay.col.amount")}</dt><dd className="pay-r">{won(r.amount)}</dd>
              <dt>{t("pay.col.refunded")}</dt><dd className="pay-r">{wonOrDash(r.refundedAmount)}</dd>
            </dl>
            <button type="button" className="btn btn--ghost btn--sm" onClick={() => onOpen(r.id)}>{t("pay.rf.open")}</button>
          </li>
        ))}
      </ul>
      <Pager list={list} onPage={onPage} />
    </div>
  );
}

type Group = PaymentSums & { key: string; name: string };

/** Totals per hospital or per treatment for the chosen hospital and dates. */
export function GroupTable({ kind, hospitalId, from, to }: { kind: "hospitals" | "treatments"; hospitalId: number; from: string; to: string }) {
  const t = useT();
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<{ key: string; res: ApiResult<PaymentTable<PaymentHospitalSums | PaymentTreatmentSums>> } | null>(null);
  const key = `${kind}|${hospitalId}|${from}|${to}|${tick}`;

  useEffect(() => {
    const c = new AbortController();
    const q = { hospitalId, from, to };
    const run = kind === "hospitals" ? fetchPaymentsByHospital(q, c.signal) : fetchPaymentsByTreatment(q, c.signal);
    void run.then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) {
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      setGot({ key, res });
    });
    return () => c.abort();
    // `key` stands for the inputs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const loading = got?.key !== key;
  const failed = got?.key === key && !got.res.ok;
  const data = got?.res.ok ? got.res.data : null;
  const rows: Group[] = (data?.items ?? []).map((r) =>
    "hospitalId" in r ? { ...r, key: String(r.hospitalId), name: r.hospitalName } : { ...r, key: `t-${r.treatmentName}`, name: r.treatmentName || t("pay.noName") },
  );
  const sum = (f: (r: Group) => number) => rows.reduce((a, r) => a + f(r), 0);

  if (failed) return <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={() => setTick((n) => n + 1)}>{t("pay.err.retry")}</button>}>{t("pay.err.list")}</Notice>;
  if (!data) return <p className="muted" role="status">{t("pay.loading")}</p>;
  if (rows.length === 0 && !loading) {
    return (
      <div className="bk-none">
        <Icon name="search" size={28} />
        <h2>{t("pay.empty.title")}</h2>
        <p>{t("pay.empty.body")}</p>
      </div>
    );
  }
  return (
    <div className={loading ? "bk-busy" : undefined} aria-busy={loading}>
      {data.truncated ? <Notice tone="info" icon="info">{t("pay.truncated")}</Notice> : null}
      <table className="pay-gtbl">
        <thead>
          <tr>
            <th scope="col">{t(kind === "hospitals" ? "pay.col.hospital" : "pay.col.treatment")}</th>
            <th scope="col" className="pay-r">{t("pay.col.payments")}</th><th scope="col" className="pay-r">{t("pay.col.received")}</th>
            <th scope="col" className="pay-r">{t("pay.col.recognized")}</th><th scope="col" className="pay-r">{t("pay.col.held")}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.key}>
              <th scope="row"><span className="pay-ell" title={r.name}>{r.name}</span></th>
              <td className="pay-r">{won(r.payments)}</td><td className="pay-r">{won(r.received)}</td><td className="pay-r">{won(r.recognized)}</td><td className="pay-r">{won(r.held)}</td>
            </tr>
          ))}
        </tbody>
        {!data.truncated ? (
          <tfoot>
            <tr>
              <th scope="row">{t("pay.total")}</th>
              <td className="pay-r">{won(sum((r) => r.payments))}</td><td className="pay-r">{won(sum((r) => r.received))}</td>
              <td className="pay-r">{won(sum((r) => r.recognized))}</td><td className="pay-r">{won(sum((r) => r.held))}</td>
            </tr>
          </tfoot>
        ) : null}
      </table>
    </div>
  );
}
