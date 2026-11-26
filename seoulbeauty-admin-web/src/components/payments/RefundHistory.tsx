"use client";

import { useEffect, useState } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { fetchRefundHistory, type ApiResult, type RefundHistory as History, type RefundHistoryRow } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";
import { monthDayTime, won } from "@/lib/payments";

/** Every refund in the admin's scope, newest first, 20 at a time. Mount it with a changing `key` to start again from the first page. */
export default function RefundHistory({ own }: { own: boolean }) {
  const t = useT();
  const [page, setPage] = useState(1);
  const [tick, setTick] = useState(0);
  const [rows, setRows] = useState<RefundHistoryRow[]>([]);
  const [last, setLast] = useState<{ key: string; res: ApiResult<History> } | null>(null);
  const key = `${page}#${tick}`;

  useEffect(() => {
    const c = new AbortController();
    void fetchRefundHistory(page, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) { window.location.assign(PHP_ADMIN_URL); return; }
      if (res.ok) setRows((prev) => (page === 1 ? res.data.items : [...prev.filter((r) => !res.data.items.some((n) => n.id === r.id)), ...res.data.items]));
      setLast({ key, res });
    });
    return () => c.abort();
    // `key` stands for the inputs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const loading = last?.key !== key;
  const failed = last?.key === key && !last.res.ok;
  const more = last?.res.ok && last.res.data.hasMore;

  if (failed && rows.length === 0) return <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={() => setTick((n) => n + 1)}>{t("pay.err.retry")}</button>}>{t("pay.rh.err")}</Notice>;
  if (last === null) return <p className="muted" role="status">{t("pay.loading")}</p>;
  if (rows.length === 0) return <div className="bk-none"><Icon name="search" size={28} /><h2>{t("pay.rh.empty")}</h2></div>;
  return (
    <div className={loading ? "bk-busy" : undefined} aria-busy={loading}>
      <div className="pay-scroll">
        <table className="pay-gtbl">
          <thead>
            <tr>
              <th scope="col">{t("pay.rh.when")}</th><th scope="col">{t("pay.rh.patient")}</th>
              {!own ? <th scope="col">{t("pay.rh.hospital")}</th> : null}
              <th scope="col" className="pay-r">{t("pay.rh.amount")}</th><th scope="col">{t("pay.rh.reason")}</th><th scope="col">{t("pay.rh.by")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td className="pay-nowrap">{monthDayTime(r.createdAt)}</td>
                <td><span className="pay-ell" title={r.patientName}>{r.patientName || "—"}</span></td>
                {!own ? <td><span className="pay-ell" title={r.hospitalName}>{r.hospitalName}</span></td> : null}
                <td className="pay-r">{won(r.amount)}</td>
                <td>{r.reason || "—"}</td>
                <td><span className="pay-ell" title={r.actorName}>{r.actorName || "—"}</span></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {failed ? <Notice tone="warn" icon="alert">{t("pay.rh.err")}</Notice> : null}
      {more ? <div className="pay-more-btn"><button type="button" className="btn btn--ghost" disabled={loading} onClick={() => setPage((n) => n + 1)}>{t("pay.rh.more")}</button></div> : null}
    </div>
  );
}
