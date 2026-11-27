"use client";

import { useEffect, useRef, type ReactNode } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { useT } from "@/lib/locale";

/** A modal dialog: a centred box from 640px up, a sheet from the bottom below it. Escape and the backdrop close it unless a request is running. */
export function NtModal({ title, titleId, busy, onClose, children }: { title: string; titleId: string; busy: boolean; onClose: () => void; children: ReactNode }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);
  return createPortal(
    <dialog
      ref={ref}
      className="dialog nt-dlg"
      aria-labelledby={titleId}
      onCancel={(e) => { if (busy) e.preventDefault(); }}
      onClose={() => { if (!busy) onClose(); }}
      onClick={(e) => { if (e.target === e.currentTarget && !busy) onClose(); }}
    >
      <div className="dialog__box">
        <div className="dialog__head">
          <h2 className="dialog__title" id={titleId}>{title}</h2>
          <button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("nt.close")}><Icon name="close" size={18} /></button>
        </div>
        {children}
      </div>
    </dialog>,
    document.body,
  );
}

/** How the notification looks on a phone: the title (two lines at most) and the text. Text typed by the admin is shown as plain text. */
export function PushCard({ title, message }: { title: string; message: string }) {
  const t = useT();
  const empty = title.trim() === "";
  return (
    <div className="nt-push" aria-label={t("nt.p.card")}>
      <span className="nt-push__ic" aria-hidden="true"><Icon name="megaphone" size={18} /></span>
      <div className="nt-push__tx">
        <p className="nt-push__app">{t("nt.p.app")} · {t("nt.p.now")}</p>
        <p className={empty ? "nt-push__t nt-push__t--ph" : "nt-push__t"}>{empty ? t("nt.p.titlePh") : title}</p>
        {message.trim() !== "" ? <p className="nt-push__m">{message}</p> : null}
      </div>
    </div>
  );
}

/** One line of the count table: a label on the left, the value on the right. `big` is the number the admin cares about most. */
export function LedgerRow({ label, value, big, sub }: { label: string; value: string; big?: boolean; sub?: string }) {
  return (
    <div className={big ? "nt-lr nt-lr--big" : "nt-lr"}>
      <dt>{label}{sub ? <small>{sub}</small> : null}</dt>
      <dd>{value}</dd>
    </div>
  );
}
