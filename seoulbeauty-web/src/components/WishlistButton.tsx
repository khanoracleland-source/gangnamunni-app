"use client";
import { useEffect, useRef } from "react";
import { useMutation, useQuery, useQueryClient, type QueryClient } from "@tanstack/react-query";
import { api, qk, type Hospital, type HospitalDetail, type HospitalPage, type MyWishlistItem, type SearchView, type WishlistState } from "@/lib/api";
import { useRequireLogin } from "@/lib/me";
import wishIcon from "@/assets/icons/wish.png";
import wishOnIcon from "@/assets/icons/wish-on.png";
import styles from "./WishlistButton.module.css";

/**
 * One star change is written to EVERY cached copy of that hospital (lists, detail, search view, my wishlist), so any screen
 * opened from the cache (before its own refetch answers) already shows the new state. The server stays the truth: the next open refetches.
 */
export function syncWishlist(qc: QueryClient, id: number, st: WishlistState) {
  qc.setQueryData<WishlistState>(qk.wishlist(id), st);
  const patch = <T extends Hospital>(h: T): T => (h.id === id ? { ...h, wishlisted: st.wishlisted, wishlistCount: st.count } : h);
  qc.setQueriesData<HospitalPage>({ queryKey: ["hospitals"] }, (p) => p && Array.isArray(p.items) ? { ...p, items: p.items.map(patch) } : p);
  qc.setQueryData<HospitalDetail>(qk.hospital(id), (h) => h && patch(h));
  qc.setQueryData<SearchView>(qk.searchView(id), (v) => v && { ...v, hospital: patch(v.hospital) });
  if (st.wishlisted) qc.removeQueries({ queryKey: qk.myWishlist, exact: true }); // a new row needs the server's data: no stale list in the meantime
  else qc.setQueryData<{ items: MyWishlistItem[] }>(qk.myWishlist, (w) => w && { items: w.items.filter((i) => i.hospitalId !== id) });
}

/** ★ toggle like includes/wishlist_button.php (icon 'star'). Anonymous tap → PHP login, then back here. */
/** `initial` (from the list row) seeds the cache so a 20-card list makes no extra requests. */
export default function WishlistButton({ hospitalId, size = "sm", initialCount, initial }: { hospitalId: number; size?: "sm" | "lg"; initialCount?: number; initial?: WishlistState }) {
  const qc = useQueryClient();
  const requireLogin = useRequireLogin();
  // A list row already carries the mark: no request per card (a 20-card list used to fire 20). Only the detail page (no `initial`) asks.
  const { data } = useQuery({ queryKey: qk.wishlist(hospitalId), queryFn: () => api.wishlist(hospitalId), initialData: initial, enabled: initial === undefined });
  const m = useMutation({
    mutationFn: () => api.toggleWishlist(hospitalId),
    onMutate: async () => {
      await qc.cancelQueries({ queryKey: qk.wishlist(hospitalId) });
      const prev = qc.getQueryData<WishlistState>(qk.wishlist(hospitalId));
      if (prev) syncWishlist(qc, hospitalId, { wishlisted: !prev.wishlisted, count: Math.max(0, prev.count + (prev.wishlisted ? -1 : 1)) });
      return { prev };
    },
    onError: (_e, _v, ctx) => { if (ctx?.prev) syncWishlist(qc, hospitalId, ctx.prev); },
    onSuccess: (r) => syncWishlist(qc, hospitalId, { wishlisted: r.wishlisted, count: r.count }),
  });
  // the list was refreshed (opened again, changed elsewhere): its row is the newer truth, unless a tap is in flight
  // isPending is read through a ref, NOT a dependency: otherwise the effect re-runs when the tap finishes and overwrites the fresh result with the stale row value
  const pending = useRef(false);
  useEffect(() => { pending.current = m.isPending; }, [m.isPending]);
  const seedW = initial?.wishlisted, seedC = initial?.count;
  useEffect(() => {
    if (seedW === undefined || seedC === undefined || pending.current) return;
    qc.setQueryData<WishlistState>(qk.wishlist(hospitalId), { wishlisted: seedW, count: seedC });
  }, [seedW, seedC, hospitalId, qc]);
  const on = data?.wishlisted ?? false;
  const count = data?.count ?? initialCount;
  return (
    <button
      type="button"
      className={`${styles.btn} ${styles[size]} ${on ? styles.on : ""}`}
      aria-pressed={on}
      aria-label={on ? "찜 해제" : "찜하기"}
      disabled={m.isPending}
      onClick={(e) => { e.preventDefault(); e.stopPropagation(); void requireLogin(() => m.mutateAsync()); }}
    >
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={(on ? wishOnIcon : wishIcon).src} alt="" aria-hidden width={size === "lg" ? 24 : 22} height={size === "lg" ? 24 : 22} />
      {size === "lg" && count != null && <span className={styles.count}>{count}</span>}
    </button>
  );
}
