import { Suspense } from "react";
import type { Metadata } from "next";
import { EventDetailRoute } from "@/components/Routes";
import DetailSkeleton from "@/components/DetailSkeleton";

export const metadata: Metadata = { title: "이벤트 상세" };

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={<DetailSkeleton />}><EventDetailRoute /></Suspense>;
}
