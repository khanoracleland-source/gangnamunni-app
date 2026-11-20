"use client";

import { useEffect, useRef, type ReactNode } from "react";

/** A native <dialog> that is open while `open`: Esc, the focus trap and the return of focus come with it. `kind` picks the shape (centred, right-hand panel, bottom sheet on phones). */
export default function BookingModal({ open, label, kind = "dialog", locked = false, onClose, children }: { open: boolean; label: string; kind?: "dialog" | "sheet" | "drawer"; locked?: boolean; onClose: () => void; children: ReactNode }) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (open && !d.open) {
      d.showModal();
      d.querySelector<HTMLElement>("[data-autofocus]")?.focus();
    }
    if (!open && d.open) d.close();
  }, [open]);
  return (
    <dialog
      ref={ref}
      className={`dialog bk-modal bk-modal--${kind}`}
      aria-label={label}
      onClose={onClose}
      // While something is being done (locked) the dialog cannot be dismissed.
      onCancel={(e) => {
        if (locked) e.preventDefault();
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget && !locked) onClose();
      }}
    >
      {open ? children : null}
    </dialog>
  );
}
