import type { Metadata } from "next";
import Qna from "@/components/Qna";

export const metadata: Metadata = { title: "Q&A" };

export default function Page() { return <Qna />; }
