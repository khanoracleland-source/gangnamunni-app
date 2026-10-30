"use client";
import { notFound, useParams } from "next/navigation";
import BookingDetail from "./BookingDetail";
import ConsultationDetail from "./ConsultationDetail";
import ProgressDetail from "./ProgressDetail";

/** The id comes from the URL in the browser, so the page itself stays a static shell. */
function useId(): number {
  const n = Number(useParams<{ id: string }>().id);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) notFound();
  return n;
}
export const BookingDetailRoute = () => <BookingDetail id={useId()} />;
export const ConsultationDetailRoute = () => <ConsultationDetail id={useId()} />;
export const ProgressDetailRoute = () => <ProgressDetail id={useId()} />;
