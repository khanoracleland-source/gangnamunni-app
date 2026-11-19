import type { Metadata } from "next";
import { Suspense } from "react";
import HospitalApplyPage from "@/components/hospital-apply/HospitalApplyPage";

export const metadata: Metadata = { title: "병원 등록" };

// Rendered per request so the response is never stored by a shared cache; the HTML is only a frame
// and everything on the page is read in the browser with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  return (
    <Suspense fallback={null}>
      <HospitalApplyPage />
    </Suspense>
  );
}
