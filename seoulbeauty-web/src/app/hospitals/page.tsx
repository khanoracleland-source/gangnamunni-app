import { Suspense } from "react";
import type { Metadata } from "next";
import HospitalsPage from "@/components/HospitalsPage";
import ListSkeleton from "@/components/ListSkeleton";

export const metadata: Metadata = { title: "병원" };

/**
 * Static shell: no server-side data. The browser reads the query cache and fetches JSON from /next/api (Nest).
 * The Suspense fallback is baked into the static HTML, so it cannot depend on the query string: it takes the rounded
 * card shape of the browse list (the common hard-load case: tab, deep link, reload). A hard-loaded keyword search
 * (?q=) therefore shows rounded placeholders for a moment before its divided rows; client-side navigation from the
 * search box never shows the fallback at all.
 */
export default function Page() {
  return (
    <Suspense fallback={<ListSkeleton rounded />}>
      <HospitalsPage />
    </Suspense>
  );
}
