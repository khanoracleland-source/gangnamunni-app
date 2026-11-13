"use client";

import type { ReactNode } from "react";
import Icon, { type IconName } from "./Icon";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";

function StatePanel({ icon, title, children, actions, role }: { icon: IconName; title: string; children: ReactNode; actions: ReactNode; role: "alert" | "status" }) {
  return (
    <section className="panel" role={role}>
      <h2 className="panel__head">
        <Icon name={icon} size={18} />
        {title}
      </h2>
      <div className="panel__body">
        <p className="panel__text">{children}</p>
        <div className="panel__actions">{actions}</div>
      </div>
    </section>
  );
}

function BackLink() {
  const t = useT();
  return (
    <a className="btn btn--secondary" href={PHP_ADMIN_URL}>
      <Icon name="external" />
      {t("state.legacy")}
    </a>
  );
}

/** Signed in, but not as a super admin. */
export function ForbiddenScreen() {
  const t = useT();
  return (
    <StatePanel icon="lock" title={t("state.forbidden.title")} actions={<BackLink />} role="alert">
      {t("state.forbidden.body")}
    </StatePanel>
  );
}

export type ErrorKind = "disabled" | "session" | "network";

export function ErrorScreen({ kind, onRetry, retrying }: { kind: ErrorKind; onRetry: () => void; retrying: boolean }) {
  const t = useT();
  return (
    <StatePanel
      icon="alert"
      title={t(`state.${kind}.title`)}
      role="alert"
      actions={
        <>
          <button type="button" className="btn btn--primary" onClick={onRetry} disabled={retrying}>
            <Icon name="refresh" className={retrying ? "is-spinning" : undefined} />
            {t("state.retry")}
          </button>
          <BackLink />
        </>
      }
    >
      {t(`state.${kind}.body`)}
    </StatePanel>
  );
}

/** Shown for the moment between a 401 and the browser leaving for the PHP login page. */
export function RedirectingScreen() {
  const t = useT();
  return (
    <StatePanel icon="lock" title={t("state.redirect.title")} actions={<BackLink />} role="status">
      {t("state.redirect.body")}
    </StatePanel>
  );
}
