import type { Metadata } from "next";
import RegisterProfile from "@/components/RegisterProfile";

export const metadata: Metadata = { title: "회원 정보 입력", robots: { index: false } };

/** Member-only, static shell: the browser sends the session cookie to the API (401 → the login page). */
export default function Page() {
  return <RegisterProfile />;
}
