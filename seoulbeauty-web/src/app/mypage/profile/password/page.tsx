import { Suspense } from "react";
import type { Metadata } from "next";
import AccountPassword from "@/components/AccountPassword";

export const metadata: Metadata = { title: "비밀번호 변경" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><AccountPassword /></Suspense>;
}
