import type { Metadata } from "next";
import { Suspense } from "react";
import BookingsPage from "@/components/bookings/BookingsPage";

export const metadata: Metadata = { title: "예약 관리" };

// Rendered per request; the draft screen reads nothing from the server except who is signed in.
export const dynamic = "force-dynamic";

export default function Page() {
  return (
    <Suspense fallback={null}>
      <BookingsPage />
    </Suspense>
  );
}
