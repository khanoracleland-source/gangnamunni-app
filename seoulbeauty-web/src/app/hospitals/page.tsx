import { Suspense } from "react";
import type { Metadata } from "next";
import HospitalsPage from "@/components/HospitalsPage";
import ListSkeleton from "@/components/ListSkeleton";

export const metadata: Metadata = { title: "병원" };

/** Static shell: no server-side data. The browser reads the query cache and fetches JSON from /next/api (Nest). */
export default function Page() {
  return (
    <Suspense fallback={<ListSkeleton />}>
      <HospitalsPage />
    </Suspense>
  );
}
