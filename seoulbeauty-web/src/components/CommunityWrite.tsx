"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl } from "@/lib/api";
import { useLoginUrl, useMe } from "@/lib/me";
import styles from "./CommunityWrite.module.css";

import type { CommunityForm } from "@/lib/api";

/** community_write.php: new post or (with `id`) edit of the member's own post. Posts go to admin review (status pending). */
export default function CommunityWrite({ id }: { id: number | null }) {
  const router = useRouter();
  const me = useMe();
  const loginUrl = useLoginUrl();
  const loggedIn = me.data?.loggedIn === true;
  const form = useQuery({ queryKey: ["communityForm", id], queryFn: () => api.communityForm(id ?? undefined), enabled: loggedIn, staleTime: 0, gcTime: 0, retry: false });

  if (me.data && !loggedIn) return <div className={styles.empty}><a href={loginUrl()}>로그인이 필요합니다. 로그인하기</a></div>;
  if (form.isError) {
    const e = form.error;
    return <div className={styles.empty}>{e instanceof ApiError && (e.status === 403 || e.status === 404) ? e.message : "불러오지 못했습니다."} <button type="button" onClick={() => router.push("/community")}>목록으로</button></div>;
  }
  if (!form.data) return <div className={styles.empty}>불러오는 중…</div>;
  return <Editor id={id} data={form.data} />;
}

type Pick = { file: File; url: string };

function Editor({ id, data }: { id: number | null; data: CommunityForm }) {
  const router = useRouter();
  const qc = useQueryClient();
  const [category, setCategory] = useState(data.post?.category ?? "");
  const [title, setTitle] = useState(data.post?.title ?? "");
  const [content, setContent] = useState(data.post?.content ?? "");
  const [kept, setKept] = useState<string[]>(data.post?.photos ?? []);
  const [removed, setRemoved] = useState<string[]>([]);
  const [picks, setPicks] = useState<Pick[]>([]);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const live = useRef<Pick[]>([]);
  const fileRef = useRef<HTMLInputElement>(null);
  useEffect(() => { live.current = picks; }, [picks]);
  useEffect(() => () => live.current.forEach((p) => URL.revokeObjectURL(p.url)), []);

  const slots = data.maxPhotos - kept.length;
  const onPick = (e: React.ChangeEvent<HTMLInputElement>) => {
    const picked = Array.from(e.target.files ?? []);
    e.target.value = "";
    const tooBig = picked.find((f) => f.size > data.maxPhotoBytes);
    if (tooBig) { setError("이미지 크기는 5MB 이하여야 합니다."); return; }
    setError("");
    const room = Math.max(0, slots - picks.length);
    setPicks((cur) => [...cur, ...picked.slice(0, room).map((file) => ({ file, url: URL.createObjectURL(file) }))]);
  };
  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;
    const fd = new FormData();
    fd.set("category", category); fd.set("title", title.trim()); fd.set("content", content.trim());
    removed.forEach((u) => fd.append("deletePhotos", u));
    picks.forEach((p) => fd.append("photos", p.file));
    setBusy(true); setError("");
    try {
      await api.saveCommunityPost(id, fd);
      void qc.invalidateQueries({ queryKey: ["community"] });
      void qc.invalidateQueries({ queryKey: ["communityPost"] });
      window.alert("게시글이 접수되었습니다. 관리자 검토 후 공개됩니다.");
      router.push("/community");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "저장하지 못했습니다. 다시 시도해주세요.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <form className={styles.wrap} onSubmit={onSubmit}>
      <div className={styles.head}>
        <button type="button" className={styles.back} onClick={() => router.back()} aria-label="뒤로 가기">←</button>
        <div className={styles.title}>{id ? "게시글 수정" : "글쓰기"}</div>
      </div>
      <select className={styles.field} value={category} onChange={(e) => setCategory(e.target.value)} required aria-label="카테고리">
        <option value="" disabled>카테고리 선택</option>
        {data.categories.map((c) => <option key={c} value={c}>{c}</option>)}
      </select>
      <input className={styles.field} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={200} required placeholder="제목을 입력하세요." aria-label="제목" />
      <textarea className={`${styles.field} ${styles.area}`} value={content} onChange={(e) => setContent(e.target.value)} minLength={10} required placeholder="내용을 10자 이상 입력하세요." aria-label="내용" />
      <div className={styles.photos}>
        {kept.map((u) => (
          <div key={u} className={styles.thumb}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={assetUrl(u) ?? ""} alt="첨부 사진" />
            <button type="button" aria-label="사진 삭제" onClick={() => { setKept((k) => k.filter((x) => x !== u)); setRemoved((r) => [...r, u]); }}>×</button>
          </div>
        ))}
        {picks.map((p) => (
          <div key={p.url} className={styles.thumb}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={p.url} alt="새 사진" />
            <button type="button" aria-label="사진 삭제" onClick={() => { URL.revokeObjectURL(p.url); setPicks((cur) => cur.filter((x) => x !== p)); }}>×</button>
          </div>
        ))}
        {slots - picks.length > 0 && (
          <>
            {/* Button + ref, not a label: Android WebView opens the chooser twice for label-wrapped inputs. */}
            <button type="button" className={styles.add} onClick={() => fileRef.current?.click()}>＋ 사진</button>
            <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden onChange={onPick} />
          </>
        )}
      </div>
      <div className={styles.hint}>사진 최대 {data.maxPhotos}장, 장당 5MB. 등록 후 관리자 검토를 거쳐 공개됩니다.</div>
      {error && <div className={styles.error} role="alert">{error}</div>}
      <button type="submit" className={styles.submit} disabled={busy}>{busy ? "저장 중…" : id ? "수정하기" : "등록하기"}</button>
    </form>
  );
}
