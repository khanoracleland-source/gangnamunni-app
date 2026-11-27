"use client";

import { useEffect, useState, type ReactNode } from "react";
import Icon from "../Icon";
import AdminShell from "../AdminShell";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import AlertsBoard from "./AlertsBoard";
import PurgeDialog from "./PurgeDialog";
import { fetchMe, type Me } from "@/lib/api";
import type { AlertTab } from "@/lib/alerts";
import { markAlertsTabRead, type AlertCounts } from "@/lib/alertsApi";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

/** The admin's own alert inbox: the page frame (who may open it). Every signed-in admin sees only their own alerts. */
export default function AlertsPage() {
  const t = useT();
  const [phase, setPhase] = useState<Phase>("loading");
  const [errorKind, setErrorKind] = useState<ErrorKind>("load");
  const [me, setMe] = useState<Me | null>(null);
  const [attempt, setAttempt] = useState(0);
  const [tab, setTab] = useState<AlertTab>("all");
  const [tick, setTick] = useState(0);
  const [counts, setCounts] = useState<AlertCounts | null>(null);
  const [flash, setFlash] = useState<{ tone: "ok" | "warn"; text: string } | null>(null);
  const [purgeOpen, setPurgeOpen] = useState(false);
  const [marking, setMarking] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    void (async () => {
      const res = await fetchMe(controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setMe(res.data);
        if (!res.data.features.alerts) {
          setErrorKind("alertsOff");
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

  const reload = () => setTick((n) => n + 1);
  const unreadInTab = counts ? counts[tab] : 0;
  const readTab = () => {
    if (marking || unreadInTab === 0) return;
    setMarking(true);
    setFlash(null);
    void markAlertsTabRead(tab).then((res) => {
      setMarking(false);
      if (res.ok) {
        setFlash({ tone: "ok", text: t("alerts.flash.read", { n: String(res.data.updated) }) });
        reload();
      } else if (res.status === 401) leaveToLogin();
      else setFlash({ tone: "warn", text: t("alerts.readTab.failed") });
    });
  };

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("alerts.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen />;
  else if (phase === "error") screen = <ErrorScreen kind={errorKind} onRetry={() => { setPhase("loading"); setAttempt((n) => n + 1); }} retrying={false} />;

  const actions = phase === "ready" ? (
    <div className="page-actions">
      <button type="button" className="btn btn--ghost" onClick={readTab} disabled={marking || unreadInTab === 0} aria-busy={marking}>
        <Icon name="check" size={16} />
        {t(tab === "all" ? "alerts.readAll" : "alerts.readTab")}
      </button>
      <button type="button" className="btn btn--ghost" onClick={() => setPurgeOpen(true)}>
        <Icon name="trash" size={16} />
        {t("alerts.purge.open")}
      </button>
    </div>
  ) : undefined;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("alerts.title")} description={t("alerts.subtitle")} actions={actions} screen={screen}>
      {phase === "ready" && me ? <AlertsBoard tab={tab} onTab={(next) => { setTab(next); setFlash(null); }} tick={tick} onReload={reload} onCounts={setCounts} notice={flash} features={me.features} scope={me.admin.scope} /> : null}
      {purgeOpen ? (
        <PurgeDialog
          onClose={() => setPurgeOpen(false)}
          onUnauthorized={leaveToLogin}
          onDone={(deleted) => { setPurgeOpen(false); setFlash({ tone: "ok", text: t("alerts.flash.purged", { n: String(deleted) }) }); reload(); }}
        />
      ) : null}
    </AdminShell>
  );
}
