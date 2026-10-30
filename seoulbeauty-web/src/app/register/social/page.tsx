import type { Metadata } from "next";
import SocialRegister from "@/components/SocialRegister";

export const metadata: Metadata = { title: "회원가입" };

export default function Page() {
  return <SocialRegister />;
}
