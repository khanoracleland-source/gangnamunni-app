"use client";

import { useEffect, useId, useRef, useState } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { useT } from "@/lib/locale";

/** Permanent removal: the button stays off until the doctor's name is typed exactly. Native <dialog>; cannot be dismissed while `busy`. */
export default function DeleteDoctorDialog({
  name,
  busy,
  error,
  onConfirm,
  onClose,
}: {
  /** null = closed. */
  name: string | null;
  busy: boolean;
  error: string | null;
  onConfirm: () => void;
  onClose: () => void;
}) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const [typed, setTyped] = useState("");

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (name !== null && !dialog.open) {
      setTyped("");
      dialog.showModal();
    }
    if (name === null && dialog.open) dialog.close();
  }, [name]);

  const match = name !== null && typed.trim() === name.trim() && name.trim() !== "";

  return (
    <dialog
      ref={ref}
      className="dialog dc-dialog-del"
      aria-labelledby={titleId}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onClose();
      }}
    >
      {name !== null ? (
        <form
          className="dialog__box"
          onSubmit={(e) => {
            e.preventDefault();
            if (match && !busy) onConfirm();
          }}
        >
          <div className="dialog__head">
            <h2 id={titleId} className="dialog__title">{t("d.dl.title")}</h2>
            <button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("m.close")} title={t("m.close")}>
              <Icon name="close" />
            </button>
          </div>
          <p className="dialog__text dialog__text--strong" translate="no">{name}</p>
          <p className="dialog__text">{t("d.dl.body")}</p>
          <label className="field dc-typed">
            <span className="field__label">{t("d.dl.type")}</span>
            <input className="input" value={typed} autoComplete="off" autoCapitalize="off" spellCheck={false} disabled={busy} onChange={(e) => setTyped(e.target.value)} data-autofocus="" />
          </label>
          {error ? (
            <div className="dialog__err">
              <Notice tone="warn" icon="alert">{error}</Notice>
            </div>
          ) : null}
          <div className="dialog__foot dc-foot">
            <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("d.ed.cancel")}</button>
            <button type="submit" className="btn btn--ghost btn--reject" disabled={!match || busy}>
              {busy ? t("d.ed.saving") : t("d.dl.confirm")}
            </button>
          </div>
        </form>
      ) : null}
    </dialog>
  );
}
