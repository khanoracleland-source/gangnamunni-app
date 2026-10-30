import { Suspense } from "react";
import type { Metadata } from "next";
import AccountWithdraw from "@/components/AccountWithdraw";

export const metadata: Metadata = { title: "회원 탈퇴" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><AccountWithdraw /></Suspense>;
}
