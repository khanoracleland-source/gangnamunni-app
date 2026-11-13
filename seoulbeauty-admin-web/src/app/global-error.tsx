"use client";

import "./globals.css";
import ErrorView from "@/components/ErrorView";
import { LOCALE_STORAGE_KEY } from "@/lib/i18n";
import { THEME_STORAGE_KEY } from "@/lib/config";

// Last resort: replaces the whole document, so it brings its own <html>, theme and language setup.
const BOOT_SCRIPT = `try{var d=document.documentElement;var t=localStorage.getItem(${JSON.stringify(THEME_STORAGE_KEY)});d.setAttribute("data-theme",t==="dark"?"dark":"light");var l=localStorage.getItem(${JSON.stringify(LOCALE_STORAGE_KEY)});d.setAttribute("lang",l==="uz"?"uz":"ko")}catch(e){}`;

export default function GlobalError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <html lang="ko" translate="no" data-theme="light" suppressHydrationWarning>
      <head>
        <meta name="google" content="notranslate" />
        <script dangerouslySetInnerHTML={{ __html: BOOT_SCRIPT }} />
      </head>
      <body className="notranslate">
        <ErrorView error={error} reset={reset} />
      </body>
    </html>
  );
}
