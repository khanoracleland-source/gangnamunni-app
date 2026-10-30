"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api, ApiError, qk, type MyPointsData } from "@/lib/api";
import LoginPrompt from "./LoginPrompt";
import SupportHeader from "./SupportHeader";
import styles from "./Points.module.css";

type Filter = "all" | "earn" | "use";
const TABS: { key: Filter; label: string }[] = [{ key: "all", label: "전체" }, { key: "earn", label: "적립" }, { key: "use", label: "차감" }];
const P = (v: number) => `${v.toLocaleString("ko-KR")}P`;

/** my_points.php: balance, nearest expiry, this month's totals and the last 50 ledger rows with the 전체/적립/차감 filter. */
export default function MyPoints({ initial, anon }: { initial?: MyPointsData; anon?: boolean }) {
  const router = useRouter();
  const q = useQuery({ queryKey: qk.myPoints, queryFn: () => api.myPoints(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const [filter, setFilter] = useState<Filter>("all");
  const loggedOut = anon || (q.error instanceof ApiError && q.error.status === 401);
  const d = q.data;
  /** The menu is paused: the PHP page sends everybody to 마이페이지. */
  useEffect(() => { if (d && !d.visible) router.replace("/mypage"); }, [d, router]);

  return (
    <div className={styles.wrap}>
      <SupportHeader title="포인트" fallback="/mypage" />
      {loggedOut ? <LoginPrompt text="로그인하면 포인트를 확인할 수 있습니다." /> : !d || !d.visible ? (
        <div className={styles.state}>{q.error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => q.refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : (() => {
        const items = d.logs.filter((l) => filter === "all" || l.kind === filter);
        return (
          <>
            <section className={styles.summary}>
              <div className={styles.cap}>사용 가능 포인트</div>
              <div className={styles.bal}>{d.balance.toLocaleString("ko-KR")}<small>P</small></div>
              {d.expiry && (
                <div className={styles.exp}>
                  <span>{Number(d.expiry.date.slice(5, 7))}월 {Number(d.expiry.date.slice(8, 10))}일 소멸 예정</span>
                  <span>{P(d.expiry.amount)}</span>
                </div>
              )}
              <div className={`${styles.boxes} ${d.expiry ? "" : styles.boxes0}`}>
                <div className={styles.box}><div className={styles.k}>이번 달 적립</div><div className={styles.v}>+{P(d.monthEarn)}</div></div>
                <div className={styles.box}><div className={styles.k}>이번 달 차감</div><div className={styles.v}>-{P(d.monthUse)}</div></div>
              </div>
              <p className={styles.note}>사용 기한은 적립건마다 달라요. 내역에서 건별 기한을 확인하세요.</p>
            </section>
            <div className={styles.band} />
            <div className={styles.tabs} role="tablist">
              {TABS.map((t) => <button key={t.key} type="button" role="tab" aria-selected={filter === t.key} className={`${styles.tab} ${filter === t.key ? styles.tabOn : ""}`} onClick={() => setFilter(t.key)}>{t.label}</button>)}
            </div>
            <div className={styles.count}>총 <span data-testid="cnt">{items.length}</span>건</div>
            {d.logs.length === 0 ? <div className={styles.empty}>포인트 내역이 없습니다.</div> : items.length === 0 ? <div className={styles.empty}>해당 내역이 없습니다.</div> : (
              <div className={styles.list}>
                {items.map((l) => (
                  <div key={l.id} className={styles.item}>
                    <div className={styles.l}>
                      <div className={styles.title}><span className={`${styles.tag} ${l.type === "earn" ? styles.tagEarn : ""}`}>{l.label}</span><span className={styles.t}>{l.reason}</span></div>
                      <div className={styles.meta}>{l.meta}</div>
                    </div>
                    <div className={styles.r}>
                      <div className={`${styles.amt} ${l.kind === "earn" ? styles.amtEarn : ""}`}>{(l.kind === "earn" ? "+" : "-") + l.amount.toLocaleString("ko-KR")}</div>
                      {l.balance !== null && <div className={styles.after}>{P(l.balance)}</div>}
                    </div>
                  </div>
                ))}
              </div>
            )}
            {d.hasMore && <div className={styles.more}>최근 50건까지 표시됩니다.</div>}
          </>
        );
      })()}
      <div className={styles.band} />
      <Link className={styles.policyRow} href="/points-policy">포인트 정책 보기<span aria-hidden="true">›</span></Link>
    </div>
  );
}
