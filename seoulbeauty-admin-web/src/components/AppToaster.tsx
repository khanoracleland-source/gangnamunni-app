"use client";

import { useEffect, useRef } from "react";
import { Toaster } from "sonner";

export const TOAST_LAYER_ID = "app-toast-layer";

/** A modal dialog sits in the top layer, above everything else, and its backdrop dims the page: a toast fired while one is open would be hidden under it. The toasts live in a popover so they join the top layer too; calling this after a toast was added puts the layer above a dialog opened after it. */
export function raiseToastLayer(): void {
  const el = document.getElementById(TOAST_LAYER_ID);
  if (!el || typeof el.hidePopover !== "function" || !el.matches(":popover-open")) return;
  el.hidePopover();
  el.showPopover();
}

// One toaster for the whole panel. Colours come from the panel's own tokens, so light and dark themes
// follow `data-theme` without a separate theme prop.
export default function AppToaster() {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = ref.current;
    if (el && typeof el.showPopover === "function" && !el.matches(":popover-open")) el.showPopover();
    // While a modal dialog is open the page outside it cannot be clicked: a click on a toast button would fall through to the dialog (and close it).
    // A click whose point lies over a toast button is therefore handed to that button, and goes no further.
    const onClick = (e: MouseEvent) => {
      if (e.detail === 0 || (e.target instanceof Node && el?.contains(e.target))) return;
      for (const b of document.querySelectorAll<HTMLButtonElement>("[data-sonner-toast] button")) {
        const r = b.getBoundingClientRect();
        if (e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom) {
          e.stopPropagation();
          e.preventDefault();
          b.click();
          return;
        }
      }
    };
    // Hover does not reach the toast either, so the button under the pointer is marked by hand (hand cursor and hover colour).
    let hot: HTMLButtonElement | null = null;
    const onMove = (e: MouseEvent) => {
      let found: HTMLButtonElement | null = null;
      for (const b of document.querySelectorAll<HTMLButtonElement>("[data-sonner-toast] button")) {
        const r = b.getBoundingClientRect();
        if (e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom) { found = b; break; }
      }
      if (found === hot) return;
      hot?.removeAttribute("data-hot");
      hot = found;
      hot?.setAttribute("data-hot", "");
      document.documentElement.classList.toggle("toast-hot", hot !== null);
    };
    document.addEventListener("click", onClick, true);
    document.addEventListener("mousemove", onMove, true);
    return () => {
      document.removeEventListener("click", onClick, true);
      document.removeEventListener("mousemove", onMove, true);
      document.documentElement.classList.remove("toast-hot");
    };
  }, []);
  return (
    <div ref={ref} id={TOAST_LAYER_ID} popover="manual" className="toast-layer">
    <Toaster
      position="top-center"
      offset="calc(var(--topbar-h) + 12px)"
      mobileOffset={{ top: "calc(var(--topbar-h) + 12px)", left: 16, right: 16 }}
      duration={5000}
      closeButton
      style={
        {
          "--normal-bg": "var(--surface)",
          "--normal-text": "var(--ink)",
          "--normal-border": "var(--line)",
          "--border-radius": "var(--r-md)",
          "--width": "min(480px, calc(100vw - 32px))",
          fontFamily: "var(--font)",
        } as React.CSSProperties
      }
    />
    </div>
  );
}
