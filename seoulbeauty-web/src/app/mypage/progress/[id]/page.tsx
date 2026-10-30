import { Suspense } from "react";
import type { Metadata } from "next";
import { ProgressDetailRoute } from "@/components/MineIdRoutes";

export const metadata: Metadata = { title: "경과 후기" };

/** Member-only. Static shell: the id is read from the URL in the browser. */
export default function Page() {
  return <Suspense fallback={null}><ProgressDetailRoute /></Suspense>;
}
