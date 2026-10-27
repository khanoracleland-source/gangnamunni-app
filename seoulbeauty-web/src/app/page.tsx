import { Suspense } from "react";
import type { Metadata } from "next";
import { api } from "@/lib/api";
import Home from "@/components/Home";
import ListSkeleton from "@/components/ListSkeleton";

export const metadata: Metadata = {
  title: { absolute: "서울미녀" },
  description: "성형외과, 피부과, 미용 클리닉을 한눈에 비교하고 간편하게 예약하세요. 실제 시술 후기부터 병원 상세 정보까지 서울미녀 하나로 해결!",
};

/** index.php — the app's start page. Data is public (no cookie needed), fetched server-side for a full first paint. */
async function HomeWithData() {
  let initial;
  try {
    initial = await api.home();
  } catch {
    initial = undefined;
  }
  return <Home initial={initial} />;
}

export default function HomePage() {
  return (
    <Suspense fallback={<ListSkeleton rows={4} />}>
      <HomeWithData />
    </Suspense>
  );
}
