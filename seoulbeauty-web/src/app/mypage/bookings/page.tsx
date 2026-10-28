import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import MyBookings from "@/components/MyBookings";

export const metadata: Metadata = { title: "내 예약/결제 내역" };

/** Member-only: the session cookie goes to the API; a visitor gets the login prompt. */
async function Body() {
  const { data, anon } = await loadMine((ctx) => api.myBookings(ctx));
  return <MyBookings initial={data} anon={anon} />;
}

export default function Page() {
  return <Suspense fallback={null}><Body /></Suspense>;
}
