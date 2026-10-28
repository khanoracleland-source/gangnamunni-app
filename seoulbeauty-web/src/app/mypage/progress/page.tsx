import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import ProgressList from "@/components/ProgressList";

export const metadata: Metadata = { title: "경과 후기" };

/** my_progress_reviews.php — member only; the feature itself is behind the points v2 switch. */
async function Body() {
  const { data, anon } = await loadMine((ctx) => api.myProgress(ctx));
  return <ProgressList initial={data} anon={anon} />;
}

export default function Page() {
  return <Suspense fallback={null}><Body /></Suspense>;
}
