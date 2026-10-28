import { Suspense } from "react";
import type { Metadata } from "next";
import { api, type NotiFilter } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import MyNotifications from "@/components/MyNotifications";

export const metadata: Metadata = { title: "알림" };

const filterOf = (v: string | string[] | undefined): NotiFilter => (v === "booking" || v === "review" || v === "benefit" ? v : "all");

/** Member-only: the session cookie goes to the API; a visitor gets the login prompt. */
async function Body({ searchParams }: { searchParams: Promise<{ type?: string | string[] }> }) {
  const type = filterOf((await searchParams).type);
  const { data, anon } = await loadMine((ctx) => api.myNotifications(type, 1, ctx));
  return <MyNotifications initial={data} initialType={type} anon={anon} />;
}

export default function Page({ searchParams }: { searchParams: Promise<{ type?: string | string[] }> }) {
  return <Suspense fallback={null}><Body searchParams={searchParams} /></Suspense>;
}
