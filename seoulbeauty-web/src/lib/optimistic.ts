import type { QueryClient, QueryKey } from "@tanstack/react-query";
import { qk, type CommunityList, type CommunityPost, type ReviewDetail } from "./api";

/**
 * "Cache first, server second": a tap (like, 찜, read, delete…) is written to the cache at once, so the screen answers immediately,
 * and only then sent to the server. The server's answer replaces the guess; an error puts the previous value back.
 * Before writing, the caller cancels the fetches of the same keys (cancelQueries) so a refresh that was already on its way
 * cannot overwrite the new value with the old one.
 */

/** Every cached entry under `filter`, to put back if the server refuses. */
export const snapshot = (qc: QueryClient, filter: QueryKey): [QueryKey, unknown][] => qc.getQueriesData({ queryKey: filter });
export function restore(qc: QueryClient, snap: [QueryKey, unknown][]): void {
  for (const [key, data] of snap) qc.setQueryData(key, data);
}

/** One like change is written to EVERY cached copy of that post / review: all community lists (also MY 커뮤니티) and its detail screen. */
export function syncLike(qc: QueryClient, kind: "post" | "review", id: number, liked: boolean, count: number): void {
  qc.setQueriesData<CommunityList>({ queryKey: ["community"] }, (p) =>
    p && Array.isArray(p.items) ? { ...p, items: p.items.map((i) => (i.kind === kind && i.id === id ? { ...i, liked, likeCount: count } : i)) } : p);
  if (kind === "post") qc.setQueryData<CommunityPost>(qk.communityPost(id), (p) => p && { ...p, liked, likeCount: count });
  else qc.setQueryData<ReviewDetail>(qk.reviewDetail(id), (p) => p && { ...p, liked, likeCount: count });
}
/** The count a toggle leads to, before the server says so. */
export const toggled = (liked: boolean, count: number) => ({ liked: !liked, count: Math.max(0, count + (liked ? -1 : 1)) });

/** Now in the API's timestamp format (KST "YYYY-MM-DD HH:MM:SS"), for a row shown before the server has stored it. */
export const kstNow = () => new Date(Date.now() + 9 * 3600_000).toISOString().slice(0, 19).replace("T", " ");
