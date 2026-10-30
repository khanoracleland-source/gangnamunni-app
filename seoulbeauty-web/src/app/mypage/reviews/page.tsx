import { Suspense } from "react";
import type { Metadata } from "next";
import MyReviews from "@/components/MyReviews";

export const metadata: Metadata = { title: "내가 작성한 후기" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><MyReviews /></Suspense>;
}
