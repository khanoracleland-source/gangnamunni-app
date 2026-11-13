"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState, useSyncExternalStore, type ReactNode } from "react";
import Icon, { type IconName } from "./Icon";
import type { Admin } from "@/lib/api";
import { BASE_PATH, PHP_ADMIN_URL, PHP_LOGOUT_URL } from "@/lib/config";
import { MENU } from "@/lib/menu";
import { getServerTheme, getTheme, setTheme, subscribeTheme } from "@/lib/theme";

const subscribeNever = () => () => {};

const ROLE_LABEL: Record<string, string> = { super_admin: "최고관리자" };

type Props = {
  /** null until /v1/me has answered (or when it refused). */
  admin: Admin | null;
  title: string;
  description: string;
  icon: IconName;
  /** Right side of the page heading block (refresh controls and the like). */
  actions?: ReactNode;
  children: ReactNode;
};

/** Frame shared by every admin page: fixed top bar, sidebar, page heading block, content. */
export default function AdminShell({ admin, title, description, icon, actions, children }: Props) {
  const pathname = usePathname();
  const theme = useSyncExternalStore(subscribeTheme, getTheme, getServerTheme);
  // The drawer state only matters below 768px, where the sidebar is off-screen until opened.
  const [drawerOpen, setDrawerOpen] = useState(false);
  const menuButtonRef = useRef<HTMLButtonElement>(null);
  const sidebarRef = useRef<HTMLElement>(null);
  const drawerWasOpen = useRef(false);
  // False on the server and during hydration, true afterwards: the day/night label depends on the
  // saved theme, which only the browser knows, so it is drawn after mount instead of guessed.
  const mounted = useSyncExternalStore(subscribeNever, () => true, () => false);
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(new Set());

  // Opening the drawer moves focus into it; closing it (Esc, backdrop, a link) gives focus back to the button.
  useEffect(() => {
    if (drawerOpen) {
      sidebarRef.current?.focus();
    } else if (drawerWasOpen.current) {
      menuButtonRef.current?.focus();
    }
    drawerWasOpen.current = drawerOpen;
  }, [drawerOpen]);

  useEffect(() => {
    if (!drawerOpen) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setDrawerOpen(false);
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [drawerOpen]);

  const toggleGroup = (id: string) =>
    setCollapsed((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  // The button offers the mode you would switch to, like the PHP admin does.
  const nextTheme = theme === "dark" ? "light" : "dark";
  const nextThemeLabel = nextTheme === "dark" ? "야간" : "주간";
  const displayName = admin ? admin.name || admin.username : "";
  const roleLabel = admin ? (ROLE_LABEL[admin.role] ?? admin.role) : "";

  return (
    <>
      <a className="skip-link" href="#content">본문으로 건너뛰기</a>

      <header className="topbar">
        <button
          type="button"
          className="btn btn--secondary btn--icon topbar__menu"
          ref={menuButtonRef}
          aria-label={drawerOpen ? "메뉴 닫기" : "메뉴 열기"}
          aria-expanded={drawerOpen}
          aria-controls="sidebar"
          onClick={() => setDrawerOpen((v) => !v)}
        >
          <Icon name="menu" />
        </button>
        <Link href="/" className="topbar__brand">
          {/* eslint-disable-next-line @next/next/no-img-element -- a fixed 32px logo: the image optimizer would add nothing */}
          <img className="topbar__logo" src={`${BASE_PATH}/seoul_beauty_logo.png`} alt="" width={32} height={32} />
          <span className="topbar__name">서울미녀 Admin</span>
        </Link>

        <div className="topbar__right">
          <a className="btn btn--secondary topbar__legacy" href={PHP_ADMIN_URL} aria-label="기존 관리자로 이동" title="기존 관리자">
            <Icon name="external" />
            <span className="topbar__legacy-text">기존 관리자</span>
          </a>
          <button
            type="button"
            className="btn btn--secondary topbar__theme"
            onClick={() => setTheme(nextTheme)}
            aria-label={mounted ? `${nextThemeLabel} 모드로 전환` : "주간/야간 모드 전환"}
            title="주간/야간 모드"
          >
            {mounted ? <Icon name={nextTheme === "dark" ? "moon" : "sun"} /> : null}
            <span className="topbar__theme-text">{mounted ? nextThemeLabel : ""}</span>
          </button>
          {admin ? (
            <>
              <span className="badge badge--name" title="로그인한 관리자">{displayName}</span>
              <span className="badge badge--role">{roleLabel}</span>
            </>
          ) : null}
          <a className="btn btn--danger btn--icon" href={PHP_LOGOUT_URL} aria-label="로그아웃" title="로그아웃">
            <Icon name="logout" />
          </a>
        </div>
      </header>

      <nav id="sidebar" ref={sidebarRef} tabIndex={-1} className={drawerOpen ? "sidebar is-open" : "sidebar"} aria-label="관리자 메뉴">
        {MENU.map((group) => {
          const open = !collapsed.has(group.id);
          const listId = `menu-${group.id}`;
          return (
            <div key={group.id} className="sidebar__group">
              <button
                type="button"
                className="sidebar__group-head"
                aria-expanded={open}
                aria-controls={listId}
                onClick={() => toggleGroup(group.id)}
              >
                <span className="sidebar__group-icon"><Icon name={group.icon} size={13} /></span>
                {group.label}
                <Icon name="chevronUp" size={10} className={open ? "sidebar__chevron" : "sidebar__chevron is-closed"} />
              </button>
              <ul id={listId} className="sidebar__list" hidden={!open}>
                {group.items.map((item) => {
                  const active = pathname === item.href;
                  return (
                    <li key={item.id}>
                      <Link
                        href={item.href}
                        className={active ? "sidebar__link is-active" : "sidebar__link"}
                        aria-current={active ? "page" : undefined}
                        onClick={() => setDrawerOpen(false)}
                      >
                        <span className="sidebar__link-icon"><Icon name={item.icon} size={12} /></span>
                        {item.label}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          );
        })}
      </nav>
      {drawerOpen ? <div className="sidebar-backdrop" onClick={() => setDrawerOpen(false)} aria-hidden="true" /> : null}

      <main id="content" className="content" tabIndex={-1}>
        <div className="page-head">
          <div className="page-head__text">
            <h1 className="page-head__title">
              <Icon name={icon} size={15} />
              {title}
            </h1>
            <p className="page-head__desc">{description}</p>
          </div>
          {actions ? <div className="page-head__actions">{actions}</div> : null}
        </div>
        <div className="content__body">{children}</div>
      </main>
    </>
  );
}
