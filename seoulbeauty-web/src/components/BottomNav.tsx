"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
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

/** Design part 02 (login / sign-up / password), 07-01 설정 / 07-06 이용약관 and 07-09 탈퇴 완료 have no tab bar. */
const NO_NAV = ["/booking", "/login", "/register", "/register-terms", "/password-lost", "/password-reset", "/mypage/settings", "/mypage/withdraw/done", "/terms", "/community/write"];

/** A text field is being edited (the on-screen keyboard is up). Pickers (select, date, time, ...) and non-text inputs do not raise a keyboard. */
const NO_KEYBOARD = ["checkbox", "radio", "file", "button", "submit", "reset", "range", "color", "image", "hidden", "date", "time", "datetime-local", "month", "week"];
const isTextField = (el: EventTarget | null): boolean => {
  if (!(el instanceof HTMLElement)) return false;
  if (el instanceof HTMLTextAreaElement) return true;
  if (el instanceof HTMLInputElement) return !NO_KEYBOARD.includes(el.type);
  return el.isContentEditable;
};

/** True while a text field has focus. Whatever the keyboard does to the viewport (resize or overlay), the tab bar must not float above it.
    Mirrored on <html data-typing> so fixed bars that normally sit above the tab bar (comment form, hospital CTA) can drop to the bottom. */
function useTyping(pathname: string): boolean {
  const [typing, setTyping] = useState(false);
  useEffect(() => {
    let t = 0;
    const on = (e: FocusEvent) => { window.clearTimeout(t); if (isTextField(e.target)) setTyping(true); };
    // focus moving from one field to another passes through "out" then "in": decide after the dust settles
    const off = () => { window.clearTimeout(t); t = window.setTimeout(() => setTyping(isTextField(document.activeElement)), 120); };
    document.addEventListener("focusin", on);
    document.addEventListener("focusout", off);
    // WebKit fires no focusout when the focused field is removed from the DOM (search overlay closing after Enter): re-check when the DOM changes
    const mo = new MutationObserver(() => { if (!isTextField(document.activeElement)) setTyping(false); });
    mo.observe(document.body, { childList: true, subtree: true });
    // Android "Back" closes the keyboard but keeps the focus: when the viewport grows back to its full height after it had shrunk for a focused field, let go of the field
    const vv = window.visualViewport;
    const height = () => vv?.height ?? window.innerHeight;
    const width = () => vv?.width ?? window.innerWidth;
    let full = height(), fullW = width(), shrunk = false;
    const onResize = () => {
      const h = height(), w = width();
      // rotation changes the width (a keyboard never does): start over from the new orientation, typing carries on
      if (Math.abs(w - fullW) > 50) { fullW = w; full = h; shrunk = false; return; }
      if (!isTextField(document.activeElement)) { full = h; fullW = w; shrunk = false; return; }
      if (h < full - 100) shrunk = true;
      else if (shrunk) { shrunk = false; (document.activeElement as HTMLElement).blur(); }
    };
    vv?.addEventListener("resize", onResize);
    window.addEventListener("resize", onResize);
    return () => { window.clearTimeout(t); mo.disconnect(); vv?.removeEventListener("resize", onResize); window.removeEventListener("resize", onResize); document.removeEventListener("focusin", on); document.removeEventListener("focusout", off); };
  }, []);
  // ... and after every navigation
  useEffect(() => { const id = window.setTimeout(() => setTyping(isTextField(document.activeElement)), 150); return () => window.clearTimeout(id); }, [pathname]);
  useEffect(() => {
    const root = document.documentElement;
    if (typing) root.setAttribute("data-typing", "1"); else root.removeAttribute("data-typing");
    return () => root.removeAttribute("data-typing");
  }, [typing]);
  return typing;
}

export default function BottomNav() {
  const pathname = usePathname();
  const typing = useTyping(pathname);
  if (NO_NAV.some((p) => pathname === p || pathname.startsWith(`${p}/`))) return null;
  const activeIdx = items.findIndex((it) => (it.next === "/" ? pathname === "/" : pathname.startsWith(it.next)));
  return (
    <nav className={`${styles.nav} ${typing ? styles.typing : ""}`} aria-label="하단 메뉴" aria-hidden={typing || undefined}>
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
