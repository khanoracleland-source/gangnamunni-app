"use client";

import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import Icon from "../Icon";
import { Notice } from "./parts";
import { rejectHospital } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { failureKey } from "@/lib/hospitalEdit";
import { useT } from "@/lib/locale";

const REASON_MAX = 1000;

/**
 * Turning down a pending application: a reason is required. Mount it when the button is pressed and unmount it in `onClose`.
 * `onRejected` runs after the server accepted; `onReload` after it said somebody else already decided.
 */
export default function RejectHospitalDialog({
  hospitalId,
  hospitalName,
  onClose,
  onRejected,
  onReload,
}: {
  hospitalId: number;
  hospitalName: string;
  onClose: () => void;
  onRejected: () => void;
  onReload: () => void;
}) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const fieldId = useId();
  const countId = useId();
  const errorId = useId();
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState(false);
  const [conflict, setConflict] = useState(false);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog || dialog.open) return;
    dialog.showModal();
    // A phone keeps its keyboard down until the field is touched, so the buttons stay in view.
    if (window.matchMedia("(pointer: fine)").matches) dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus();
  }, []);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy || conflict || reason.trim() === "") return;
    setBusy(true);
    setError(null);
    setFieldError(false);
    const res = await rejectHospital(hospitalId, reason.trim());
    if (res.ok) {
      onRejected();
      return;
    }
    setBusy(false);
    if (res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.status === 400 && res.error === "reason_required") {
      setFieldError(true);
    } else if (res.status === 409 && res.error === "already_reviewed") {
      setConflict(true);
      setError(t("rv.err.reviewed"));
    } else {
      setError(t(failureKey(res.status)));
    }
  };

  return (
    <dialog
      ref={ref}
      className="dialog dialog--wide dialog--reject"
      aria-labelledby={titleId}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) ref.current?.close();
      }}
    >
      <form className="dialog__box" onSubmit={submit} noValidate>
        <div className="dialog__head">
          <h2 id={titleId} className="dialog__title">{t("rv.reject.title")}</h2>
          <button type="button" className="ibtn dialog__close" onClick={() => ref.current?.close()} disabled={busy} aria-label={t("m.close")} title={t("m.close")}>
            <Icon name="close" />
          </button>
        </div>
        <p className="dialog__name" translate="no">{hospitalName}</p>
        <div className="field">
          <label className="field__label" htmlFor={fieldId}>
            {t("rv.reject.label")}
            <span className="field__req" aria-hidden="true"> *</span>
          </label>
          <textarea
            id={fieldId}
            className="input textarea"
            rows={5}
            maxLength={REASON_MAX}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            aria-invalid={fieldError ? true : undefined}
            aria-describedby={fieldError ? `${countId} ${errorId}` : countId}
            disabled={busy || conflict}
            data-autofocus=""
          />
          <div className="field__meta">
            {fieldError ? <p id={errorId} className="field__error" role="alert">{t("rv.reject.required")}</p> : null}
            <span id={countId} className={reason.length >= REASON_MAX ? "field__count field__count--max" : "field__count"}>{reason.length} / {REASON_MAX}</span>
          </div>
          <p className="field__hint">{t("rv.reject.hint")}</p>
        </div>
        {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
        <div className="dialog__foot">
          <button type="button" className="btn btn--ghost" onClick={() => ref.current?.close()} disabled={busy}>{conflict ? t("m.close") : t("h.c.cancel")}</button>
          {conflict ? (
            <button type="button" className="btn btn--primary" onClick={onReload}>{t("rv.reload")}</button>
          ) : (
            <button type="submit" className="btn btn--danger" disabled={reason.trim() === "" || busy}>
              {busy ? t("h.act.working") : t("rv.reject.btn")}
            </button>
          )}
        </div>
      </form>
    </dialog>
  );
}
