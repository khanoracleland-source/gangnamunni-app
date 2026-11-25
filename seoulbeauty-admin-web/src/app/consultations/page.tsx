import type { Metadata } from "next";
import { Suspense } from "react";
import ConsultationsPage from "@/components/consultations/ConsultationsPage";

export const metadata: Metadata = { title: "상담 관리" };

// Rendered per request; the page reads nothing from the server except who is signed in.
export const dynamic = "force-dynamic";

export default function Page() {
  return (
    <Suspense fallback={null}>
      <ConsultationsPage />
    </Suspense>
  );
}
