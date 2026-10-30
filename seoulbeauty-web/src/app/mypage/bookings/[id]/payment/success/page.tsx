import { Suspense } from "react";
import type { Metadata } from "next";
import { BookingPaymentSuccessRoute } from "@/components/Routes";

export const metadata: Metadata = { title: "예약 확정" };

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={null}><BookingPaymentSuccessRoute /></Suspense>;
}
