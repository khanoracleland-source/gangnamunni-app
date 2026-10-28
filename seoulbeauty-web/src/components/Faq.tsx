"use client";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api, qk, type FaqData } from "@/lib/api";
import SupportHeader from "./SupportHeader";
import styles from "./Support.module.css";

/** faq.php: category tabs + an accordion (several answers can be open at once). */
export default function Faq({ initial, cat }: { initial?: FaqData; cat: string }) {
  const { data, isError, refetch } = useQuery({ queryKey: qk.faq, queryFn: () => api.faq(), initialData: initial, staleTime: 60_000 });
  const [picked, setPicked] = useState<string | null>(null);
  const [open, setOpen] = useState<Record<number, boolean>>({});
  // First tab unless ?cat= names an existing one (faq.php).
  const active = data ? (picked && data.categories.includes(picked) ? picked : data.categories.includes(cat) ? cat : data.categories[0]) : "";
  const items = data ? data.items.filter((i) => i.category === active) : [];
  return (
    <div className={styles.wrap}>
      <SupportHeader title="자주 묻는 질문" />
      {!data ? (
        <div className={styles.empty}>{isError ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div>
      ) : (
        <>
          <div className={styles.tabs} role="tablist">
            {data.categories.map((c) => (
              <button key={c} type="button" role="tab" aria-selected={c === active} className={`${styles.tab} ${c === active ? styles.tabOn : ""}`} onClick={() => setPicked(c)}>{c}</button>
            ))}
          </div>
          <div className={styles.panel} role="tabpanel">
            {items.length === 0 ? (
              <div className={styles.fempty}>등록된 질문이 없습니다</div>
            ) : items.map((f) => (
              <div key={f.id} className={styles.fitem}>
                <button type="button" className={styles.fq} aria-expanded={!!open[f.id]} onClick={() => setOpen((o) => ({ ...o, [f.id]: !o[f.id] }))}>
                  <span>{f.question}</span>
                  <span className={`${styles.chev} ${open[f.id] ? styles.chevOpen : ""}`} aria-hidden="true">⌄</span>
                </button>
                {open[f.id] && <div className={styles.fa}>{f.answer}</div>}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
