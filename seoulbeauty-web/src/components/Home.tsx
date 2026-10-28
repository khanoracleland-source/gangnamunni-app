"use client";
import { useEffect, useRef } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, formatWon, qk, type HomeAd, type HomeData } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { observeImpressions, trackClick } from "@/lib/adTracking";
import Carousel from "./Carousel";
import SiteHeader from "./SiteHeader";
import CategoryTable from "./CategoryTable";
import styles from "./Home.module.css";

export default function Home({ initial }: { initial?: HomeData }) {
  const php = usePhpBase();
  const { data, isError, refetch } = useQuery({ queryKey: qk.home, queryFn: () => api.home(), initialData: initial, staleTime: 60_000 });
  const rootRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!rootRef.current || !data) return;
    return observeImpressions(php, rootRef.current);
  }, [data, php]);

  if (!data) {
    return (
      <div className={styles.empty}>
        {isError ? <>불러오지 못했습니다. <button className={styles.link} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}
      </div>
    );
  }
  const { ads, promos, events, categories, settings } = data;
  const showAds = settings.adShowIndex && ads.length > 0;
  const adSlides = (list: HomeAd[]) => list.map((ad) => {
    const img = assetUrl(ad.imageUrl);
    const href = ad.linkUrl.startsWith("http") || ad.linkUrl.startsWith("/") || ad.linkUrl === "#" ? ad.linkUrl : phpUrl(php, ad.linkUrl);
    return (
      <a key={ad.id} href={href} className={styles.ad} data-ad-id={ad.id} onClick={() => trackClick(php, ad.id, href)}>
        {img ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={img} alt={ad.title} className={styles.adImg} />
        ) : (
          <div className={styles.adText}><span className={styles.adBadge}>AD</span><div className={styles.adTitle}>{ad.title}</div>{ad.subtitle && <div className={styles.adSub}>{ad.subtitle}</div>}</div>
        )}
      </a>
    );
  });

  return (
    <div ref={rootRef} className={styles.home}>
      <SiteHeader home={data} />

      {showAds && settings.adAfterSearch && <section className={styles.section}><Carousel auto={settings.adAutoScroll} label="광고">{adSlides(ads)}</Carousel></section>}

      <section className={styles.section}>
        <CategoryTable categories={categories} mode={settings.tableMode} />
      </section>

      {showAds && !settings.adAfterSearch && <section className={styles.section}><Carousel auto={settings.adAutoScroll} label="광고">{adSlides(ads)}</Carousel></section>}

      {promos.length > 0 && settings.promoShowIndex && settings.blocks.promos && (
        <section className={styles.section}>
          <Carousel auto={settings.promoAutoScroll} label="프로모션">
            {promos.map((p) => (
              <a key={p.id} href={p.linkUrl.startsWith("http") || p.linkUrl.startsWith("/") || p.linkUrl === "#" ? p.linkUrl : phpUrl(php, p.linkUrl)} className={styles.promo} style={{ background: p.background }}>
                <div className={styles.promoTitle}>{p.icon ? `${p.icon} ` : ""}{p.title}</div>
                {p.subtitle && <div className={styles.promoSub}>{p.subtitle}</div>}
              </a>
            ))}
          </Carousel>
        </section>
      )}

      {events.length > 0 && settings.blocks.events && (
        <section className={styles.section}>
          <div className={styles.sectionHead}><div className={styles.sectionTitle}>이벤트</div><Link href="/events" className={styles.more} aria-label="이벤트 더보기">›</Link></div>
          <div className={styles.eventsScroll}>
            {events.map((ev) => {
              const img = assetUrl(ev.imageUrl);
              return (
                <Link key={ev.id} href={`/events/${ev.id}`} className={styles.event}>
                  <div className={styles.eventImg}>
                    {img ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={img} alt="" loading="lazy" onError={(e) => { e.currentTarget.style.display = "none"; }} />
                    ) : "🎁"}
                    {ev.discountRate != null && ev.discountRate > 0 && <span className={styles.badge}>{Math.trunc(ev.discountRate)}%</span>}
                    {ev.hasRaffle && <span className={styles.raffle}>응모</span>}
                  </div>
                  <div className={styles.eventBody}>
                    {ev.category && <div className={styles.eventCat}>{ev.category}</div>}
                    <div className={styles.eventTitle}>{ev.title}</div>
                    {ev.hospitalAddress && <div className={styles.eventHosp}>{ev.hospitalAddress}</div>}
                    {ev.discountPrice != null && ev.discountPrice > 0 && (
                      <div className={styles.eventPrice}>
                        {ev.originalPrice != null && ev.originalPrice > 0 && <s className={styles.orig}>{formatWon(ev.originalPrice)}</s>}
                        <span className={styles.disc}>{formatWon(ev.discountPrice)}</span>
                      </div>
                    )}
                  </div>
                </Link>
              );
            })}
          </div>
        </section>
      )}

      {showAds && settings.adAfterEvents && <section className={styles.section}><Carousel auto={settings.adAutoScroll} label="광고">{adSlides(ads)}</Carousel></section>}

      <footer className={styles.company} aria-label="회사 정보">
        <div className={styles.companyLogo}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={phpUrl(php, "assets/images/logo_of_footer.png")} alt="서울미녀" />
        </div>
        <p>셀럽몰 주식회사 | 대표자: 최서진</p>
        <p>서울특별시 강남구 선릉로 612, 4층(삼성동, 한일빌딩)</p>
        <p>사업자등록번호: 308-81-44202</p>
        <p>통신판매업신고: 2018-서울강남-01913호</p>
        <p>고객센터 E-mail: <a href="mailto:info@seoulbeauty.app">info@seoulbeauty.app</a></p>
        <nav className={styles.companyLinks} aria-label="회사 정책 및 고객센터">
          <a className={styles.linkStrong} href={phpUrl(php, "qna.php")}>고객센터 문의하기</a>
          <a href={phpUrl(php, "terms_of_service.php")}>이용약관</a>
          <a href={phpUrl(php, "terms_of_service.php")}>위치기반서비스 이용약관</a>
          <a className={styles.linkStrong} href={phpUrl(php, "privacy_policy.php")}>개인정보처리방침</a>
        </nav>
        <div className={styles.notice}>
          <p>셀럽몰 주식회사는 통신판매의 당사자가 아니므로,</p>
          <p>의료기관이 등록한 시/수술 정보 및 거래 등에 대해 책임을 지지 않습니다.</p>
        </div>
        <p className={styles.copyright}>©CELEBMALL Co., Ltd. All rights reserved.</p>
      </footer>
      <Link href="/hospitals" prefetch className="srOnly">병원 목록</Link>
    </div>
  );
}
