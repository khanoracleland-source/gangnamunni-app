import type { Metadata } from "next";
import AlertsPage from "@/components/alerts/AlertsPage";

export const metadata: Metadata = { title: "알림" };

// Rendered per request so the response is never stored by a shared cache; the page is a frame with no data,
// everything is fetched in the browser with the admin's own session cookie. Reached from the bell in the top bar, not from the menu.
export const dynamic = "force-dynamic";

export default function Page() {
  return <AlertsPage />;
}
