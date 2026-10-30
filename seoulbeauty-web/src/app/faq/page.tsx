import { Suspense } from "react";
import type { Metadata } from "next";
import { FaqRoute } from "@/components/Routes";

export const metadata: Metadata = { title: "자주 묻는 질문" };

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={null}><FaqRoute /></Suspense>;
}
