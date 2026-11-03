"use client";
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import resetIcon from "@/assets/icons/reset.png";
import styles from "./RegionPicker.module.css";

/** Open / close animation length (ms): keep in step with RegionPicker.module.css. */
const ANIM_MS = 400;

/** design 05-02: panel under the search row (dimmed page below), 4-column region grid, 초기화 · chosen-region chips · 필터 선택 완료. */
export default function RegionPicker(props: { open: boolean; groups: string[][]; selected: string[]; onClose: () => void; onApply: (r: string[]) => void }) {
  // Closing keeps the panel mounted for the length of the exit animation (the state is adjusted while rendering, the timer only unmounts).
  const [prevOpen, setPrevOpen] = useState(props.open);
  const [closing, setClosing] = useState(false);
  if (prevOpen !== props.open) { setPrevOpen(props.open); setClosing(!props.open); }
  useEffect(() => {
    if (!closing) return;
    const reduce = typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
    const t = setTimeout(() => setClosing(false), reduce ? 0 : ANIM_MS);
    return () => clearTimeout(t);
  }, [closing]);
  // Remount on open so the draft starts from the current selection (no effect/setState needed).
  if (!props.open && !closing) return null;
  return <RegionPickerBody key={props.selected.join(",")} {...props} closing={!props.open} />;
}

const MARK_OFF = (
  <svg className={styles.mark} viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true"><rect x="1" y="1" width="14" height="14" rx="3.5" fill="#fff" stroke="#DCD6D8" strokeWidth="1.4" /></svg>
);
const MARK_ON = (
  <svg className={styles.mark} viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true"><rect x="0.5" y="0.5" width="15" height="15" rx="4" fill="#AC1830" /><path d="M4.4 8.3l2.5 2.5 4.7-4.9" stroke="#fff" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" /></svg>
);

function RegionPickerBody({ groups, selected, onClose, onApply, closing }: { groups: string[][]; selected: string[]; onClose: () => void; onApply: (r: string[]) => void; closing: boolean }) {
  const [sel, setSel] = useState<string[]>(selected);
  const all = groups.flat();
  const toggle = (r: string) => setSel((s) => (s.includes(r) ? s.filter((x) => x !== r) : [...s, r]));
  // Rendered in <body>: `.page > *` are animated (each is a stacking context), so inside the header the panel would be painted
  // under the screens that follow it. It is placed from the anchor (the parent: search row) - the dim starts where the panel starts.
  const anchorRef = useRef<HTMLSpanElement>(null);
  const [top, setTop] = useState<number | null>(null);
  useLayoutEffect(() => {
    const measure = () => {
      const p = anchorRef.current?.parentElement;
      if (!p) return;
      const gap = parseFloat(getComputedStyle(p).getPropertyValue("--rp-gap")) || 0;
      setTop(Math.round(p.getBoundingClientRect().bottom - gap));
    };
    measure();
    window.addEventListener("resize", measure);
    return () => window.removeEventListener("resize", measure);
  }, []);
  useEffect(() => {
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => { document.body.style.overflow = prev; };
  }, []);
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);
  return (
    <>
      <span ref={anchorRef} hidden />
      {top !== null && createPortal(
        <>
          <div className={`${styles.backdrop} ${closing ? styles.out : ""}`} style={{ top }} onClick={onClose} aria-hidden="true" />
          <div className={`${styles.panel} ${closing ? styles.out : ""}`} style={{ top }} role="dialog" aria-label="지역 선택">
            <div className={styles.grid}>
              {all.length === 0 && Array.from({ length: 24 }, (_, i) => <span key={i} className={`${styles.ph} skeleton`} aria-hidden="true" />)}
              {all.map((r) => {
                const on = sel.includes(r);
                return (
                  <button key={r} type="button" className={`${styles.item} ${on ? styles.itemOn : ""}`} aria-pressed={on} onClick={() => toggle(r)}>
                    {on ? MARK_ON : MARK_OFF}{r}
                  </button>
                );
              })}
            </div>
            <div className={styles.bar}>
              <button type="button" className={styles.reset} onClick={() => setSel([])}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={resetIcon.src} alt="" width={16} height={16} />초기화
              </button>
              <div className={styles.chips}>
                {sel.map((r) => (
                  <button key={r} type="button" className={styles.chip} aria-label={`${r} 제거`} onClick={() => toggle(r)}>
                    {r}
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                  </button>
                ))}
              </div>
              <button type="button" className={styles.apply} onClick={() => onApply(sel)}>필터 선택 완료</button>
            </div>
          </div>
        </>,
        document.body,
      )}
    </>
  );
}
