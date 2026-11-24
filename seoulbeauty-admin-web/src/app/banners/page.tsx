import type { Metadata } from "next";
import { Suspense } from "react";
import BannersPage from "@/components/banners/BannersPage";

export const metadata: Metadata = { title: "배너 관리" };

// Rendered per request so the response is never stored by a shared cache. The HTML is only a frame:
// the list is fetched in the browser with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  // The page reads its state (view, search, page) from the address.
  return (
    <Suspense fallback={null}>
      <BannersPage />
    </Suspense>
  );
}
