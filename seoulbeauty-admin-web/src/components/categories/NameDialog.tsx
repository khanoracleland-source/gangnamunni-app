"use client";

import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { failureKey } from "../treatments/writeErrors";
import { updateCategory, type BoardCategory, type WriteFailure } from "@/lib/api";
import { CATEGORY_NAME_MAX, checkCategoryName, type NameProblem } from "@/lib/categoryBoard";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

const PROBLEMS: Record<NameProblem, MessageKey> = { empty: "cb.add.empty", long: "cb.add.long", dup: "cb.add.dup" };

/** Renames one category. The slug and the home order stay as they are. */
export default function NameDialog({ category, all, onClose, onSaved, onFailed }: { category: BoardCategory; all: BoardCategory[]; onClose: () => void; onSaved: () => void; onFailed: (res: WriteFailure) => void }) {
  const t = useT();
  const uid = useId();
  const ref = useRef<HTMLDialogElement>(null);
  const [name, setName] = useState(category.name);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    const input = dialog.querySelector<HTMLInputElement>("[data-autofocus]");
    input?.focus({ preventScroll: true });
    input?.select();
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    const problem = checkCategoryName(name, all, category.id);
    if (problem) {
      setError(t(PROBLEMS[problem]));
      return;
    }
    if (name.trim() === category.name) {
      onClose();
      return;
    }
    setBusy(true);
    const res = await updateCategory(category.id, { name: name.trim() });
    setBusy(false);
    if (res.ok) {
      onSaved();
      return;
    }
    if (res.status === 400 && res.error === "invalid_field") setError(t(PROBLEMS[res.reason === "too_long" ? "long" : "empty"]));
    else if (res.status === 404 || res.status === 409) onFailed(res);
    else setError(t(failureKey(res)));
  };

  return createPortal(
    <dialog
      ref={ref}
      className="dialog cb-dlg"
      aria-labelledby={`${uid}-t`}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onClose();
      }}
    >
      <form className="cb-dlg__form" onSubmit={submit} noValidate>
        <div className="cb-dlg__head">
          <h2 id={`${uid}-t`} className="dialog__title">{t("cb.dlg.rename.title")}</h2>
          <button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("m.close")} title={t("m.close")}>
            <Icon name="close" />
          </button>
        </div>
        <div className="cb-dlg__body">
          <div className="field">
            <label className="field__label" htmlFor={`${uid}-n`}>{t("cb.dlg.rename.label")}</label>
            <input
              id={`${uid}-n`}
              className="input"
              value={name}
              maxLength={CATEGORY_NAME_MAX * 2}
              autoComplete="off"
              data-autofocus=""
              aria-invalid={error ? true : undefined}
              aria-describedby={error ? `${uid}-e` : undefined}
              onChange={(e) => {
                setName(e.target.value);
                setError(null);
              }}
            />
            <div className="field__meta">
              {error ? (
                <p className="field__error" id={`${uid}-e`} role="alert">
                  <Icon name="alert" size={14} /> {error}
                </p>
              ) : (
                <span className="field__hint">{t("cb.add.hint")}</span>
              )}
              <span className="field__count">{[...name.trim()].length}/{CATEGORY_NAME_MAX}</span>
            </div>
          </div>
        </div>
        <div className="cb-dlg__foot cb-dlg__foot--end">
          <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("tmw.cancel")}</button>
          <button type="submit" className="btn btn--primary" disabled={busy} aria-busy={busy || undefined}>{busy ? t("tmw.saving") : t("tmw.save")}</button>
        </div>
      </form>
    </dialog>,
    document.body,
  );
}
