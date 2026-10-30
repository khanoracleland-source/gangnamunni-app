import { Suspense } from "react";
import type { Metadata } from "next";
import ProfileEdit from "@/components/ProfileEdit";

export const metadata: Metadata = { title: "내 정보 수정" };

/** Member-only. Static shell: the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Suspense fallback={null}><ProfileEdit /></Suspense>;
}
