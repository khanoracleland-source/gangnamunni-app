"use client";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, qk, type WishlistState } from "@/lib/api";
import { useRequireLogin } from "@/lib/me";
import styles from "./WishlistButton.module.css";

/** ★ toggle like includes/wishlist_button.php (icon 'star'). Anonymous tap → PHP login, then back here. */
/** `initial` (from the list row) seeds the cache so a 20-card list makes no extra requests. */
export default function WishlistButton({ hospitalId, size = "sm", initialCount, initial }: { hospitalId: number; size?: "sm" | "lg"; initialCount?: number; initial?: WishlistState }) {
  const qc = useQueryClient();
  const requireLogin = useRequireLogin();
  const { data } = useQuery({ queryKey: qk.wishlist(hospitalId), queryFn: () => api.wishlist(hospitalId), staleTime: 30_000, initialData: initial });
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
