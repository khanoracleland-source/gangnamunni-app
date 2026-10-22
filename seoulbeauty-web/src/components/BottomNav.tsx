"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import styles from "./BottomNav.module.css";

const PHP = process.env.NEXT_PUBLIC_ASSET_BASE || "";

// Pilot: only "병원" lives in Next.js. The other tabs still go to the PHP pages (full load).
const items = [
  { href: `${PHP}/index.php`, label: "홈", external: true },
  { href: "/hospitals", label: "병원", external: false },
  { href: `${PHP}/community.php`, label: "커뮤니티", external: true },
  { href: `${PHP}/mypage.php`, label: "마이", external: true },
];

export default function BottomNav() {
  const pathname = usePathname();
  return (
    <nav className={styles.nav} aria-label="하단 메뉴">
      {items.map((it) => {
        const active = !it.external && pathname.startsWith(it.href);
        const cls = `${styles.item} ${active ? styles.active : ""}`;
        return it.external ? (
          <a key={it.label} href={it.href} className={cls}>{it.label}</a>
        ) : (
          <Link key={it.label} href={it.href} className={cls} aria-current={active ? "page" : undefined}>{it.label}</Link>
        );
      })}
    </nav>
  );
}
