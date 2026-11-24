import type { Metadata } from "next";
import { Suspense } from "react";
import ModerationPage from "@/components/moderation/ModerationPage";

export const metadata: Metadata = { title: "콘텐츠 심사" };

// Rendered per request so the response is never stored by a shared cache. The HTML is only a frame:
// the list is fetched in the browser with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  // The page reads its state (tab, status, search, hospital, page) from the address.
  return (
    <Suspense fallback={null}>
      <ModerationPage />
    </Suspense>
  );
}
