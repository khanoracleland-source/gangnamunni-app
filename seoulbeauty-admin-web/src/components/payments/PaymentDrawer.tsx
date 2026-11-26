"use client";

import { useEffect, useId, useRef, useState } from "react";
import { toast } from "sonner";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { fetchPaymentDetail, refundPayment, type ApiResult, type PaymentDetail } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";
import { monthDayTime, newRequestId, REFUND_REASONS, won } from "@/lib/payments";

const BOOKING_STATUSES = ["pending", "hospital_checking", "confirmed", "completed", "cancelled", "refunded", "no_show"];
const BLOCKS = ["not_pg", "no_tid", "not_paid", "method_unsupported", "booking_not_cancelled", "nothing_left", "mode_mismatch"];
const ERRORS = ["busy", "request_failed", "reconcile_required", "outcome_unknown", "rejected", "ledger_failed", "refund_unavailable", "write_unavailable", "not_found", "validation"];

/**
 * One payment: the amounts, the booking, the refund history and the full refund. A side panel (native <dialog>: Esc, focus trap and return of focus come with it).
 * Mount it with a `key` of the payment id: every opening starts fresh. `onChanged` runs after a refund so the lists behind it reload.
 */
export default function PaymentDrawer({ id, onClose, onChanged }: { id: number; onClose: () => void; onChanged: () => void }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<{ key: string; res: ApiResult<PaymentDetail> } | null>(null);
  const [step, setStep] = useState<"view" | "confirm">("view");
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // One id per attempt: a repeated send of the same attempt can never refund twice. It is renewed only after the gateway refused the request.
  const requestId = useRef("");
  const key = `${id}#${tick}`;

  useEffect(() => {
    const dialog = ref.current;
    if (dialog && !dialog.open) dialog.showModal();
  }, []);

  useEffect(() => {
    const c = new AbortController();
    void fetchPaymentDetail(id, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) { window.location.assign(PHP_ADMIN_URL); return; }
      setGot({ key, res });
    });
    return () => c.abort();
    // `key` stands for the inputs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const loading = got?.key !== key;
  const failed = got?.key === key && !got.res.ok;
  const d = got?.res.ok ? got.res.data : null;
  const close = () => ref.current?.close();

  const start = () => { requestId.current = newRequestId(); setReason(""); setError(null); setStep("confirm"); };

  const run = async () => {
    if (!d || !reason || busy) return;
    setBusy(true);
    setError(null);
    const res = await refundPayment(id, requestId.current, reason);
    setBusy(false);
    if (res.ok) {
      toast.success(t("pay.rf.done", { amount: won(d.refund.amount) }));
      onChanged();
      close();
      return;
    }
    if (res.status === 401) { window.location.assign(PHP_ADMIN_URL); return; }
    if (res.error === "request_failed" || res.error === "rejected") requestId.current = newRequestId();
    const code = res.error === "refund_blocked" ? (BLOCKS.includes(res.reason ?? "") ? `pay.rf.block.${res.reason}` : "pay.rf.err.generic") : ERRORS.includes(res.error) ? `pay.rf.err.${res.error}` : "pay.rf.err.generic";
    setError(t(code as never));
    // The state may have changed behind our back (someone else refunded it): show it as it is now.
    if (res.error === "refund_blocked" || res.error === "reconcile_required") { setStep("view"); setTick((n) => n + 1); }
  };

  const item = d?.item;
  const bk = item && BOOKING_STATUSES.includes(item.bookingStatus) ? t(`bk.st.${item.bookingStatus}` as never) : item?.bookingStatus || "—";
  const canRefund = !!d && d.refund.enabled && d.refund.allowed;

  return (
    <dialog
      ref={ref}
      className="dialog pay-drawer"
      aria-labelledby={titleId}
      onCancel={(e) => { if (busy) e.preventDefault(); }}
      onClose={onClose}
      onClick={(e) => { if (e.target === e.currentTarget && !busy) close(); }}
    >
      <div className="dialog__box pay-dr">
        <div className="dialog__head">
          <h2 id={titleId} className="dialog__title">{t("pay.rf.title")}</h2>
          <button type="button" className="ibtn dialog__close" onClick={close} disabled={busy} aria-label={t("m.close")} title={t("m.close")}><Icon name="close" /></button>
        </div>

        {loading ? <p className="muted" role="status">{t("pay.loading")}</p> : null}
        {failed ? (
          <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={() => setTick((n) => n + 1)}>{t("pay.err.retry")}</button>}>{t("pay.rf.load.err")}</Notice>
        ) : null}

        {d && item ? (
          <>
            <div className="pay-dr__who">
              <p className="pay-dr__name" translate="no">{item.patientName || "—"}</p>
              <p className="muted">{t("pay.rf.no", { id: String(item.id) })}</p>
            </div>

            <dl className="pay-dr__sum">
              <div><dt>{t("pay.rf.gross")}</dt><dd>{won(d.grossAmount)}원</dd></div>
              {d.pointsUsed > 0 ? <div><dt>{t("pay.rf.points")}</dt><dd>−{won(d.pointsUsed)}P</dd></div> : null}
              <div className="pay-dr__strong"><dt>{t("pay.rf.paid")}</dt><dd>{won(item.amount)}원</dd></div>
              {item.refundedAmount > 0 ? <div><dt>{t("pay.rf.refunded")}</dt><dd>{won(item.refundedAmount)}원</dd></div> : null}
              <div className="pay-dr__due"><dt>{t("pay.rf.refundable")}</dt><dd>{won(Math.max(0, item.amount - item.refundedAmount))}원</dd></div>
            </dl>

            <dl className="pay-dr__info">
              <dt>{t("pay.rf.hospital")}</dt><dd>{item.hospitalName || "—"}</dd>
              <dt>{t("pay.rf.treatment")}</dt><dd>{item.treatmentName || "—"}</dd>
              <dt>{t("pay.rf.booking")}</dt><dd>{bk}</dd>
              <dt>{t("pay.rf.visit")}</dt><dd>{item.visitDate ? `${item.visitDate} ${item.visitTime}` : "—"}</dd>
              <dt>{t("pay.rf.paidAt")}</dt><dd>{monthDayTime(item.paidAt)}</dd>
              {d.method ? <><dt>{t("pay.rf.method")}</dt><dd>{d.method === "bank_transfer" ? t("pay.method.bank_transfer") : t("pay.method.card")}</dd></> : null}
              <dt>{t("pay.rf.tid")}</dt><dd className="pay-dr__mono">{d.tid || "—"}</dd>
            </dl>

            <section aria-label={t("pay.rf.history")}>
              <h3 className="pay-dr__h">{t("pay.rf.history")}</h3>
              {d.history.length === 0 ? <p className="muted">{t("pay.rf.history.none")}</p> : (
                <ul className="pay-dr__hist">
                  {d.history.map((h) => (
                    <li key={h.id}>
                      <strong>{won(h.amount)}원</strong>{h.reason ? ` · ${h.reason}` : ""}
                      <span className="muted">{monthDayTime(h.createdAt)} · {h.actorName || "—"}</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            {step === "view" ? (
              <div className="pay-dr__foot">
                {error ? <Notice tone="warn" icon="alert">{error}</Notice> : null}
                {canRefund ? (
                  <button type="button" className="btn btn--ghost btn--reject pay-dr__go" onClick={start}>{t("pay.rf.btn", { amount: won(d.refund.amount) })}</button>
                ) : item.status !== "refunded" ? (
                  <p className="muted pay-dr__why">{d.refund.block ? t(`pay.rf.block.${d.refund.block}` as never) : !d.refund.enabled ? t("pay.rf.off") : ""}</p>
                ) : null}
              </div>
            ) : (
              <div className="pay-dr__confirm" role="group" aria-label={t("pay.rf.confirm.title")}>
                <h3 className="pay-dr__h">{t("pay.rf.confirm.title")}</h3>
                <p className="pay-dr__amount">{t("pay.rf.confirm.amount")} <strong>{won(d.refund.amount)}원</strong></p>
                {d.pointsUsed > 0 ? <p className="muted">{t("pay.rf.confirm.points", { points: won(d.pointsUsed) })}</p> : null}
                <fieldset className="pay-dr__reasons" disabled={busy}>
                  <legend>{t("pay.rf.reason")}</legend>
                  {REFUND_REASONS.map((r, i) => (
                    <label key={r} className="pay-dr__chip">
                      <input type="radio" name="refund-reason" value={r} checked={reason === r} onChange={() => setReason(r)} />
                      <span>{t(`pay.rf.reason.${i}` as never)}</span>
                    </label>
                  ))}
                </fieldset>
                <p className="muted">{t("pay.rf.warn")}</p>
                {error ? <Notice tone="warn" icon="alert">{error}</Notice> : null}
                <div className="dialog__foot">
                  <button type="button" className="btn btn--ghost" onClick={() => { setStep("view"); setError(null); }} disabled={busy} data-autofocus="">{t("pay.rf.cancel")}</button>
                  <button type="button" className="btn btn--ghost btn--reject" onClick={() => void run()} disabled={busy || !reason}>{busy ? t("pay.rf.working") : t("pay.rf.go")}</button>
                </div>
              </div>
            )}
          </>
        ) : null}
      </div>
    </dialog>
  );
}
