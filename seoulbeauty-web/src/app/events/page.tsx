import { Suspense } from "react";
import type { Metadata } from "next";
import { EventsRoute } from "@/components/Routes";
import ListSkeleton from "@/components/ListSkeleton";

export const metadata: Metadata = { title: "이벤트" };

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={<ListSkeleton />}><EventsRoute /></Suspense>;
}
