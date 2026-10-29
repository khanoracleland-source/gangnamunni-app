import { Suspense } from "react";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import BookingPaymentSuccess from "@/components/BookingPaymentSuccess";

export const metadata: Metadata = { title: "예약 확정" };

type P = Promise<{ id: string }>;

/** booking_payment_success.php?booking_id=N */
async function Body({ params }: { params: P }) {
  const n = Number((await params).id);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) notFound();
  return <BookingPaymentSuccess id={n} />;
}

export default function Page({ params }: { params: P }) {
  return <Suspense fallback={null}><Body params={params} /></Suspense>;
}
