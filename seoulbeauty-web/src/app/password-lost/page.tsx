import type { Metadata } from "next";
import PasswordLost from "@/components/PasswordLost";

export const metadata: Metadata = { title: "비밀번호 찾기" };

export default function Page() {
  return <PasswordLost />;
}
