import { Suspense } from "react";
import type { Metadata } from "next";
import MyCommunity from "@/components/MyCommunity";

export const metadata: Metadata = { title: "MY 커뮤니티 글" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><MyCommunity /></Suspense>;
}
