"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState, type KeyboardEvent, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import { Notice } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import CategoriesTab from "./CategoriesTab";
import ConflictsTab from "./ConflictsTab";
import TreatmentsTab from "./TreatmentsTab";
import { fetchMe, fetchTreatments, type Me, type TreatmentsData } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { TREATMENT_TABS, legacyTreatmentsUrl, parseTreatmentsQuery, treatmentsHref, type TreatmentsTab as TabKey } from "@/lib/treatments";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";

type State = { phase: Phase; errorKind: ErrorKind; me: Me | null; data: TreatmentsData | null };

const INITIAL: State = { phase: "loading", errorKind: "load", me: null, data: null };

const TAB_LABELS: Record<TabKey, { long: MessageKey; short: MessageKey }> = {
  treatments: { long: "tm.tab.treatments", short: "tm.tab.treatments.short" },
  categories: { long: "tm.tab.categories", short: "tm.tab.categories.short" },
  conflicts: { long: "tm.tab.conflicts", short: "tm.tab.conflicts.short" },
};

/** The treatment master: three tabs (treatments, categories, pairs that cannot be booked together), all read from one answer. Super admin only. */
export default function TreatmentsPage() {
  const t = useT();
  const router = useRouter();
  const searchParams = useSearchParams();
  // The address is the single source of the page state, so back, forward and reload all work.
  const queryString = searchParams.toString();
  const query = useMemo(() => parseTreatmentsQuery(new URLSearchParams(queryString)), [queryString]);
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  // 401 / 403 are final for this page view: stop asking.
  const halted = useRef(false);

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const [me, data] = await Promise.all([fetchMe(controller.signal), fetchTreatments(controller.signal)]);
      if (controller.signal.aborted) return;
      const statuses = [me, data].map((r) => (r.ok ? 200 : r.status));
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
      // The server says the page is switched off: show that, whatever the list request answered.
      if (me.ok && !me.data.features.treatments) {
        setState({ ...INITIAL, phase: "error", errorKind: "treatmentsOff", me: me.data });
        return;
      }
      if (me.ok && data.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, data: data.data });
        return;
      }
      // 404 on "who am I" means the whole panel is off; 404 on the list alone means only this page is.
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !data.ok && data.status === 404 ? "treatmentsOff" : !data.ok && data.status === 503 ? "session" : "load";
      setState({ ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : null });
    };
    void run();
    return () => controller.abort();
  }, [attempt]);

  const retry = () => setAttempt((n) => n + 1);
  const { phase, me, data } = state;
  const busy = phase === "loading";

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("tm.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="super" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={busy} />;

  const readOnly = me?.features.treatmentsWrite !== true;
  // Roving tabindex: only the open tab is in the Tab order; the arrow keys (and Home/End) move between the tabs.
  const onTabKey = (e: KeyboardEvent<HTMLElement>) => {
    const index = TREATMENT_TABS.indexOf(query.tab);
    let next = index;
    if (e.key === "ArrowRight") next = (index + 1) % TREATMENT_TABS.length;
    else if (e.key === "ArrowLeft") next = (index - 1 + TREATMENT_TABS.length) % TREATMENT_TABS.length;
    else if (e.key === "Home") next = 0;
    else if (e.key === "End") next = TREATMENT_TABS.length - 1;
    else return;
    e.preventDefault();
    const tab = TREATMENT_TABS[next];
    document.getElementById(`tm-tab-${tab}`)?.focus();
    router.replace(treatmentsHref({ tab }), { scroll: false });
  };
  const counts: Record<TabKey, number> = {
    treatments: data?.items.length ?? 0,
    categories: data?.categories.length ?? 0,
    conflicts: data?.conflicts.length ?? 0,
  };

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("tm.title")} description={t("tm.desc")} screen={screen}>
      {data && readOnly ? (
        <div className="tm__notice">
          <Notice tone="warn" icon="alert" action={<a className="txtbtn" href={legacyTreatmentsUrl}>{t("tm.readOnly.legacy")}</a>}>
            {t("tm.readOnly")}
          </Notice>
        </div>
      ) : null}

      {data ? (
        <>
          <div className="tm__tabs" role="tablist" aria-label={t("tm.tabs")} onKeyDown={onTabKey}>
            {TREATMENT_TABS.map((tab) => {
              const selected = query.tab === tab;
              return (
                <Link
                  key={tab}
                  role="tab"
                  id={`tm-tab-${tab}`}
                  aria-selected={selected}
                  aria-controls="tm-panel"
                  tabIndex={selected ? 0 : -1}
                  className="tm__tab"
                  href={treatmentsHref({ tab })}
                  replace
                  scroll={false}
                >
                  <span className="tm__tab-long">{t(TAB_LABELS[tab].long)}</span>
                  <span className="tm__tab-short">{t(TAB_LABELS[tab].short)}</span>
                  <span className="tm__tab-n">{formatNumber(counts[tab])}</span>
                </Link>
              );
            })}
          </div>

          <div id="tm-panel" role="tabpanel" aria-labelledby={`tm-tab-${query.tab}`}>
            {query.tab === "treatments" ? <TreatmentsTab data={data} query={query} /> : null}
            {query.tab === "categories" ? <CategoriesTab data={data} /> : null}
            {query.tab === "conflicts" ? <ConflictsTab data={data} /> : null}
          </div>
        </>
      ) : null}
    </AdminShell>
  );
}
