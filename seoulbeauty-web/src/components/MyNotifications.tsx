"use client";
import { useRef, useState } from "react";
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
    staleTime: 0, retry: false, enabled: !anon,
  });
  const loggedOut = anon || (q.error instanceof ApiError && q.error.status === 401);
  const [toast, setToast] = useState("");
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [busy, setBusy] = useState(false);
  const say = (m: string) => { setToast(m); if (timer.current) clearTimeout(timer.current); timer.current = setTimeout(() => setToast(""), 2500); };

  const pages = q.data?.pages ?? [];
  const items = pages.flatMap((p) => p.items);
  const unread = pages[0]?.unread ?? 0;

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
    <div className={styles.wrap}>
      <MyHeader title="알림" />
      {loggedOut ? <LoginPrompt text="로그인하면 알림을 확인할 수 있습니다." /> : (
        <>
          <div className={styles.ntBar}>
            <div className={styles.bkTabs} role="tablist" aria-label="알림 필터" style={{ flex: 1, borderBottom: 0 }}>
              {TABS.map((t) => (
                <Link key={t.key} href={t.key === "all" ? "/mypage/notifications" : `/mypage/notifications?type=${t.key}`} replace role="tab" aria-selected={type === t.key} className={`${styles.bkTab} ${type === t.key ? styles.bkTabOn : ""}`} style={{ fontSize: 14 }}>{t.label}</Link>
              ))}
            </div>
            <button type="button" className={styles.ntReadAll} onClick={readAll} disabled={busy || unread === 0}>모두 읽음{unread > 0 && <span className={styles.bkCount} style={{ marginLeft: 4 }}>{unread > 99 ? "99+" : unread}</span>}</button>
          </div>
          {!q.data ? (
            <div className={styles.empty}>{q.error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => q.refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
          ) : items.length === 0 ? (
            <div className={styles.bkEmpty}>새로운 알림이 없어요<br />예약 · 결제 진행 상황을<br />이곳에서 알려드릴게요</div>
          ) : (
            <div className={styles.ntList}>
              {items.map((n) => (
                <article key={n.id} className={`${styles.ntItem} ${n.isRead ? "" : styles.ntUnread}`}>
                  <div
                    className={`${styles.ntOpen} ${n.openUrl ? styles.ntClickable : ""}`}
                    {...(n.openUrl ? { role: "button", tabIndex: 0, "aria-label": "알림 보기", onClick: () => open(n), onKeyDown: (e: React.KeyboardEvent) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(n); } } } : {})}
                  >
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img className={styles.ntIcon} src={assetUrl(`assets/icons/notifications/${ICON_FILE[n.icon]}.svg`) ?? ""} alt="" loading="lazy" />
                    <div className={styles.ntBody}>
                      <div className={styles.ntTitleRow}><span className={styles.ntTitle}>{n.title}</span>{!n.isRead && <span className={styles.ntDot} aria-label="읽지 않음" />}<span className={styles.ntTime}>{n.time}</span></div>
                      {n.message && <div className={styles.ntMsg}>{n.message}</div>}
                    </div>
                  </div>
                  <button type="button" className={styles.ntDel} onClick={() => remove(n)} aria-label="알림 삭제">×</button>
                </article>
              ))}
              {q.hasNextPage && <button type="button" className={styles.ntMore} onClick={() => q.fetchNextPage()} disabled={q.isFetchingNextPage}>{q.isFetchingNextPage ? "불러오는 중..." : "더 보기"}</button>}
            </div>
          )}
        </>
      )}
      {toast && <div className={styles.ntToast} role="status" aria-live="polite">{toast}</div>}
    </div>
  );
}
