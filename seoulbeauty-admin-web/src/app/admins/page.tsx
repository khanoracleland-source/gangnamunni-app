import type { Metadata } from "next";
import AdminList from "@/components/admins/AdminList";

export const metadata: Metadata = { title: "관리자 관리" };

// Rendered per request, like the other pages: the HTML is only a frame, the list is fetched in the
// browser with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  return <AdminList />;
}
