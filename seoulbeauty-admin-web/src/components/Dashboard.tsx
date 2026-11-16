"use client";

import { useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import AdminShell from "./AdminShell";
import Delta from "./Delta";
import HelpDialog from "./HelpDialog";
import Icon from "./Icon";
import Metric, { type MetricId } from "./Metric";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "./StateScreens";
import { fetchMe, fetchSummary, type Admin, type DashboardSummary, type Features } from "@/lib/api";
import { PHP_ADMIN_URL, REFRESH_INTERVAL_MS } from "@/lib/config";
import { formatClock, formatNumber, formatPercent, percentOf } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
type Period = "d7" | "d30";

type State = {
  phase: Phase;
  errorKind: ErrorKind;
  admin: Admin | null;
  features: Features | null;
  summary: DashboardSummary | null;
  /** When the numbers on screen were received (viewer's clock). */
  updatedAt: Date | null;
  /** The last background refresh failed: the numbers on screen are older than they should be. */
  stale: boolean;
};

const INITIAL: State = { phase: "loading", errorKind: "network", admin: null, features: null, summary: null, updatedAt: null, stale: false };

const errorKindOf = (status: number): ErrorKind => (status === 404 ? "disabled" : status === 503 ? "session" : "network");

export default function Dashboard() {
  const t = useT();
  const router = useRouter();
  const [state, setState] = useState<State>(INITIAL);
  const [busy, setBusy] = useState(false);
  const [period, setPeriod] = useState<Period>("d7");
  const [help, setHelp] = useState<MetricId | null>(null);
  const inFlight = useRef(false);
  const lastLoadAt = useRef(0);
  // 401 / 403 are final for this page view: stop asking.
  const halted = useRef(false);

  const load = useCallback(async () => {
    if (inFlight.current || halted.current) return;
    inFlight.current = true;
    lastLoadAt.current = Date.now();
    try {
      const [me, summary] = await Promise.all([fetchMe(), fetchSummary()]);
      const failed = !me.ok ? me.status : !summary.ok ? summary.status : null;
      const statuses = [me, summary].map((r) => (r.ok ? 200 : r.status));

      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      // A hospital account has no dashboard (its API answers 403): its home is the hospitals page.
      if (me.ok && me.data.admin.scope === "own" && me.data.features.hospitals) {
        halted.current = true;
        router.replace("/hospitals");
        return;
      }
      if (statuses.includes(403)) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok && summary.ok) {
        setState({ phase: "ready", errorKind: "network", admin: me.data.admin, features: me.data.features, summary: summary.data, updatedAt: new Date(), stale: false });
        return;
      }
      const kind = errorKindOf(failed ?? 0);
      setState((s) =>
        // A hiccup during a background refresh keeps the numbers already on screen (marked as stale).
        // A switched-off panel (404) always replaces them.
        s.summary && s.phase === "ready" && kind !== "disabled"
          ? { ...s, stale: true }
          : { ...INITIAL, phase: "error", errorKind: kind, admin: me.ok ? me.data.admin : s.admin, features: me.ok ? me.data.features : s.features },
      );
    } finally {
      inFlight.current = false;
      setBusy(false);
    }
  }, [router]);

  // First load, then every minute while the tab is visible. A hidden tab does not poll. Coming back
  // schedules the next fetch relative to the last one: at once when the numbers are already a minute
  // old, otherwise when they turn a minute old (the countdown is not restarted from zero).
  useEffect(() => {
    let timer: number | undefined;
    const stop = () => {
      if (timer !== undefined) window.clearTimeout(timer);
      timer = undefined;
    };
    const tick = () => {
      void load();
      timer = window.setTimeout(tick, REFRESH_INTERVAL_MS);
    };
    const start = () => {
      stop();
      const age = Date.now() - lastLoadAt.current;
      timer = window.setTimeout(tick, Math.max(0, REFRESH_INTERVAL_MS - age));
    };
    const onVisibility = () => {
      if (document.hidden) stop();
      else start();
    };
    void load();
    if (!document.hidden) start();
    document.addEventListener("visibilitychange", onVisibility);
    return () => {
      stop();
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, [load]);

  const refresh = () => {
    if (inFlight.current) return;
    setBusy(true);
    void load();
  };

  const { phase, admin, features, summary, updatedAt, stale } = state;
  const days = period === "d7" ? 7 : 30;

  let screen: React.ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={refresh} retrying={busy} />;

  const actions = (
    <div className="updated">
      <span className={stale ? "updated__text updated__text--stale" : "updated__text"} title={summary?.generatedAt ? t("updated.title", { at: summary.generatedAt }) : undefined}>
        {stale && updatedAt
          ? t("updated.failed", { time: formatClock(updatedAt) })
          : updatedAt
            ? t("updated", { time: formatClock(updatedAt) })
            : t("updated.none")}
      </span>
      {stale ? <span className="sr-only" role="alert">{t("updated.failedAlert")}</span> : null}
      <button type="button" className="txtbtn" onClick={refresh} disabled={busy} aria-label={t("refresh.aria")}>
        <Icon name="refresh" size={18} className={busy ? "is-spinning" : undefined} />
        {t("refresh")}
      </button>
    </div>
  );

  return (
    <AdminShell admin={admin} features={features} title={t("page.title")} description={t("page.desc")} actions={actions} intro={<PageIntro />} screen={screen}>
      <div className="period" role="group" aria-labelledby="period-label">
        <span id="period-label" className="period__label">{t("period.label")}</span>
        <div className="seg">
          {(["d7", "d30"] as const).map((p) => (
            <button key={p} type="button" aria-pressed={p === period} onClick={() => setPeriod(p)}>
              {t(`period.${p}`)}
            </button>
          ))}
        </div>
        <span className="period__hint">{t("period.hint")}</span>
      </div>

      {summary ? <SummaryView summary={summary} period={period} onHelp={setHelp} /> : null}

      <HelpDialog metric={help} days={days} onClose={() => setHelp(null)} />
    </AdminShell>
  );
}

/** "이 화면은 무엇인가요?": closed by default, so the numbers stay the first thing on the page. */
function PageIntro() {
  const t = useT();
  const items: { q: MessageKey; a: MessageKey }[] = [
    { q: "page.help.what", a: "page.help.whatBody" },
    { q: "page.help.refresh", a: "page.help.refreshBody" },
    { q: "page.help.period", a: "page.help.periodBody" },
    { q: "page.help.compare", a: "page.help.compareBody" },
    { q: "page.help.question", a: "page.help.questionBody" },
  ];
  return (
    <details className="intro">
      <summary className="intro__summary">
        <span>{t("page.help.toggle")}</span>
        <Icon name="chevron" size={16} className="chev" />
      </summary>
      <dl className="intro__list">
        {items.map((item) => (
          <div key={item.q} className="intro__item">
            <dt>{t(item.q)}</dt>
            <dd>{t(item.a)}</dd>
          </div>
        ))}
      </dl>
    </details>
  );
}

function Section({ id, title, desc, children }: { id: string; title: string; desc: string; children: React.ReactNode }) {
  return (
    <section className="section" aria-labelledby={id}>
      <h2 id={id}>{title}</h2>
      <p className="section__desc">{desc}</p>
      {children}
    </section>
  );
}

function SummaryView({ summary, period, onHelp }: { summary: DashboardSummary; period: Period; onHelp: (id: MetricId) => void }) {
  const t = useT();
  const { members, catalog } = summary;
  const days = period === "d7" ? 7 : 30;
  const b = summary.bookings[period];
  const consultations = summary.consultations[period];
  const cancelRate = percentOf(b.cancelled, b.count);

  // An admin who is also a member must be counted once: overall = members + admins - those who are both.
  const both = Math.min(members.adminsAlsoMembers, members.total, members.admins);
  const all = members.total + members.admins - both;
  const general = members.total - both;
  const signups = period === "d7" ? members.last7d : members.last30d;

  return (
    <>
      <Section id="sec-members" title={t("sec.members")} desc={t("sec.members.desc")}>
        <div className="grid">
          <Metric
            hero
            id="all"
            value={formatNumber(all)}
            unit="person"
            note={members.admins > 0 ? t("m.all.note", { n: formatNumber(members.admins) }) : t("m.all.noteNone")}
            split={{ general: formatNumber(general), admins: formatNumber(members.admins) }}
            onHelp={onHelp}
          />
          <Metric id="general" value={formatNumber(general)} unit="person" note={t("m.general.note")} onHelp={onHelp} />
          <Metric id="withdrawn" value={formatNumber(members.withdrawn)} unit="person" note={t("m.withdrawn.note")} onHelp={onHelp} />
          <Metric id="today" value={formatNumber(members.today)} unit="person" note={t("m.today.note")} onHelp={onHelp} />
          <Metric id="signup" value={formatNumber(signups)} unit="person" note={t("m.signup.note", { days })} onHelp={onHelp} />
        </div>
      </Section>

      <Section id="sec-bookings" title={t("sec.bookings")} desc={t("sec.bookings.desc")}>
        <div className="grid">
          <Metric id="bookCount" value={formatNumber(b.count)} unit="case" note={<Delta current={b.count} previous={b.prev.count} days={days} />} onHelp={onHelp} />
          <Metric id="bookAmount" value={formatNumber(b.amount)} unit="won" note={<Delta current={b.amount} previous={b.prev.amount} days={days} />} onHelp={onHelp} />
          <Metric id="bookPaid" value={formatNumber(b.paidAmount)} unit="won" note={<Delta current={b.paidAmount} previous={b.prev.paidAmount} days={days} />} onHelp={onHelp} />
          <Metric id="bookRefund" value={formatNumber(b.refundedAmount)} unit="won" note={t("m.bookRefund.note", { days })} onHelp={onHelp} />
          <Metric
            id="bookCancel"
            value={formatNumber(b.cancelled)}
            unit="case"
            note={cancelRate === null ? t("m.bookCancel.noteNone") : t("m.bookCancel.note", { rate: formatPercent(cancelRate) })}
            onHelp={onHelp}
          />
          <Metric
            id="bookPending"
            value={formatNumber(b.pending)}
            unit="case"
            note={
              b.pending > 0 ? (
                <>
                  <span className="badge">{t("m.bookPending.badge")}</span>
                  <span>{t("m.bookPending.note")}</span>
                </>
              ) : (
                t("m.bookPending.note")
              )
            }
            onHelp={onHelp}
          />
          <Metric id="consult" value={formatNumber(consultations)} unit="case" note={t("m.consult.note", { days })} onHelp={onHelp} />
        </div>
      </Section>

      <Section id="sec-hospitals" title={t("sec.hospitals")} desc={t("sec.hospitals.desc")}>
        <div className="grid">
          <Metric id="hospitals" value={formatNumber(catalog.hospitals)} unit="place" note={t("m.hospitals.note", { n: formatNumber(catalog.hospitalsPending) })} onHelp={onHelp} />
          <Metric id="doctors" value={formatNumber(catalog.doctors)} unit="person" note={t("m.doctors.note")} onHelp={onHelp} />
          <Metric id="treatments" value={formatNumber(catalog.treatments)} unit="item" note={t("m.treatments.note")} onHelp={onHelp} />
        </div>
      </Section>
    </>
  );
}
