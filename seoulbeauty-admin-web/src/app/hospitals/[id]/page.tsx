import type { Metadata } from "next";
import HospitalDetail from "@/components/hospitals/HospitalDetail";

export const metadata: Metadata = { title: "병원 상세" };

// Rendered per request so the response is never stored by a shared cache (a statically prerendered
// page is sent with s-maxage=1 year, and after a redeploy it would point at chunks that are gone).
// The HTML itself is only a frame with no data: the hospital is fetched in the browser
// with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <HospitalDetail id={id} />;
}
