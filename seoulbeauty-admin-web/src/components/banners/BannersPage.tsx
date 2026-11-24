"use client";

import { useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import BannerList from "./BannerList";
import ScrollCard from "./ScrollCard";
import { fetchBannerHospitals, fetchMe, type DoctorHospitalOption, type Me } from "@/lib/api";
import { parseBannerView } from "@/lib/banners";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
type State = { phase: Phase; errorKind: ErrorKind; me: Me | null; hospitals: DoctorHospitalOption[] };
const INITIAL: State = { phase: "loading", errorKind: "load", me: null, hospitals: [] };

/** The banners of the home page and My page. Super admin only: the server answers everyone else 403. */
export default function BannersPage() {
  const t = useT();
  const searchParams = useSearchParams();
  const queryString = searchParams.toString();
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  const halted = useRef(false);

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const [me, hospitals] = await Promise.all([fetchMe(controller.signal), fetchBannerHospitals(controller.signal)]);
      if (controller.signal.aborted) return;
      const statuses = [me, hospitals].map((r) => (r.ok ? 200 : r.status));
      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      if (statuses.includes(403) || (me.ok && me.data.admin.scope !== "all")) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok && !me.data.features.banners) {
        setState({ ...INITIAL, phase: "error", errorKind: "bannersOff", me: me.data });
        return;
      }
      if (me.ok && hospitals.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, hospitals: hospitals.data });
        return;
      }
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !hospitals.ok && hospitals.status === 404 ? "bannersOff" : !hospitals.ok && hospitals.status === 503 ? "session" : "load";
      setState({ ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : null });
    };
    void run();
    return () => controller.abort();
  }, [attempt]);

  const { phase, me, hospitals } = state;
  const view = useMemo(() => parseBannerView(new URLSearchParams(queryString)), [queryString]);
  const canWrite = me?.features.bannersWrite === true;

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("ban.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={() => setAttempt((n) => n + 1)} retrying={false} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("ban.title")} description={t("ban.desc")} screen={screen}>
      {phase === "ready" ? (
        <div className="ban-page">
          <ScrollCard canWrite={canWrite} />
          <BannerList view={view} hospitals={hospitals} canWrite={canWrite} />
        </div>
      ) : null}
    </AdminShell>
  );
}
