import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingScreen } from "@/components/StateScreens";
import NotificationsPage from "@/components/notifications/NotificationsPage";

export const metadata: Metadata = { title: "회원 푸시 발송" };

// Rendered per request so the response is never stored by a shared cache; the page is a frame with no data,
// everything is fetched in the browser with the admin's own session cookie. The tab is read from the address.
export const dynamic = "force-dynamic";

export default function Page() {
  return (
    <Suspense fallback={<LoadingScreen text="…" />}>
      <NotificationsPage />
    </Suspense>
  );
}
