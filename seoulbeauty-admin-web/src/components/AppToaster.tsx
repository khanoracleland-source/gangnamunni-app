"use client";

import { Toaster } from "sonner";

// One toaster for the whole panel. Colours come from the panel's own tokens, so light and dark themes
// follow `data-theme` without a separate theme prop.
export default function AppToaster() {
  return (
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
  );
}
