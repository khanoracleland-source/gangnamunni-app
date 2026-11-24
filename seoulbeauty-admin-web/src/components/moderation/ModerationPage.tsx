"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { Notice } from "../hospitals/parts";
import PostsTab from "./PostsTab";
import ReviewsTab from "./ReviewsTab";
import { fetchMe, fetchModerationHospitals, type DoctorHospitalOption, type Me } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";
import { moderationHref, parseModerationView, type ModerationTab } from "@/lib/moderation";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
type State = { phase: Phase; errorKind: ErrorKind; me: Me | null; ownHospital: string; hospitals: DoctorHospitalOption[] };
const INITIAL: State = { phase: "loading", errorKind: "load", me: null, ownHospital: "", hospitals: [] };

/** Reviews and community posts waiting for a decision. A hospital account sees only the reviews of its own hospital, with no community tab. */
export default function ModerationPage() {
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
      const [me, hospitals] = await Promise.all([fetchMe(controller.signal), fetchModerationHospitals(controller.signal)]);
      if (controller.signal.aborted) return;
      const statuses = [me, hospitals].map((r) => (r.ok ? 200 : r.status));
      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      if (statuses.includes(403)) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok && !me.data.features.moderation) {
        setState({ ...INITIAL, phase: "error", errorKind: "moderationOff", me: me.data });
        return;
      }
      if (me.ok && hospitals.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, ownHospital: me.data.admin.scope === "own" ? (hospitals.data[0]?.name ?? "") : "", hospitals: hospitals.data });
        return;
      }
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !hospitals.ok && hospitals.status === 404 ? "moderationOff" : !hospitals.ok && hospitals.status === 503 ? "session" : "load";
      setState({ ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : null });
    };
    void run();
    return () => controller.abort();
  }, [attempt]);

  const { phase, me, ownHospital, hospitals } = state;
  const isSuper = me?.admin.scope === "all";
  const view = useMemo(() => parseModerationView(new URLSearchParams(queryString), isSuper), [queryString, isSuper]);

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("mod.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={() => setAttempt((n) => n + 1)} retrying={false} />;

  const tabs: ModerationTab[] = isSuper ? ["reviews", "community"] : ["reviews"];

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("mod.title")} description={t(isSuper ? "mod.desc.super" : "mod.desc.hosp")} screen={screen}>
      {phase === "ready" ? (
        <>
          {!isSuper && ownHospital !== "" ? <Notice>{t("mod.scope.own", { name: ownHospital })}</Notice> : null}
          <nav className="mod-tabs" aria-label={t("mod.tabs.label")}>
            {tabs.map((tab) => (
              <Link
                key={tab}
                aria-current={view.tab === tab ? "page" : undefined}
                className={view.tab === tab ? "mod-tab is-active" : "mod-tab"}
                href={moderationHref({ ...view, tab, status: "pending", search: "", hospitalId: 0, page: 1 })}
                replace
              >
                {t(tab === "reviews" ? "mod.tab.reviews" : "mod.tab.community")}
              </Link>
            ))}
          </nav>
          {view.tab === "reviews" ? <ReviewsTab view={view} hospitals={hospitals} isSuper={isSuper} canDecide={me?.features.moderationWrite === true} canEdit={me?.features.moderationEdit === true} canDelete={me?.features.moderationDelete === true} /> : <PostsTab view={view} canDecide={me?.features.moderationWrite === true} canEdit={me?.features.moderationEdit === true} canDelete={me?.features.moderationDelete === true} />}
        </>
      ) : null}
    </AdminShell>
  );
}
