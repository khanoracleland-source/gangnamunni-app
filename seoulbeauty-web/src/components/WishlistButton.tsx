"use client";
import { useEffect, useRef } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, qk, type WishlistState } from "@/lib/api";
import { useRequireLogin } from "@/lib/me";
import styles from "./WishlistButton.module.css";

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
      if (prev) qc.setQueryData<WishlistState>(qk.wishlist(hospitalId), { wishlisted: !prev.wishlisted, count: prev.count + (prev.wishlisted ? -1 : 1) });
      return { prev };
    },
    onError: (_e, _v, ctx) => { if (ctx?.prev) qc.setQueryData(qk.wishlist(hospitalId), ctx.prev); },
    onSuccess: (r) => qc.setQueryData(qk.wishlist(hospitalId), { wishlisted: r.wishlisted, count: r.count }),
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
      <span aria-hidden>{on ? "★" : "☆"}</span>
      {size === "lg" && count != null && <span className={styles.count}>{count}</span>}
    </button>
  );
}
