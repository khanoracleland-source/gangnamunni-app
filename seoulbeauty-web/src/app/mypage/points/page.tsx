import { Suspense } from "react";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import MyPoints from "@/components/MyPoints";

export const metadata: Metadata = { title: "포인트" };

/** my_points.php: member-only; while the points menu is paused the PHP page sends everybody to 마이페이지 (so does this one). */
async function Body() {
  const { data, anon } = await loadMine((ctx) => api.myPoints(ctx));
  if (data && !data.visible) redirect("/mypage");
  return <MyPoints initial={data} anon={anon} />;
}

export default function Page() {
  return <Suspense fallback={null}><Body /></Suspense>;
}
