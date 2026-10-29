import { Suspense } from "react";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import BookingCancel from "@/components/BookingCancel";

export const metadata: Metadata = { title: "예약 취소 요청" };

type P = Promise<{ id: string }>;
type SP = Promise<Record<string, string | string[] | undefined>>;

/** booking_cancel_request.php: the reason comes from the sheet on 내 예약 (query), everything else from the API. */
async function Body({ params, searchParams }: { params: P; searchParams: SP }) {
  const n = Number((await params).id);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) notFound();
  const sp = await searchParams;
  const raw = Array.isArray(sp.reason) ? sp.reason[0] : sp.reason;
  return <BookingCancel id={n} reason={(raw ?? "").trim().slice(0, 255)} />;
}

export default function Page({ params, searchParams }: { params: P; searchParams: SP }) {
  return <Suspense fallback={null}><Body params={params} searchParams={searchParams} /></Suspense>;
}
