import { Suspense } from "react";
import type { Metadata } from "next";
import MyNotifications from "@/components/MyNotifications";

export const metadata: Metadata = { title: "알림" };

/** Member-only. Static shell: the filter comes from ?type= in the browser, the list from the query cache / API. */
export default function Page() {
  return <Suspense fallback={null}><MyNotifications initialType="all" /></Suspense>;
}
