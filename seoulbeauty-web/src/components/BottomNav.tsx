"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import styles from "./BottomNav.module.css";

// Pilot: 홈, 병원, 커뮤니티 and 마이페이지 live in Next.js. The other tabs go to the PHP pages on the
// same origin (full load), so the app stays on its trusted host.
const items = [
  { next: "/", label: "홈" },
  { next: "/hospitals", label: "병원" },
  { next: "/community", label: "커뮤니티" },
  { next: "/mypage", label: "마이페이지" },
] as const;

export default function BottomNav() {
  const pathname = usePathname();
  return (
    <nav className={styles.nav} aria-label="하단 메뉴">
      {items.map((it) => {
        const active = it.next === "/" ? pathname === "/" : pathname.startsWith(it.next);
        return (
          <Link key={it.label} href={it.next} className={`${styles.item} ${active ? styles.active : ""}`}
            aria-current={active ? "page" : undefined}>{it.label}</Link>
        );
      })}
    </nav>
  );
}
