import type { Metadata } from "next";
import Dashboard from "@/components/Dashboard";

// The title template of the root layout only reaches nested routes, so this one is written out.
export const metadata: Metadata = { title: "대시보드 | 서울미녀 관리자" };

// Rendered per request so the response is never stored by a shared cache (a statically prerendered
// page is sent with s-maxage=1 year, and after a redeploy it would point at chunks that are gone).
// The HTML itself is only a frame with no data: every number is fetched in the browser
// with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  return <Dashboard />;
}
