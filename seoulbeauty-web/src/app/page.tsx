import type { Metadata } from "next";
import Home from "@/components/Home";

export const metadata: Metadata = {
  title: { absolute: "서울미녀" },
  description: "성형외과, 피부과, 미용 클리닉을 한눈에 비교하고 간편하게 예약하세요. 실제 시술 후기부터 병원 상세 정보까지 서울미녀 하나로 해결!",
};

/** index.php — the app's start page. Static shell; the data (public) loads in the browser from the query cache / Nest JSON. */
export default function HomePage() {
  return <Home />;
}
