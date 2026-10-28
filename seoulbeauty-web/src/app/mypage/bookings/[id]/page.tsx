import { Suspense } from "react";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import BookingDetail from "@/components/BookingDetail";

export const metadata: Metadata = { title: "예약 상세" };

type P = Promise<{ id: string }>;

async function Body({ params }: { params: P }) {
  const n = Number((await params).id);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) notFound();
  const { data, anon } = await loadMine((ctx) => api.bookingDetail(n, ctx));
  return <BookingDetail id={n} initial={data} anon={anon} />;
}

export default function Page({ params }: { params: P }) {
  return <Suspense fallback={null}><Body params={params} /></Suspense>;
}
