import type { Metadata } from "next";
import PasswordReset from "@/components/PasswordReset";

export const metadata: Metadata = { title: "비밀번호 재설정" };

export default function Page() {
  return <PasswordReset />;
}
