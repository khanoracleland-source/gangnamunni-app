import { Suspense } from "react";
import { cookies } from "next/headers";
import type { Metadata } from "next";
import { api, normalize, type HospitalListParams, type HospitalSort } from "@/lib/api";
import HospitalList from "@/components/HospitalList";
import HospitalSearchView from "@/components/HospitalSearchView";
import ListSkeleton from "@/components/ListSkeleton";
import SiteHeader from "@/components/SiteHeader";

export const metadata: Metadata = { title: "병원" };

type SP = Promise<Record<string, string | string[] | undefined>>;

const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) || "";

function toParams(sp: Record<string, string | string[] | undefined>): HospitalListParams {
  return { q: one(sp.q), category: one(sp.category), treatment: one(sp.treatment), region: one(sp.region), dosage: one(sp.dosage), sort: one(sp.sort) as HospitalSort };
}

/** Reads searchParams (runtime API) and fetches the first page on the server for a full first paint. */
async function ListWithData({ searchParams }: { searchParams: SP }) {
  const sp = await searchParams;
  const params = toParams(sp);
  const q = params.q || "";
  // hospitals.php?q=...: results mode header (no logo row). hid= switches to the hospital search view (03-12).
  const hid = Number(one(sp.hid));
  if (Number.isInteger(hid) && hid > 0) {
    let initial;
    try { initial = await api.searchView(hid); } catch { initial = undefined; }
    return (
      <>
        <SiteHeader key={`hid-${hid}-${q}`} mode="results" q={q} />
        <HospitalSearchView hid={hid} q={q} tcat={Number(one(sp.tcat)) || 0} sort={one(sp.sort)} initial={initial} />
      </>
    );
  }
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
      {/* keyed by the keyword so a new search remounts the header (overlay closed, keyword shown) */}
      <SiteHeader key={`list-${q}`} mode={q ? "results" : "nav"} q={q} />
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
