import { Suspense } from "react";
import type { Metadata } from "next";
import { ReviewWriteRoute } from "@/components/Routes";

export const metadata: Metadata = { title: "시술후기 작성" };

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={null}><ReviewWriteRoute /></Suspense>;
}
