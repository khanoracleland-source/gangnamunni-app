import type { Metadata } from "next";
import { Suspense } from "react";
import DoctorsPage from "@/components/doctors/DoctorsPage";

export const metadata: Metadata = { title: "의사 관리" };

// Rendered per request so the response is never stored by a shared cache. The HTML is only a frame:
// the list is fetched in the browser with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  // The list reads its state (hospital, search, status, page) from the address.
  return (
    <Suspense fallback={null}>
      <DoctorsPage />
    </Suspense>
  );
}
