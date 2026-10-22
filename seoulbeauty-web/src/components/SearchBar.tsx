"use client";
import { useRouter } from "next/navigation";
import { useState } from "react";
import styles from "./SearchBar.module.css";

export default function SearchBar({ initial }: { initial: string }) {
  const router = useRouter();
  const [q, setQ] = useState(initial);
  return (
    <form
      className={styles.bar}
      role="search"
      onSubmit={(e) => {
        e.preventDefault();
        const v = q.trim();
        router.push(v ? `/hospitals?q=${encodeURIComponent(v)}` : "/hospitals");
      }}
    >
      <input
        className={styles.input}
        type="search"
        inputMode="search"
        enterKeyHint="search"
        placeholder="병원명, 지역, 시술 검색"
        value={q}
        onChange={(e) => setQ(e.target.value)}
        aria-label="검색"
      />
      <button type="submit" className={styles.btn}>검색</button>
    </form>
  );
}
