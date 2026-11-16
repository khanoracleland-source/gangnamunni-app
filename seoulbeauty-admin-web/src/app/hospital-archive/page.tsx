import type { Metadata } from "next";
import { Suspense } from "react";
import HospitalArchiveList from "@/components/archive/HospitalArchiveList";

export const metadata: Metadata = { title: "삭제 병원 보관함" };

// Rendered per request so the response is never stored by a shared cache; the data is fetched in the browser
// with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  // The list reads its page and page size from the address.
  return (
    <Suspense fallback={null}>
      <HospitalArchiveList />
    </Suspense>
  );
}
