import { Suspense } from "react";
import type { Metadata } from "next";
import MySettings from "@/components/MySettings";

export const metadata: Metadata = { title: "알림 설정" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><MySettings /></Suspense>;
}
