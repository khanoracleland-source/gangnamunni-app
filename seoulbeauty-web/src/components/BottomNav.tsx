"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import styles from "./BottomNav.module.css";
import { phpUrl, usePhpBase } from "@/lib/php";

// Pilot: only "병원" lives in Next.js. The other tabs go to the PHP pages on the
// same origin (full load), so the app stays on its trusted host.
const items = [
  { php: "index.php", label: "홈" },
  { next: "/hospitals", label: "병원" },
  { php: "community.php", label: "커뮤니티" },
  { php: "mypage.php", label: "마이" },
] as const;

export default function BottomNav() {
  const pathname = usePathname();
  const php = usePhpBase();
  return (
    <nav className={styles.nav} aria-label="하단 메뉴">
      {items.map((it) => {
        if ("php" in it) {
          return <a key={it.label} href={phpUrl(php, it.php)} className={styles.item}>{it.label}</a>;
        }
        const active = pathname.startsWith(it.next);
        return (
          <Link key={it.label} href={it.next} className={`${styles.item} ${active ? styles.active : ""}`}
            aria-current={active ? "page" : undefined}>{it.label}</Link>
        );
      })}
    </nav>
  );
}
