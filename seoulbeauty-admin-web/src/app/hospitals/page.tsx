import type { Metadata } from "next";
import { Suspense } from "react";
import HospitalList from "@/components/hospitals/HospitalList";

export const metadata: Metadata = { title: "병원 관리" };

// Rendered per request so the response is never stored by a shared cache (a statically prerendered
// page is sent with s-maxage=1 year, and after a redeploy it would point at chunks that are gone).
// The HTML itself is only a frame with no data: the list is fetched in the browser
// with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  // The list reads its state (search, filters, page) from the address.
  return (
    <Suspense fallback={null}>
      <HospitalList />
    </Suspense>
  );
}
