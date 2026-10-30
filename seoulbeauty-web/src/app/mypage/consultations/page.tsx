import { Suspense } from "react";
import type { Metadata } from "next";
import MyConsultations from "@/components/MyConsultations";

export const metadata: Metadata = { title: "상담 내역" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><MyConsultations /></Suspense>;
}
