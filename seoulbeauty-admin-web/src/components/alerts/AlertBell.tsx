"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import Icon from "../Icon";
import { fetchAlertsUnread } from "@/lib/alertsApi";
import { ALERT_POLL_MS, ALERT_UNREAD_EVENT, ALERTS_ROUTE } from "@/lib/alerts";
import { useT } from "@/lib/locale";

/**
 * The bell in the top bar, in front of the language button: opens the admin's own inbox. A red dot shows while something is unread
 * (the count itself is only in the accessible name). Drawn only when the server has the inbox switched on.
 */
export default function AlertBell({ enabled }: { enabled: boolean }) {
  const t = useT();
  const pathname = usePathname();
  const [unread, setUnread] = useState(0);

  useEffect(() => {
    if (!enabled) return;
    let controller = new AbortController();
    // The session is gone (401): stop asking until the page is opened again.
    let signedOut = false;
    const load = () => {
      if (signedOut) return;
      controller.abort();
      controller = new AbortController();
      const mine = controller;
      void fetchAlertsUnread(mine.signal).then((res) => {
        if (mine.signal.aborted) return;
        if (res.ok) setUnread(res.data);
        else if (res.status === 401) signedOut = true;
      });
    };
    load();
    const onFocus = () => load();
    const onVisible = () => { if (document.visibilityState === "visible") load(); };
    const onCount = (e: Event) => {
      const n = (e as CustomEvent<number>).detail;
      if (typeof n === "number" && n >= 0) setUnread(Math.floor(n));
    };
    const timer = window.setInterval(() => { if (document.visibilityState === "visible") load(); }, ALERT_POLL_MS);
    window.addEventListener("focus", onFocus);
    document.addEventListener("visibilitychange", onVisible);
    window.addEventListener(ALERT_UNREAD_EVENT, onCount);
    return () => {
      controller.abort();
      window.clearInterval(timer);
      window.removeEventListener("focus", onFocus);
      document.removeEventListener("visibilitychange", onVisible);
      window.removeEventListener(ALERT_UNREAD_EVENT, onCount);
    };
  }, [enabled]);

  if (!enabled) return null;
  const count = unread;
  const label = count > 0 ? t("alerts.bell.unread", { n: count }) : t("alerts.bell.none");
  return (
    <Link href={ALERTS_ROUTE} className="ibtn alert-bell" aria-label={label} title={t("alerts.bell")} aria-current={pathname === ALERTS_ROUTE ? "page" : undefined}>
      <Icon name="bell" />
      {count > 0 ? <span className="alert-bell__dot" aria-hidden="true" /> : null}
    </Link>
  );
}
