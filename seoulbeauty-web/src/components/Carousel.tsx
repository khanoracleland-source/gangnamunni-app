"use client";
import { useEffect, useRef, useState } from "react";
import styles from "./Carousel.module.css";

/** Horizontal, one-slide-per-view carousel with optional auto-advance (index.php carousel: 5 s, pauses on touch). */
export default function Carousel({ children, auto, intervalMs = 5000, label }: { children: React.ReactNode[]; auto: boolean; intervalMs?: number; label: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const [idx, setIdx] = useState(0);
  const n = children.length;

  useEffect(() => {
    if (!auto || n < 2) return;
    let paused = false;
    const el = ref.current;
    const onDown = () => { paused = true; };
    const onUp = () => { paused = false; };
    el?.addEventListener("pointerdown", onDown);
    el?.addEventListener("pointerup", onUp);
    const t = setInterval(() => {
      if (paused || document.hidden) return;
      setIdx((i) => (i + 1) % n);
    }, intervalMs);
    return () => { clearInterval(t); el?.removeEventListener("pointerdown", onDown); el?.removeEventListener("pointerup", onUp); };
  }, [auto, n, intervalMs]);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    el.scrollTo({ left: idx * el.clientWidth, behavior: "smooth" });
  }, [idx]);

  if (n === 0) return null;
  return (
    <div className={styles.wrap} aria-roledescription="carousel" aria-label={label}>
      <div
        ref={ref}
        className={styles.track}
        onScroll={(e) => {
          const el = e.currentTarget;
          const i = Math.round(el.scrollLeft / Math.max(1, el.clientWidth));
          if (i !== idx) setIdx(i);
        }}
      >
        {children.map((c, i) => (
          <div key={i} className={styles.slide}>{c}</div>
        ))}
      </div>
      {n > 1 && (
        <div className={styles.dots} aria-hidden>
          {children.map((_, i) => <span key={i} className={`${styles.dot} ${i === idx ? styles.dotOn : ""}`} />)}
        </div>
      )}
    </div>
  );
}
