"use client";
import { forwardRef, useEffect, useImperativeHandle, useRef } from "react";
import { createPortal } from "react-dom";
import styles from "./BottomSheet.module.css";

/**
 * THE bottom sheet. Every modal that slides up from the bottom uses this component (design 02-02, 02-06, 05-05 …).
 * Rules (owner, 2026-11-05 — see claude/modal-bottom-sheet-standard):
 *  - no ✕ close button inside; closing is: tap the dim, drag the grip down past the threshold, Esc, or the sheet's own button
 *  - opens from the bottom in 0.4 s, closes to the bottom in 0.4 s — also when the sheet's own button (e.g. 신청하기 / 확인) closes it
 *  - grip 40×4 #1A1718; dragging it follows the finger; released past 30 % of the height (or flicked) it glides shut, else springs back
 *  - rendered in a portal on <body> above the floating tab bar (z 1000); dim rgba(26,23,24,.45); radius 16 16 0 0; width ≤ 640
 *  - `dismissible={false}`: a decision sheet that only its own button closes (no dim tap, Esc or drag)
 * `onClose` runs after the slide-out. A parent closes it with the animation through the ref: `ref.current?.close()`.
 */
export type BottomSheetHandle = { close: (ms?: number) => void };
const DURATION = 400;

const BottomSheet = forwardRef<BottomSheetHandle, { open: boolean; onClose: () => void; label: string; children: React.ReactNode; dismissible?: boolean }>(
  function BottomSheet({ open, onClose, label, children, dismissible = true }, ref) {
    const sheetRef = useRef<HTMLDivElement>(null);
    const dimRef = useRef<HTMLDivElement>(null);
    const drag = useRef<{ y: number; t: number; dy: number; id: number } | null>(null);
    const closing = useRef(false);
    const onCloseRef = useRef(onClose);
    const dismissibleRef = useRef(dismissible);
    useEffect(() => { onCloseRef.current = onClose; dismissibleRef.current = dismissible; });

    const close = (ms: number = DURATION) => {
      if (closing.current) return;
      closing.current = true;
      const el = sheetRef.current, dim = dimRef.current;
      // 1) freeze both exactly where they are now (end of the open animation, mid-open, or mid-drag) and flush that style;
      // 2) only then start the slide-out. Doing `animation: none` and the transition in one go made the browser skip the
      //    transition: the sheet and the dim vanished at once instead of gliding down (measured in Chrome, 2026-11-10).
      if (el) { const t = getComputedStyle(el).transform; el.style.transform = t === "none" ? "translateY(0)" : t; el.style.animation = "none"; el.style.transition = "none"; }
      if (dim) { dim.style.opacity = getComputedStyle(dim).opacity; dim.style.animation = "none"; dim.style.transition = "none"; }
      void el?.offsetHeight;
      if (el) { el.style.transition = `transform ${ms}ms cubic-bezier(.4,0,.2,1)`; el.style.transform = "translateY(100%)"; }
      if (dim) { dim.style.transition = `opacity ${ms}ms ease`; dim.style.opacity = "0"; }
      window.setTimeout(() => { closing.current = false; onCloseRef.current(); }, ms);
    };
    const closeRef = useRef(close);
    useEffect(() => { closeRef.current = close; });
    useImperativeHandle(ref, () => ({ close: (ms?: number) => closeRef.current(ms) }), []);

    useEffect(() => {
      if (!open) return;
      const onKey = (e: KeyboardEvent) => { if (e.key === "Escape" && dismissibleRef.current) closeRef.current(); };
      const prev = document.body.style.overflow;
      document.body.style.overflow = "hidden";
      window.addEventListener("keydown", onKey);
      return () => { document.body.style.overflow = prev; window.removeEventListener("keydown", onKey); };
    }, [open]);

    if (!open || typeof document === "undefined") return null;

    const start = (e: React.PointerEvent) => {
      if (closing.current) return;
      drag.current = { y: e.clientY, t: e.timeStamp, dy: 0, id: e.pointerId };
      e.currentTarget.setPointerCapture(e.pointerId);
      if (sheetRef.current) { sheetRef.current.style.transition = "none"; sheetRef.current.style.animation = "none"; }
      if (dimRef.current) { dimRef.current.style.transition = "none"; dimRef.current.style.animation = "none"; }
    };
    const move = (e: React.PointerEvent) => {
      const d = drag.current; if (!d || d.id !== e.pointerId) return;
      d.dy = Math.max(0, e.clientY - d.y);
      if (sheetRef.current) sheetRef.current.style.transform = `translateY(${d.dy}px)`;
      if (dimRef.current) dimRef.current.style.opacity = String(Math.max(0.2, 1 - d.dy / 400));
    };
    const end = (e: React.PointerEvent) => {
      const d = drag.current; if (!d || d.id !== e.pointerId) return;
      drag.current = null;
      const h = sheetRef.current?.offsetHeight ?? 300;
      const v = d.dy / Math.max(1, e.timeStamp - d.t); // px/ms
      if (d.dy > h * 0.3 || (v > 0.5 && d.dy > 24)) { close(); return; }   // past the threshold or flicked → glide shut
      if (sheetRef.current) { sheetRef.current.style.transition = "transform .3s cubic-bezier(.2,.8,.2,1)"; sheetRef.current.style.transform = "translateY(0)"; }
      if (dimRef.current) { dimRef.current.style.transition = "opacity .3s"; dimRef.current.style.opacity = "1"; }
    };

    return createPortal(
      <div ref={dimRef} className={styles.dim} onClick={dismissible ? () => close() : undefined}>
        <div ref={sheetRef} className={styles.sheet} role="dialog" aria-modal="true" aria-label={label} onClick={(e) => e.stopPropagation()}>
          {dismissible ? (
            <div className={styles.gripZone} onPointerDown={start} onPointerMove={move} onPointerUp={end} onPointerCancel={end} aria-hidden="true">
              <div className={styles.grip} />
            </div>
          ) : <div className={styles.noGrip} />}
          {children}
        </div>
      </div>,
      document.body,
    );
  },
);
export default BottomSheet;
