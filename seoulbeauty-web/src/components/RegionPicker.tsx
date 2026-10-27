"use client";
import { useState } from "react";
import styles from "./RegionPicker.module.css";

/** includes/header.php #regionPanel: dropdown under the search bar, checkbox grid (groups merged like PHP), 초기화 / 필터 선택 완료. */
export default function RegionPicker(props: { open: boolean; groups: string[][]; selected: string[]; onClose: () => void; onApply: (r: string[]) => void }) {
  // Remount on open so the draft starts from the current selection (no effect/setState needed).
  if (!props.open) return null;
  return <RegionPickerBody key={props.selected.join(",")} {...props} />;
}

function RegionPickerBody({ groups, selected, onClose, onApply }: { groups: string[][]; selected: string[]; onClose: () => void; onApply: (r: string[]) => void }) {
  const [sel, setSel] = useState<string[]>(selected);
  const all = groups.flat();
  const toggle = (r: string) => setSel((s) => (s.includes(r) ? s.filter((x) => x !== r) : [...s, r]));
  return (
    <>
      <div className={styles.backdrop} onClick={onClose} aria-hidden="true" />
      <div className={styles.panel} role="dialog" aria-label="지역 선택">
        <div className={styles.grid}>
          {all.map((r) => (
            <label key={r} className={sel.includes(r) ? styles.on : ""}>
              <input type="checkbox" className={styles.cb} checked={sel.includes(r)} onChange={() => toggle(r)} />
              {r}
            </label>
          ))}
        </div>
        <div className={styles.actions}>
          <button type="button" className={styles.reset} onClick={() => setSel([])}>↺ 초기화</button>
          <div className={styles.tags}>
            {sel.map((r) => <span key={r} className={styles.tag}>{r} <span role="button" aria-label={`${r} 제거`} onClick={() => toggle(r)}>×</span></span>)}
          </div>
          <button type="button" className={styles.apply} onClick={() => onApply(sel)}>필터 선택 완료</button>
        </div>
      </div>
    </>
  );
}
