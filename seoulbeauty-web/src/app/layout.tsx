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
        {/* hms.seoulbeauty.app is the patient app's host. A normal browser that opens a page there is sent to the public domain
            (same path; the session cookie is shared: domain=.seoulbeauty.app). The app is left alone three ways: the FlutterBridge
            channel (checked after load, it can arrive late), a WebView user agent (Android "; wv)", iOS without "Safari/") and ?app=.
            /next/api/* never runs this script, so payment returns and social callbacks on this host are unaffected. */}
        <script dangerouslySetInnerHTML={{ __html: 'try{(function(){if(location.hostname.toLowerCase()!=="hms.seoulbeauty.app")return;var ua=navigator.userAgent;var ios=/iPhone|iPad|iPod/.test(ua)||(/Macintosh/.test(ua)&&navigator.maxTouchPoints>1);if(/; wv\\)/.test(ua)||(ios&&!/Safari\\//.test(ua))||/[?&]app=/.test(location.search))return;var go=function(){if(window.FlutterBridge||document.documentElement.getAttribute("data-app"))return;location.replace("https://seoulbeauty.app"+location.pathname+location.search+location.hash)};var later=function(){setTimeout(go,300)};if(document.readyState==="complete")later();else window.addEventListener("load",later)})()}catch(e){}' }} />
        {/* The app hands the device's FCM token to the page once per page load, right when the load finishes, and does not retry
            (webview_bridge.dart requestFcmTokenForWebsite): the two functions must exist before React does. Keep the token and tell
            whoever listens (lib/pushToken.ts, event "sb-push-token"). Nobody calls them in a normal browser. */}
        <script dangerouslySetInnerHTML={{ __html: 'try{(function(){var f=function(t){if(typeof t!=="string"||!t)return;window.__sbPushToken=t;try{window.dispatchEvent(new Event("sb-push-token"))}catch(e){}};window.onTokenReceived=f;window.receiveFCMToken=f})()}catch(e){}' }} />
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
