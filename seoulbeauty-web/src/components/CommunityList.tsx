"use client";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, assetUrl, qk, type CommunityItem, type CommunityList as Data, type CommunityListParams, type CommunityTab } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { useLoginUrl, useMe, useRequireLogin } from "@/lib/me";
import ContentActions from "./ContentActions";
import { timeDisplay, num } from "@/lib/community";
import { COMMUNITY_REGION_KEY, writeRegions } from "./SiteHeader";
import styles from "./CommunityList.module.css";

const ICON = (name: string) => assetUrl(`assets/icons/${name}.png?v=1`) ?? "";

function pageHref(p: { tab: CommunityTab; category: string; region: string; q: string }, page: number): string {
  const u = new URLSearchParams();
  if (p.tab !== "latest") u.set("tab", p.tab);
  if (p.tab !== "review" && p.category) u.set("category", p.category);
  if (p.region) u.set("region", p.region);
  if (p.q) u.set("q", p.q);
  if (page > 1) u.set("page", String(page));
  const qs = u.toString();
  return `/community${qs ? `?${qs}` : ""}`;
}

/** community.php: 3 tabs, post cards with like / comment / view counts, pagination, write button. */
export default function CommunityList({ params, initial }: { params: Required<Pick<CommunityListParams, "tab" | "category" | "region" | "q">> & { page: number }; initial?: Data }) {
  const router = useRouter();
  const pathname = usePathname();
  const sp = useSearchParams();
  const php = usePhpBase();
  const qc = useQueryClient();
  const me = useMe();
  const loginUrl = useLoginUrl();
  const requireLogin = useRequireLogin();
  const tab = (["latest", "popular", "review"].includes(params.tab) ? params.tab : "latest") as CommunityTab;
  const key = qk.community({ ...params, tab });
  const { data, isError, refetch } = useQuery({
    queryKey: key,
    queryFn: () => api.community({ ...params, tab }),
    initialData: initial,
    staleTime: 15_000,
  });
  const [open, setOpen] = useState<Record<string, boolean>>({});
  const [toast, setToast] = useState("");
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => () => { if (timer.current) clearTimeout(timer.current); }, []);
  const say = (msg: string) => {
    setToast(msg);
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => setToast(""), 2000);
  };

  // community.php page_js: a region in the URL is remembered; without one the remembered regions are applied.
  useEffect(() => {
    if (params.region) { writeRegions(params.region.split(",").filter(Boolean), COMMUNITY_REGION_KEY); return; }
    try {
      const saved = JSON.parse(localStorage.getItem(COMMUNITY_REGION_KEY) || "[]");
      if (Array.isArray(saved) && saved.length) {
        const u = new URLSearchParams(sp.toString());
        u.set("region", saved.map(String).join(","));
        router.replace(`${pathname}?${u}`);
      }
    } catch { /* ignore */ }
  }, [params.region, pathname, router, sp]);

  const like = useMutation({
    mutationFn: (it: CommunityItem) => (it.kind === "review" ? api.toggleReviewLike(it.id) : api.toggleCommunityLike(it.id)),
    onSuccess: (r, it) => {
      qc.setQueryData<Data>(key, (p) => p && { ...p, items: p.items.map((i) => (i.kind === it.kind && i.id === it.id ? { ...i, liked: r.liked, likeCount: r.count } : i)) });
      say(r.liked ? "좋아요 반영되었습니다." : "좋아요 취소되었습니다.");
    },
  });
  const toggleLike = (it: CommunityItem) => {
    if (like.isPending) return;
    void requireLogin(() => like.mutateAsync(it)).catch(() => say("좋아요 처리 중 오류가 발생했습니다."));
  };

  const detailHref = (it: CommunityItem) => (it.kind === "review" ? phpUrl(php, `review_detail.php?id=${it.id}`) : `/community/${it.id}`);
  const go = (it: CommunityItem) => {
    if (it.kind === "review") window.location.assign(detailHref(it));
    else router.push(detailHref(it));
  };
  const regionStr = params.region;
  const tabHref = (t: CommunityTab) => pageHref({ tab: t, category: "", region: regionStr, q: "" }, 1);

  return (
    <div className={styles.wrap}>
      <h2 className={styles.title}>커뮤니티</h2>
      <div className={styles.tabs}>
        <Link href={tabHref("latest")} className={`${styles.tab} ${tab === "latest" ? styles.tabOn : ""}`}>최신순</Link>
        <Link href={tabHref("popular")} className={`${styles.tab} ${tab === "popular" ? styles.tabOn : ""}`}>인기순</Link>
        <Link href={tabHref("review")} className={`${styles.tab} ${tab === "review" ? styles.tabOn : ""}`}>시술 후기</Link>
      </div>

      {!data ? (
        <div className={styles.empty}>
          {isError ? <>불러오지 못했습니다. <button className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}
        </div>
      ) : data.items.length === 0 ? (
        <div className={styles.empty}>
          <div className={styles.emptyIcon} aria-hidden="true">💬</div>
          <p>아직 게시글이 없습니다.<br />첫 번째 글을 작성해보세요!</p>
        </div>
      ) : (
        <>
          <ul className={styles.list}>
            {data.items.map((it) => {
              const k = `${it.kind}-${it.id}`;
              const expanded = !!open[k];
              const href = detailHref(it);
              return (
                <li key={k} className={styles.item}
                  onClick={(e) => { if (!(e.target as HTMLElement).closest("a,button")) go(it); }}>
                  <div className={styles.content}>
                    <span className={styles.tag}>{it.category}</span>
                    {it.kind === "review"
                      ? <a href={href} className={styles.ptitle}>{it.title}</a>
                      : <Link href={href} className={styles.ptitle}>{it.title}</Link>}
                    <div className={`${styles.preview} ${expanded ? styles.expanded : ""}`}>{expanded && it.fullText ? it.fullText : it.preview}</div>
                    <div className={styles.readRow}>
                      {it.fullText ? (
                        <button type="button" className={styles.readmore} aria-expanded={expanded}
                          onClick={() => setOpen((o) => ({ ...o, [k]: !o[k] }))}>{expanded ? "접기" : "...더보기"}</button>
                      ) : <span />}
                      <span className={styles.time} suppressHydrationWarning>{timeDisplay(it.createdAt, it.updatedAt)}</span>
                    </div>
                    {it.photos.length > 0 && (
                      <div className={styles.thumbs}>
                        {it.photos.slice(0, 2).map((src, i) => (
                          <div className={styles.thumb} key={i}>
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            <img src={assetUrl(src) ?? ""} alt="" loading="lazy" />
                          </div>
                        ))}
                      </div>
                    )}
                    <div className={styles.author}>
                      <div className={styles.avatar}>
                        {it.authorAvatar ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={assetUrl(it.authorAvatar) ?? ""} alt="" />
                        ) : null}
                      </div>
                      <span className={styles.aname}>{it.authorName}</span>
                      <div className={styles.stats}>
                        <span className={`${styles.stat} ${styles.like} ${it.liked ? styles.liked : ""}`}
                          role="button" aria-pressed={it.liked} aria-label="좋아요"
                          onClick={(e) => { e.stopPropagation(); toggleLike(it); }}>
                          {/* eslint-disable-next-line @next/next/no-img-element */}
                          <img src={ICON(it.liked ? "icon-community-heart-on" : "icon-community-heart")} alt="" />
                          <span>{num(it.likeCount)}</span>
                        </span>
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <span className={styles.stat}><img src={ICON("icon-community-comment")} alt="" />{num(it.commentCount)}</span>
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <span className={styles.stat}><img src={ICON("icon-community-view")} alt="" />{num(it.viewCount)}</span>
                        <ContentActions type={it.kind === "review" ? "review" : "community"} id={it.id} isMine={it.isMine} authorName={it.authorName} className={styles.more}
                          onDone={(w) => say(w === "deleted" ? "게시글이 삭제되었습니다." : w === "blocked" ? "사용자를 차단했습니다." : "신고가 접수되었습니다.")} />
                      </div>
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>
          {data.totalPages > 1 && (
            <nav className={styles.pager} aria-label="페이지">
              {data.page > 1 && <Link href={pageHref({ ...params, tab }, data.page - 1)} aria-label="이전">&laquo;</Link>}
              {Array.from({ length: Math.min(data.totalPages, data.page + 2) - Math.max(1, data.page - 2) + 1 }, (_, i) => Math.max(1, data.page - 2) + i).map((p) =>
                p === data.page ? <span key={p} className={styles.cur} aria-current="page">{p}</span> : <Link key={p} href={pageHref({ ...params, tab }, p)}>{p}</Link>)}
              {data.page < data.totalPages && <Link href={pageHref({ ...params, tab }, data.page + 1)} aria-label="다음">&raquo;</Link>}
            </nav>
          )}
        </>
      )}

      {me.data?.loggedIn ? (
        <Link href="/community/write" className={styles.fab} title="글쓰기" aria-label="글쓰기">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={ICON("icon-community-write")} alt="" />
        </Link>
      ) : (
        <a href={loginUrl()} className={styles.fab} title="글쓰기" aria-label="글쓰기 (로그인 필요)">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={ICON("icon-community-write")} alt="" />
        </a>
      )}
      {toast && <div className={styles.toast} role="status" aria-live="polite">{toast}</div>}
    </div>
  );
}
