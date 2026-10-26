import { Suspense } from "react";
import { cookies } from "next/headers";
import type { Metadata } from "next";
import { api, normalize, type HospitalListParams, type HospitalSort } from "@/lib/api";
import HospitalList from "@/components/HospitalList";
import ListSkeleton from "@/components/ListSkeleton";
import SearchBar from "@/components/SearchBar";

export const metadata: Metadata = { title: "병원" };

type SP = Promise<Record<string, string | string[] | undefined>>;

const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) || "";

function toParams(sp: Record<string, string | string[] | undefined>): HospitalListParams {
  return { q: one(sp.q), category: one(sp.category), treatment: one(sp.treatment), region: one(sp.region), sort: one(sp.sort) as HospitalSort };
}

/** Reads searchParams (runtime API) and fetches the first page on the server for a full first paint. */
async function ListWithData({ searchParams }: { searchParams: SP }) {
  const params = toParams(await searchParams);
  // Forward the session cookie so the first paint already knows the member's wishlist marks.
  const cookie = (await cookies()).toString();
  let initial;
  try {
    initial = await api.hospitals(normalize(params), { cookie });
  } catch {
    initial = undefined; // the client component will retry and show an error state
  }
  return (
    <>
      <SearchBar initial={params.q || ""} />
      <HospitalList params={params} initial={initial} />
    </>
  );
}

export default function HospitalsPage({ searchParams }: { searchParams: SP }) {
  return (
    <Suspense fallback={<ListSkeleton />}>
      <ListWithData searchParams={searchParams} />
    </Suspense>
  );
}
