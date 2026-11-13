import type { Metadata, Viewport } from "next";
import "./globals.css";
import { THEME_STORAGE_KEY } from "@/lib/config";

export const metadata: Metadata = {
  title: { default: "서울미녀 관리자", template: "%s | 서울미녀 관리자" },
  description: "서울미녀 관리자",
  robots: { index: false, follow: false },
};

export const viewport: Viewport = { width: "device-width", initialScale: 1 };

// Runs before first paint: puts the saved day/night choice on <html>, so a dark-mode user never
// sees a white flash. Storage can be blocked; then the default (light) stays.
const THEME_SCRIPT = `try{var t=localStorage.getItem(${JSON.stringify(THEME_STORAGE_KEY)});document.documentElement.setAttribute("data-theme",t==="dark"?"dark":"light")}catch(e){}`;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ko" data-theme="light" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: THEME_SCRIPT }} />
      </head>
      <body>{children}</body>
    </html>
  );
}
