import { Suspense } from "react";
import type { Metadata } from "next";
import { BookingPaymentRoute } from "@/components/Routes";

export const metadata: Metadata = { title: "결제를 진행해볼까요?" };

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={null}><BookingPaymentRoute /></Suspense>;
}
