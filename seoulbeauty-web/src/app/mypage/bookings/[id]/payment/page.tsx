import { Suspense } from "react";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import BookingPayment from "@/components/BookingPayment";

export const metadata: Metadata = { title: "결제를 진행해볼까요?" };

type P = Promise<{ id: string }>;
type SP = Promise<Record<string, string | string[] | undefined>>;

/** booking_payment.php?booking_id=N[&pay_error=CODE] */
async function Body({ params, searchParams }: { params: P; searchParams: SP }) {
  const n = Number((await params).id);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) notFound();
  const sp = await searchParams;
  const raw = Array.isArray(sp.pay_error) ? sp.pay_error[0] : sp.pay_error;
  return <BookingPayment id={n} payError={(raw ?? "").replace(/[^A-Z_]/g, "")} />;
}

export default function Page({ params, searchParams }: { params: P; searchParams: SP }) {
  return <Suspense fallback={null}><Body params={params} searchParams={searchParams} /></Suspense>;
}
