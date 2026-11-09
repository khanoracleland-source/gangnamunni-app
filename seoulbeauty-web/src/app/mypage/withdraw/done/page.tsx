import type { Metadata } from "next";
import WithdrawDone from "@/components/WithdrawDone";

export const metadata: Metadata = { title: "회원 탈퇴 완료", robots: { index: false } };

/** Public on purpose: the session is already gone when the member lands here, so there is nothing to ask the API. */
export default function Page() {
  return <WithdrawDone />;
}
