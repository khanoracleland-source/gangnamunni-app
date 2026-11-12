"use client";
import Loading from "./Loading";
import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type CommunityItem } from "@/lib/api";
import { num, timeDisplay } from "@/lib/community";
import ContentActions from "./ContentActions";
import LoginPrompt from "./LoginPrompt";
import MyHeader from "./MyHeader";
import { useToast } from "./Toast";
import styles from "./CommunityList.module.css";
import { syncLike, toggled } from "@/lib/optimistic";

const ICON = (name: string) => assetUrl(`assets/icons/${name}.png?v=1`) ?? "";
const href = (tab: string, page: number) => {
  const u = new URLSearchParams();
  if (tab !== "latest") u.set("tab", tab);
  if (page > 1) u.set("page", String(page));
  const qs = u.toString();
  return `/mypage/community${qs ? `?${qs}` : ""}`;
};

/** 마이페이지 › MY 커뮤니티 글: the member's own community posts (최신순 / 인기순) with a 수정하기 button on every card. */
export default function MyCommunity() {
  const router = useRouter();
  const qc = useQueryClient();
  const sp = useSearchParams();
  const say = useToast();
  const tab = sp.get("tab") === "popular" ? "popular" : "latest";
  const page = Math.max(1, Number(sp.get("page")) || 1);
  const key = qk.myCommunity(tab, page);
  const { data, error, isError, refetch } = useQuery({ queryKey: key, queryFn: () => api.myCommunity({ tab, page }), retry: false });
  const loggedOut = error instanceof ApiError && error.status === 401;
  const [open, setOpen] = useState<Record<number, boolean>>({});

  // ?page= beyond the last page (e.g. the last post of the last page was deleted): go to the last page instead of showing "empty"
  useEffect(() => {
    if (data && data.items.length === 0 && page > 1) router.replace(href(tab, Math.min(page - 1, Math.max(1, data.totalPages))));
  }, [data, page, tab, router]);

  const like = useMutation({
    mutationFn: (it: CommunityItem) => api.toggleCommunityLike(it.id),
    // cache first (this list, the main lists and the post's own screen), then the server; an error puts the old value back
    onMutate: async (it) => { await qc.cancelQueries({ queryKey: ["community"] }); const t = toggled(it.liked, it.likeCount); syncLike(qc, "post", it.id, t.liked, t.count); },
    onSuccess: (r, it) => {
      syncLike(qc, "post", it.id, r.liked, r.count);
      say(r.liked ? "좋아요 반영되었습니다." : "좋아요 취소되었습니다.");
    },
    onError: (_e, it) => { syncLike(qc, "post", it.id, it.liked, it.likeCount); say("좋아요 처리 중 오류가 발생했습니다."); },
  });

  return (
    <div className={styles.wrap}>
      <MyHeader title="MY 커뮤니티 글" right={null} />
      {loggedOut ? <LoginPrompt /> : (
        <>
          <div className={styles.tabs} style={{ position: "static" }}>
            <Link href={href("latest", 1)} replace className={`${styles.tab} ${tab === "latest" ? styles.tabOn : ""}`}>최신순</Link>
            <Link href={href("popular", 1)} replace className={`${styles.tab} ${tab === "popular" ? styles.tabOn : ""}`}>인기순</Link>
          </div>
          {!data ? (
            <div className={styles.empty}>
              {isError ? <>불러오지 못했습니다. <button className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : <Loading />}
            </div>
          ) : data.items.length === 0 ? (
            <div className={styles.empty}>
              <div className={styles.emptyIcon} aria-hidden="true">💬</div>
              <p>작성한 커뮤니티 글이 없습니다.<br />첫 번째 글을 작성해보세요!</p>
            </div>
          ) : (
            <>
              <ul className={styles.list}>
                {data.items.map((it) => {
                  const expanded = !!open[it.id];
                  const detail = `/community/${it.id}`;
                  return (
                    <li key={it.id} className={styles.item} onClick={(e) => { if (!(e.target as HTMLElement).closest("a,button")) router.push(detail); }}>
                      <div className={styles.content}>
                        <div className={styles.author}>
                          <div className={styles.avatar}>
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            {it.authorAvatar ? <img src={assetUrl(it.authorAvatar) ?? ""} alt="" /> : null}
                          </div>
                          <span className={styles.aname}>{it.authorName}</span>
                          <ContentActions type="community" id={it.id} isMine={it.isMine} authorName={it.authorName} className={styles.more}
                            onDone={(w) => { if (w === "deleted") say("게시글이 삭제되었습니다."); }} />
                        </div>
                        <span className={styles.cat}>{it.category}</span>
                        <Link href={detail} className={styles.ptitle}>{it.title}</Link>
                        <div className={`${styles.preview} ${expanded ? styles.expanded : ""}`}>{expanded && it.fullText ? it.fullText : it.preview}</div>
                        <div className={styles.readRow}>
                          {it.fullText ? (
                            <button type="button" className={styles.readmore} aria-expanded={expanded}
                              onClick={() => setOpen((o) => ({ ...o, [it.id]: !o[it.id] }))}>{expanded ? "접기" : "…더보기"}</button>
                          ) : <span />}
                          <span className={styles.time} suppressHydrationWarning>{timeDisplay(it.createdAt, it.updatedAt)}</span>
                        </div>
                        {it.photos.length > 0 && (
                          <div className={`${styles.thumbs} ${it.photos.length === 1 ? styles.thumbsOne : ""}`}>
                            {it.photos.slice(0, 2).map((src, i) => (
                              <div className={styles.thumb} key={i}>
                                {/* eslint-disable-next-line @next/next/no-img-element */}
                                <img src={assetUrl(src) ?? ""} alt="" loading="lazy" />
                              </div>
                            ))}
                          </div>
                        )}
                        <div className={styles.stats}>
                          <span className={`${styles.stat} ${styles.like} ${it.liked ? styles.liked : ""}`} role="button" aria-pressed={it.liked} aria-label="좋아요"
                            onClick={(e) => { e.stopPropagation(); if (!like.isPending) like.mutate(it); }}>
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            <img src={ICON(it.liked ? "icon-community-heart-on" : "icon-community-heart")} alt="" />
                            <span>좋아요 {num(it.likeCount)}</span>
                          </span>
                          {/* eslint-disable-next-line @next/next/no-img-element */}
                          <span className={styles.stat}><img src={ICON("icon-community-comment")} alt="" />댓글 {num(it.commentCount)}</span>
                          {/* eslint-disable-next-line @next/next/no-img-element */}
                          <span className={styles.stat}><img src={ICON("icon-community-view")} alt="" />조회 {num(it.viewCount)}</span>
                        </div>
                        <Link href={`/community/write?id=${it.id}`} className={styles.edit}>수정하기</Link>
                      </div>
                    </li>
                  );
                })}
              </ul>
              {data.totalPages > 1 && (
                <nav className={styles.pager} aria-label="페이지">
                  {data.page > 1 && <Link href={href(tab, data.page - 1)} aria-label="이전">&laquo;</Link>}
                  {Array.from({ length: Math.min(data.totalPages, data.page + 2) - Math.max(1, data.page - 2) + 1 }, (_, i) => Math.max(1, data.page - 2) + i).map((p) =>
                    p === data.page ? <span key={p} className={styles.cur} aria-current="page">{p}</span> : <Link key={p} href={href(tab, p)}>{p}</Link>)}
                  {data.page < data.totalPages && <Link href={href(tab, data.page + 1)} aria-label="다음">&raquo;</Link>}
                </nav>
              )}
            </>
          )}
        </>
      )}
    </div>
  );
}
