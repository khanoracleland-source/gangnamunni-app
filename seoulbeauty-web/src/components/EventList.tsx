"use client";
import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, formatWon, qk, type EventList as Data, type EventSummary, type PageAd } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import { observeImpressions, trackClick } from "@/lib/adTracking";
import Carousel from "./Carousel";
import styles from "./EventList.module.css";

/** ad_banner.php $_gradients */
const GRADIENTS = [
  "linear-gradient(135deg, #E91E63 0%, #FF5722 100%)",
  "linear-gradient(135deg, #1565C0 0%, #42A5F5 100%)",
  "linear-gradient(135deg, #6A1B9A 0%, #AB47BC 100%)",
  "linear-gradient(135deg, #00695C 0%, #26A69A 100%)",
  "linear-gradient(135deg, #E65100 0%, #FF9800 100%)",
];

function href(category: string, raffle: boolean): string {
  const u = new URLSearchParams();
  if (category) u.set("category", category);
  if (raffle) u.set("filter", "raffle");
  const qs = u.toString();
  return `/events${qs ? `?${qs}` : ""}`;
}

function EventCard({ ev }: { ev: EventSummary }) {
  const [broken, setBroken] = useState(false);
  const img = assetUrl(ev.imageUrl);
  return (
    <Link href={`/events/${ev.id}`} className={styles.card}>
      {ev.discountRate != null && ev.discountRate > 0 && <div className={styles.badge}>{Math.trunc(ev.discountRate)}% 할인</div>}
      {ev.hasRaffle && <div className={styles.raffle}>🎰 응모</div>}
      <div className={styles.img}>
        {img && !broken ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={img} alt={ev.title} loading="lazy" onError={() => setBroken(true)}
            ref={(el) => { if (el && el.complete && el.naturalWidth === 0) setBroken(true); /* failed before hydration */ }} />
        ) : "🎁"}
      </div>
      <div className={styles.info}>
        {ev.category && <div className={styles.cat2}>{ev.category}</div>}
        <div className={styles.name}>{ev.title}</div>
        {ev.hospitalName && <div className={styles.hosp}>{ev.hospitalName}</div>}
        {ev.subtitle && <div className={styles.sub}>{ev.subtitle}</div>}
        {ev.discountPrice != null && ev.discountPrice > 0 && ev.originalPrice != null && ev.originalPrice > 0 && (
          <div className={styles.price}>
            <span className={styles.orig}>{formatWon(ev.originalPrice)}</span>
            <span className={styles.disc}>{formatWon(ev.discountPrice)}</span>
          </div>
        )}
        {ev.endDate && <div className={styles.date}>~{ev.endDate.replace(/-/g, ".")}</div>}
      </div>
    </Link>
  );
}

function AdBanner({ ads, auto }: { ads: PageAd[]; auto: boolean }) {
  const php = usePhpBase();
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => (ref.current ? observeImpressions(php, ref.current) : undefined), [ads, php]);
  if (!ads.length) return null;
  return (
    <div ref={ref} className={styles.ad}>
      <Carousel auto={auto} intervalMs={4000} label="광고">
        {ads.map((ad, i) => {
          const img = assetUrl(ad.imageUrl);
          const to = ad.linkUrl.startsWith("http") || ad.linkUrl.startsWith("/") || ad.linkUrl === "#" ? ad.linkUrl : phpUrl(php, ad.linkUrl);
          return (
            <a key={ad.id} href={to} className={styles.adLink} data-ad-id={ad.id} onClick={() => trackClick(php, ad.id, to)}>
              {img ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={img} alt={ad.title} className={styles.adImg} />
              ) : (
                <div className={styles.adText} style={{ background: GRADIENTS[i % GRADIENTS.length] }}>
                  <div className={styles.adBadge}>AD</div>
                  <div className={styles.adTitle}>{ad.title}</div>
                  {ad.subtitle && <div className={styles.adSub}>{ad.subtitle}</div>}
                  <div className={styles.adCta}>자세히 보기 →</div>
                </div>
              )}
            </a>
          );
        })}
      </Carousel>
    </div>
  );
}

/** events.php: 전체/응모 tabs, category chips, card grid (2 columns above 768 px, 1 below — as in events.php). */
export default function EventList({ category, raffle, initial }: { category: string; raffle: boolean; initial?: Data }) {
  const { data, isError, refetch } = useQuery({
    queryKey: qk.events(category, raffle),
    queryFn: () => api.events({ category, filter: raffle ? "raffle" : "" }),
    initialData: initial,
  });
  return (
    <div>
      <div className={styles.head}>
        <Link href="/" className={styles.back} aria-label="홈으로">←</Link>
        <h1 className={styles.title}>특별 이벤트</h1>
      </div>
      {data && <AdBanner ads={data.ads} auto={data.adAutoScroll} />}
      <div className={styles.tabs}>
        <Link href={href(category, false)} className={`${styles.tab} ${!raffle ? styles.tabOn : ""}`}>전체 이벤트</Link>
        <Link href={href(category, true)} className={`${styles.tab} ${raffle ? styles.tabOn : ""}`}>🎰 응모 이벤트</Link>
      </div>
      {data && (
        <div className={styles.cats}>
          <Link href={href("", raffle)} className={`${styles.cat} ${!category ? styles.catOn : ""}`}>전체</Link>
          {data.categories.map((c) => <Link key={c} href={href(c, raffle)} className={`${styles.cat} ${category === c ? styles.catOn : ""}`}>{c}</Link>)}
        </div>
      )}
      {!data ? (
        <div className={styles.empty}>
          {isError ? <>불러오지 못했습니다. <button className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}
        </div>
      ) : data.items.length === 0 ? (
        <div className={styles.empty}>
          <div className={styles.emptyIcon}>🎁</div>
          <p>{raffle ? "진행 중인 응모 이벤트가 없습니다." : "진행 중인 이벤트가 없습니다."}</p>
        </div>
      ) : (
        <div className={styles.grid}>{data.items.map((ev) => <EventCard key={ev.id} ev={ev} />)}</div>
      )}
    </div>
  );
}
