import { Suspense } from "react";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import ConsultationDetail from "@/components/ConsultationDetail";

export const metadata: Metadata = { title: "상담 상세" };

type P = Promise<{ id: string }>;

async function Body({ params }: { params: P }) {
  const n = Number((await params).id);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) notFound();
  const { data, anon } = await loadMine((ctx) => api.consultation(n, ctx));
  return <ConsultationDetail id={n} initial={data} anon={anon} />;
}

export default function Page({ params }: { params: P }) {
  return <Suspense fallback={null}><Body params={params} /></Suspense>;
}
