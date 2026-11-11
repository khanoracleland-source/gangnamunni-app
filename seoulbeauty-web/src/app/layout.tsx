import type { Metadata, Viewport } from "next";
import { Suspense } from "react";
import "@fontsource-variable/noto-sans-kr"; // design font: Noto Sans KR (self-hosted, korean + latin slices load on demand)
import "./globals.css";
import Providers from "./providers";
import BottomNav from "@/components/BottomNav";

/** Link preview (KakaoTalk, etc.): the same image the PHP pages use (includes/header.php). It must be an absolute URL. */
const OG_IMAGE = `${(process.env.NEXT_PUBLIC_ASSET_BASE || "https://seoulbeauty.app").replace(/\/$/, "")}/assets/images/og-seoulbeauty.jpg`;

export const metadata: Metadata = {
  title: { default: "서울미녀", template: "%s | 서울미녀" },
  description: "서울미녀 — 병원 찾기",
  openGraph: {
    type: "website", siteName: "서울미녀", title: "서울미녀", description: "서울미녀 — 병원 찾기", locale: "ko_KR",
    images: [{ url: OG_IMAGE, width: 1200, height: 630, alt: "서울미녀 SEOUL BEAUTY" }],
  },
  twitter: { card: "summary_large_image", title: "서울미녀", description: "서울미녀 — 병원 찾기", images: [OG_IMAGE] },
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
    <html lang="ko" suppressHydrationWarning>
      <head>
        {/* Inside the Flutter app the WebView already sits below the status bar (native SafeArea) yet Android still reports env(safe-area-inset-*):
            mark the document so the web layer does not add the inset a second time (see --sat in globals.css). */}
        <script dangerouslySetInnerHTML={{ __html: 'try{var m=function(){if(window.FlutterBridge){var d=document.documentElement;d.setAttribute("data-app","1");if(/iPhone|iPad|iPod/.test(navigator.userAgent)||(/Macintosh/.test(navigator.userAgent)&&navigator.maxTouchPoints>1))d.setAttribute("data-ios","1")}};m();document.addEventListener("DOMContentLoaded",m);window.addEventListener("load",m)}catch(e){}' }} />
      </head>
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
