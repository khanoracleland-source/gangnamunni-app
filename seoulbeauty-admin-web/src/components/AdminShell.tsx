"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState, useSyncExternalStore, type ReactNode } from "react";
import Icon, { type IconName } from "./Icon";
import type { Admin } from "@/lib/api";
import { BASE_PATH, PHP_ADMIN_URL, PHP_LOGOUT_URL } from "@/lib/config";
import type { Locale, MessageKey } from "@/lib/i18n";
import { setLocale, useLocale, useT } from "@/lib/locale";
import { MENU } from "@/lib/menu";
import { getServerTheme, getTheme, setTheme, subscribeTheme } from "@/lib/theme";

const subscribeNever = () => () => {};

type Props = {
  /** null until /v1/me has answered (or when it refused). */
  admin: Admin | null;
  title: string;
  description: string;
  icon: IconName;
  /** Collapsible explanation under the heading. */
  intro?: ReactNode;
  children: ReactNode;
};

/** Frame shared by every admin page: top bar, grouped menu on the left, page heading, content. */
export default function AdminShell({ admin, title, description, icon, intro, children }: Props) {
  const t = useT();
  const locale = useLocale();
  const pathname = usePathname();
  const theme = useSyncExternalStore(subscribeTheme, getTheme, getServerTheme);
  // The drawer state only matters below 900px, where the menu is off-screen until opened.
  const [drawerOpen, setDrawerOpen] = useState(false);
  const menuButtonRef = useRef<HTMLButtonElement>(null);
  const sidebarRef = useRef<HTMLElement>(null);
  const drawerWasOpen = useRef(false);
  // False on the server and during hydration, true afterwards: the theme button depends on the saved
  // theme, which only the browser knows, so it is drawn after mount instead of guessed.
  const mounted = useSyncExternalStore(subscribeNever, () => true, () => false);
  // Groups the admin opened or closed by hand; the group of the current page and the first group start open.
  const [toggled, setToggled] = useState<ReadonlySet<string>>(new Set());

  useEffect(() => {
    if (drawerOpen) sidebarRef.current?.focus();
    else if (drawerWasOpen.current) menuButtonRef.current?.focus();
    drawerWasOpen.current = drawerOpen;
  }, [drawerOpen]);

  useEffect(() => {
    if (!drawerOpen) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setDrawerOpen(false);
        return;
      }
      if (e.key !== "Tab") return;
      const nav = sidebarRef.current;
      if (!nav) return;
      // Keep Tab inside the open drawer: wrap from the last control to the first and back.
      const items = Array.from(nav.querySelectorAll<HTMLElement>("a[href], button:not([disabled])")).filter((el) => el.offsetParent !== null);
      if (items.length === 0) return;
      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;
      if (e.shiftKey && (active === first || active === nav)) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
      } else if (!nav.contains(active)) {
        e.preventDefault();
        first.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [drawerOpen]);

  const toggleGroup = (key: string) =>
    setToggled((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });

  const nextTheme = theme === "dark" ? "light" : "dark";
  const themeLabel = nextTheme === "dark" ? t("top.theme.dark") : t("top.theme.light");
  const displayName = admin ? admin.name || admin.username : "";
  const roleKey = admin ? (`role.${admin.role}` as MessageKey) : null;
  const roleLabel = admin ? (roleKey === "role.super_admin" ? t("role.super_admin") : admin.role) : "";

  return (
    <>
      <a className="skip-link" href="#content">{t("skip")}</a>

      <header className="topbar">
        <button
          type="button"
          className="icon-btn topbar__menu"
          ref={menuButtonRef}
          aria-label={drawerOpen ? t("menu.close") : t("menu.open")}
          aria-expanded={drawerOpen}
          aria-controls="sidebar"
          onClick={() => setDrawerOpen((v) => !v)}
        >
          <Icon name="menu" size={20} />
        </button>
        <Link href="/" className="topbar__brand">
          {/* eslint-disable-next-line @next/next/no-img-element -- a fixed 32px logo: the image optimizer would add nothing */}
          <img className="topbar__logo" src={`${BASE_PATH}/seoul_beauty_logo.png`} alt="" width={32} height={32} />
          <span className="topbar__name">{t("brand")}</span>
        </Link>

        <div className="topbar__right">
          <label className="lang">
            <span className="sr-only">{t("top.language")}</span>
            <Icon name="globe" size={16} />
            <select value={locale} onChange={(e) => setLocale(e.target.value as Locale)}>
              <option value="ko">한국어</option>
              <option value="uz">O&apos;zbekcha</option>
            </select>
          </label>
          <button
            type="button"
            className="icon-btn topbar__theme"
            onClick={() => setTheme(nextTheme)}
            aria-label={mounted ? (nextTheme === "dark" ? t("top.theme.toDark") : t("top.theme.toLight")) : t("top.theme.toDark")}
            title={mounted ? themeLabel : undefined}
          >
            {mounted ? <Icon name={nextTheme === "dark" ? "moon" : "sun"} size={18} /> : <Icon name="sun" size={18} />}
          </button>
          <a className="topbar__legacy" href={PHP_ADMIN_URL} title={t("top.legacyTitle")}>
            <Icon name="external" size={15} />
            <span className="topbar__legacy-text">{t("top.legacy")}</span>
          </a>
          {admin ? (
            <span className="topbar__who" title={t("top.signedIn")}>
              <span className="topbar__avatar" aria-hidden="true">{(displayName || "?").slice(0, 1).toUpperCase()}</span>
              <span className="topbar__who-text">
                <span className="topbar__who-name">{displayName}</span>
                <span className="topbar__who-role">{roleLabel}</span>
              </span>
            </span>
          ) : null}
          <a className="icon-btn" href={PHP_LOGOUT_URL} aria-label={t("top.logout")} title={t("top.logout")}>
            <Icon name="logout" size={18} />
          </a>
        </div>
      </header>

      <nav id="sidebar" ref={sidebarRef} tabIndex={-1} className={drawerOpen ? "sidebar is-open" : "sidebar"} aria-label={t("menu.label")}>
        <p className="sidebar__legend">{t("menu.legend")}</p>
        <a className="sidebar__legacy-link" href={PHP_ADMIN_URL}>
          <Icon name="external" size={15} />
          <span>{t("top.legacy")}</span>
        </a>
        {MENU.map((group, index) => {
          const hasActive = group.items.some((item) => item.status === "new" && item.href === pathname);
          const open = (index === 0 || hasActive) !== toggled.has(group.key);
          const listId = `menu-${group.key}`;
          const groupLabel = group.label[locale];
          return (
            <div key={group.key} className="sidebar__group">
              <button type="button" className="sidebar__head" aria-expanded={open} aria-controls={listId} onClick={() => toggleGroup(group.key)}>
                <span className="sidebar__head-icon"><Icon name={group.icon} size={16} /></span>
                <span className="sidebar__head-text">{groupLabel}</span>
                <Icon name="chevronUp" size={12} className={open ? "sidebar__chevron" : "sidebar__chevron is-closed"} />
              </button>
              <ul id={listId} className="sidebar__list" hidden={!open}>
                {group.items.map((item) => {
                  const label = item.label[locale];
                  if (item.status === "new") {
                    const active = pathname === item.href;
                    return (
                      <li key={item.key}>
                        <Link href={item.href} className={active ? "sidebar__link is-active" : "sidebar__link"} aria-current={active ? "page" : undefined} onClick={() => setDrawerOpen(false)}>
                          <Icon name={item.icon} size={15} />
                          <span className="sidebar__label">{label}</span>
                        </Link>
                      </li>
                    );
                  }
                  return (
                    <li key={item.key}>
                      <a href={item.href} className="sidebar__link" title={t("menu.legacyHint")}>
                        <Icon name={item.icon} size={15} />
                        <span className="sidebar__label">{label}</span>
                        <span className="tag-legacy">{t("menu.legacy")}</span>
                      </a>
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
          <h1 className="page-head__title">
            <Icon name={icon} size={22} />
            {title}
          </h1>
          <p className="page-head__desc">{description}</p>
        </div>
        {intro}
        <div className="content__body">{children}</div>
      </main>
    </>
  );
}
