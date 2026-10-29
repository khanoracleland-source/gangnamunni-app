import { Suspense } from "react";
import type { Metadata } from "next";
import Booking from "@/components/Booking";

export const metadata: Metadata = { title: "예약하기" };

type SP = Promise<Record<string, string | string[] | undefined>>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) ?? "";
const int = (v: string | string[] | undefined) => { const n = Number(one(v)); return Number.isInteger(n) && n > 0 && n < 2147483647 ? n : 0; };

/** booking.php?id=&treatment_id=&option_id=&treatment=&rebook=1&via=again&source_booking_id= — everything else comes from the API per member. */
async function Body({ searchParams }: { searchParams: SP }) {
  const sp = await searchParams;
  const id = int(sp.id);
  if (!id) return <div style={{ padding: 60, textAlign: "center", color: "#777" }}>병원 정보를 찾을 수 없습니다.</div>;
  return <Booking id={id} treatmentId={int(sp.treatment_id)} optionId={int(sp.option_id)} treatmentName={one(sp.treatment).trim().slice(0, 100)}
    rebook={one(sp.rebook) === "1"} via={one(sp.via)} sourceId={int(sp.source_booking_id)} />;
}

export default function Page({ searchParams }: { searchParams: SP }) {
  return <Suspense fallback={null}><Body searchParams={searchParams} /></Suspense>;
}
