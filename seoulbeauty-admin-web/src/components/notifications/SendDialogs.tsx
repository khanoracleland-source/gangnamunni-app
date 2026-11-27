"use client";

import { useId, useRef, useState } from "react";
import { Notice } from "../hospitals/parts";
import { LedgerRow, NtModal, PushCard } from "./NtParts";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";
import { isMarketingType, isNightInput, kstInput, morningOf, newRequestId, writeErrorKey } from "@/lib/notifications";
import { scheduleNotification, sendNotification, type SendInput, type SendResult } from "@/lib/notificationsApi";

type Props = { input: SendInput; onClose: () => void; onUnauthorized: () => void };

/** "Send now": shows what goes out, asks for one confirmation, sends once and then reports what happened. */
export function ConfirmSendDialog({ input, resolved, onClose, onUnauthorized, onStale, onDone, onHistory }: Props & { resolved: number; onStale: () => void; onDone: () => void; onHistory: () => void }) {
  const t = useT();
  const titleId = useId();
  // One id for this dialog: pressing again after a lost answer sends nothing twice (the server keeps the first result).
  const requestId = useRef(newRequestId());
  const [checked, setChecked] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<SendResult | null>(null);

  const send = async () => {
    if (busy || !checked) return;
    setBusy(true);
    setError(null);
    const res = await sendNotification(input, requestId.current, resolved);
    if (res.ok) {
      setResult(res.data);
      setBusy(false);
      onDone();
      return;
    }
    if (res.status === 401) return onUnauthorized();
    if (res.status === 409 && res.error === "audience_changed") onStale();
    setError(t(writeErrorKey(res)));
    setBusy(false);
  };

  if (result) {
    return (
      <NtModal key="result" title={t(result.duplicate ? "nt.r.dup" : "nt.r.title")} titleId={titleId} busy={false} onClose={onClose}>
        <dl className="nt-led">
          <LedgerRow label={t("nt.led.resolved")} value={t("nt.unit.people", { n: formatNumber(result.resolved) })} />
          <LedgerRow label={t("nt.led.inapp")} value={t("nt.unit.people", { n: formatNumber(result.inApp) })} />
          <LedgerRow label={t("nt.r.pushOk")} value={t("nt.unit.people", { n: formatNumber(result.pushSent) })} big />
          {result.pushFailed > 0 ? <LedgerRow label={t("nt.r.pushFail")} value={formatNumber(result.pushFailed)} /> : null}
          {result.pushSkipped > 0 ? <LedgerRow label={t("nt.r.pushSkip")} value={t("nt.unit.people", { n: formatNumber(result.pushSkipped) })} /> : null}
          {result.deadTokens > 0 ? <LedgerRow label={t("nt.r.dead")} value={formatNumber(result.deadTokens)} /> : null}
        </dl>
        {result.inAppFailed > 0 ? <div className="dialog__err"><Notice tone="warn" icon="alert">{t("nt.r.inappFail", { n: formatNumber(result.inAppFailed) })}</Notice></div> : null}
        {result.pushError !== "" ? <div className="dialog__err"><Notice tone="warn" icon="alert">{t("nt.r.pushError", { msg: result.pushError })}</Notice></div> : null}
        <div className="dialog__foot nt-foot">
          <button type="button" className="btn btn--ghost" onClick={onHistory}>{t("nt.r.history")}</button>
          <button type="button" className="btn btn--primary" onClick={onClose} data-autofocus="">{t("nt.close")}</button>
        </div>
      </NtModal>
    );
  }

  return (
    <NtModal title={t("nt.c.title")} titleId={titleId} busy={busy} onClose={onClose}>
      <p className="dialog__text">{t("nt.c.body", { n: formatNumber(resolved) })}</p>
      <div className="nt-dlg__card"><PushCard title={input.title} message={input.message} /></div>
      <label className="nt-cf">
        <input type="checkbox" checked={checked} onChange={(e) => setChecked(e.target.checked)} disabled={busy} />
        <span>{t("nt.c.check", { n: formatNumber(resolved) })}</span>
      </label>
      {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
      <div className="dialog__foot nt-foot">
        <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("nt.cancel")}</button>
        <button type="button" className="btn btn--primary" onClick={() => void send()} disabled={busy || !checked} aria-busy={busy} data-autofocus="">
          {busy ? t("nt.c.working") : t("nt.c.send", { n: formatNumber(resolved) })}
        </button>
      </div>
    </NtModal>
  );
}

/** "Plan a send": the time (Korean time) and, for an advertisement, what to do with the night. */
export function ScheduleDialog({ input, onClose, onUnauthorized, onPlanned }: Props & { onPlanned: () => void }) {
  const t = useT();
  const titleId = useId();
  const [now] = useState(() => Date.now());
  const [runAt, setRunAt] = useState(() => kstInput(Math.ceil((now + 3600_000) / 3600_000) * 3600_000));
  const [delay, setDelay] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const marketing = isMarketingType(input.type);
  const night = marketing && isNightInput(runAt);

  const save = async () => {
    if (busy) return;
    if (runAt === "") return setError(t("nt.err.bad_run_at"));
    setBusy(true);
    setError(null);
    const res = await scheduleNotification(input, runAt, marketing && delay ? "delay" : "skip");
    if (res.ok) return onPlanned();
    if (res.status === 401) return onUnauthorized();
    setError(t(writeErrorKey(res)));
    setBusy(false);
  };

  return (
    <NtModal title={t("nt.s.title")} titleId={titleId} busy={busy} onClose={onClose}>
      <form onSubmit={(e) => { e.preventDefault(); void save(); }} noValidate>
        <p className="dialog__text">{t("nt.s.body")}</p>
        <div className="nt-dlg__card"><PushCard title={input.title} message={input.message} /></div>
        <label className="nt-fld">
          <span className="nt-fld__l">{t("nt.s.at")}</span>
          <input className="input" type="datetime-local" step={60} min={kstInput(now + 120_000)} max={kstInput(now + 90 * 86_400_000)} value={runAt} onChange={(e) => setRunAt(e.target.value)} disabled={busy} data-autofocus="" />
          <span className="nt-fld__h">{t("nt.s.help")}</span>
        </label>
        {night ? (
          <div className="nt-night">
            <Notice tone="warn" icon="clock">{t("nt.s.night")}</Notice>
            <button type="button" className="btn btn--ghost" onClick={() => setRunAt(morningOf(runAt))} disabled={busy}>{t("nt.s.move")}</button>
          </div>
        ) : null}
        {marketing ? (
          <label className="nt-cf">
            <input type="checkbox" checked={delay} onChange={(e) => setDelay(e.target.checked)} disabled={busy} />
            <span>{t("nt.s.delay")}</span>
          </label>
        ) : null}
        {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
        <div className="dialog__foot nt-foot">
          <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("nt.cancel")}</button>
          <button type="submit" className="btn btn--primary" disabled={busy} aria-busy={busy}>{busy ? t("nt.c.working") : t("nt.s.save")}</button>
        </div>
      </form>
    </NtModal>
  );
}
