import type { Metadata, Viewport } from "next";
import "./globals.css";
import { THEME_STORAGE_KEY } from "@/lib/config";
import { LOCALE_STORAGE_KEY } from "@/lib/i18n";

export const metadata: Metadata = {
  title: { default: "서울미녀 관리자", template: "%s | 서울미녀 관리자" },
  description: "서울미녀 관리자",
  robots: { index: false, follow: false },
  // Keeps the browser's page translator away: it rewrites text nodes while React still holds references
  // to them, and the next update (for instance switching 7 to 30 days) then breaks the page.
  // The panel has its own Korean / Uzbek switch instead.
  other: { google: "notranslate" },
};

export const viewport: Viewport = { width: "device-width", initialScale: 1 };

// Runs before first paint: puts the saved theme and language on <html>, so a dark-mode or Uzbek user
// never sees a flash of the defaults. Storage can be blocked; then the defaults (light, ko) stay.
const BOOT_SCRIPT = `try{var d=document.documentElement;var t=localStorage.getItem(${JSON.stringify(THEME_STORAGE_KEY)});d.setAttribute("data-theme",t==="dark"?"dark":"light");var l=localStorage.getItem(${JSON.stringify(LOCALE_STORAGE_KEY)});d.setAttribute("lang",l==="uz"?"uz":"ko")}catch(e){}`;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ko" translate="no" data-theme="light" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: BOOT_SCRIPT }} />
      </head>
      <body className="notranslate">{children}</body>
    </html>
  );
}
