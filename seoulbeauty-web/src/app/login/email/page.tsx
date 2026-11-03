import { Suspense } from "react";
import type { Metadata } from "next";
import EmailLogin from "@/components/EmailLogin";

export const metadata: Metadata = { title: "이메일 로그인" };

export default function Page() {
  return <Suspense><EmailLogin /></Suspense>;
}
