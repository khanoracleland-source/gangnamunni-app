import type { Metadata, Viewport } from "next";
import { Suspense } from "react";
import "./globals.css";
import Providers from "./providers";
import BottomNav from "@/components/BottomNav";

export const metadata: Metadata = {
  title: { default: "서울미녀", template: "%s | 서울미녀" },
  description: "서울미녀 — 병원 찾기",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  maximumScale: 1,
  viewportFit: "cover",
  themeColor: "#ffffff",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ko">
      <body>
        <Providers>
          <main className="page">{children}</main>
          {/* usePathname is a runtime hook: keep it out of the static shell */}
          <Suspense fallback={null}>
            <BottomNav />
          </Suspense>
        </Providers>
      </body>
    </html>
  );
}
