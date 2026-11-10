"use client";
import Link from "next/link";
import { useToast } from "./Toast";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import DetailSkeleton from "./DetailSkeleton";
import { api, ApiError, assetUrl, PLACEHOLDER, qk, type DetailTab, type HospitalDetail as Detail } from "@/lib/api";
import ReviewList from "./ReviewList";
import WishlistButton from "./WishlistButton";
import ConsultSheet from "./ConsultSheet";
import TreatmentList from "./TreatmentList";
import DoctorList, { DoctorMiniList } from "./DoctorList";
import HospitalMap from "./HospitalMap";
import TreatmentEvent from "./TreatmentEvent";
import styles from "./HospitalDetail.module.css";
import starIcon from "@/assets/icons/star.png";
import trophyIcon from "@/assets/icons/trophy.png";
import shareIcon from "@/assets/icons/share.png";
import { addrDistrict } from "./HospitalCard";

const DAYS = ["월", "화", "수", "목", "금", "토", "일"];
// design 04-01 tab order: 홈 · 의료진 · 시술목록 · 시술후기
const TAB_LABEL: Record<DetailTab, string> = { home: "홈", doctors: "의료진", treatments: "시술목록", reviews: "시술후기" };

const RANGE = /(\d{1,2}):(\d{2})\s*[~\-–]\s*(\d{1,2}):(\d{2})/;
const parseRange = (s: string | undefined): [number, number] | null => {
  const m = (s ?? "").match(RANGE);
  if (!m) return null;
  const from = +m[1] * 60 + +m[2]; let to = +m[3] * 60 + +m[4];
  if (to <= from) to += 24 * 60;                      // overnight, e.g. 18:00 ~ 02:00
  return [from, to];
};
/**
 * 진료중 / 점심시간 / 진료종료 by Seoul time. Yesterday's overnight range wins while it is still running
 * (Mon 18:00 ~ 02:00 still counts at Tue 01:00 even when Tue itself is 휴무); otherwise today's range decides.
 * "휴무" / unparseable → null. Returns the range that applies so the row can show it.
 */
function openState(hours: Record<string, unknown>, todayIdx: number, lunch: string | null, now: Date): { state: "open" | "lunch" | "closed"; hoursText: string } | null {
  const kst = new Intl.DateTimeFormat("en-GB", { hour: "2-digit", minute: "2-digit", hour12: false, timeZone: "Asia/Seoul" }).format(now);
  const [hh, mm] = kst.split(":").map(Number);
  const cur = hh * 60 + mm;
  const text = (i: number) => (typeof hours[DAYS[(i + 7) % 7]] === "string" ? (hours[DAYS[(i + 7) % 7]] as string) : undefined);
  const inLunch = (c: number) => { const l = parseRange(lunch ?? undefined); return !!l && c >= l[0] && c < l[1]; };
  // yesterday's range that runs past midnight
  const yText = text(todayIdx - 1), y = parseRange(yText);
  if (y && y[1] > 24 * 60 && cur + 24 * 60 < y[1]) return { state: inLunch(cur + 24 * 60) ? "lunch" : "open", hoursText: yText! };
  const tText = text(todayIdx), t = parseRange(tText);
  if (!t) return null;
  if (cur < t[0] || cur >= t[1]) return { state: "closed", hoursText: tText! };
  return { state: inLunch(cur) ? "lunch" : "open", hoursText: tText! };
}
const STATE_LABEL = { open: "진료중", lunch: "점심시간", closed: "진료종료" } as const;

/** Re-renders once a minute so 진료중 / 진료종료 follows the clock while the page stays open. */
function useNow(): Date {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => { const t = window.setInterval(() => setNow(new Date()), 60_000); return () => window.clearInterval(t); }, []);
  return now;
}

/** design 04-01: rounded image strip with a thin progress line (scroll-snap, no JS carousel). */
function HeroSlider({ images }: { images: string[] }) {
  const ref = useRef<HTMLDivElement>(null);
  const [idx, setIdx] = useState(0);
  useEffect(() => {
    const el = ref.current; if (!el) return;
    const onScroll = () => setIdx(Math.round(el.scrollLeft / Math.max(1, el.clientWidth)));
    el.addEventListener("scroll", onScroll, { passive: true });
    return () => el.removeEventListener("scroll", onScroll);
  }, []);
  const n = images.length;
  return (
    <div className={styles.slider}>
      <div ref={ref} className={styles.slides} aria-roledescription="carousel" aria-label="병원 사진">
        {images.map((src, i) => (
          // eslint-disable-next-line @next/next/no-img-element
          <img key={i} src={src} alt="" className={styles.slide} loading={i === 0 ? "eager" : "lazy"} decoding="async"
            onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
        ))}
      </div>
      {n > 1 && (
        <div className={styles.progress} aria-hidden="true">
          <span className={styles.progressBar} style={{ width: `${100 / n}%`, left: `${(100 / n) * Math.min(idx, n - 1)}%` }} />
        </div>
      )}
    </div>
  );
}

