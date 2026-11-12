"use client";
import Loading from "./Loading";
import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, PLACEHOLDER, qk, type MyWishlistItem } from "@/lib/api";
import { syncWishlist } from "./WishlistButton";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import styles from "./MyPages.module.css";

/** my_wishlist.php (hospitals): the member's starred hospitals; the star removes one. */
export default function MyWishlist({ initial, anon }: { initial?: { items: MyWishlistItem[] }; anon?: boolean }) {
  const qc = useQueryClient();
  const { data, error, refetch } = useQuery({ queryKey: qk.myWishlist, queryFn: () => api.myWishlist(), initialData: initial, retry: false, enabled: !anon });
  const remove = useMutation({
    mutationFn: (hospitalId: number) => api.toggleWishlist(hospitalId),
    // the row leaves the list at once, then the server is told; an error puts the list back
    onMutate: async (hospitalId) => {
      await qc.cancelQueries({ queryKey: qk.myWishlist });
      const prev = qc.getQueryData<{ items: MyWishlistItem[] }>(qk.myWishlist);
      qc.setQueryData<{ items: MyWishlistItem[] }>(qk.myWishlist, (w) => w && { items: w.items.filter((i) => i.hospitalId !== hospitalId) });
      return { prev };
    },
    onError: (_e, _v, ctx) => { if (ctx?.prev) qc.setQueryData(qk.myWishlist, ctx.prev); },
    onSuccess: (r, hospitalId) => {
      // toggle: removed when it comes back unwishlisted; if it was re-added by mistake, reload the list instead
      syncWishlist(qc, hospitalId, { wishlisted: r.wishlisted, count: r.count });
      if (r.wishlisted) void qc.invalidateQueries({ queryKey: qk.myWishlist });
    },
  });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  return (
    <div className={styles.wrap}>
      <MyHeader title="찜한 병원을 확인해보세요" />
      {loggedOut ? <LoginPrompt /> : !data ? (
        <div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : <Loading />}</div>
      ) : data.items.length === 0 ? (
        <div className={styles.empty}>찜한 병원이 없습니다<br />관심있는 병원을 찜해보세요<br /><Link href="/hospitals" className={styles.retry}>병원 둘러보기</Link></div>
      ) : (
        <div className={styles.grid}>
          {data.items.map((it) => {
            const img = assetUrl(it.imageUrl) ?? PLACEHOLDER;
            const body = (
              <>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={img} alt={it.name} className={styles.himg} onError={(e) => { e.currentTarget.onerror = null; e.currentTarget.src = PLACEHOLDER; }} />
                <div className={styles.hinfo}>
                  <div className={styles.hname}>{it.name}</div>
                  {it.available ? (
                    it.address && <div className={styles.haddr}><span className={styles.hdistrict}>{it.district}</span><span className={styles.hsep}>|</span><span>{it.address}</span></div>
                  ) : <div className={styles.hoff}>현재 이용할 수 없는 병원입니다</div>}
                </div>
              </>
            );
            return (
              <div key={it.id} className={`${styles.hcard} ${it.available ? "" : styles.hcardOff}`}>
                <button type="button" className={styles.star} aria-label="찜 해제" disabled={remove.isPending} onClick={() => remove.mutate(it.hospitalId)}>★</button>
                {it.available ? <Link href={`/hospitals/${it.hospitalId}`} className={styles.hlink}>{body}</Link> : <div className={styles.hlink}>{body}</div>}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
