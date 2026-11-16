"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useMemo, useRef, useState, useSyncExternalStore, type ReactNode } from "react";
import Icon from "./Icon";
import LangSelect from "./LangSelect";
import type { Admin, Features } from "@/lib/api";
import { BASE_PATH, PHP_ADMIN_URL, PHP_LOGOUT_URL } from "@/lib/config";
import type { MessageKey } from "@/lib/i18n";
import { useLocale, useT } from "@/lib/locale";
import { buildMenu, homeRoute, isActiveRoute } from "@/lib/menu";
import { getServerTheme, getTheme, setTheme, subscribeTheme } from "@/lib/theme";

const subscribeNever = () => () => {};

/** Roles that have a translated name; any other role is shown as stored. */
const ROLE_KEYS = new Map<string, MessageKey>([
  ["super_admin", "role.super_admin"],
  ["hospital_super_admin", "role.hospital_super_admin"],
  ["hospital_admin", "role.hospital_admin"],
]);

type Props = {
  /** null until /v1/me has answered (or when it refused). */
  admin: Admin | null;
  /** Pages the server has switched on; null together with `admin`. */
  features?: Features | null;
  title: string;
  description: string;
  /** Right side of the page heading (last update, refresh button). */
  actions?: ReactNode;
  /** Collapsible explanation under the heading. */
  intro?: ReactNode;
  /** When set, replaces the whole page body (heading included): loading, error and access screens. */
  screen?: ReactNode;
  children: ReactNode;
};

/** Frame shared by every admin page: top bar, grouped menu on the left, page heading, content. */
export default function AdminShell({ admin, features = null, title, description, actions, intro, screen, children }: Props) {
  const t = useT();
  const locale = useLocale();
  const pathname = usePathname();
  const theme = useSyncExternalStore(subscribeTheme, getTheme, getServerTheme);
  // The drawer state only matters below 1200px, where the menu is off-screen until opened.
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
      const items = Array.from(nav.querySelectorAll<HTMLElement>("a[href], summary, button:not([disabled])")).filter((el) => el.offsetParent !== null);
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
  const roleKey = admin ? ROLE_KEYS.get(admin.role) : undefined;
  const roleLabel = admin ? (roleKey ? t(roleKey) : admin.role) : "";
  const scope = admin ? admin.scope : null;
  // Until the signed-in admin is known (loading, 401/403/error screens) no page group is drawn: the role decides which ones may be shown.
  const menu = useMemo(() => (admin ? buildMenu(features, scope) : []), [admin, features, scope]);

  return (
    <>
      <a className="skip-link" href="#content">{t("skip")}</a>

      <div className="app">
        <header className="topbar">
          <button
            type="button"
            className="ibtn topbar__menu"
            ref={menuButtonRef}
            aria-label={drawerOpen ? t("menu.close") : t("menu.open")}
            aria-expanded={drawerOpen}
            aria-controls="sidebar"
            onClick={() => setDrawerOpen((v) => !v)}
          >
            <Icon name="menu" />
          </button>
          <Link href={homeRoute(scope)} className="brand">
            {/* eslint-disable-next-line @next/next/no-img-element -- fixed 32px static file, no optimizer needed */}
            <img className="brand__logo" src={`${BASE_PATH}/seoul_beauty_logo.png`} width={32} height={32} alt="서울미녀" />
            <span className="brand__name">{t("brand")}</span>
          </Link>

          <span className="spacer" />
          {admin ? <span className="who" title={displayName ? `${t("top.signedIn")}: ${displayName}` : t("top.signedIn")}>{roleLabel}</span> : null}
          <LangSelect />
          <a className="ibtn top-legacy" href={PHP_ADMIN_URL} title={t("top.legacyTitle")} aria-label={t("top.legacyTitle")}>
            <Icon name="external" />
            <span>{t("top.legacy")}</span>
          </a>
          <button
            type="button"
            className="ibtn"
            onClick={() => setTheme(nextTheme)}
            aria-label={mounted ? (nextTheme === "dark" ? t("top.theme.toDark") : t("top.theme.toLight")) : t("top.theme.toDark")}
            title={mounted ? themeLabel : undefined}
          >
            <Icon name={mounted && nextTheme === "light" ? "sun" : "moon"} />
          </button>
          <a className="ibtn" href={PHP_LOGOUT_URL} aria-label={t("top.logout")} title={t("top.logout")}>
            <Icon name="logout" />
          </a>
        </header>

        <nav id="sidebar" ref={sidebarRef} tabIndex={-1} className={drawerOpen ? "sidebar is-open" : "sidebar"} aria-label={t("menu.label")}>
          {admin ? (
            <p className="sidebar__who">
              <span>{t("top.signedIn")}</span>
              <b>{displayName || roleLabel}</b>
              {displayName ? <span>{roleLabel}</span> : null}
            </p>
          ) : null}
          <a className={scope === "own" || !admin ? "legacy-link legacy-link--always" : "legacy-link"} href={PHP_ADMIN_URL}>
            <Icon name="external" />
            <span>{t("state.legacy")}</span>
          </a>
          {menu.map((group, index) => {
            const hasActive = group.items.some((item) => item.status === "new" && isActiveRoute(pathname, item.href));
            const open = (index === 0 || hasActive) !== toggled.has(group.key);
            return (
              <details
                key={group.key}
                className="nav-group"
                open={open}
                onToggle={(e) => {
                  if (e.currentTarget.open !== open) toggleGroup(group.key);
                }}
              >
                <summary>
                  <Icon name={group.icon} />
                  <span className="nav-group__text">{group.label[locale]}</span>
                  <Icon name="chevron" size={16} className="chev" />
                </summary>
                <ul>
                  {group.items.map((item) => {
                    const label = item.label[locale];
                    if (item.status === "new") {
                      const active = isActiveRoute(pathname, item.href);
                      return (
                        <li key={item.key}>
                          <Link href={item.href} className="nav-item" aria-current={active ? "page" : undefined} onClick={() => setDrawerOpen(false)}>
                            <span className="nav-item__label">{label}</span>
                          </Link>
                        </li>
                      );
                    }
                    return (
                      <li key={item.key}>
                        <a href={item.href} className="nav-item" title={t("menu.legacyHint")}>
                          <span className="nav-item__label">{label}</span>
                          <span className="tag">{t("menu.legacy")}</span>
                        </a>
                      </li>
                    );
                  })}
                </ul>
              </details>
            );
          })}
          {admin ? <p className="legend">{scope === "own" ? t("menu.legendOwn") : t("menu.legend")}</p> : null}
        </nav>
        {drawerOpen ? <div className="drawer-scrim" onClick={() => setDrawerOpen(false)} aria-hidden="true" /> : null}

        {screen ? (
          <main id="content" className="main main--state" tabIndex={-1}>{screen}</main>
        ) : (
          <main id="content" className="main" tabIndex={-1}>
            <div className="page">
              <div className="page-head">
                <div className="page-head__text">
                  <h1>{title}</h1>
                  <p>{description}</p>
                </div>
                {actions}
              </div>
              {intro}
              {children}
            </div>
          </main>
        )}
      </div>
    </>
  );
}
