import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import AccountEmail from "@/components/AccountEmail";

export const metadata: Metadata = { title: "이메일 변경" };

/** Member-only: the session cookie goes to the API; a visitor gets the login prompt. */
async function Body() {
  const { data, anon } = await loadMine((ctx) => api.myProfile(ctx));
  return <AccountEmail initial={data} anon={anon} />;
}

export default function Page() {
  return <Suspense fallback={null}><Body /></Suspense>;
}
