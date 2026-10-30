import { Suspense } from "react";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { api } from "@/lib/api";
import PointsPolicy from "@/components/PointsPolicy";

export const metadata: Metadata = { title: "포인트 정책" };

/** my_points_policy.php?tab=: public; while the points menu is hidden everybody is sent to 마이페이지. */
async function Body({ searchParams }: { searchParams: Promise<{ tab?: string | string[] }> }) {
  const tab = (await searchParams).tab;
  const info = await api.pointsPolicyInfo().catch(() => ({ visible: false, expireDays: 0 }));
  if (!info.visible) redirect("/mypage");
  return <PointsPolicy expireDays={info.expireDays} initialTab={typeof tab === "string" ? tab : "earn"} />;
}

export default function Page({ searchParams }: { searchParams: Promise<{ tab?: string | string[] }> }) {
  return <Suspense fallback={null}><Body searchParams={searchParams} /></Suspense>;
}
