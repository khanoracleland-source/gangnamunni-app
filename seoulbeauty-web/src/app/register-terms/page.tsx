import { Suspense } from "react";
import type { Metadata } from "next";
import { RegisterTermsRoute } from "@/components/Routes";

export const metadata: Metadata = { title: "회원가입 약관 및 개인정보 정책" };

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={null}><RegisterTermsRoute /></Suspense>;
}
