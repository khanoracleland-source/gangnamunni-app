"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import AdminShell from "./AdminShell";
import Delta from "./Delta";
import HelpDialog from "./HelpDialog";
import Icon, { type IconName } from "./Icon";
import Metric, { MetricSkeleton, type MetricId } from "./Metric";
import { ErrorScreen, ForbiddenScreen, RedirectingScreen, type ErrorKind } from "./StateScreens";
import { fetchMe, fetchSummary, type Admin, type DashboardSummary } from "@/lib/api";
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
  summary: DashboardSummary | null;
  /** When the numbers on screen were received (viewer's clock). */
  updatedAt: Date | null;
  /** The last background refresh failed: the numbers on screen are older than they should be. */
  stale: boolean;
};

const INITIAL: State = { phase: "loading", errorKind: "network", admin: null, summary: null, updatedAt: null, stale: false };

const errorKindOf = (status: number): ErrorKind => (status === 404 ? "disabled" : status === 503 ? "session" : "network");

export default function Dashboard() {
  const t = useT();
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
      if (statuses.includes(403)) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok && summary.ok) {
        setState({ phase: "ready", errorKind: "network", admin: me.data, summary: summary.data, updatedAt: new Date(), stale: false });
        return;
      }
      const kind = errorKindOf(failed ?? 0);
      setState((s) =>
        // A hiccup during a background refresh keeps the numbers already on screen (marked as stale).
        // A switched-off panel (404) always replaces them.
        s.summary && s.phase === "ready" && kind !== "disabled"
          ? { ...s, stale: true }
          : { ...INITIAL, phase: "error", errorKind: kind, admin: me.ok ? me.data : s.admin },
      );
    } finally {
      inFlight.current = false;
      setBusy(false);
    }
  }, []);

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

  const { phase, admin, summary, updatedAt, stale } = state;
  const canRefresh = phase === "ready" || phase === "error";
  const days = period === "d7" ? 7 : 30;

  return (
    <AdminShell
      admin={admin}
      title={t("page.title")}
      description={t("page.desc")}
      icon="gauge"
      intro={<PageIntro />}
    >
      <div className="toolbar">
        <div className="toolbar__period">
          <span id="period-label" className="toolbar__label">{t("period.label")}</span>
          <div className="segmented" role="group" aria-labelledby="period-label">
            {(["d7", "d30"] as const).map((p) => (
              <button key={p} type="button" className={p === period ? "segmented__btn is-active" : "segmented__btn"} aria-pressed={p === period} onClick={() => setPeriod(p)}>
                {t(`period.${p}`)}
              </button>
            ))}
          </div>
          <span className="toolbar__hint">{t("period.hint")}</span>
        </div>
        <div className="toolbar__refresh">
          <span className={stale ? "updated updated--stale" : "updated"} title={summary?.generatedAt ? t("updated.title", { at: summary.generatedAt }) : undefined}>
            {stale && updatedAt
              ? t("updated.failed", { time: formatClock(updatedAt) })
              : updatedAt
                ? t("updated", { time: formatClock(updatedAt) })
                : t("updated.none")}
          </span>
          {stale ? <span className="sr-only" role="alert">{t("updated.failedAlert")}</span> : null}
          <button type="button" className="btn btn--secondary" onClick={refresh} disabled={!canRefresh || busy} aria-label={t("refresh.aria")}>
            <Icon name="refresh" className={busy ? "is-spinning" : undefined} />
            {t("refresh")}
          </button>
        </div>
      </div>

      {phase === "loading" ? <LoadingView /> : null}
      {phase === "redirecting" ? <RedirectingScreen /> : null}
      {phase === "forbidden" ? <ForbiddenScreen /> : null}
      {phase === "error" ? <ErrorScreen kind={state.errorKind} onRetry={refresh} retrying={busy} /> : null}
      {phase === "ready" && summary ? <SummaryView summary={summary} period={period} onHelp={setHelp} /> : null}

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
        <Icon name="info" size={18} />
        <span>{t("page.help.toggle")}</span>
        <Icon name="chevronUp" size={12} className="intro__chevron" />
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

function Section({ id, icon, title, desc, children }: { id: string; icon: IconName; title: string; desc: string; children: React.ReactNode }) {
  return (
    <section className="section" aria-labelledby={id}>
      <div className="section__head">
        <h2 id={id} className="section__title">
          <Icon name={icon} size={20} />
          {title}
        </h2>
        <p className="section__desc">{desc}</p>
      </div>
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
      <Section id="sec-members" icon="people" title={t("sec.members")} desc={t("sec.members.desc")}>
        <div className="grid grid--members">
          <Metric
            hero
            id="all"
            value={formatNumber(all)}
            unit="person"
            note={members.admins > 0 ? t("m.all.note", { n: formatNumber(members.admins) }) : t("m.all.noteNone")}
            onHelp={onHelp}
          />
          <Metric id="general" value={formatNumber(general)} unit="person" note={t("m.general.note")} onHelp={onHelp} />
          <Metric id="withdrawn" value={formatNumber(members.withdrawn)} unit="person" note={t("m.withdrawn.note")} onHelp={onHelp} />
          <Metric id="today" value={formatNumber(members.today)} unit="person" note={t("m.today.note")} onHelp={onHelp} />
          <Metric id="signup" value={formatNumber(signups)} unit="person" note={t("m.signup.note", { days })} onHelp={onHelp} />
        </div>
      </Section>

      <Section id="sec-bookings" icon="calendar" title={t("sec.bookings")} desc={t("sec.bookings.desc")}>
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
          <Metric id="bookPending" value={formatNumber(b.pending)} unit="case" note={t("m.bookPending.note")} onHelp={onHelp} />
          <Metric id="consult" value={formatNumber(consultations)} unit="case" note={t("m.consult.note", { days })} onHelp={onHelp} />
        </div>
      </Section>

      <Section id="sec-hospitals" icon="hospital" title={t("sec.hospitals")} desc={t("sec.hospitals.desc")}>
        <div className="grid">
          <Metric id="hospitals" value={formatNumber(catalog.hospitals)} unit="place" note={t("m.hospitals.note", { n: formatNumber(catalog.hospitalsPending) })} onHelp={onHelp} />
          <Metric id="doctors" value={formatNumber(catalog.doctors)} unit="person" note={t("m.doctors.note")} onHelp={onHelp} />
          <Metric id="treatments" value={formatNumber(catalog.treatments)} unit="item" note={t("m.treatments.note")} onHelp={onHelp} />
        </div>
      </Section>
    </>
  );
}

/** Same sections and card boxes as the real page, so nothing jumps when the numbers arrive. */
function LoadingView() {
  const t = useT();
  const rows: { id: string; icon: IconName; title: MessageKey; desc: MessageKey; cards: number; hero?: boolean }[] = [
    { id: "members", icon: "people", title: "sec.members", desc: "sec.members.desc", cards: 5, hero: true },
    { id: "bookings", icon: "calendar", title: "sec.bookings", desc: "sec.bookings.desc", cards: 7 },
    { id: "hospitals", icon: "hospital", title: "sec.hospitals", desc: "sec.hospitals.desc", cards: 3 },
  ];
  return (
    <div role="status" aria-busy="true">
      <span className="sr-only">{t("loading")}</span>
      {rows.map((row) => (
        <div key={row.id} className="section" aria-hidden="true">
          <div className="section__head">
            <h2 className="section__title">
              <Icon name={row.icon} size={20} />
              {t(row.title)}
            </h2>
            <p className="section__desc">{t(row.desc)}</p>
          </div>
          <div className={row.hero ? "grid grid--members" : "grid"}>
            {Array.from({ length: row.cards }, (_, i) => (
              <MetricSkeleton key={i} hero={row.hero && i === 0} />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
