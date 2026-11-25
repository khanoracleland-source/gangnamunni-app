"use client";

import { useEffect } from "react";

/** How long the closing animation of a dialog runs (the CSS transition in globals.css, "Dialog motion"). */
const EXIT_MS = 160;
const replayed = new WeakSet<Event>();

/**
 * Lets a dialog fade and slide out before the screen that owns it drops it.
 *
 * A native <dialog> closes at once and the screens unmount it from its `close` event, so a closing animation never gets time to show.
 * This holds that one event back for the length of the CSS exit transition and then fires it again, unchanged,
 * so every dialog of the panel gets the same motion without each screen having to wait on its own.
 * With reduced motion, or a browser that cannot animate a closing dialog, the event is not held back.
 */
export default function DialogMotion() {
  useEffect(() => {
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)");
    const animates = typeof CSS !== "undefined" && typeof CSS.supports === "function" && CSS.supports("transition-behavior", "allow-discrete") && CSS.supports("overlay", "auto");
    const onClose = (e: Event) => {
      const dialog = e.target;
      if (!(dialog instanceof HTMLDialogElement) || replayed.has(e) || reduce.matches || !animates) return;
      if (!dialog.matches("dialog.dialog, dialog.ha-dlg")) return;
      e.stopImmediatePropagation();
      window.setTimeout(() => {
        // Opened again in the meantime, or taken off the page: the held-back event no longer describes it.
        if (dialog.open || !dialog.isConnected) return;
        const again = new Event("close");
        replayed.add(again);
        dialog.dispatchEvent(again);
      }, EXIT_MS);
    };
    // `close` does not bubble: the capture phase on the document is the one place that sees it before the dialog's own listener.
    document.addEventListener("close", onClose, true);
    return () => document.removeEventListener("close", onClose, true);
  }, []);
  return null;
}
