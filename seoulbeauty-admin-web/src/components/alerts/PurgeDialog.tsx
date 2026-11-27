"use client";

import { useId, useRef, useState } from "react";
import { Notice } from "../hospitals/parts";
import { NtModal } from "../notifications/NtParts";
import { PURGE_DEFAULT_DAYS, PURGE_MAX_DAYS, PURGE_MIN_DAYS, parsePurgeDays } from "@/lib/alerts";
import { purgeAlerts } from "@/lib/alertsApi";
import { useT } from "@/lib/locale";

/** Asks for the age in days, then deletes the admin's own read alerts older than that (once; nothing else is touched). */
export default function PurgeDialog({ onClose, onUnauthorized, onDone }: { onClose: () => void; onUnauthorized: () => void; onDone: (deleted: number) => void }) {
  const t = useT();
  const titleId = useId();
  const hintId = useId();
  const [text, setText] = useState(String(PURGE_DEFAULT_DAYS));
  const [busy, setBusy] = useState(false);
  // Set at once when a run starts, so a second Enter in the same moment sends nothing.
  const running = useRef(false);
  const [error, setError] = useState<string | null>(null);
  const days = parsePurgeDays(text);

  const run = async () => {
    if (busy || running.current || days === null) return;
    running.current = true;
    setBusy(true);
    setError(null);
    const res = await purgeAlerts(days);
    if (res.ok) {
      onDone(res.data.deleted);
      return;
    }
    running.current = false;
    if (res.status === 401) return onUnauthorized();
    setError(t(res.status === 400 ? "alerts.purge.badDays" : "alerts.purge.failed"));
    setBusy(false);
  };

  return (
    <NtModal title={t("alerts.purge.title")} titleId={titleId} busy={busy} onClose={onClose}>
      <p className="dialog__text">{t("alerts.purge.body")}</p>
      <div className="al-purge">
        <label htmlFor={`${titleId}-days`} className="field__label">{t("alerts.purge.days")}</label>
        <input
          id={`${titleId}-days`}
          className="input al-purge__input"
          type="number"
          inputMode="numeric"
          min={PURGE_MIN_DAYS}
          max={PURGE_MAX_DAYS}
          step={1}
          value={text}
          onChange={(e) => setText(e.target.value)}
          disabled={busy}
          aria-invalid={days === null}
          aria-describedby={days === null ? hintId : undefined}
          onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); void run(); } }}
        />
        <span>{t("alerts.purge.unit")}</span>
      </div>
      {days === null ? <p className="field__error" id={hintId}>{t("alerts.purge.hint")}</p> : null}
      {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
      <div className="dialog__foot nt-foot">
        <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("alerts.purge.cancel")}</button>
        <button type="button" className="btn btn--danger" onClick={() => void run()} disabled={busy || days === null} aria-busy={busy} data-autofocus="">
          {busy ? t("alerts.purge.working") : t("alerts.purge.confirm")}
        </button>
      </div>
    </NtModal>
  );
}
