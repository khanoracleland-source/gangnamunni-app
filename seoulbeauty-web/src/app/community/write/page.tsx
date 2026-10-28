import { Suspense } from "react";
import type { Metadata } from "next";
import CommunityWrite from "@/components/CommunityWrite";

export const metadata: Metadata = { title: "글쓰기" };

type SP = Promise<Record<string, string | string[] | undefined>>;

/** community_write.php[?id=N] — the form loads client-side (login required, so there is nothing to render for anonymous SSR). */
async function Body({ searchParams }: { searchParams: SP }) {
  const raw = (await searchParams).id;
  const n = Number(Array.isArray(raw) ? raw[0] : raw);
  return <CommunityWrite id={Number.isInteger(n) && n >= 1 && n <= 2147483647 ? n : null} />;
}

export default function WritePage({ searchParams }: { searchParams: SP }) {
  return <Suspense fallback={null}><Body searchParams={searchParams} /></Suspense>;
}
