import { Suspense } from "react";
import { notFound } from "next/navigation";
import { api, ApiError, toDetailTab } from "@/lib/api";
import HospitalDetail from "@/components/HospitalDetail";
import DetailSkeleton from "@/components/DetailSkeleton";

type P = Promise<{ id: string }>;
type SP = Promise<Record<string, string | string[] | undefined>>;

function parseId(raw: string): number | null {
  const n = Number(raw);
  return Number.isInteger(n) && n > 0 ? n : null;
}

async function DetailWithData({ params, searchParams }: { params: P; searchParams: SP }) {
  const id = parseId((await params).id);
  const sp = await searchParams;
  const tab = toDetailTab(Array.isArray(sp.tab) ? sp.tab[0] : sp.tab);
  if (!id) notFound();
  let initial;
  try {
    initial = await api.hospital(id);
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) notFound();
    initial = undefined;
  }
  return <HospitalDetail id={id} initial={initial} initialTab={tab} />;
}

export default function HospitalPage({ params, searchParams }: { params: P; searchParams: SP }) {
  return (
    <Suspense fallback={<DetailSkeleton />}>
      <DetailWithData params={params} searchParams={searchParams} />
    </Suspense>
  );
}
