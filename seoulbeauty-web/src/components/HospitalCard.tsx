"use client";
import Link from "next/link";
import { useQueryClient } from "@tanstack/react-query";
import { api, assetUrl, formatWon, PLACEHOLDER, qk, type Hospital } from "@/lib/api";
import { usePhpBase } from "@/lib/php";
import { trackSlotClick } from "@/lib/adTracking";
import WishlistButton from "./WishlistButton";
import styles from "./HospitalCard.module.css";

/** extract_addr_district(): "서울 강남구 …" -> "강남" (2nd token first). */
export function addrDistrict(addr: string | null): string {
  const parts = (addr || "").trim().split(/\s+/).filter(Boolean);
  const order = parts[1] ? [parts[1], ...parts] : parts;
  const d = order.find((p) => p.endsWith("구"));
  return d ? d.slice(0, -1) : "";
}

/** hospital_dosage_label(): 2.00 -> "2", 0.50 -> "0.5" */
const dosageText = (v: number | null, u: string | null) => (v && v > 0 && u ? `${String(Number(v.toFixed(2)))}${u}` : "");

export default function HospitalCard({ h, keyword, slotToken }: { h: Hospital; keyword?: string; slotToken?: string }) {
  const qc = useQueryClient();
  const php = usePhpBase();
  const img = assetUrl(h.thumbnailUrl) || PLACEHOLDER;
  const t = h.treatment;
  const isSearchRow = !!keyword;
  // hospital_list_content.php card-tag-price block
  const dosage = t ? dosageText(t.dosageValue, t.dosageUnit) : "";
  const treatLine = t ? [t.name, dosage].filter(Boolean).join(" ") : "";
  const perUnit = t && t.priceMin > 0 && t.dosageValue && t.dosageValue > 0 && t.dosageUnit && t.dosageUnit.trim() !== "회"
    ? `1${t.dosageUnit}당 ${Math.round(t.priceMin / t.dosageValue).toLocaleString("ko-KR")}원` : "";
  const listPrice = !t ? formatWon(h.minPrice) : null;
  const district = addrDistrict(h.address);
  // Warm the detail cache on touch/hover so the detail page paints instantly.
  const prefetch = () => {
    qc.prefetchQuery({ queryKey: qk.hospital(h.id), queryFn: () => api.hospital(h.id) });
    qc.prefetchQuery({ queryKey: qk.reviews(h.id), queryFn: () => api.reviews(h.id) });
  };
  const slotAttrs = h.adSlot
    ? { "data-ad-slot-booking": h.adSlot.bookingId, "data-ad-slot-token": slotToken || "", "data-ad-slot-keyword": keyword || "", "data-ad-slot-rank": h.adSlot.rank }
    : {};
  return (
    <Link
      href={`/hospitals/${h.id}`}
      className={styles.card}
      onTouchStart={prefetch}
      onMouseEnter={prefetch}
      onFocus={prefetch}
      onClick={(e) => { if (h.adSlot) trackSlotClick(php, e.currentTarget, e.currentTarget.href); }}
      {...slotAttrs}
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- images live on the PHP site */}
      <img src={img} alt="" className={styles.thumb} loading="lazy" decoding="async"
        onError={(e) => { if (e.currentTarget.src !== PLACEHOLDER) e.currentTarget.src = PLACEHOLDER; }} />
      <div className={styles.info}>
        <div className={styles.name}>{h.name}</div>
        <div className={styles.wish}><WishlistButton hospitalId={h.id} initial={{ wishlisted: h.wishlisted, count: h.wishlistCount }} /></div>
        {h.address && <div className={styles.addr}>{district && <><span className={styles.district}>{district}</span><span className={styles.sep}> | </span></>}{h.address}</div>}
        {(treatLine || (t && t.priceMin > 0)) && (
          <div className={styles.tagPrice}>
            {treatLine && <div className={styles.tagItem}>{treatLine}</div>}
            {t && t.priceMin > 0 && (
              <>
                {!isSearchRow && t.priceMax > 0 && <div className={styles.vat}>VAT 포함&nbsp;&nbsp;{t.priceMax.toLocaleString("ko-KR")}원</div>}
                <div className={styles.priceRow}>
                  <span className={styles.sale}><span className={styles.num}>{t.priceMin.toLocaleString("ko-KR")}</span><span className={styles.unit}>원</span></span>
                  {isSearchRow && perUnit && <span className={styles.per}>{perUnit}</span>}
                </div>
              </>
            )}
          </div>
        )}
        {!t && (
          <div className={styles.meta}>
            {h.showRating && h.rating != null && h.rating > 0 && <span className={styles.rating}>★ {h.rating.toFixed(1)}</span>}
            {h.reviewCount > 0 && <span>후기 {h.reviewCount}</span>}
            {listPrice && <span className={styles.price}>{listPrice}~</span>}
          </div>
        )}
        {!t && h.treatmentTags.length > 0 && (
          <div className={styles.tags}>
            {h.treatmentTags.slice(0, 3).map((x) => <span key={x} className={styles.tag}>{x}</span>)}
          </div>
        )}
      </div>
    </Link>
  );
}
