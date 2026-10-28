import { Suspense } from "react";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import ProgressDetail from "@/components/ProgressDetail";

export const metadata: Metadata = { title: "경과 후기" };
type Params = Promise<{ id: string }>;

/** progress_review.php?id=N */
async function Body({ params }: { params: Params }) {
  const n = Number((await params).id);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) notFound();
  const { data, anon } = await loadMine((ctx) => api.progressDetail(n, ctx));
  return <ProgressDetail id={n} initial={data} anon={anon} />;
}

export default function Page({ params }: { params: Params }) {
  return <Suspense fallback={null}><Body params={params} /></Suspense>;
}
