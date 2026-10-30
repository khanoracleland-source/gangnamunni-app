import type { Metadata } from "next";
import Mypage from "@/components/Mypage";

export const metadata: Metadata = { title: "마이페이지" };

/** Member-only: static shell; the browser sends the session cookie to the API (401 → the login prompt). */
export default function Page() {
  return <Mypage />;
}
