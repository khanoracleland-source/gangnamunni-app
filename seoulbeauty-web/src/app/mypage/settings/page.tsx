import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import { loadMine } from "@/lib/mine";
import MySettings from "@/components/MySettings";

export const metadata: Metadata = { title: "알림 설정" };

async function Body() {
  const { data, anon } = await loadMine((ctx) => api.notificationSettings(ctx));
  return <MySettings initial={data} anon={anon} />;
}

export default function Page() {
  return <Suspense fallback={null}><Body /></Suspense>;
}
