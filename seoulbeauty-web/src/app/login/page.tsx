import { Suspense } from "react";
import type { Metadata } from "next";
import Login from "@/components/Login";

export const metadata: Metadata = { title: "로그인" };

export default function Page() {
  return <Suspense><Login /></Suspense>;
}
