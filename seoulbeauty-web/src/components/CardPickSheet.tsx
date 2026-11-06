"use client";
import { useRef, useState } from "react";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import { INICIS_CARDS, INSTALLMENT_MIN_AMOUNT, quotaLabel, quotaOptions } from "@/lib/inicisCards";
import styles from "./CardPickSheet.module.css";

const Chevron = ({ up }: { up: boolean }) => (
  <svg className={`${styles.chev} ${up ? styles.chevUp : ""}`} width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A1718" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
);

/** Design 05-29 / 05-30 / 05-31: pick the card company, then the installment; the choice is sent to Inicis as a direct card call. */
export default function CardPickSheet({ open, amount, card, quota, onClose, onPick }: {
  open: boolean; amount: number; card: string; quota: string; onClose: () => void; onPick: (card: string, quota: string) => void;
}) {
  const sheet = useRef<BottomSheetHandle>(null);
  return (
    <BottomSheet ref={sheet} open={open} onClose={onClose} label="카드사·할부 선택">
      {/* mounted only while open, so every opening starts from the saved choice */}
      <Body amount={amount} card={card} quota={quota} onPick={(c, q) => { onPick(c, q); sheet.current?.close(); }} />
    </BottomSheet>
  );
}

function Body({ amount, card, quota, onPick }: { amount: number; card: string; quota: string; onPick: (card: string, quota: string) => void }) {
  const [c, setC] = useState(card);
  const [q, setQ] = useState(quota);
  const [openList, setOpenList] = useState<"card" | "quota" | null>(card ? null : "card");
  const name = INICIS_CARDS.find((x) => x.code === c)?.name;
  const quotas = quotaOptions(amount);
  return (
    <div className={styles.inner}>
      <h2 className={styles.title}>카드사·할부를 선택해 주세요</h2>
      <button type="button" className={`${styles.field} ${name ? "" : styles.fieldEmpty}`} aria-expanded={openList === "card"} onClick={() => setOpenList(openList === "card" ? null : "card")}>
        <span className={styles.fieldText}>{name ?? "카드사 선택"}</span><Chevron up={openList === "card"} />
      </button>
      {openList === "card" && (
        <ul className={styles.list}>
          {INICIS_CARDS.map((x) => (
            <li key={x.code}><button type="button" className={`${styles.item} ${x.code === c ? styles.itemOn : ""}`} onClick={() => { setC(x.code); setQ("00"); setOpenList(null); }}>{x.name}</button></li>
          ))}
        </ul>
      )}
      {c && (
        <>
          <button type="button" className={styles.field} aria-expanded={openList === "quota"} onClick={() => setOpenList(openList === "quota" ? null : "quota")}>
            <span className={styles.fieldText}>{quotaLabel(q)}</span><Chevron up={openList === "quota"} />
          </button>
          {openList === "quota" && (
            <ul className={styles.list}>
              {quotas.map((x) => (
                <li key={x}><button type="button" className={`${styles.item} ${x === q ? styles.itemOn : ""}`} onClick={() => { setQ(x); setOpenList(null); }}>{quotaLabel(x)}</button></li>
              ))}
            </ul>
          )}
          {amount < INSTALLMENT_MIN_AMOUNT && <p className={styles.note}>할부는 {INSTALLMENT_MIN_AMOUNT.toLocaleString("ko-KR")}원 이상 결제할 때만 선택할 수 있어요.</p>}
        </>
      )}
      <button type="button" className={styles.done} disabled={!c} onClick={() => onPick(c, q)}>선택 완료</button>
    </div>
  );
}
