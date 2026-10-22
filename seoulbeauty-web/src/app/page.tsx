import { redirect } from "next/navigation";

/** Pilot: the Next.js app starts at the hospital list. */
export default function Home() {
  redirect("/hospitals");
}
