import { Suspense } from "react";
import type { Metadata } from "next";
import AccountEmail from "@/components/AccountEmail";

export const metadata: Metadata = { title: "이메일 변경" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><AccountEmail /></Suspense>;
}
