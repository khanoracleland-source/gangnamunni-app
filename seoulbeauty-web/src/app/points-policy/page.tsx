import { Suspense } from "react";
import type { Metadata } from "next";
import { PointsPolicyRoute } from "@/components/Routes";

export const metadata: Metadata = { title: "포인트 정책" };

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={null}><PointsPolicyRoute /></Suspense>;
}
