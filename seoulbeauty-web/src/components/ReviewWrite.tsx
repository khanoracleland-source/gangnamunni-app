"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk } from "@/lib/api";
import type { ReviewForm } from "@/lib/api";
import { useLoginUrl, useMe } from "@/lib/me";
import { shrinkImage } from "@/lib/image";
import { phpUrl, usePhpBase } from "@/lib/php";
import styles from "./ReviewWrite.module.css";

const UNKNOWN = "담당 의사 모름";

/** review_write.php: new 후기 for a booking/hospital, or (with `editId`) edit of the member's own published one. Both wait for admin approval. */
export default function ReviewWrite({ bookingId, hospitalId, editId }: { bookingId: number; hospitalId: number; editId: number }) {
  const router = useRouter();
  const me = useMe();
  const loginUrl = useLoginUrl();
  const loggedIn = me.data?.loggedIn === true;
  const form = useQuery({
    queryKey: ["reviewForm", bookingId, hospitalId, editId],
    queryFn: () => api.reviewForm({ booking_id: bookingId || undefined, hospital_id: hospitalId || undefined, id: editId || undefined }),
    enabled: loggedIn, staleTime: 0, gcTime: 0, retry: false,
  });

  if (me.data && !loggedIn) return <div className={styles.empty}><a href={loginUrl()}>로그인이 필요합니다. 로그인하기</a></div>;
  if (form.isError) {
    const e = form.error;
    return (
      <div className={styles.empty}>
        {e instanceof ApiError && e.status === 404 ? "수정할 수 있는 후기가 아닙니다." : e instanceof ApiError && e.status === 403 ? "접근할 수 없습니다." : "불러오지 못했습니다."}{" "}
        <button type="button" onClick={() => router.replace("/mypage/reviews")}>후기 목록으로</button>
      </div>
    );
  }
  if (!form.data) return <div className={styles.empty}>불러오는 중…</div>;
  return <Editor data={form.data} />;
}

type Pick = { file: File; url: string };

