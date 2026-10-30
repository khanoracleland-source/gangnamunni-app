import { Suspense } from "react";
import type { Metadata } from "next";
import ProgressList from "@/components/ProgressList";

export const metadata: Metadata = { title: "경과 후기" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><ProgressList /></Suspense>;
}
