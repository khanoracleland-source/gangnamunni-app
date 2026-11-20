import type { Metadata } from "next";
import { Suspense } from "react";
import PaymentsPage from "@/components/payments/PaymentsPage";

export const metadata: Metadata = { title: "예약 결제" };

// Rendered per request; the screen reads nothing from the server except who is signed in.
export const dynamic = "force-dynamic";

export default function Page() {
  return (
    <Suspense fallback={null}>
      <PaymentsPage />
    </Suspense>
  );
}
