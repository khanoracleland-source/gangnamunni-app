"use client";
import { useEffect, useRef } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type MypageSummary } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { observeImpressions, trackClick } from "@/lib/adTracking";
import Carousel from "./Carousel";
import LoginPrompt from "./LoginPrompt";
import styles from "./MyPages.module.css";

const ICON = (name: string) => assetUrl(`assets/icons/${name}.png`) ?? "";
const ARROW = <span className={styles.arrow} aria-hidden="true">›</span>;

/** mypage.php: profile, counters, the mypage ad carousel and the menu. Heavier sub-pages (bookings, points, notifications, events) stay in PHP. */
export default function Mypage({ initial, anon }: { initial?: MypageSummary; anon?: boolean }) {
  const php = usePhpBase();
  const { data, error, refetch } = useQuery({ queryKey: qk.mypage, queryFn: () => api.mypage(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const rootRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!rootRef.current || !data) return;
    return observeImpressions(php, rootRef.current);
  }, [data, php]);

  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
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
    <div className={`${styles.section} ${styles.sectionTight}`}>
      {row("/qna", null, "고객센터", undefined, true)}
      {row("/faq", null, "자주 묻는 질문", undefined, true)}
    </div>
  );

  return (
    <div className={styles.wrap} ref={rootRef}>
      <div className={styles.head}>
        <span className={styles.headTitle}>마이페이지</span>
        {!loggedOut && <Link href="/mypage/settings" className={styles.headSide} aria-label="설정">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={ICON("icon-mypage-settings")} alt="" width={30} height={30} /></Link>}
      </div>

      {loggedOut ? (
        <>
          <LoginPrompt />
          {support}
        </>
      ) : !data ? (
        <div className={styles.empty}>
          {error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}
        </div>
      ) : (
        <>
          <Link href="/mypage/profile" className={styles.profile}>
            <span className={styles.avatar}>
              {data.avatarUrl ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={assetUrl(data.avatarUrl) ?? ""} alt="프로필" />
              ) : Array.from(data.nickname)[0]}
            </span>
            <span className={styles.pname}>{data.nickname}</span>
            {ARROW}
          </Link>

          <div className={styles.section}>
            <div className={styles.sectionTitle}>병원 이용</div>
            {row("/mypage/bookings", "icon-mypage-booking", "내 예약 / 결제 내역", data.bookingCount > 0 ? `예약 ${data.bookingCount.toLocaleString("ko-KR")}건` : undefined, true)}
            {data.points.visible && row("/mypage/points", "icon-mypage-point", "포인트", `${data.points.balance.toLocaleString("ko-KR")}P`, true)}
            {row("/mypage/wishlist", "icon-mypage-wishlist", "병원 찜", undefined, true)}
            {row("/mypage/reviews", "icon-mypage-review", "내가 작성한 후기", undefined, true)}
            {row("/mypage/consultations", data.consultCount > 0 ? "icon-mypage-consult-active" : "icon-mypage-consult", "상담 내역", data.consultCount > 0 ? `${data.consultCount.toLocaleString("ko-KR")}건` : undefined, true)}
          </div>

          {data.ads.show && (
            <div className={styles.ads}>
              <Carousel auto={data.ads.autoScroll} label="광고">
                {data.ads.items.map((ad) => {
                  const img = assetUrl(ad.imageUrl);
                  const href = ad.linkUrl.startsWith("http") || ad.linkUrl.startsWith("/") || ad.linkUrl === "#" ? ad.linkUrl : phpUrl(php, ad.linkUrl);
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
          )}

          <div className={styles.section}>
            <div className={styles.sectionTitle}>커뮤니티</div>
            {row("/community", "icon-mypage-community", "MY 커뮤니티 글", undefined, true)}
            {row("/mypage/wishlist", "icon-mypage-like", "MY 좋아요 목록", undefined, true)}
          </div>
          {support}
        </>
      )}
    </div>
  );
}
