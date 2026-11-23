import type { Metadata } from "next";
import { Suspense } from "react";
import FeeRatesPage from "@/components/payments/FeeRatesPage";

export const metadata: Metadata = { title: "결제 수수료율" };

export const dynamic = "force-dynamic";

export default function Page() {
  return (
    <Suspense fallback={null}>
      <FeeRatesPage />
    </Suspense>
  );
}