function Editor({ data }: { data: ReviewForm }) {
  const router = useRouter();
  const qc = useQueryClient();
  const php = usePhpBase();
  const edit = data.mode === "edit";
  const [ids, setIds] = useState<number[]>(data.selectedDoctorIds);
  const [unknown, setUnknown] = useState(data.doctorUnknown);
  const [rating, setRating] = useState(data.review?.rating ?? 0);
  const [title, setTitle] = useState(data.review?.title ?? "");
  const [content, setContent] = useState(data.review?.content ?? "");
  const [before, setBefore] = useState<Pick[]>([]);
  const [after, setAfter] = useState<Pick[]>([]);
  const [consent, setConsent] = useState(false);
  const [agree, setAgree] = useState(false);
  const [sheet, setSheet] = useState(false);
  const [draft, setDraft] = useState<number[]>([]);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const live = useRef<Pick[]>([]);
  const fileRefs = useRef<Record<string, HTMLInputElement | null>>({});
  useEffect(() => { live.current = [...before, ...after]; }, [before, after]);
  useEffect(() => () => live.current.forEach((p) => URL.revokeObjectURL(p.url)), []);

  const { photoSlots, photoBytes, titleMax, contentMin, contentMax } = data.limits;
  const names = ids.map((id) => data.doctors.find((d) => d.id === id)?.name).filter((x): x is string => !!x);
  const hasPhotos = before.length + after.length > 0;

  const [shrinking, setShrinking] = useState(false);
  const addPicks = (cur: Pick[], set: (p: Pick[]) => void) => async (e: React.ChangeEvent<HTMLInputElement>) => {
    const picked = Array.from(e.target.files ?? []);
    e.target.value = "";
    if (shrinking || !picked.length) return;
    if (cur.length + picked.length > photoSlots) setError("시술 전·후 사진은 각각 최대 2장까지 첨부할 수 있습니다.");
    else setError("");
    setShrinking(true);
    try {
      const small = await Promise.all(picked.slice(0, Math.max(0, photoSlots - cur.length)).map((f) => shrinkImage(f)));
      if (small.some((f) => f.size > photoBytes)) { setError("각 사진은 5MB 이하만 첨부할 수 있습니다."); return; }
      set([...cur, ...small.map((file) => ({ file, url: URL.createObjectURL(file) }))]);
    } finally {
      setShrinking(false);
    }
  };
  const dropPick = (cur: Pick[], set: (p: Pick[]) => void, p: Pick) => { URL.revokeObjectURL(p.url); set(cur.filter((x) => x !== p)); };

  const openSheet = () => { setDraft(ids); setSheet(true); };
  const toggleDraft = (id: number) => setDraft((d) => (d.includes(id) ? d.filter((x) => x !== id) : [...d, id]));
  const confirmSheet = () => { if (!draft.length) return; setIds(draft); setUnknown(false); setSheet(false); };

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;
    if (!ids.length && !unknown) { setError("담당 의사를 선택하거나 담당 의사 모름을 체크해주세요."); return; }
    if (rating < 1) { setError("평점을 선택해주세요."); return; }
    if (!title.trim()) { setError("제목을 입력해주세요."); return; }
    if (Array.from(content.trim()).length < contentMin) { setError("내용을 10자 이상 입력해주세요."); return; }
    if (hasPhotos && !consent) { setError("사진 첨부 시 동의가 필요합니다."); return; }
    if (!agree) { setError("커뮤니티 가이드라인에 동의해주세요."); return; }
    const fd = new FormData();
    if (edit) fd.set("edit_id", String(data.editId));
    else if (data.bookingId > 0) fd.set("booking_id", String(data.bookingId));
    fd.set("hospital_id", String(data.hospitalId));
    if (unknown) fd.set("doctor_unknown", "1"); else fd.set("doctor_ids", ids.join(","));
    fd.set("rating", String(rating)); fd.set("title", title.trim()); fd.set("content", content.trim());
    fd.set("agree_guidelines", "1");
    if (hasPhotos) fd.set("photo_consent", "1");
    before.forEach((p) => fd.append("photos_before", p.file));
    after.forEach((p) => fd.append("photos_after", p.file));
    setBusy(true); setError("");
    try {
      const r = await api.saveReview(fd);
      void qc.invalidateQueries({ queryKey: qk.mypage });
      void qc.invalidateQueries({ queryKey: qk.reviewDetail(data.editId) });
      if (r.photoError) window.alert("후기는 등록되었지만 사진 저장에 실패했습니다. 수정에서 다시 첨부해주세요.");
      router.push("/mypage/reviews");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "저장하지 못했습니다. 다시 시도해주세요.");
    } finally {
      setBusy(false);
    }
  };

  const grid = (label: string, cur: Pick[], set: (p: Pick[]) => void, name: string) => (
    <div className={styles.pgroup}>
      <div className={styles.plabel}>{label}</div>
      <div className={styles.photos}>
        {cur.map((p) => (
          <div key={p.url} className={styles.thumb}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={p.url} alt="" />
            <button type="button" aria-label="사진 삭제" onClick={() => dropPick(cur, set, p)}>×</button>
          </div>
        ))}
        {cur.length < photoSlots && (
          <>
            {/* Button + ref, not a label: Android WebView opens the chooser twice for label-wrapped inputs. */}
            <button type="button" className={styles.add} aria-label={name} onClick={() => fileRefs.current[name]?.click()}>＋ 사진 추가</button>
            <input ref={(el) => { fileRefs.current[name] = el; }} type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden onChange={addPicks(cur, set)} />
          </>
        )}
        <span className={styles.cap}>{cur.length} / {photoSlots}</span>
      </div>
    </div>
  );

  const card = data.card;
  return (
    <form className={styles.wrap} onSubmit={onSubmit}>
      <div className={styles.head}>
        <button type="button" className={styles.back} onClick={() => router.back()} aria-label="뒤로 가기">←</button>
        <div className={styles.title}>{edit ? "시술후기 수정" : "시술후기 작성"}</div>
      </div>

      {card && (
        <div className={styles.card}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img className={styles.cardImg} src={assetUrl(card.imageUrl ?? "assets/images/seoul_beauty_hospital_placeholder.jpeg") ?? ""} alt={card.name} />
          <div className={styles.cardInfo}>
            <div className={styles.cardName}>{card.name}</div>
            {card.treatment && <div className={styles.cardTreat}>{card.treatment}</div>}
            {card.address && <div className={styles.cardMeta}>{card.address}</div>}
            {(card.doctorName || card.bookingDate) && (
              <div className={styles.cardMeta}>{card.doctorName}{card.doctorName && card.bookingDate ? " | " : ""}{card.bookingDate ? `시술일 ${card.bookingDate.replace(/-/g, ".")}` : ""}</div>
            )}
          </div>
        </div>
      )}

      {data.treatments.length > 0 && (
        <section className={styles.sec}>
          <div className={styles.label}>어떤 시술을 받으셨나요?</div>
          <div className={styles.chips}>
            {data.treatments.map((t) => <span key={t} className={`${styles.chip} ${t === data.selectedTreatment ? styles.chipOn : ""}`}>{t}</span>)}
          </div>
        </section>
      )}

      <section className={styles.sec}>
        <div className={styles.rowHead}>
          <div className={styles.label}>담당 의사<span className={styles.req}>*</span></div>
          <button type="button" className={styles.search} onClick={openSheet}>의사 검색</button>
        </div>
        {!unknown && names.length > 0 && (
          <div className={styles.picked}>
            {ids.map((id) => {
              const nm = data.doctors.find((d) => d.id === id)?.name;
              return nm ? <span key={id} className={styles.pchip}>{nm}<button type="button" aria-label="선택 해제" onClick={() => setIds((c) => c.filter((x) => x !== id))}>×</button></span> : null;
            })}
          </div>
        )}
        <label className={styles.check}>
          <input type="checkbox" checked={unknown} onChange={(e) => { setUnknown(e.target.checked); if (e.target.checked) setIds([]); }} />
          <span>{UNKNOWN}</span>
        </label>
      </section>

      <section className={styles.sec}>
        <div className={styles.label}>평점<span className={styles.req}>*</span></div>
        <div className={styles.stars} role="radiogroup" aria-label="평점">
          {[1, 2, 3, 4, 5].map((n) => (
            <button key={n} type="button" role="radio" aria-checked={rating === n} aria-label={`${n}점`} className={n <= rating ? styles.starOn : styles.star} onClick={() => setRating(n)}>★</button>
          ))}
        </div>
      </section>

      <section className={styles.sec}>
        <div className={styles.label}>제목<span className={styles.req}>*</span></div>
        <input className={styles.field} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={titleMax} placeholder="후기 제목을 입력하세요." aria-label="제목" />
      </section>

      <section className={styles.sec}>
        <div className={styles.label}>내용<span className={styles.req}>*</span></div>
        <textarea className={`${styles.field} ${styles.area}`} value={content} onChange={(e) => setContent(e.target.value)} maxLength={contentMax} placeholder="상세한 시술 경험을 공유해주세요." aria-label="내용" />
        <div className={styles.counter}>{Array.from(content).length}자 / 최소 {contentMin}자</div>
      </section>

      <section className={styles.sec}>
        <div className={styles.label}>시술 전후 사진 <span className={styles.note}>(최대 4장, 각 5MB 이하)</span></div>
        {grid("시술 전", before, setBefore, "시술 전 사진")}
        {grid("시술 후", after, setAfter, "시술 후 사진")}
        {edit && <div className={styles.note}>새 사진을 올리면 해당 구분의 기존 사진이 교체됩니다.</div>}
        {hasPhotos && (
          <label className={styles.check}>
            <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />
            <span>시술 후기 사진을 첨부합니다. 등록된 사진은 후기와 함께 공개됩니다.</span>
          </label>
        )}
      </section>

      <label className={`${styles.check} ${styles.agree}`}>
        <input type="checkbox" checked={agree} onChange={(e) => setAgree(e.target.checked)} />
        <span><a href={phpUrl(php, "community_guidelines.php")} target="_blank" rel="noreferrer">커뮤니티 가이드라인</a>을 읽었으며, 허위·광고·욕설 등 부적절한 콘텐츠 작성 시 삭제 및 계정 제재에 동의합니다.</span>
      </label>
      <p className={styles.note}>등록된 후기는 관리자 승인 후 공개됩니다.</p>
      {error && <div className={styles.error} role="alert">{error}</div>}
      <button type="submit" className={styles.submit} disabled={busy}>{busy ? "저장 중…" : edit ? "수정했어요" : "작성했어요"}</button>

      {sheet && (
        <div className={styles.modal} onClick={(e) => { if (e.target === e.currentTarget) setSheet(false); }}>
          <section className={styles.sheet} role="dialog" aria-modal="true" aria-labelledby="rw-doc-title">
            <h2 id="rw-doc-title">어떤 원장님께 시술받으셨나요?</h2>
            <div className={styles.dlist}>
              {data.doctors.length === 0 ? (
                <div className={styles.dempty}>등록된 의사가 없습니다.<br />담당 의사 모름을 선택해주세요.</div>
              ) : data.doctors.map((d) => (
                <button key={d.id} type="button" aria-pressed={draft.includes(d.id)} className={`${styles.dcard} ${draft.includes(d.id) ? styles.dcardOn : ""}`} onClick={() => toggleDraft(d.id)}>
                  <span className={styles.dphoto}>
                    {d.photoUrl && (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={assetUrl(d.photoUrl) ?? ""} alt="" />
                    )}
                  </span>
                  <span className={styles.dcopy}>
                    <span className={styles.dname}>{d.name}</span>
                    <span className={styles.dspec}>{d.specialty}</span>
                    {d.tags.length > 0 && <span className={styles.dtags}>{d.tags.map((t) => <span key={t}>{t}</span>)}</span>}
                  </span>
                </button>
              ))}
            </div>
            <button type="button" className={styles.dconfirm} disabled={draft.length === 0} onClick={confirmSheet}>선택했어요</button>
          </section>
        </div>
      )}
    </form>
  );
}
