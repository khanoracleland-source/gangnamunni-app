import { Suspense } from "react";
import type { Metadata } from "next";
import ReviewWrite from "@/components/ReviewWrite";

export const metadata: Metadata = { title: "시술후기 작성" };

type SP = Promise<Record<string, string | string[] | undefined>>;
const num = (v: string | string[] | undefined) => {
  const n = Number(Array.isArray(v) ? v[0] : v);
  return Number.isInteger(n) && n >= 1 && n <= 2147483647 ? n : 0;
};

/** review_write.php[?booking_id=&hospital_id=&id=] — login required, so the form loads client-side. */
async function Body({ searchParams }: { searchParams: SP }) {
  const sp = await searchParams;
  return <ReviewWrite bookingId={num(sp.booking_id)} hospitalId={num(sp.hospital_id)} editId={num(sp.id)} />;
}

export default function Page({ searchParams }: { searchParams: SP }) {
  return <Suspense fallback={null}><Body searchParams={searchParams} /></Suspense>;
}
