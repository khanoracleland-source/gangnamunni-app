"use client";
import { useState } from "react";
import Link from "next/link";
import type { HomeCategory } from "@/lib/api";
import styles from "./CategoryTable.module.css";

/**
 * index.php 시술 카테고리 테이블. PHP has three admin-selectable modes:
 *  - excel (default): left column = categories (tap to switch), right = that category's treatments
 *  - row / hybrid: one horizontally scrolling row of chips per category
 */
export default function CategoryTable({ categories, mode }: { categories: HomeCategory[]; mode: "excel" | "row" | "hybrid" }) {
  const [active, setActive] = useState(0);
  if (!categories.length) return null;
  const chip = (t: string) => <Link key={t} href={`/hospitals?treatment=${encodeURIComponent(t)}`} className={styles.chip}>{t}</Link>;

  if (mode === "row" || mode === "hybrid") {
    return (
      <div className={styles.table}>
        {categories.map((c) => (
          <div key={c.id} className={styles.row}>
            <div className={`${styles.major} ${mode === "hybrid" ? styles.majorSticky : ""}`}>{c.name}</div>
            <div className={styles.subs}>{c.treatments.map(chip)}</div>
          </div>
        ))}
      </div>
    );
  }
  const cur = categories[Math.min(active, categories.length - 1)];
  return (
    <div className={styles.panel}>
      <div className={styles.nav} role="tablist" aria-label="시술 분류">
        {categories.map((c, i) => (
          <button key={c.id} role="tab" aria-selected={i === active} className={`${styles.navItem} ${i === active ? styles.navOn : ""}`} onClick={() => setActive(i)}>{c.name}</button>
        ))}
      </div>
      <div className={styles.grid} role="tabpanel">
        {cur.treatments.length ? cur.treatments.map((t) => (
          <Link key={t} href={`/hospitals?treatment=${encodeURIComponent(t)}`} className={styles.cell}>{t}</Link>
        )) : <div className={styles.none}>등록된 시술이 없습니다</div>}
      </div>
    </div>
  );
}
