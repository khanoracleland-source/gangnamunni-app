import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import RegisterTerms from "@/components/RegisterTerms";

export const metadata: Metadata = { title: "회원가입 약관 및 개인정보 정책" };

async function Body() {
  const initial = await api.registerTerms().catch(() => undefined);
  return <RegisterTerms initial={initial} />;
}

export default function Page() { return <Suspense fallback={null}><Body /></Suspense>; }
