"use client";

import { useEffect, useId, useRef } from "react";
import Icon from "../Icon";
import { Notice } from "./parts";
import { useT } from "@/lib/locale";

export type ConfirmSpec = {
  title: string;
  /** What the question is about (a hospital's name), shown between the title and the text. */
  name?: string;
  body: string;
  /** A further line after the text, in the same style. */
  note?: string;
  confirmLabel: string;
  cancelLabel: string;
  /** Which button takes focus first: "cancel" for choices that are hard to take back. */
  focus?: "confirm" | "cancel";
};

/** Asks before something happens. Native <dialog>: Esc, focus trap and return of focus come with it; while `busy` it cannot be dismissed. */
export default function ConfirmDialog({
  spec,
  busy = false,
  error = null,
  onConfirm,
  onClose,
}: {
  spec: ConfirmSpec | null;
  busy?: boolean;
  error?: string | null;
  onConfirm: () => void;
  onClose: () => void;
}) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (spec && !dialog.open) {
      dialog.showModal();
      dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus();
    }
    if (!spec && dialog.open) dialog.close();
  }, [spec]);

  const focusConfirm = spec?.focus === "confirm";

  return (
    <dialog
      ref={ref}
      className="dialog"
      aria-labelledby={titleId}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        // A click on the backdrop lands on the <dialog> element itself.
        if (e.target === e.currentTarget && !busy) onClose();
      }}
    >
      {spec ? (
        <div className="dialog__box">
          <div className="dialog__head">
            <h2 id={titleId} className="dialog__title">{spec.title}</h2>
            <button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("m.close")} title={t("m.close")}>
              <Icon name="close" />
            </button>
          </div>
          {spec.name ? <p className="dialog__text dialog__text--strong" translate="no">{spec.name}</p> : null}
          <p className="dialog__text">{spec.body}</p>
          {spec.note ? <p className="dialog__text">{spec.note}</p> : null}
          {error ? (
            <div className="dialog__err">
              <Notice tone="warn" icon="alert">{error}</Notice>
            </div>
          ) : null}
          <div className="dialog__foot">
            <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy} data-autofocus={focusConfirm ? undefined : ""}>
              {spec.cancelLabel}
            </button>
            <button type="button" className="btn btn--primary" onClick={onConfirm} disabled={busy} data-autofocus={focusConfirm ? "" : undefined}>
              {busy ? t("h.act.working") : spec.confirmLabel}
            </button>
          </div>
        </div>
      ) : null}
    </dialog>
  );
}
