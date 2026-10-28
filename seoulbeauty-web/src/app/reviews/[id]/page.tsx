import { Suspense } from "react";
import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { api, ApiError } from "@/lib/api";
import ReviewDetail from "@/components/ReviewDetail";

export const metadata: Metadata = { title: "후기 상세" };

type P = Promise<{ id: string }>;

async function Body({ params }: { params: P }) {
  const n = Number((await params).id);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) notFound();
  // Blocked authors are hidden per viewer, so the session cookie goes along.
  const cookie = (await cookies()).toString();
  let initial;
  try {
    initial = await api.reviewDetail(n, { cookie });
  } catch (e) {
    if (e instanceof ApiError && (e.status === 404 || e.status === 400)) notFound();
    initial = undefined;
  }
  return <ReviewDetail id={n} initial={initial} />;
}

export default function Page({ params }: { params: P }) {
  return <Suspense fallback={null}><Body params={params} /></Suspense>;
}
