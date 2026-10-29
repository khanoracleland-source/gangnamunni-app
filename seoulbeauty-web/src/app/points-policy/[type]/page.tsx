import { Suspense } from "react";
import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { api } from "@/lib/api";
import { POINTS_POLICY } from "@/content/legal";
import PointsPolicyDetail from "@/components/PointsPolicyDetail";

export const metadata: Metadata = { title: "포인트 정책" };

/** my_points_policy_detail.php: while the points menu is hidden the PHP page sends everybody to 마이페이지; so does this one. */
async function Body({ params }: { params: Promise<{ type: string }> }) {
  const { type } = await params;
  const policy = Object.hasOwn(POINTS_POLICY, type) ? POINTS_POLICY[type] : null;
  if (!policy) notFound();
  const state = await api.pointsPolicy().catch(() => ({ visible: false }));
  if (!state.visible) redirect("/mypage");
  return <PointsPolicyDetail policy={policy} />;
}

export default function Page({ params }: { params: Promise<{ type: string }> }) {
  return <Suspense fallback={null}><Body params={params} /></Suspense>;
}
