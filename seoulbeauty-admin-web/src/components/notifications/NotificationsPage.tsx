"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState, type KeyboardEvent, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { fetchMe, type Me } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";
import HistoryTab from "./HistoryTab";
import ScheduledTab from "./ScheduledTab";
import SendTab from "./SendTab";
import { NOTIFICATION_TABS, TAB_LABEL, notificationsHref, parseTab, type NotificationTab } from "@/lib/notifications";
import { fetchNotificationOptions, fetchScheduled, type NotificationOptions } from "@/lib/notificationsApi";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

/** Sending notifications to members: the page frame (who may open it, the tabs). The tabs themselves come in their own files. Super admin only. */
export default function NotificationsPage() {
  const t = useT();
  const [phase, setPhase] = useState<Phase>("loading");
  const [errorKind, setErrorKind] = useState<ErrorKind>("load");
  const [me, setMe] = useState<Me | null>(null);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    void (async () => {
      const res = await fetchMe(controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setMe(res.data);
        if (res.data.admin.role !== "super_admin") setPhase("forbidden");
        else if (!res.data.features.notifications) {
          setErrorKind("notificationsOff");
          setPhase("error");
        } else setPhase("ready");
        return;
      }
      if (res.status === 401) {
        setPhase("redirecting");
        leaveToLogin();
      } else if (res.status === 403) setPhase("forbidden");
      else {
        setErrorKind(res.status === 404 ? "disabled" : res.status === 503 ? "session" : "load");
        setPhase("error");
      }
    })();
    return () => controller.abort();
  }, [attempt]);

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("nt.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen />;
  else if (phase === "error") screen = <ErrorScreen kind={errorKind} onRetry={() => { setPhase("loading"); setAttempt((n) => n + 1); }} retrying={false} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("nt.title")} description={t("nt.subtitle")} screen={screen}>
      {phase === "ready" && me ? <Board me={me} /> : null}
    </AdminShell>
  );
}

function Board({ me }: { me: Me }) {
  const t = useT();
  const router = useRouter();
  const tab = parseTab(useSearchParams().get("tab"));
  const [options, setOptions] = useState<NotificationOptions | null>(null);
  const [optionsFailed, setOptionsFailed] = useState(false);
  const [plans, setPlans] = useState<number | null>(null);
  const [planTick, setPlanTick] = useState(0);
  const scheduleOn = me.features.notificationsSchedule;

  useEffect(() => {
    const c = new AbortController();
    void fetchNotificationOptions(c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      else if (res.ok) setOptions(res.data);
      else setOptionsFailed(true);
    });
    return () => c.abort();
  }, []);

  // The number on the "planned" tab: the plans that still wait.
  useEffect(() => {
    if (!scheduleOn) return;
    const c = new AbortController();
    void fetchScheduled(c.signal).then((res) => {
      if (!c.signal.aborted && res.ok) setPlans(res.data.items.filter((p) => p.status === "pending").length);
    });
    return () => c.abort();
  }, [scheduleOn, planTick]);

  // Roving tabindex: only the open tab is in the Tab order; the arrow keys (and Home/End) move between the tabs.
  const onTabKey = (e: KeyboardEvent<HTMLElement>) => {
    const index = NOTIFICATION_TABS.indexOf(tab);
    let next = index;
    if (e.key === "ArrowRight") next = (index + 1) % NOTIFICATION_TABS.length;
    else if (e.key === "ArrowLeft") next = (index - 1 + NOTIFICATION_TABS.length) % NOTIFICATION_TABS.length;
    else if (e.key === "Home") next = 0;
    else if (e.key === "End") next = NOTIFICATION_TABS.length - 1;
    else return;
    e.preventDefault();
    const target: NotificationTab = NOTIFICATION_TABS[next];
    document.getElementById(`nt-tab-${target}`)?.focus();
    router.replace(notificationsHref(target), { scroll: false });
  };
  // The open tab stays in view when the row scrolls (phones).
  useEffect(() => {
    document.getElementById(`nt-tab-${tab}`)?.scrollIntoView({ inline: "nearest", block: "nearest" });
  }, [tab]);

  const sendOff = !me.features.notificationsSend || (options !== null && !options.sendEnabled);
  const count = (k: NotificationTab): number | null => (k === "scheduled" && scheduleOn ? plans : null);

  return (
    <div className="nt-page">
      <div className="nt-top">
        {sendOff ? <span className="nt-key" title={t("nt.badge.off.title")}>{t("nt.badge.off")}</span> : null}
      </div>
      {sendOff ? <Notice icon="alert" tone="warn">{t("nt.off.notice")}</Notice> : null}
      {options && !options.fcmConfigured && !sendOff ? <Notice icon="alert" tone="warn">{t("nt.fcm.notice")}</Notice> : null}
      {optionsFailed ? <Notice icon="alert" tone="warn">{t("nt.options.failed")}</Notice> : null}

      <div className="nt-tabs" role="tablist" aria-label={t("nt.tabs")} onKeyDown={onTabKey}>
        {NOTIFICATION_TABS.map((k) => {
          const selected = tab === k;
          const n = count(k);
          return (
            <Link key={k} role="tab" id={`nt-tab-${k}`} aria-selected={selected} aria-controls="nt-panel" tabIndex={selected ? 0 : -1} className="nt-tab" href={notificationsHref(k)} replace scroll={false}>
              {t(TAB_LABEL[k])}
              {n !== null ? <span className="nt-tab__n">{formatNumber(n)}</span> : null}
            </Link>
          );
        })}
      </div>

      <div id="nt-panel" role="tabpanel" aria-labelledby={`nt-tab-${tab}`} className="nt-panel">
        {tab === "send" ? (
          <SendTab me={me} options={options} onPlanned={() => { setPlanTick((n) => n + 1); router.replace(notificationsHref("scheduled"), { scroll: false }); }} />
        ) : tab === "history" ? (
          <HistoryTab />
        ) : tab === "scheduled" ? (
          <ScheduledTab enabled={scheduleOn && options?.scheduleEnabled !== false} onChanged={() => setPlanTick((n) => n + 1)} />
        ) : (
          <div className="nt-soon">
            <Icon name="phone" size={20} />
            <p>{t("nt.soon")}</p>
          </div>
        )}
      </div>
    </div>
  );
}