/** `treatmentId` = detail.php?treatment_id= : the 홈 tab opens with that treatment's 이벤트 block (design 05-08). */
export default function HospitalDetail({ id, initial, initialTab = "home", treatmentId = 0 }: { id: number; initial?: Detail; initialTab?: DetailTab; treatmentId?: number }) {
  const router = useRouter();
  const qc = useQueryClient();
  const [tab, setTab] = useState<DetailTab>(initialTab);
  const [seen, setSeen] = useState<DetailTab[]>([initialTab]);
  const [consultOpen, setConsultOpen] = useState(false);
  const [hoursOpen, setHoursOpen] = useState(false);
  const showToast = useToast();
  const now = useNow();
  const { data: h, error, isError, refetch } = useQuery({
    queryKey: qk.hospital(id),
    queryFn: () => api.hospital(id),
    initialData: initial,
    retry: (n: number, e: unknown) => !(e instanceof ApiError && (e.status === 404 || e.status === 400)) && n < 1,
  });
  // every tab's data is fetched as soon as the page is open, so a tab opens with its content already there (no skeleton flash)
  const ready = !!h;
  useEffect(() => {
    if (!ready) return;
    void qc.prefetchQuery({ queryKey: qk.doctors(id), queryFn: () => api.doctors(id) });
    void qc.prefetchQuery({ queryKey: qk.treatments(id), queryFn: () => api.treatments(id) });
    void qc.prefetchQuery({ queryKey: qk.reviews(id), queryFn: () => api.reviews(id) });
  }, [ready, id, qc]);
  const missing = error instanceof ApiError && (error.status === 404 || error.status === 400);

  if (!h) {
    if (!isError) return <DetailSkeleton />;
    return (
      <div className={styles.empty}>
        {missing ? "병원을 찾을 수 없습니다." : <>불러오지 못했습니다. <button className={styles.link} onClick={() => refetch()}>다시 시도</button></>}
      </div>
    );
  }

  const hero = assetUrl(h.thumbnailUrl || h.mainImageUrl || h.profileImageUrl) || PLACEHOLDER;
  const slides = (h.images.length ? h.images.map((src) => assetUrl(src) || "").filter(Boolean) : [hero]).slice(0, 10);
  const bh = (h.businessHours && typeof h.businessHours === "object" ? h.businessHours : {}) as Record<string, unknown>;
  const hours = DAYS.filter((d) => typeof bh[d] === "string").map((d) => [d, bh[d] as string] as const);
  const lunch = typeof bh["점심"] === "string" ? (bh["점심"] as string) : null;
  const today = new Intl.DateTimeFormat("ko-KR", { weekday: "short", timeZone: "Asia/Seoul" }).format(now);
  const todayHours = typeof bh[today] === "string" ? (bh[today] as string) : undefined;
  const st = openState(bh, DAYS.indexOf(today), lunch, now);
  const isBest = h.badges.some((b) => b.includes("우수"));
  const district = addrDistrict(h.address);
  const copyAddress = async () => {
    try { await navigator.clipboard.writeText(h.address ?? ""); showToast("주소를 복사했습니다."); }
    catch { showToast("주소를 복사하지 못했습니다."); }
  };
  const back = () => (window.history.length > 1 ? router.back() : router.push("/hospitals"));
  const share = async () => {
    const url = window.location.href;
    if (typeof navigator.share === "function") {
      // The native sheet handles it; a dismissed sheet (AbortError) is not a request to copy the link.
      try { await navigator.share({ title: h.name, url }); } catch { /* dismissed or unsupported payload */ }
      return;
    }
    try { await navigator.clipboard.writeText(url); showToast("링크를 복사했습니다."); }
    catch { showToast("링크를 복사하지 못했습니다."); }
  };
  const selectTab = (t: DetailTab) => {
    setTab(t);
    setSeen((s) => (s.includes(t) ? s : [...s, t]));
    if (t === "treatments") qc.prefetchQuery({ queryKey: qk.treatments(id), queryFn: () => api.treatments(id) });
    if (t === "doctors") qc.prefetchQuery({ queryKey: qk.doctors(id), queryFn: () => api.doctors(id) });
    try {
      const u = new URL(window.location.href);
      if (t === "home") u.searchParams.delete("tab"); else u.searchParams.set("tab", t);   // treatment_id stays in the URL
      window.history.replaceState(window.history.state, "", u);
    } catch { /* ignore */ }
  };

  return (
    <article className={styles.root}>
      {/* design 04-01: back · centred name · spacer; tabs right under it; then the shared info block */}
      <header className={styles.top}>
        <button onClick={back} className={styles.back} aria-label="뒤로 가기">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
        <h1 className={styles.title}>{h.name}</h1>
        <button type="button" className={styles.topBtn} onClick={share} aria-label="공유">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={shareIcon.src} alt="" width={22} height={22} />
        </button>
      </header>

      <nav className={styles.tabs} role="tablist">
        <span className={styles.tabBar} style={{ transform: `translateX(${Object.keys(TAB_LABEL).indexOf(tab) * 100}%)` }} aria-hidden="true" />
        {(Object.keys(TAB_LABEL) as DetailTab[]).map((t) => (
          <button key={t} role="tab" aria-selected={tab === t} className={`${styles.tab} ${tab === t ? styles.tabActive : ""}`} onClick={() => selectTab(t)}>
            {TAB_LABEL[t]}
          </button>
        ))}
      </nav>

      <HeroSlider images={slides} />

      <div className={styles.nameRow}>
        <span className={styles.name}>{h.name}</span>
        <WishlistButton hospitalId={h.id} initialCount={h.wishlistCount} />
      </div>
      {((h.showRating && h.rating != null && h.rating > 0) || isBest) && (
        <div className={styles.ratingRow}>
          {h.showRating && h.rating != null && h.rating > 0 && (
            <span className={styles.rating}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={starIcon.src} alt="" width={15} height={15} /><b>{h.rating.toFixed(1)}</b><span className={styles.ratingOf}>/ 5.0</span>
            </span>
          )}
          {isBest && (
            <span className={styles.best}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={trophyIcon.src} alt="" width={15} height={15} />고객평가우수병원
            </span>
          )}
        </div>
      )}
      {hours.length > 0 && (
        <div className={styles.hoursBlock}>
          <button type="button" className={styles.hoursRow} onClick={() => setHoursOpen((v) => !v)} aria-expanded={hoursOpen} aria-controls="detail-hours">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="#4A4446" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
            {st && <span className={styles.openState}>{STATE_LABEL[st.state]}</span>}
            <span className={styles.hoursText}>{st ? st.hoursText : (todayHours ?? "휴무")}</span>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#1A1718" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className={hoursOpen ? styles.chevUp : undefined}><path d="M6 9l6 6 6-6" /></svg>
          </button>
          {hoursOpen && (
            <dl id="detail-hours" className={styles.hours}>
              {hours.map(([d, v]) => (<div key={d} className={d === today ? styles.todayRow : undefined}><dt>{d}</dt><dd>{v}</dd></div>))}
              {lunch && <div><dt>점심</dt><dd>{lunch}</dd></div>}
            </dl>
          )}
        </div>
      )}

      {seen.includes("home") && (
        <div hidden={tab !== "home"}>
          {treatmentId > 0 && <TreatmentEvent hospitalId={id} treatmentId={treatmentId} />}
          {/* design 05-04: 시술 병원 summary, address + 주소복사, 병원소개, 의료진 소개 */}
          <h2 className={styles.secTitleLg}>시술 병원</h2>
          <div className={styles.hospCard}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={hero} alt="" className={styles.hospThumb} loading="lazy" decoding="async"
              onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
            <div className={styles.hospInfo}>
              <div className={styles.hospName}>{h.name}</div>
              {h.address && <div className={styles.hospAddr}>{district && <><span>{district}</span><span className={styles.sep}> | </span></>}{h.address}</div>}
              <div className={styles.hospMeta}>
                {h.showRating && h.rating != null && h.rating > 0 && (
                  <span className={styles.rating}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={starIcon.src} alt="" width={13} height={13} /><b>{h.rating.toFixed(1)}</b>
                    {h.reviewCount > 0 && <span className={styles.ratingOf}>({h.reviewCount.toLocaleString("ko-KR")})</span>}
                  </span>
                )}
                {isBest && (
                  <span className={styles.best}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={trophyIcon.src} alt="" width={13} height={13} />고객평가우수병원
                  </span>
                )}
              </div>
            </div>
          </div>
          {h.address && (
            <div className={styles.addrRow}>
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#1A1718" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>
              <span className={styles.addrText}>{h.address}</span>
              <button type="button" className={styles.pill} onClick={copyAddress}>주소복사</button>
            </div>
          )}
          {/* design 05-04: map right under the address (detail.php 오시는 길) */}
          {(!!h.address || (!!h.latitude && !!h.longitude)) && <HospitalMap hospital={h} />}
          {h.introduction && (
            <>
              <h2 className={styles.secTitle}>병원소개</h2>
              <p className={styles.intro}>{h.introduction}</p>
            </>
          )}
          <DoctorMiniList hospitalId={id} />
        </div>
      )}
      {seen.includes("doctors") && <div hidden={tab !== "doctors"}><DoctorList hospitalId={id} /></div>}
      {seen.includes("treatments") && <div hidden={tab !== "treatments"}><TreatmentList hospitalId={id} hospital={h} /></div>}
      {seen.includes("reviews") && <div hidden={tab !== "reviews"}><ReviewList hospitalId={id} /></div>}

      {/* design 04-01: 상담신청 (accent) · 예약하기 (grey). Booking still lives in PHP (same origin keeps the app's session). */}
      <div className={styles.cta}>
        <button type="button" className={styles.ctaPrimary} onClick={() => setConsultOpen(true)}>상담신청</button>
        <Link href={`/booking?id=${id}&from=hospital`} className={styles.ctaSecondary}>예약하기</Link>
      </div>
      <ConsultSheet hospital={h} open={consultOpen} onClose={() => setConsultOpen(false)} />
    </article>
  );
}
