import type { Metadata } from "next";
import { Suspense } from "react";
import TreatmentUnitsPage from "@/components/treatment-units/TreatmentUnitsPage";

export const metadata: Metadata = { title: "단위 관리" };

// Rendered per request so the response is never stored by a shared cache; the list is fetched in the browser
// with the admin's own session cookie. The page reads its search text from the address.
export const dynamic = "force-dynamic";

export default function Page() {
  return (
    <Suspense fallback={null}>
      <TreatmentUnitsPage />
    </Suspense>
  );
}
