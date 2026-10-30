import { Suspense } from "react";
import type { Metadata } from "next";
import MyWishlist from "@/components/MyWishlist";

export const metadata: Metadata = { title: "찜한 병원" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><MyWishlist /></Suspense>;
}
