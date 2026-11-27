"use client";

import { useEffect, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import AlertsBoard from "./AlertsBoard";
import { fetchMe, type Me } from "@/lib/api";
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

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("alerts.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen />;
  else if (phase === "error") screen = <ErrorScreen kind={errorKind} onRetry={() => { setPhase("loading"); setAttempt((n) => n + 1); }} retrying={false} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("alerts.title")} description={t("alerts.subtitle")} screen={screen}>
      {phase === "ready" && me ? <AlertsBoard /> : null}
    </AdminShell>
  );
}
