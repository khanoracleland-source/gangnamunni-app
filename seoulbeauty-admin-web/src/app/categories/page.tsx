import type { Metadata } from "next";
import CategoryBoardPage from "@/components/categories/CategoryBoardPage";

export const metadata: Metadata = { title: "카테고리 관리" };

// Rendered per request so the response is never stored by a shared cache. The HTML is only a frame:
// the board is fetched in the browser with the admin's own session cookie.
export const dynamic = "force-dynamic";

export default function Page() {
  return <CategoryBoardPage />;
}
