import { Suspense } from "react";
import type { Metadata } from "next";
import { ConsultationDetailRoute } from "@/components/MineIdRoutes";

export const metadata: Metadata = { title: "상담 상세" };

/** Member-only. Static shell: the id is read from the URL in the browser. */
export default function Page() {
  return <Suspense fallback={null}><ConsultationDetailRoute /></Suspense>;
}
