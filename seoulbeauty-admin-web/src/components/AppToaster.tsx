"use client";

import { Toaster } from "sonner";

// One toaster for the whole panel. Colours come from the panel's own tokens, so light and dark themes
// follow `data-theme` without a separate theme prop.
export default function AppToaster() {
  return (
    <Toaster
      position="bottom-center"
      duration={4000}
      closeButton
      style={
        {
          "--normal-bg": "var(--surface)",
          "--normal-text": "var(--ink)",
          "--normal-border": "var(--line)",
          "--border-radius": "var(--r-md)",
          fontFamily: "var(--font)",
        } as React.CSSProperties
      }
    />
  );
}
