import { Suspense } from "react";
import { cookies } from "next/headers";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import CommunityList from "@/components/CommunityList";
import ListSkeleton from "@/components/ListSkeleton";
import SiteHeader from "@/components/SiteHeader";

export const metadata: Metadata = { title: "커뮤니티" };

type SP = Promise<Record<string, string | string[] | undefined>>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) || "";

/** community.php?tab=&category=&region=&q=&page= — header with the search bar (search/region apply to the community). */
async function ListWithData({ searchParams }: { searchParams: SP }) {
  const sp = await searchParams;
  const tabRaw = one(sp.tab);
  const tab = tabRaw === "popular" || tabRaw === "review" ? tabRaw : "latest";
  const category = tab === "review" ? "" : one(sp.category).trim().slice(0, 100);
  const region = one(sp.region).trim().slice(0, 500);
  const q = one(sp.q).trim().slice(0, 100);
  const pageNum = Number(one(sp.page));
  const page = Number.isInteger(pageNum) && pageNum >= 1 && pageNum <= 100000 ? pageNum : 1;
  // Blocked authors and the member's own likes depend on the session: forward the cookie for the first paint.
  const cookie = (await cookies()).toString();
  let initial;
  try {
    initial = await api.community({ tab, category, region, q, page }, { cookie });
  } catch {
    initial = undefined; // the client component retries and shows an error state
  }
  return (
    <>
      <SiteHeader target="community" q={q} />
      <CommunityList params={{ tab, category, region, q, page }} initial={initial} />
    </>
  );
}

export default function CommunityPage({ searchParams }: { searchParams: SP }) {
  return (
    <Suspense fallback={<ListSkeleton />}>
      <ListWithData searchParams={searchParams} />
    </Suspense>
  );
}
