import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import EventList from "@/components/EventList";
import ListSkeleton from "@/components/ListSkeleton";
import SiteHeader from "@/components/SiteHeader";

export const metadata: Metadata = { title: "이벤트" };

type SP = Promise<Record<string, string | string[] | undefined>>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) || "";

/** events.php?category=&filter=raffle — header.php with `$show_search = false`. */
async function ListWithData({ searchParams }: { searchParams: SP }) {
  const sp = await searchParams;
  const category = one(sp.category).trim().slice(0, 100);
  const raffle = one(sp.filter) === "raffle";
  let initial;
  try {
    initial = await api.events({ category, filter: raffle ? "raffle" : "" });
  } catch {
    initial = undefined; // the client component retries and shows an error state
  }
  return (
    <>
      <SiteHeader showSearch={false} />
      <EventList category={category} raffle={raffle} initial={initial} />
    </>
  );
}

export default function EventsPage({ searchParams }: { searchParams: SP }) {
  return (
    <Suspense fallback={<ListSkeleton />}>
      <ListWithData searchParams={searchParams} />
    </Suspense>
  );
}
