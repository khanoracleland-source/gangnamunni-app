"use client";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { api, assetUrl, PLACEHOLDER, qk, type HospitalDetail } from "@/lib/api";
import { addrDistrict } from "./HospitalCard";
import starIcon from "@/assets/icons/star.png";
import trophyIcon from "@/assets/icons/trophy.png";
import styles from "./TreatmentList.module.css";

/**
 * design 04-03 시술목록 탭: one rounded card per price option (72 thumb, option name, district | address,
 * rating · 병원후기 · badge, VAT 포함 + price right-aligned). A card opens the booking with that option picked.
 */
export default function TreatmentList({ hospitalId, hospital }: { hospitalId: number; hospital: HospitalDetail }) {
  const { data, isPending, isError } = useQuery({ queryKey: qk.treatments(hospitalId), queryFn: () => api.treatments(hospitalId) });
  const h = hospital;
  const thumb = assetUrl(h.thumbnailUrl || h.mainImageUrl || h.profileImageUrl) || PLACEHOLDER;
  const district = addrDistrict(h.address);
  const badge = h.badges.find((b) => b.includes("인기")) ? "인기병원" : h.badges.some((b) => b.includes("우수")) ? "고객평가우수병원" : null;
  const rows = data ? data.items.flatMap((t) => t.options.map((o) => ({ t, o }))) : [];
  return (
    <section aria-labelledby="treat-h">
      <h2 id="treat-h" className={styles.h2}>시술목록</h2>
      {isPending && <div className={styles.skWrap} aria-busy="true"><div className={`skeleton ${styles.sk}`} /><div className={`skeleton ${styles.sk}`} /></div>}
      {isError && <p className={styles.muted}>시술 목록을 불러오지 못했습니다.</p>}
      {data && rows.length === 0 && <p className={styles.muted}>등록된 시술이 없습니다.</p>}
      {rows.length > 0 && (
        <ul className={styles.list}>
          {rows.map(({ t, o }) => (
            <li key={o.id}>
              <Link href={`/booking?id=${hospitalId}&treatment_id=${t.treatmentId}&option_id=${o.id}&treatment=${encodeURIComponent(t.name)}`} className={styles.card}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={thumb} alt="" className={styles.thumb} loading="lazy" decoding="async"
                  onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
                <div className={styles.info}>
                  <div className={styles.name}>{o.displayName || `${t.name} ${o.optionName}`.trim()}{t.promoted && <span className={styles.promo}>추천</span>}</div>
                  {h.address && <div className={styles.addr}>{district && <><span>{district}</span><span className={styles.sep}> | </span></>}{h.address}</div>}
                  <div className={styles.meta}>
                    {h.showRating && h.rating != null && h.rating > 0 && (
                      <span className={styles.rating}>
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img src={starIcon.src} alt="" width={12} height={12} /><b>{h.rating.toFixed(1)}</b>
                      </span>
                    )}
                    {h.reviewCount > 0 && <span className={styles.reviews}>병원후기 <span className={styles.count}>({h.reviewCount.toLocaleString("ko-KR")})</span></span>}
                    {badge && (
                      <span className={styles.badge}>
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img src={trophyIcon.src} alt="" width={12} height={12} />{badge}
                      </span>
                    )}
                  </div>
                  <div className={styles.vat}>VAT 포함{o.regularPrice > o.price && <> · <s>{o.regularPrice.toLocaleString("ko-KR")}원</s></>}</div>
                  <div className={styles.priceRow}>
                    {o.discountRate > 0 && <span className={styles.rate}>{o.discountRate}%</span>}
                    <span className={styles.price}>{o.price.toLocaleString("ko-KR")}</span><span className={styles.won}>원</span>
                  </div>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
