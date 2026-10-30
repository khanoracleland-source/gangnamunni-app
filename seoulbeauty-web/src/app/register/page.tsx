import type { Metadata } from "next";
import Register from "@/components/Register";

export const metadata: Metadata = { title: "회원가입" };

export default function Page() {
  return <Register />;
}
