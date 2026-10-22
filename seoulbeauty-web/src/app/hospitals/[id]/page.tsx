import { Suspense } from "react";
import { notFound } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import HospitalDetail from "@/components/HospitalDetail";
import DetailSkeleton from "@/components/DetailSkeleton";

type P = Promise<{ id: string }>;

function parseId(raw: string): number | null {
  const n = Number(raw);
  return Number.isInteger(n) && n > 0 ? n : null;
}

async function DetailWithData({ params }: { params: P }) {
  const id = parseId((await params).id);
  if (!id) notFound();
  let initial;
  try {
    initial = await api.hospital(id);
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) notFound();
    initial = undefined;
  }
  return <HospitalDetail id={id} initial={initial} />;
}

export default function HospitalPage({ params }: { params: P }) {
  return (
    <Suspense fallback={<DetailSkeleton />}>
      <DetailWithData params={params} />
    </Suspense>
  );
}
