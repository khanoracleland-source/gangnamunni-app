"use client";
import { useEffect, useRef } from "react";
import styles from "./BottomSheet.module.css";

/**
 * Design bottom sheet (02-02, 02-06 …): dimmed backdrop, grip, slides up. Close = drag the grip down, tap the backdrop or Esc.
 * `onClose` runs after the slide-out animation. `dismissible={false}`: a decision sheet that only its own button closes (no backdrop tap, Esc or drag). On wide screens (≥600px) it is a centred card (no grip).
 */
export default function BottomSheet({ open, onClose, label, children, dismissible = true }: { open: boolean; onClose: () => void; label: string; children: React.ReactNode; dismissible?: boolean }) {
  const sheetRef = useRef<HTMLDivElement>(null);
  const dimRef = useRef<HTMLDivElement>(null);
  const drag = useRef<{ y: number; t: number; dy: number; id: number } | null>(null);
  const closing = useRef(false);
  const onCloseRef = useRef(onClose);
  const dismissibleRef = useRef(dismissible);
  useEffect(() => { onCloseRef.current = onClose; dismissibleRef.current = dismissible; });

  const close = () => {
    if (closing.current) return;
    closing.current = true;
    const el = sheetRef.current, dim = dimRef.current;
    if (el) { el.style.transition = "transform .24s cubic-bezier(.4,0,1,1)"; el.style.transform = "translateY(100%)"; }
    if (dim) { dim.style.transition = "opacity .24s"; dim.style.opacity = "0"; }
    window.setTimeout(() => { closing.current = false; onCloseRef.current(); }, 230);
  };
  const closeRef = useRef(close);
  useEffect(() => { closeRef.current = close; });

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape" && dismissibleRef.current) closeRef.current(); };
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    window.addEventListener("keydown", onKey);
    return () => { document.body.style.overflow = prev; window.removeEventListener("keydown", onKey); };
  }, [open]);

  if (!open) return null;

  const start = (e: React.PointerEvent) => {
    drag.current = { y: e.clientY, t: e.timeStamp, dy: 0, id: e.pointerId };
    e.currentTarget.setPointerCapture(e.pointerId);
    if (sheetRef.current) { sheetRef.current.style.transition = "none"; sheetRef.current.style.animation = "none"; }
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
    if (d.dy > h * 0.3 || (v > 0.5 && d.dy > 24)) { close(); return; }
    if (sheetRef.current) { sheetRef.current.style.transition = "transform .22s cubic-bezier(.2,.8,.2,1)"; sheetRef.current.style.transform = "translateY(0)"; }
    if (dimRef.current) { dimRef.current.style.transition = "opacity .22s"; dimRef.current.style.opacity = "1"; }
  };

  return (
    <div ref={dimRef} className={styles.dim} onClick={dismissible ? close : undefined}>
      <div ref={sheetRef} className={styles.sheet} role="dialog" aria-modal="true" aria-label={label} onClick={(e) => e.stopPropagation()}>
        {dismissible ? (
          <div className={styles.gripZone} onPointerDown={start} onPointerMove={move} onPointerUp={end} onPointerCancel={end} aria-hidden="true">
            <div className={styles.grip} />
          </div>
        ) : <div className={styles.noGrip} />}
        {children}
      </div>
    </div>
  );
}
