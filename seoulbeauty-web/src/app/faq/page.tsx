import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import Faq from "@/components/Faq";

export const metadata: Metadata = { title: "자주 묻는 질문" };

type SP = Promise<Record<string, string | string[] | undefined>>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) || "";

/** faq.php?cat= — public. */
async function Body({ searchParams }: { searchParams: SP }) {
  const cat = one((await searchParams).cat).trim().slice(0, 50);
  let initial;
  try { initial = await api.faq(); } catch { initial = undefined; }
  return <Faq initial={initial} cat={cat} />;
}

export default function Page({ searchParams }: { searchParams: SP }) {
  return <Suspense fallback={null}><Body searchParams={searchParams} /></Suspense>;
}
