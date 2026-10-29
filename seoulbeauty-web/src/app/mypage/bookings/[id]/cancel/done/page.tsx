import { Suspense } from "react";
import type { Metadata } from "next";
import BookingCancelDone from "@/components/BookingCancelDone";

export const metadata: Metadata = { title: "취소 완료" };

/** booking_cancel_complete.php */
export default function Page() {
  return <Suspense fallback={null}><BookingCancelDone /></Suspense>;
}
