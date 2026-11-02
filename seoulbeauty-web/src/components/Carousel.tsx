"use client";
import { Children, cloneElement, isValidElement, useCallback, useEffect, useRef, useState, type ReactElement } from "react";
import styles from "./Carousel.module.css";

/**
 * One slide per view, looping. Swipe/drag with the pointer (vertical scrolling still works), auto-advance
 * every `intervalMs` (pauses while hovering, touching, off-screen or in a background tab; the timer restarts
 * after every manual change). No dots, no counter.
 * Loop = a clone of slide 0 after the last slide: after sliding onto it we jump back to 0 without animation.
 */
export default function Carousel({ children, auto, intervalMs = 5000, label }: { children: React.ReactNode[]; auto: boolean; intervalMs?: number; label: string }) {
  const slides = Children.toArray(children);
  const n = slides.length;
  const wrapRef = useRef<HTMLDivElement>(null);
  const [pos, setPos] = useState(0); // 0..n, n = the clone of slide 0
  const [anim, setAnim] = useState(true);
  const [dx, setDx] = useState(0);
  const [dragging, setDragging] = useState(false);
  const [tick, setTick] = useState(0); // bumps on every manual change → restarts the auto timer
  const gesture = useRef({ x: 0, y: 0, w: 1, active: false, drag: false, moved: false });
  const paused = useRef(false);
  const visible = useRef(true);
  const afterJump = useRef<(() => void) | null>(null);
  const idx = n ? pos % n : 0;

  const next = useCallback(() => { setAnim(true); setPos((p) => (p >= n ? p : p + 1)); }, [n]);
  const prev = useCallback(() => {
    setAnim(true);
    setPos((p) => {
      if (p > 0) return p - 1;
      afterJump.current = () => setPos(n - 1); // from 0: jump to the clone (n) silently, then slide back to n-1
      setAnim(false);
      return n;
    });
  }, [n]);

  // After a silent jump (anim off) re-enable the transition on the next paint, then run the follow-up move.
  useEffect(() => {
    if (anim) return;
    const id = requestAnimationFrame(() => requestAnimationFrame(() => {
      setAnim(true);
      const f = afterJump.current; afterJump.current = null;
      if (f) f();
    }));
    return () => cancelAnimationFrame(id);
  }, [anim, pos]);

  // Landed on the clone → back to slide 0 without animation (fallback timer in case transitionend never fires).
  const normalize = useCallback(() => { setAnim(false); setPos((p) => (p >= n ? 0 : p)); }, [n]);
  useEffect(() => {
    if (pos !== n || n < 2) return;
    const t = setTimeout(normalize, 700);
    return () => clearTimeout(t);
  }, [pos, n, normalize]);

  // Auto-advance.
  useEffect(() => {
    if (!auto || n < 2) return;
    if (typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) return;
    const t = setInterval(() => {
      if (paused.current || !visible.current || document.hidden) return;
      next();
    }, intervalMs);
    return () => clearInterval(t);
  }, [auto, n, intervalMs, next, tick]);

  // Only advance while the carousel is actually on screen.
  useEffect(() => {
    const el = wrapRef.current;
    if (!el || !("IntersectionObserver" in window)) return;
    const io = new IntersectionObserver(([e]) => { visible.current = e.isIntersecting; }, { threshold: 0.2 });
    io.observe(el);
    return () => io.disconnect();
  }, []);

  if (n === 0) return null;
  if (n === 1) return <div className={styles.wrap} aria-roledescription="carousel" aria-label={label}><div className={styles.slide}>{slides[0]}</div></div>;

  const manual = (fn: () => void) => { fn(); setTick((t) => t + 1); };
  const g = gesture.current;
  const onPointerDown = (e: React.PointerEvent) => {
    if (e.pointerType === "mouse" && e.button !== 0) return;
    if (pos >= n) normalize();
    g.x = e.clientX; g.y = e.clientY; g.w = wrapRef.current?.clientWidth || 1; g.active = true; g.drag = false; g.moved = false;
    paused.current = true;
  };
  const onPointerMove = (e: React.PointerEvent) => {
    if (!g.active) return;
    const mx = e.clientX - g.x, my = e.clientY - g.y;
    if (!g.drag) {
      if (Math.abs(mx) < 6 || Math.abs(mx) < Math.abs(my)) return;
      g.drag = true; g.moved = true; setDragging(true);
      try { wrapRef.current?.setPointerCapture(e.pointerId); } catch { /* ignore */ }
    }
    setDx(mx);
  };
  const end = (e: React.PointerEvent) => {
    if (!g.active) return;
    g.active = false;
    paused.current = false;
    if (g.drag) {
      const mx = e.clientX - g.x;
      setDragging(false); setDx(0);
      if (e.type !== "pointercancel" && Math.abs(mx) > Math.min(80, g.w * 0.2)) manual(mx < 0 ? next : prev);
      g.drag = false;
    }
  };

  const offset = `calc(${-pos * 100}% + ${dx}px)`;
  return (
    <div
      ref={wrapRef}
      className={`${styles.wrap} ${dragging ? styles.dragging : ""}`}
      aria-roledescription="carousel"
      aria-label={label}
      onPointerDown={onPointerDown}
      onPointerMove={onPointerMove}
      onPointerUp={end}
      onPointerCancel={end}
      onPointerEnter={(e) => { if (e.pointerType === "mouse") paused.current = true; }}
      onPointerLeave={(e) => { if (e.pointerType === "mouse" && !g.active) paused.current = false; }}
      onClickCapture={(e) => { if (g.moved) { e.preventDefault(); e.stopPropagation(); g.moved = false; } }}
    >
      <div
        className={styles.track}
        style={{ transform: `translate3d(${offset},0,0)`, transition: anim && !dragging ? "transform .45s cubic-bezier(.22,.61,.36,1)" : "none" }}
        onTransitionEnd={(e) => { if (e.target === e.currentTarget && pos >= n) normalize(); }}
      >
        {slides.map((c, i) => (
          <div key={i} className={styles.slide} aria-roledescription="slide" aria-label={`${i + 1} / ${n}`} aria-hidden={i !== idx || undefined} inert={i !== idx || undefined}>{c}</div>
        ))}
        {/* clone of slide 0 (no ad-tracking attribute, so impressions are not counted twice) */}
        <div className={styles.slide} aria-hidden inert>
          {isValidElement(slides[0]) ? cloneElement(slides[0] as ReactElement<Record<string, unknown>>, { "data-ad-id": undefined, tabIndex: -1 }) : slides[0]}
        </div>
      </div>
    </div>
  );
}
