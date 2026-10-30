"use client";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import DetailSkeleton from "./DetailSkeleton";
import { api, ApiError, assetUrl, qk, type EventDetail as Detail } from "@/lib/api";
import { useMe, useRequireLogin } from "@/lib/me";
import styles from "./EventDetail.module.css";

const FALLBACK = "data:image/svg+xml;utf8," + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400"><rect fill="#f0f0f0" width="640" height="400"/><text fill="#aaa" font-size="16" x="50%" y="50%" text-anchor="middle" dominant-baseline="middle">No Image</text></svg>');

/** event_detail.php formatPhone(): digits only, 3-4-4 hyphens, max 11 digits. */
export function formatPhone(raw: string): string {
  const v = raw.replace(/[^0-9]/g, "").slice(0, 11);
  if (v.length <= 3) return v;
  if (v.length <= 7) return `${v.slice(0, 3)}-${v.slice(3)}`;
  return `${v.slice(0, 3)}-${v.slice(3, 7)}-${v.slice(7)}`;
}
const dots = (d: string | null) => (d ? d.replace(/-/g, ".") : "");
const won = (n: number) => n.toLocaleString("ko-KR");

export default function EventDetail({ id, initial }: { id: number; initial?: Detail }) {
  const router = useRouter();
  const qc = useQueryClient();
  const me = useMe();
  const requireLogin = useRequireLogin();
  const { data: ev, error, isError, refetch } = useQuery({ queryKey: qk.event(id), queryFn: () => api.event(id), initialData: initial, retry: (n: number, e: unknown) => !(e instanceof ApiError && (e.status === 404 || e.status === 400)) && n < 1, });
  const missing = error instanceof ApiError && (error.status === 404 || error.status === 400);

  const [shot, setShot] = useState(0);
  const [name, setName] = useState<string | null>(null);
  const [phone, setPhone] = useState<string | null>(null);
  const [email, setEmail] = useState("");
  const [toast, setToast] = useState<{ msg: string; kind: "ok" | "err" } | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => () => { if (timer.current) clearTimeout(timer.current); }, []);
  const say = (msg: string, kind: "ok" | "err") => {
    setToast({ msg, kind });
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => setToast(null), 3000);
  };

  const enter = useMutation({
    mutationFn: (v: { name: string; phone: string; email: string }) => api.enterEvent(id, v),
    onSuccess: () => {
      say("응모가 완료되었습니다!", "ok");
      qc.setQueryData<Detail>(qk.event(id), (p) => (p && p.raffle ? { ...p, raffle: { ...p.raffle, entered: true, entryCount: p.raffle.entryCount + 1 } } : p));
    },
    onError: (e) => say(e instanceof ApiError ? e.message : "서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.", "err"),
  });

  if (!ev) {
    if (!isError) return <DetailSkeleton />;
    return (
      <div className={styles.empty}>
        {missing ? "이벤트를 찾을 수 없습니다." : <>불러오지 못했습니다. <button className={styles.retry} onClick={() => refetch()}>다시 시도</button></>}
      </div>
    );
  }

  const main = assetUrl(ev.imageUrl);
  const shots = main ? [main, ...ev.images.map((i) => assetUrl(i.imageUrl) ?? "").filter(Boolean)] : [];
  const cur = Math.min(shot, Math.max(0, shots.length - 1));
  const r = ev.raffle;
  const hasPrice = ev.originalPrice > 0 || ev.discountPrice > 0;
  const back = () => (window.history.length > 1 ? router.back() : router.push("/events"));

  const nameVal = name ?? me.data?.user?.name ?? "";
  const phoneVal = phone ?? formatPhone(me.data?.user?.phone ?? "");
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nameVal.trim()) return say("이름을 입력해주세요.", "err");
    if (phoneVal.replace(/[^0-9]/g, "").length < 10) return say("올바른 전화번호를 입력해주세요.", "err");
    void requireLogin(() => enter.mutateAsync({ name: nameVal.trim(), phone: phoneVal.replace(/[^0-9]/g, ""), email: email.trim() })).catch(() => {});
  };

  return (
    <article className={styles.root}>
      <header className={styles.top}>
        <button type="button" onClick={back} className={styles.back} aria-label="뒤로가기">←</button>
        <div className={styles.topTitle}>{ev.title}</div>
      </header>

      {shots.length > 0 && (
        <>
          <div className={styles.gallery}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={shots[cur]} alt={ev.title} className={styles.main} onClick={() => setShot((cur + 1) % shots.length)}
              onError={(e) => { if (e.currentTarget.src !== FALLBACK) e.currentTarget.src = FALLBACK; }}
              ref={(el) => { if (el && el.complete && el.naturalWidth === 0 && el.src !== FALLBACK) el.src = FALLBACK; /* failed before hydration */ }} />
            {shots.length > 1 && <div className={styles.counter}>{cur + 1} / {shots.length}</div>}
          </div>
          {shots.length > 1 && (
            <div className={styles.thumbs}>
              {shots.map((s, i) => (
                <button key={`${s}-${i}`} type="button" className={`${styles.thumb} ${i === cur ? styles.thumbOn : ""}`} onClick={() => setShot(i)} aria-label={`이미지 ${i + 1}`}>
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={s} alt="" />
                </button>
              ))}
            </div>
          )}
        </>
      )}

      <section className={styles.content}>
        {ev.category && <span className={styles.badge} style={{ background: ev.categoryColor }}>{ev.category}</span>}
        <h1 className={styles.h1}>{ev.title}</h1>
        {ev.subtitle && <p className={styles.sub}>{ev.subtitle}</p>}
        {ev.description && <div className={styles.desc}>{ev.description}</div>}
      </section>

      {hasPrice && (
        <section className={styles.section}>
          <div className={styles.label}>가격 안내</div>
          <div className={styles.priceRow}>
            {ev.originalPrice > 0 && ev.discountPrice > 0 && ev.originalPrice > ev.discountPrice ? (
              <>
                <span className={styles.orig}>{won(ev.originalPrice)}원</span>
                <span className={styles.disc}>{won(ev.discountPrice)}<span className={styles.unit}>원</span></span>
                {ev.discountRate > 0 && <span className={styles.rate}>{Math.trunc(ev.discountRate)}% OFF</span>}
              </>
            ) : (
              <span className={styles.disc}>{won(ev.discountPrice > 0 ? ev.discountPrice : ev.originalPrice)}<span className={styles.unit}>원</span></span>
            )}
          </div>
        </section>
      )}

      {(ev.startDate || ev.endDate) && (
        <section className={styles.section}>
          <div className={styles.periodRow}>
            <span className={styles.periodIcon} aria-hidden>📅</span>
            <div className={styles.dates}>{dots(ev.startDate)} ~ {dots(ev.endDate)}</div>
            <span className={`${styles.status} ${ev.ended ? styles.ended : styles.active}`}>{ev.ended ? "종료" : "진행중"}</span>
          </div>
        </section>
      )}

      {r && (
        <section className={styles.section}>
          <div className={styles.raffleHead}>
            <span className={styles.raffleIcon} aria-hidden>{r.typeIcon}</span>
            <h2>응모 이벤트</h2>
          </div>
          <div className={styles.typeDesc}><strong>{r.typeLabel}</strong> — {r.typeDesc}</div>
          <div className={styles.infoGrid}>
            <div className={styles.infoCard}><div className={styles.infoLabel}>응모 유형</div><div className={styles.infoValue}>{r.typeLabel}</div></div>
            <div className={styles.infoCard}><div className={styles.infoLabel}>당첨자 수</div><div className={styles.infoValue}>{r.winnerCount}명</div></div>
            <div className={styles.infoCard}><div className={styles.infoLabel}>현재 응모</div><div className={styles.infoValue}>{r.entryCount}{r.maxEntries > 0 ? ` / ${r.maxEntries}` : ""}</div></div>
            <div className={styles.infoCard}><div className={styles.infoLabel}>응모 마감</div><div className={styles.infoValue}>{r.endDate ? r.endDate.slice(5).replace("-", ".") : "-"}</div></div>
          </div>
          {r.prize && (
            <div className={styles.prize}>
              <div className={styles.prizeTitle}>🎁 경품 안내</div>
              <div className={styles.prizeBody}>{r.prize}</div>
            </div>
          )}
          {r.maxEntries > 0 && (
            <div className={styles.progress}>
              <div className={styles.bar}><div className={styles.fill} style={{ width: `${Math.min(100, (r.entryCount / Math.max(1, r.maxEntries)) * 100)}%` }} /></div>
              <div className={styles.progressText}><span>{r.entryCount}명 응모</span><span>{r.maxEntries}명 정원</span></div>
            </div>
          )}
          {r.entered ? (
            <div className={styles.stat}>
              <div className={styles.statIcon} aria-hidden>✅</div>
              <div className={`${styles.statText} ${styles.okText}`}>응모가 완료되었습니다</div>
              <div className={styles.statDesc}>당첨 결과는 마감일 이후 발표됩니다.</div>
            </div>
          ) : r.closed ? (
            <div className={styles.stat}>
              <div className={styles.statIcon} aria-hidden>🚫</div>
              <div className={`${styles.statText} ${styles.noText}`}>응모가 마감되었습니다</div>
              <div className={styles.statDesc}>{r.closedReason === "period" ? "응모 기간이 종료되었습니다." : "최대 응모 인원에 도달했습니다."}</div>
            </div>
          ) : (
            <form className={styles.form} onSubmit={submit} autoComplete="off">
              {me.data && !me.data.loggedIn && <p className={styles.hint}>응모는 로그인 후 가능해요. 응모하기를 누르면 로그인 화면으로 이동합니다.</p>}
              <div className={styles.group}>
                <label className={styles.flabel} htmlFor="ev-name">이름 <span className={styles.req}>*</span></label>
                <input id="ev-name" className={styles.input} type="text" name="user_name" placeholder="실명을 입력해주세요" maxLength={50} required value={nameVal} onChange={(e) => setName(e.target.value)} />
              </div>
              <div className={styles.group}>
                <label className={styles.flabel} htmlFor="ev-phone">연락처 <span className={styles.req}>*</span></label>
                <input id="ev-phone" className={styles.input} type="tel" inputMode="numeric" name="user_phone" placeholder="010-0000-0000" maxLength={13} required value={phoneVal} onChange={(e) => setPhone(formatPhone(e.target.value))} />
              </div>
              <div className={styles.group}>
                <label className={styles.flabel} htmlFor="ev-email">이메일</label>
                <input id="ev-email" className={styles.input} type="email" name="user_email" placeholder="example@email.com" value={email} onChange={(e) => setEmail(e.target.value)} />
              </div>
              <button type="submit" className={styles.submit} disabled={enter.isPending} aria-busy={enter.isPending}>{enter.isPending ? "처리 중..." : "응모하기"}</button>
            </form>
          )}
        </section>
      )}

      {ev.hospitalName && (
        <section className={`${styles.section} ${styles.hospital}`}>
          <Link href={ev.hospitalId ? `/hospitals/${ev.hospitalId}` : "/hospitals"}>
            <span className={styles.hospIcon} aria-hidden>🏥</span>
            <span className={styles.hospName}>{ev.hospitalName}</span>
            <span className={styles.hospArrow}>›</span>
          </Link>
        </section>
      )}

      {toast && <div className={`${styles.toast} ${toast.kind === "ok" ? styles.toastOk : styles.toastErr}`} role="status" aria-live="polite">{toast.msg}</div>}
    </article>
  );
}
