"use client";

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

/** Signed in, but not as a super admin. */
export function ForbiddenScreen() {
  const t = useT();
  return (
    <StateView icon="lock" title={t("state.forbidden.title")} actions={<BackLink primary />} role="alert">
      {t("state.forbidden.body")}
    </StateView>
  );
}

export type ErrorKind = "disabled" | "session" | "network";

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
export function LoadingScreen() {
  const t = useT();
  return (
    <section className="state" role="status" aria-busy="true">
      <p className="state__loading">{t("loading")}</p>
    </section>
  );
}
