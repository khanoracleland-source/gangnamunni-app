import { Suspense } from "react";
import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { api, ApiError } from "@/lib/api";
import EventDetail from "@/components/EventDetail";
import DetailSkeleton from "@/components/DetailSkeleton";
import SiteHeader from "@/components/SiteHeader";

export const metadata: Metadata = { title: "이벤트 상세" };

type P = Promise<{ id: string }>;

function parseId(raw: string): number | null {
  const n = Number(raw);
  return Number.isInteger(n) && n > 0 && n <= 2147483647 ? n : null;
}

async function DetailWithData({ params }: { params: P }) {
  const id = parseId((await params).id);
  if (!id) notFound();
  // The detail carries the member's own 응모 state: forward the session cookie for the first paint.
  const cookie = (await cookies()).toString();
  let initial;
  try {
    initial = await api.event(id, { cookie });
  } catch (e) {
    if (e instanceof ApiError && (e.status === 404 || e.status === 400)) notFound();
    initial = undefined;
  }
  return (
    <>
      <SiteHeader showSearch={false} />
      <EventDetail id={id} initial={initial} />
    </>
  );
}

export default function EventPage({ params }: { params: P }) {
  return (
    <Suspense fallback={<DetailSkeleton />}>
      <DetailWithData params={params} />
    </Suspense>
  );
}
