"use client";
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import DetailSkeleton from "./DetailSkeleton";
import { useToast } from "./Toast";
import { api, ApiError, assetUrl, qk, type CommunityPost } from "@/lib/api";
import { useLoginUrl, useMe, useRequireLogin } from "@/lib/me";
import { commentAge, num, shortDate } from "@/lib/community";
import { kstNow, syncLike, toggled } from "@/lib/optimistic";
import ContentActions from "./ContentActions";
import styles from "./CommunityDetail.module.css";

const ICON = (name: string) => assetUrl(`assets/icons/${name}.png?v=1`) ?? "";

/** detail_community.php: post, nested comments (one reply level), post + comment likes, comment form. */
const VIEWED = new Set<number>();

export default function CommunityDetail({ id, initial }: { id: number; initial?: CommunityPost }) {
  const router = useRouter();
  const qc = useQueryClient();
  const me = useMe();
  const loginUrl = useLoginUrl();
  const requireLogin = useRequireLogin();
  // detail_community.php counts one view per page load: the first fetch of this page load asks for it, refetches do not
  const { data: post, error, isError, refetch } = useQuery({ queryKey: qk.communityPost(id), queryFn: () => { const view = !VIEWED.has(id); VIEWED.add(id); return api.communityPost(id, { view }); }, initialData: initial, retry: (n: number, e: unknown) => !(e instanceof ApiError && (e.status === 404 || e.status === 400)) && n < 1, });
  const missing = error instanceof ApiError && (error.status === 404 || error.status === 400);

  const [expanded, setExpanded] = useState(false);
  const [overflowing, setOverflowing] = useState(false);
  const bodyRef = useRef<HTMLDivElement>(null);
  const [text, setText] = useState("");
  const [replyTo, setReplyTo] = useState<{ id: number; name: string } | null>(null);
  const [busy, setBusy] = useState<Record<number, boolean>>({});
  const scrollTo = useRef<number | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const formRef = useRef<HTMLFormElement>(null);
  const say = useToast();
  const touchLists = () => { void qc.invalidateQueries({ queryKey: ["community"] }); };

  // Show "...더보기" only when the text exceeds the three-line preview (as detail_community.php does).
  useLayoutEffect(() => {
    const el = bodyRef.current;
    if (!el || !post) return;
    const measure = () => {
      const open = el.classList.contains(styles.bodyOpen);
      if (open) el.classList.remove(styles.bodyOpen);
      setOverflowing(el.scrollHeight > el.clientHeight + 1);
      if (open) el.classList.add(styles.bodyOpen);
    };
    measure();
    window.addEventListener("resize", measure);
    return () => window.removeEventListener("resize", measure);
  }, [post]);

  // Keep the composer above a mobile keyboard (visualViewport), like the PHP page.
  useEffect(() => {
    const form = formRef.current;
    const vv = window.visualViewport;
    if (!form || !vv) return;
    const place = () => {
      const inset = vv.scale === 1 ? Math.max(0, window.innerHeight - vv.height - vv.offsetTop) : 0;
      if (inset > 0) form.style.setProperty("--comment-bottom", `${inset}px`);
      else form.style.removeProperty("--comment-bottom");
    };
    vv.addEventListener("resize", place);
    vv.addEventListener("scroll", place);
    return () => { vv.removeEventListener("resize", place); vv.removeEventListener("scroll", place); };
  }, [me.data?.loggedIn, post?.id]);

  useEffect(() => {
    if (scrollTo.current == null) return;
    const el = document.getElementById(`comment-${scrollTo.current}`);
    if (el) { el.scrollIntoView({ block: "center" }); scrollTo.current = null; }
  }, [post]);

  const patch = (fn: (p: CommunityPost) => CommunityPost) => qc.setQueryData<CommunityPost>(qk.communityPost(id), (p) => (p ? fn(p) : p));

  const likePost = useMutation({
    mutationFn: () => api.toggleCommunityLike(id),
    // cache first (this screen and the post's row in every cached list), then the server; an error puts the old value back
    onMutate: async () => {
      await Promise.all([qc.cancelQueries({ queryKey: qk.communityPost(id) }), qc.cancelQueries({ queryKey: ["community"] })]);
      const prev = qc.getQueryData<CommunityPost>(qk.communityPost(id));
      if (prev) { const t = toggled(prev.liked, prev.likeCount); syncLike(qc, "post", id, t.liked, t.count); }
      return { prev };
    },
    onError: (_e, _v, ctx) => { if (ctx?.prev) syncLike(qc, "post", id, ctx.prev.liked, ctx.prev.likeCount); },
    onSuccess: (r) => syncLike(qc, "post", id, r.liked, r.count),
  });
  const setComment = (cid: number, liked: boolean, likeCount: number) => patch((p) => ({ ...p, comments: p.comments.map((c) => (c.id === cid ? { ...c, liked, likeCount } : c)) }));
  const likeComment = useMutation({
    mutationFn: (v: { cid: number; liked: boolean }) => api.setCommunityCommentLike(id, v.cid, v.liked),
    onMutate: async (v) => {
      await qc.cancelQueries({ queryKey: qk.communityPost(id) });
      const prev = qc.getQueryData<CommunityPost>(qk.communityPost(id))?.comments.find((c) => c.id === v.cid);
      if (prev) setComment(v.cid, v.liked, Math.max(0, prev.likeCount + (v.liked === prev.liked ? 0 : v.liked ? 1 : -1)));
      return { prev };
    },
    onError: (_e, v, ctx) => { if (ctx?.prev) setComment(v.cid, ctx.prev.liked, ctx.prev.likeCount); },
    onSuccess: (r, v) => setComment(v.cid, r.liked, r.count),
  });
  const submit = useMutation({
    mutationFn: (v: { content: string; parentId: number }) => api.addCommunityComment(id, v.content, v.parentId),
    // The comment is shown at once (a temporary negative id, after its parent's replies or at the end: the API's thread order) and
    // the field is cleared; the server's list then replaces it. An error takes it out again and gives the text back.
    onMutate: async (v) => {
      await qc.cancelQueries({ queryKey: qk.communityPost(id) });
      const tempId = -Date.now();
      const u = me.data?.user;
      patch((p) => {
        const list = [...p.comments];
        let at = list.length;
        const pi = v.parentId ? list.findIndex((c) => c.id === v.parentId) : -1;
        if (pi >= 0) { at = pi + 1; while (at < list.length && list[at].parentId === v.parentId) at++; }
        list.splice(at, 0, { id: tempId, parentId: v.parentId, authorName: u?.nickname || u?.name || "", content: v.content, createdAt: kstNow(), likeCount: 0, liked: false, isMine: true });
        return { ...p, comments: list, commentCount: p.commentCount + 1 };
      });
      const draft = { text, replyTo };
      setText(""); setReplyTo(null);
      scrollTo.current = tempId;
      return { tempId, draft };
    },
    onError: (_e, _v, ctx) => {
      if (!ctx) return;
      patch((p) => ({ ...p, comments: p.comments.filter((c) => c.id !== ctx.tempId), commentCount: Math.max(0, p.commentCount - 1) }));
      setText(ctx.draft.text); setReplyTo(ctx.draft.replyTo);
    },
    onSuccess: async (r) => {
      await qc.invalidateQueries({ queryKey: qk.communityPost(id) });
      touchLists();
      scrollTo.current = r.id;
      // the refetched comments are already rendered when this runs after invalidate; scroll right away if present
      document.getElementById(`comment-${r.id}`)?.scrollIntoView({ block: "center" });
    },
  });

  if (!post) {
    if (!isError) return <DetailSkeleton />;
    return (
      <div className={styles.empty}>
        {missing ? "게시글을 찾을 수 없습니다." : <>불러오지 못했습니다. <button className={styles.retry} onClick={() => refetch()}>다시 시도</button></>}
      </div>
    );
  }

  const back = () => (window.history.length > 1 ? router.back() : router.push("/community"));
  const errMsg = (e: unknown, fallback: string) => (e instanceof ApiError && e.status !== 401 ? e.message : fallback);
  const onLikePost = () => {
    if (likePost.isPending) return;
    void requireLogin(() => likePost.mutateAsync()).catch(() => say("좋아요 처리 중 오류가 발생했습니다."));
  };
  const onLikeComment = (cid: number, liked: boolean) => {
    if (busy[cid] || cid < 0) return; // a comment still on its way to the server has no id to like
    setBusy((b) => ({ ...b, [cid]: true }));
    void requireLogin(() => likeComment.mutateAsync({ cid, liked: !liked }))
      .catch(() => say("좋아요 처리에 실패했습니다. 다시 시도해주세요."))
      .finally(() => setBusy((b) => ({ ...b, [cid]: false })));
  };
  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const content = text.trim();
    if (!content || submit.isPending) return;
    void requireLogin(() => submit.mutateAsync({ content, parentId: replyTo?.id ?? 0 }))
      .catch((err) => say(errMsg(err, "댓글을 저장하지 못했습니다. 다시 시도해주세요.")));
  };
  const reply = (cid: number, name: string) => { setReplyTo({ id: cid, name }); inputRef.current?.focus(); };
  // deleted or blocked: the post is no longer shown to this viewer, go back to the list
  const afterAction = (w: "deleted" | "blocked" | "reported") => { if (w === "reported") say("신고가 접수되었습니다."); else router.push("/community"); };

  return (
    <>
      <div className={styles.head}>
        <button type="button" className={styles.back} onClick={back} aria-label="뒤로 가기"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg></button>
        <div className={styles.headTitle}>커뮤니티 댓글</div>
      </div>
      <div className={styles.wrap}>
        <article className={styles.card}>
          <div className={styles.meta}>
            <span className={styles.avatar} aria-hidden="true">
              {post.authorAvatar ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={assetUrl(post.authorAvatar) ?? ""} alt="" />
              ) : null}
            </span>
            <span className={styles.author}>{post.authorName}</span>
            <ContentActions type="community" id={post.id} isMine={post.isAuthor} authorName={post.authorName} className={styles.more} onDone={afterAction} />
          </div>
          <span className={styles.badge}>{post.category}</span>
          <h1 className={styles.ptitle}>{post.title}</h1>
          <div ref={bodyRef} id="postContent" className={`${styles.body} ${expanded ? styles.bodyOpen : ""}`}>{post.content}</div>
          <div className={styles.readRow}>
            {overflowing || expanded ? (
              <button type="button" aria-expanded={expanded} aria-controls="postContent" onClick={() => setExpanded((v) => !v)}>{expanded ? "접기" : "…더보기"}</button>
            ) : <span />}
            <time>{shortDate(post.createdAt)}</time>
          </div>
          {post.photos.length > 0 && (
            <div className={styles.photos}>
              {post.photos.map((src, i) => (
                <a key={i} href={assetUrl(src) ?? "#"} target="_blank" rel="noopener noreferrer">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={assetUrl(src) ?? ""} alt="게시글 첨부 사진" loading="lazy" />
                </a>
              ))}
            </div>
          )}
          <div className={styles.stats}>
            <button type="button" className={styles.stat} aria-label="좋아요" aria-pressed={post.liked} onClick={onLikePost}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={ICON(post.liked ? "icon-community-heart-on" : "icon-community-heart")} alt="" />좋아요 {num(post.likeCount)}
            </button>
            <a href="#comments" className={styles.stat} aria-label="댓글">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={ICON("icon-community-comment")} alt="" />댓글 {num(post.comments.length)}
            </a>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <span className={styles.stat} aria-label="조회수"><img src={ICON("icon-community-view")} alt="" />조회 {num(post.viewCount)}</span>
          </div>
        </article>

        <section id="comments">
          <div className={styles.ctitle}>댓글 {post.commentCount}</div>
          {post.comments.length === 0 ? (
            <div className={styles.noComments}>아직 댓글이 없습니다. 첫 댓글을 남겨보세요!</div>
          ) : post.comments.map((c) => (
            <div key={c.id} id={`comment-${c.id}`} className={`${styles.comment} ${c.parentId ? styles.reply : ""}`}>
              <div className={styles.crow}>
                <span className={styles.avatar} aria-hidden="true" />
                <span className={styles.cauthor}>{c.authorName}</span>
                <span className={styles.cage} suppressHydrationWarning>{commentAge(c.createdAt)}</span>
                <time className={styles.cdate}>{shortDate(c.createdAt)}</time>
                {/* a comment still on its way to the server (temporary negative id) has nothing to delete or report yet */}
                {c.id > 0 && <ContentActions type="comment" id={c.id} isMine={c.isMine} authorName={c.authorName} className={styles.cmore} onDone={(w) => (w === "blocked" ? afterAction(w) : say("신고가 접수되었습니다."))} />}
              </div>
              <div className={styles.ctext}>{c.content}</div>
              <div className={styles.tools}>
                <button type="button" className={styles.cbtn} aria-label="댓글 좋아요" aria-pressed={c.liked} disabled={!!busy[c.id]} onClick={() => onLikeComment(c.id, c.liked)}>
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={ICON(c.liked ? "icon-community-heart-on" : "icon-community-heart")} alt="" />{c.likeCount > 0 ? num(c.likeCount) : ""}
                </button>
                {!c.parentId && (
                  <button type="button" className={styles.cbtn} onClick={() => reply(c.id, c.authorName)}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={ICON("icon-community-comment")} alt="" />댓글쓰기
                  </button>
                )}
              </div>
            </div>
          ))}
        </section>

        {me.data && !me.data.loggedIn ? (
          <div className={`${styles.form} ${styles.loginBar}`}>
            <a href={loginUrl()}>로그인하고 댓글 작성하기</a>
          </div>
        ) : (
          <form ref={formRef} className={styles.form} onSubmit={onSubmit}>
            {replyTo && (
              <div className={styles.target}>
                <span>{replyTo.name}님에게 답글 작성 중</span>
                <button type="button" onClick={() => setReplyTo(null)} aria-label="답글 취소">취소</button>
              </div>
            )}
            <input ref={inputRef} className={styles.input} type="text" aria-label="댓글" maxLength={500} required
              placeholder={replyTo ? "답글을 입력하세요." : "댓글을 입력하세요."} value={text} onChange={(e) => setText(e.target.value)} />
            <button type="submit" className={styles.submit} disabled={submit.isPending}>등록</button>
          </form>
        )}
      </div>
    </>
  );
}
