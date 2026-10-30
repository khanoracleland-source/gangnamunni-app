import { Suspense } from "react";
import type { Metadata } from "next";
import { BookingDetailRoute } from "@/components/MineIdRoutes";

export const metadata: Metadata = { title: "예약 상세" };

/** Member-only. Static shell: the id is read from the URL in the browser. */
export default function Page() {
  return <Suspense fallback={null}><BookingDetailRoute /></Suspense>;
}
