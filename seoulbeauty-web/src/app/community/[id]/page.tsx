import { Suspense } from "react";
import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { api, ApiError } from "@/lib/api";
import CommunityDetail from "@/components/CommunityDetail";
import DetailSkeleton from "@/components/DetailSkeleton";

export const metadata: Metadata = { title: "커뮤니티" };

type P = Promise<{ id: string }>;

function parseId(raw: string): number | null {
  const n = Number(raw);
  return Number.isInteger(n) && n > 0 && n <= 2147483647 ? n : null;
}

async function DetailWithData({ params }: { params: P }) {
  const id = parseId((await params).id);
  if (!id) notFound();
  // Blocked users' posts are hidden per viewer, so the session cookie goes along; this render counts one view.
  const cookie = (await cookies()).toString();
  let initial;
  try {
    initial = await api.communityPost(id, { view: true }, { cookie });
  } catch (e) {
    if (e instanceof ApiError && (e.status === 404 || e.status === 400)) notFound();
    initial = undefined;
  }
  return (
    // detail_community.php hides the global nav and the search bar: just the page header inside the component.
    <CommunityDetail id={id} initial={initial} />
  );
}

export default function CommunityPostPage({ params }: { params: P }) {
  return (
    <Suspense fallback={<DetailSkeleton />}>
      <DetailWithData params={params} />
    </Suspense>
  );
}
