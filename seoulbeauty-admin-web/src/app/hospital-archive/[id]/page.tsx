import type { Metadata } from "next";
import { Suspense } from "react";
import HospitalArchiveDetail from "@/components/archive/HospitalArchiveDetail";

export const metadata: Metadata = { title: "보관된 병원" };

// Rendered per request so the response is never stored by a shared cache; the data is fetched in the browser
// with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  // The page reads ?deleted=1 from the address.
  return (
    <Suspense fallback={null}>
      <HospitalArchiveDetail id={id} />
    </Suspense>
  );
}
