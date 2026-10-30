import { Suspense } from "react";
import type { Metadata } from "next";
import MyBookings from "@/components/MyBookings";

export const metadata: Metadata = { title: "내 예약/결제 내역" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><MyBookings /></Suspense>;
}
