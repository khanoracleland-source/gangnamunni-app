import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import AccountWithdraw from "@/components/AccountWithdraw";

export const metadata: Metadata = { title: "회원 탈퇴" };

/** Member-only: the session cookie goes to the API; a visitor gets the login prompt. */
async function Body() {
  const { data, anon } = await loadMine((ctx) => api.myProfile(ctx));
  return <AccountWithdraw initial={data} anon={anon} />;
}

export default function Page() {
  return <Suspense fallback={null}><Body /></Suspense>;
}
