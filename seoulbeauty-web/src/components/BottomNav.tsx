"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import homeOn from "@/assets/icons/home.png";
import homeOff from "@/assets/icons/home-off.png";
import hospitalOn from "@/assets/icons/hospital.png";
import hospitalOff from "@/assets/icons/hospital-off.png";
import communityOn from "@/assets/icons/community.png";
import communityOff from "@/assets/icons/community-off.png";
import myOn from "@/assets/icons/my.png";
import myOff from "@/assets/icons/my-off.png";
import styles from "./BottomNav.module.css";

// Pilot: 홈, 병원, 커뮤니티 and 마이페이지 live in Next.js. The other tabs go to the PHP pages on the
// same origin (full load), so the app stays on its trusted host.
const items = [
  { next: "/", label: "홈", on: homeOn, off: homeOff },
  { next: "/hospitals", label: "병원", on: hospitalOn, off: hospitalOff },
  { next: "/community", label: "커뮤니티", on: communityOn, off: communityOff },
  { next: "/mypage", label: "마이페이지", on: myOn, off: myOff },
] as const;

export default function BottomNav() {
  const pathname = usePathname();
  const activeIdx = items.findIndex((it) => (it.next === "/" ? pathname === "/" : pathname.startsWith(it.next)));
  return (
    <nav className={styles.nav} aria-label="하단 메뉴">
      {/* sliding pill: the nav stays mounted across pages, so changing the index animates it left/right */}
      <span className={styles.pill} aria-hidden style={{ transform: `translateX(calc(${Math.max(activeIdx, 0)} * (100% + 3px)))`, opacity: activeIdx < 0 ? 0 : 1 }} />
      {items.map((it) => {
        const active = it.next === "/" ? pathname === "/" : pathname.startsWith(it.next);
        return (
          <Link key={it.label} href={it.next} className={`${styles.item} ${active ? styles.active : ""}`}
            aria-current={active ? "page" : undefined}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={(active ? it.on : it.off).src} alt="" width={24} height={24} />
            <span>{it.label}</span>
          </Link>
        );
      })}
    </nav>
  );
}
