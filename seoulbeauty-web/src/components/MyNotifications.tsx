"use client";
import Loading from "./Loading";
import { useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useInfiniteQuery, useQueryClient, type InfiniteData } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type MyNotification, type MyNotificationsPage, type NotiFilter, type NotiIcon } from "@/lib/api";
import { meKey } from "@/lib/me";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import styles from "./MyPages.module.css";

const TABS: { key: NotiFilter; label: string }[] = [
  { key: "all", label: "전체" }, { key: "booking", label: "예약 · 결제" }, { key: "review", label: "커뮤니티" }, { key: "benefit", label: "혜택" },
];
const ICON_FILE: Record<NotiIcon, string> = { calendar: "booking", payment: "payment", clock: "clock", gift: "gift", review: "review", bell: "bell" };
type Cache = InfiniteData<MyNotificationsPage, number>;

/** my_notifications.php: filter tabs, 더 보기 paging, read on open, 모두 읽음 and delete. Only booking notifications open something (내 예약). */
export default function MyNotifications({ initial, initialType, anon }: { initial?: MyNotificationsPage; initialType: NotiFilter; anon?: boolean }) {
  const router = useRouter();
  const qc = useQueryClient();
  const raw = useSearchParams().get("type");
  const type: NotiFilter = raw === "booking" || raw === "review" || raw === "benefit" ? raw : "all";
  const key = qk.myNotifications(type);
  const q = useInfiniteQuery({
    queryKey: key, queryFn: ({ pageParam }) => api.myNotifications(type, pageParam), initialPageParam: 1,
    getNextPageParam: (last) => (last.hasMore ? last.page + 1 : undefined),
    initialData: initial && initialType === type ? { pages: [initial], pageParams: [1] } : undefined,
    // cache first: a filter that was opened (or prefetched) shows at once and is asked again only when 60 s old
    staleTime: 60_000, refetchOnMount: true, refetchOnWindowFocus: true,
    retry: false, enabled: !anon,
  });
  const loggedOut = anon || (q.error instanceof ApiError && q.error.status === 401);
  const [toast, setToast] = useState("");
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [busy, setBusy] = useState(false);
  const say = (m: string) => { setToast(m); if (timer.current) clearTimeout(timer.current); timer.current = setTimeout(() => setToast(""), 2500); };

  const pages = q.data?.pages ?? [];
  const items = pages.flatMap((p) => p.items);
  const unread = pages[0]?.unread ?? 0;

  // After the visible list is in, quietly load the other filters' first page: a chip tap is then served from the cache.
  const loaded = !!q.data && !q.isFetching;
  useEffect(() => {
    if (!loaded || anon || loggedOut) return;
    for (const t of TABS) {
      if (t.key === type) continue;
      void qc.prefetchInfiniteQuery({
        queryKey: qk.myNotifications(t.key), initialPageParam: 1, staleTime: 60_000,
        queryFn: ({ pageParam }) => api.myNotifications(t.key, pageParam as number),
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loaded, type]);

  // The black pill slides under the active chip (measured), instead of jumping.
  const chipsRef = useRef<HTMLDivElement>(null);
  const [pill, setPill] = useState<{ left: number; width: number } | null>(null);
  const measure = useCallback(() => {
    const el = chipsRef.current?.querySelector<HTMLElement>('[aria-selected="true"]');
    if (el) setPill((o) => (o && o.left === el.offsetLeft && o.width === el.offsetWidth ? o : { left: el.offsetLeft, width: el.offsetWidth }));
  }, []);
  useLayoutEffect(() => { measure(); }, [measure, type, loggedOut]);
  useEffect(() => { window.addEventListener("resize", measure); return () => window.removeEventListener("resize", measure); }, [measure]);

  /** Every filter's cached list shows the same rows: update them all, then refresh the header dot. */
  const patchAll = (fn: (p: MyNotificationsPage) => MyNotificationsPage) => {
    qc.setQueriesData<Cache>({ queryKey: ["mypage", "notifications"] }, (old) => (old ? { ...old, pages: old.pages.map(fn) } : old));
    qc.invalidateQueries({ queryKey: meKey });
  };

  const open = (n: MyNotification) => {
    if (!n.isRead) {
      patchAll((p) => ({ ...p, unread: Math.max(0, p.unread - 1), items: p.items.map((i) => (i.id === n.id ? { ...i, isRead: true } : i)) }));
      api.notificationRead(n.id).catch(() => {});
    }
    if (n.openUrl) router.push(n.openUrl);
  };
  const readAll = async () => {
    if (busy || unread === 0) return;
    setBusy(true);
    try {
      await api.notificationsReadAll();
      patchAll((p) => ({ ...p, unread: 0, items: p.items.map((i) => ({ ...i, isRead: true })) }));
      say("모든 알림을 읽음 처리했습니다.");
    } catch { say("처리 중 오류가 발생했습니다."); } finally { setBusy(false); }
  };
  const remove = async (n: MyNotification) => {
    try {
      const r = await api.notificationDelete(n.id);
      if (!r.success) { say("삭제할 수 없습니다."); return; }
      patchAll((p) => ({ ...p, total: Math.max(0, p.total - 1), unread: n.isRead ? p.unread : Math.max(0, p.unread - 1), items: p.items.filter((i) => i.id !== n.id) }));
      say("알림을 삭제했습니다.");
    } catch { say("네트워크 오류가 발생했습니다."); }
  };

  return (
    <div className={`${styles.wrap} ${styles.ntWrap}`}>
      <MyHeader title="알림" backToCaller right={!loggedOut && (
        <button type="button" className={styles.ntReadAll} onClick={readAll} disabled={busy || unread === 0}>모두 읽음</button>
      )} />
      {loggedOut ? <LoginPrompt text="로그인하면 알림을 확인할 수 있습니다." /> : (
        <>
          <div className={styles.ntChips} role="tablist" aria-label="알림 필터" ref={chipsRef} data-ready={pill ? "1" : undefined}>
            {pill && <span className={styles.ntPill} style={{ transform: `translateX(${pill.left}px)`, width: pill.width }} aria-hidden="true" />}
            {TABS.map((t) => (
              <Link key={t.key} href={t.key === "all" ? "/mypage/notifications" : `/mypage/notifications?type=${t.key}`} replace scroll={false}
                onClick={(e) => { // only the query string changes: swap it in place (no route request to the server), the cached list shows at once
                  if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                  e.preventDefault();
                  window.history.replaceState(null, "", t.key === "all" ? window.location.pathname : `?type=${t.key}`);
                }} role="tab" aria-selected={type === t.key} className={`${styles.ntChip} ${type === t.key ? styles.ntChipOn : ""}`}>{t.label}</Link>
            ))}
          </div>
          {!q.data ? (
            <div className={styles.empty}>{q.error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => q.refetch()}>다시 시도</button></> : <Loading />}</div>
          ) : items.length === 0 ? (
            <div className={styles.ntEmpty}>
              <div className={styles.ntEmptyIco}>
                <svg width="34" height="34" viewBox="0 0 1000 1000" fill="none" stroke="#AC1830" strokeWidth="40" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M207 530 V370 A293 293 0 0 1 793 370 V530 Q870 630 870 760 V780 H130 V760 Q130 630 207 530 Z" /><path d="M382 780 A118 118 0 0 0 618 780" /></svg>
              </div>
              <div className={styles.ntEmptyTitle}>새로운 알림이 없어요</div>
              <div className={styles.ntEmptySub}>예약 · 결제 진행 상황을<br />이곳에서 알려드릴게요</div>
            </div>
          ) : (
            <div className={styles.ntList}>
              {items.map((n) => (
                <article key={n.id} className={styles.ntCard}>
                  <div
                    className={`${styles.ntOpen} ${n.openUrl ? styles.ntClickable : ""}`}
                    {...(n.openUrl ? { role: "button", tabIndex: 0, "aria-label": "알림 보기", onClick: () => open(n), onKeyDown: (e: React.KeyboardEvent) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(n); } } } : {})}
                  >
                    <span className={styles.ntIcoWrap}>
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={assetUrl(`assets/icons/notifications/${ICON_FILE[n.icon]}.svg`) ?? ""} alt="" width={20} height={20} loading="lazy" />
                    </span>
                    <div className={styles.ntBody}>
                      <div className={styles.ntMeta}><span>{n.message ? n.title : ""}</span>{!n.isRead && <span className={styles.ntDot} aria-label="읽지 않음" />}<span className={styles.ntTime}>{n.time}</span></div>
                      <div className={styles.ntText}>{n.message || n.title}</div>
                    </div>
                  </div>
                  <button type="button" className={styles.ntDel} onClick={() => remove(n)} aria-label="알림 삭제">×</button>
                </article>
              ))}
              {q.hasNextPage && <button type="button" className={styles.ntMore} onClick={() => q.fetchNextPage()} disabled={q.isFetchingNextPage}>{q.isFetchingNextPage ? "불러오는 중..." : "더 보기"}</button>}
              {!q.hasNextPage && <div className={styles.ntFoot}>최근 30일 동안의 알림만 보여드려요</div>}
                          </div>
          )}
        </>
      )}
      {toast && <div className={styles.ntToast} role="status" aria-live="polite">{toast}</div>}
    </div>
  );
}
