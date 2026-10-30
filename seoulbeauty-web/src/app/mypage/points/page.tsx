import { Suspense } from "react";
import type { Metadata } from "next";
import MyPoints from "@/components/MyPoints";

export const metadata: Metadata = { title: "포인트" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><MyPoints /></Suspense>;
}
