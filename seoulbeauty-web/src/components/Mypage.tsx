"use client";
import { useEffect, useRef } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type MypageSummary } from "@/lib/api";
import { useMe } from "@/lib/me";
import { phpUrl, usePhpBase } from "@/lib/php";
import { siteHref } from "@/lib/phpLink";
import { observeImpressions, trackClick } from "@/lib/adTracking";
import Carousel from "./Carousel";
import LoginPrompt from "./LoginPrompt";
import styles from "./Mypage.module.css";

const ICON = (name: string) => assetUrl(`assets/icons/${name}.png`) ?? "";
const ARROW = (
  <svg className={styles.chev} viewBox="0 0 24 24" fill="none" stroke="#1A1718" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
);
const seoulMonth = () => Number(new Intl.DateTimeFormat("en-US", { month: "numeric", timeZone: "Asia/Seoul" }).format(new Date()));

/** mypage.php: profile, counters, the mypage ad carousel and the menu. Heavier sub-pages (bookings, points, notifications, events) stay in PHP. */
export default function Mypage({ initial, anon }: { initial?: MypageSummary; anon?: boolean }) {
  const php = usePhpBase();
  // /me only decides which layout to show; /v1/mypage runs in parallel (no waterfall) and a failing /me never blocks it.
  const me = useMe();
  const meKnownOut = !!me.data && !me.data.loggedIn;
  const { data, error, refetch } = useQuery({ queryKey: qk.mypage, queryFn: () => api.mypage(), initialData: initial, retry: false, enabled: !anon && !meKnownOut });
  const rootRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!rootRef.current || !data) return;
    return observeImpressions(php, rootRef.current);
  }, [data, php]);

  const loggedOut = anon || meKnownOut || (error instanceof ApiError && error.status === 401);
  const resolving = !loggedOut && !data && !error && me.isPending; // nothing known yet (a /me error is not 'pending': the summary/error path takes over)
  const row = (href: string, icon: string | null, text: string, badge?: string, next = false) => {
    const inner = (
      <>
        {icon !== null && (
          <span className={styles.rowIcon}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={ICON(icon)} alt="" aria-hidden="true" />
          </span>
        )}
        <span className={styles.rowText}>{text}</span>
        {badge && <span className={styles.badge}>{badge}</span>}
        {ARROW}
      </>
    );
    return next ? <Link href={href} className={styles.row}>{inner}</Link> : <a href={phpUrl(php, href)} className={styles.row}>{inner}</a>;
  };
  const support = (
    <div className={styles.card}>
      {row("/qna", null, "고객센터", undefined, true)}
      {row("/faq", null, "자주 묻는 질문", undefined, true)}
    </div>
  );

  return (
    <div className={styles.my} ref={rootRef}>
      <div className={styles.head}>
        <span className={styles.headTitle}>마이페이지</span>
        {!loggedOut && !resolving && <Link href="/mypage/settings" className={styles.headSide} aria-label="설정">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={ICON("icon-mypage-settings")} alt="" width={24} height={24} /></Link>}
      </div>

      <div className={styles.canvas}>
      {resolving ? null : loggedOut ? (
        <>
          <LoginPrompt />
          {support}
        </>
      ) : !data && error ? (
        <div className={styles.empty}>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></div>
      ) : (
        <>
          <div className={styles.card}>
          <Link href="/mypage/profile" className={styles.profile}>
            <span className={styles.avatar}>
              {!data ? <span className="skeleton" style={{ display: "block", width: "100%", height: "100%", borderRadius: "50%" }} /> : data.avatarUrl ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={assetUrl(data.avatarUrl) ?? ""} alt="프로필" />
              ) : Array.from(data.name)[0]}
            </span>
            <span className={styles.pname}>{data ? data.name : <span className="skeleton" style={{ display: "inline-block", width: 96, height: 18, borderRadius: 4 }} />}</span>
            {ARROW}
          </Link>
          </div>

          <div className={styles.card}>
            <div className={styles.sectionTitle}>병원 이용</div>
            {row("/mypage/bookings", "icon-mypage-booking", "내 예약 / 결제 내역", data && data.bookingCount > 0 ? `예약 ${data.bookingCount.toLocaleString("ko-KR")}건` : undefined, true)}
            {data?.points.visible && row("/mypage/points", "icon-mypage-point", "포인트", `${data.points.balance.toLocaleString("ko-KR")}P`, true)}
            {row("/mypage/wishlist", "icon-mypage-wishlist", "병원 찜", undefined, true)}
            {row("/mypage/reviews", "icon-mypage-review", "내가 작성한 후기", undefined, true)}
            {row("/mypage/consultations", data && data.consultCount > 0 ? "icon-mypage-consult-active" : "icon-mypage-consult", "상담 내역", data && data.consultCount > 0 ? `${data.consultCount.toLocaleString("ko-KR")}건` : undefined, true)}
          </div>

          {/* mypage ad slot (admin banner management → 마이페이지): the carousel when ads exist, else the design's text card.
              Only once the summary is loaded: the static shell must not bake the build month in. */}
          {data && (data.ads.show && data.ads.items.length > 0 ? (
            <div className={styles.ads}>
              <Carousel auto={data.ads.autoScroll} label="광고">
                {data.ads.items.map((ad) => {
                  const img = assetUrl(ad.imageUrl);
                  const href = siteHref(php, ad.linkUrl);
                  return (
                    <a key={ad.id} href={href} className={styles.ad} data-ad-id={ad.id} onClick={() => trackClick(php, ad.id, href)}>
                      {img ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={img} alt={ad.title} className={styles.adImg} />
                      ) : (
                        <div className={styles.adText}><div className={styles.adTitle}>{ad.title}</div>{ad.subtitle && <div className={styles.adSub}>{ad.subtitle}</div>}</div>
                      )}
                    </a>
                  );
                })}
              </Carousel>
            </div>
          ) : (
            <div className={`${styles.card} ${styles.cardFlat}`}>
              <div className={styles.banner}>
                <div className={styles.bannerSub}>상담부터 시술 결과까지, 오직 고객 후기로만</div>
                <div className={styles.bannerTitle}>고객들이 선택!<br />{seoulMonth()}월 고객평가 우수병원</div>
              </div>
            </div>
          ))}
          <div className={styles.card}>
            <div className={styles.sectionTitle}>커뮤니티</div>
            {row("/mypage/community", "icon-mypage-community", "MY 커뮤니티 글", undefined, true)}
            {row("/mypage/wishlist", "icon-mypage-like", "MY 좋아요 목록", undefined, true)}
          </div>
          {support}
        </>
      )}
      </div>
    </div>
  );
}
