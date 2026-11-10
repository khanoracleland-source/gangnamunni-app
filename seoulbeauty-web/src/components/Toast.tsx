"use client";
import { createContext, useContext, useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import styles from "./Toast.module.css";

/**
 * THE toast. Every short message that slides up from the bottom ("좋아요 반영되었습니다." …) goes through `useToast()`.
 * Rules (owner, 2026-11-10):
 *  - it rises from the bottom edge slowly (0.4 s, same easing as BottomSheet), stays ~2 s (longer for long messages), then sinks back down slowly (0.4 s)
 *    with "reduce motion" on, both moves are a 0.2 s fade (the JS timing follows the CSS)
 *  - only one toast is on screen: if another message arrives, the current one first sinks down, then the new one rises
 *    (messages that arrive while one is already waiting replace it — only the latest is shown, no long queue)
 *  - rendered in a portal on <body> above the floating tab bar (z 1100), never blocks taps
 * Usage: `const toast = useToast(); toast("좋아요 반영되었습니다.");`  (optional 2nd arg: how long it stays, in ms; default 2 s + 50 ms per character beyond 20, max 4 s)
 */
export const TOAST_MOVE_MS = 400; // keep in sync with Toast.module.css (.4s)
const REDUCED_MOVE_MS = 200; // prefers-reduced-motion: fade only (.2s in the CSS)
const HOLD_MS = 2000;
const HOLD_MAX_MS = 4000;

const moveMs = () => (typeof window.matchMedia === "function" && window.matchMedia("(prefers-reduced-motion: reduce)").matches ? REDUCED_MOVE_MS : TOAST_MOVE_MS);
/** Short messages stay 2 s; longer ones get 50 ms per extra character so that they can still be read (max 4 s). */
const holdFor = (msg: string) => Math.min(HOLD_MAX_MS, HOLD_MS + Math.max(0, msg.length - 20) * 50);

type Item = { id: number; msg: string; hold: number };
type Shown = Item & { leaving: boolean };

function createController(setShown: (s: Shown | null) => void) {
  let seq = 0;
  let cur: Item | null = null; // on screen right now (also while it is sinking)
  let leaving = false;
  let pending: Item | null = null; // waits for the current one to sink
  let hideT = 0;
  let outT = 0;

  function present(it: Item) {
    cur = it;
    leaving = false;
    setShown({ ...it, leaving: false });
    hideT = window.setTimeout(leave, moveMs() + it.hold);
  }
  function leave() {
    if (!cur || leaving) return;
    leaving = true;
    window.clearTimeout(hideT);
    setShown({ ...cur, leaving: true });
    outT = window.setTimeout(() => {
      cur = null;
      leaving = false;
      setShown(null);
      const next = pending;
      pending = null;
      if (next) present(next);
    }, moveMs());
  }
  return {
    show(msg: string, hold = holdFor(msg)) {
      const it: Item = { id: ++seq, msg, hold };
      if (!cur) present(it);
      else { pending = it; leave(); }
    },
    dispose() { window.clearTimeout(hideT); window.clearTimeout(outT); },
  };
}

const Ctx = createContext<(msg: string, holdMs?: number) => void>(() => {});
export const useToast = () => useContext(Ctx);

/** One toast. It mounts below the screen and is lifted in on the next frame, so that closing mid-rise reverses smoothly. */
function ToastView({ msg, leaving }: { msg: string; leaving: boolean }) {
  const [entered, setEntered] = useState(false);
  useEffect(() => {
    let b = 0;
    const a = requestAnimationFrame(() => { b = requestAnimationFrame(() => setEntered(true)); });
    return () => { cancelAnimationFrame(a); cancelAnimationFrame(b); };
  }, []);
  return (
    <div className={`${styles.toast} ${entered && !leaving ? styles.show : ""}`} role="status" aria-live="polite">{msg}</div>
  );
}

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [shown, setShown] = useState<Shown | null>(null);
  const ctl = useMemo(() => createController(setShown), []);
  useEffect(() => () => ctl.dispose(), [ctl]);
  const show = useMemo(() => ctl.show, [ctl]);
  return (
    <Ctx.Provider value={show}>
      {children}
      {/* `shown` only ever becomes non-null after a user action in the browser, so document is always there */}
      {shown && createPortal(<ToastView key={shown.id} msg={shown.msg} leaving={shown.leaving} />, document.body)}
    </Ctx.Provider>
  );
}
