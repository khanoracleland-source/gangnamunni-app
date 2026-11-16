"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import Icon, { type IconName } from "./Icon";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";

/** Centered message that takes the place of the page: icon in a ring, one heading, one paragraph, buttons. */
function StateView({ icon, title, children, actions, role }: { icon: IconName; title: string; children: ReactNode; actions: ReactNode; role: "alert" | "status" }) {
  return (
    <section className="state" role={role}>
      <div className="state__in">
        <div className="state__ring"><Icon name={icon} size={22} /></div>
        <h1>{title}</h1>
        <p>{children}</p>
        <div className="state__acts">{actions}</div>
      </div>
    </section>
  );
}

function BackLink({ primary }: { primary?: boolean }) {
  const t = useT();
  return (
    <a className={primary ? "btn btn--primary" : "btn btn--ghost"} href={PHP_ADMIN_URL}>
      {t("state.legacy")}
    </a>
  );
}

/**
 * Signed in, but this account may not open the page.
 * "super": a page for super admins only. "hospitals": a page for super admins and hospital accounts.
 */
export function ForbiddenScreen({ variant = "super" }: { variant?: "super" | "hospitals" }) {
  const t = useT();
  const key = variant === "hospitals" ? "state.forbiddenHospitals" : "state.forbidden";
  return (
    <StateView icon="lock" title={t(`${key}.title`)} actions={<BackLink primary />} role="alert">
      {t(`${key}.body`)}
    </StateView>
  );
}

/**
 * "disabled": the whole panel is switched off. "hospitalsOff": only the hospitals page is.
 * "session": the sign-in check is down. "network": the dashboard numbers did not arrive. "load": any other page's data did not arrive.
 */
export type ErrorKind = "disabled" | "hospitalsOff" | "archiveOff" | "session" | "network" | "load";

export function ErrorScreen({ kind, onRetry, retrying }: { kind: ErrorKind; onRetry: () => void; retrying: boolean }) {
  const t = useT();
  return (
    <StateView
      icon="unplug"
      title={t(`state.${kind}.title`)}
      role="alert"
      actions={
        <>
          <button type="button" className="btn btn--primary" onClick={onRetry} disabled={retrying}>
            {t("state.retry")}
          </button>
          <BackLink />
        </>
      }
    >
      {t(`state.${kind}.body`)}
    </StateView>
  );
}

/** The address points at something that does not exist or that this account may not see (the server answers the same for both). */
export function NotFoundScreen({ backHref, backLabel }: { backHref: string; backLabel: string }) {
  const t = useT();
  return (
    <StateView
      icon="info"
      title={t("state.notFound.title")}
      role="alert"
      actions={
        <>
          <Link className="btn btn--primary" href={backHref}>{backLabel}</Link>
          <BackLink />
        </>
      }
    >
      {t("state.notFound.body")}
    </StateView>
  );
}

/** Shown for the moment between a 401 and the browser leaving for the PHP login page. */
export function RedirectingScreen() {
  const t = useT();
  return (
    <StateView icon="lock" title={t("state.redirect.title")} actions={null} role="status">
      {t("state.redirect.body")}
    </StateView>
  );
}

/** First load: one line in the middle of the page. */
export function LoadingScreen({ text }: { text?: string }) {
  const t = useT();
  return (
    <section className="state" role="status" aria-busy="true">
      <p className="state__loading">{text ?? t("loading")}</p>
    </section>
  );
}
