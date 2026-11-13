"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import AdminShell from "./AdminShell";
import Delta from "./Delta";
import Icon, { type IconName } from "./Icon";
import StatCard, { StatCardSkeleton } from "./StatCard";
import { ErrorScreen, ForbiddenScreen, RedirectingScreen, type ErrorKind } from "./StateScreens";
import { fetchMe, fetchSummary, type Admin, type DashboardSummary } from "@/lib/api";
import { PHP_ADMIN_URL, REFRESH_INTERVAL_MS } from "@/lib/config";
import { formatClock, formatNumber, formatPercent, percentOf } from "@/lib/format";

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
  const [state, setState] = useState<State>(INITIAL);
  const [busy, setBusy] = useState(false);
  const [period, setPeriod] = useState<Period>("d7");
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

  const actions = (
    <>
      <span className={stale ? "updated updated--stale" : "updated"} title={summary?.generatedAt ? `집계 시각 ${summary.generatedAt} (KST)` : undefined}>
        {stale ? (
          <>
            <span aria-hidden="true">⚠ </span>업데이트 실패 ·{" "}
          </>
        ) : null}
        마지막 업데이트 {updatedAt ? formatClock(updatedAt) : "--:--:--"}
      </span>
      {stale ? <span className="sr-only" role="alert">대시보드 업데이트에 실패했습니다</span> : null}
      <button type="button" className="btn btn--warning" onClick={refresh} disabled={!canRefresh || busy} aria-label="대시보드 새로고침">
        <Icon name="refresh" className={busy ? "is-spinning" : undefined} />
        새로고침
      </button>
    </>
  );

  return (
    <AdminShell admin={admin} title="대시보드" description="전체 현황을 한눈에 확인합니다." icon="gauge" actions={actions}>
      {phase === "loading" ? <LoadingView /> : null}
      {phase === "redirecting" ? <RedirectingScreen /> : null}
      {phase === "forbidden" ? <ForbiddenScreen /> : null}
      {phase === "error" ? <ErrorScreen kind={state.errorKind} onRetry={refresh} retrying={busy} /> : null}
      {phase === "ready" && summary ? <SummaryView summary={summary} period={period} onPeriod={setPeriod} /> : null}
    </AdminShell>
  );
}

function SectionHead({ id, icon, title, children }: { id: string; icon: IconName; title: string; children?: React.ReactNode }) {
  return (
    <div className="section__head">
      <h2 id={id} className="section__title">
        <Icon name={icon} size={16} />
        {title}
      </h2>
      {children}
    </div>
  );
}

const PERIODS: { id: Period; days: number }[] = [
  { id: "d7", days: 7 },
  { id: "d30", days: 30 },
];

function SummaryView({ summary, period, onPeriod }: { summary: DashboardSummary; period: Period; onPeriod: (p: Period) => void }) {
  const { members, catalog } = summary;
  const days = period === "d7" ? 7 : 30;
  const b = summary.bookings[period];
  const consultations = summary.consultations[period];
  const cancelRate = percentOf(b.cancelled, b.count);

  return (
    <>
      <section className="section" aria-labelledby="sec-members">
        <SectionHead id="sec-members" icon="people" title="회원" />
        <div className="stat-grid">
          <StatCard index={0} label="총 회원 수" value={formatNumber(members.total)} unit="명" note="탈퇴 회원 제외" />
          <StatCard index={1} label="오늘 신규" value={formatNumber(members.today)} unit="명" note="오늘 가입한 회원" />
          <StatCard index={2} label="7일 신규" value={formatNumber(members.last7d)} unit="명" note="최근 7일 가입" />
          <StatCard index={3} label="30일 신규" value={formatNumber(members.last30d)} unit="명" note="최근 30일 가입" />
        </div>
      </section>

      <section className="section" aria-labelledby="sec-bookings">
        <SectionHead id="sec-bookings" icon="calendar" title="예약">
          <div className="segmented" role="group" aria-label="집계 기간">
            {PERIODS.map((p) => (
              <button key={p.id} type="button" className={p.id === period ? "segmented__btn is-active" : "segmented__btn"} aria-pressed={p.id === period} onClick={() => onPeriod(p.id)}>
                {p.days}일
              </button>
            ))}
          </div>
        </SectionHead>
        <div className="stat-grid">
          <StatCard index={0} label="예약 건수" value={formatNumber(b.count)} unit="건" note={<Delta current={b.count} previous={b.prev.count} days={days} />} />
          <StatCard index={1} label="예약 금액" value={formatNumber(b.amount)} unit="원" note={<Delta current={b.amount} previous={b.prev.amount} days={days} />} />
          <StatCard index={2} label="결제 금액(순)" value={formatNumber(b.paidAmount)} unit="원" note={<Delta current={b.paidAmount} previous={b.prev.paidAmount} days={days} />} />
          <StatCard index={3} label="환불 금액" value={formatNumber(b.refundedAmount)} unit="원" note={`최근 ${days}일 결제분 환불`} />
          <StatCard index={4} label="취소 건수" value={formatNumber(b.cancelled)} unit="건" note={`취소율 ${formatPercent(cancelRate)}`} />
          <StatCard index={5} label="승인 대기" value={formatNumber(b.pending)} unit="건" note="확정을 기다리는 예약" />
          <StatCard index={6} label="상담 신청" value={formatNumber(consultations)} unit="건" note={`최근 ${days}일 신청`} />
        </div>
      </section>

      <section className="section" aria-labelledby="sec-hospitals">
        <SectionHead id="sec-hospitals" icon="hospital" title="병원" />
        <div className="stat-grid">
          <StatCard index={0} label="등록 병원 수" value={formatNumber(catalog.hospitals)} unit="곳" note={`승인 대기 ${formatNumber(catalog.hospitalsPending)}곳`} />
          <StatCard index={1} label="의사 수" value={formatNumber(catalog.doctors)} unit="명" note="활동 중인 의사" />
          <StatCard index={2} label="시술 수" value={formatNumber(catalog.treatments)} unit="개" note="노출 중인 시술" />
        </div>
      </section>
    </>
  );
}

/** Same sections and card boxes as the real page, so nothing jumps when the numbers arrive. */
function LoadingView() {
  const rows: { id: string; icon: IconName; title: string; cards: number }[] = [
    { id: "members", icon: "people", title: "회원", cards: 4 },
    { id: "bookings", icon: "calendar", title: "예약", cards: 7 },
    { id: "hospitals", icon: "hospital", title: "병원", cards: 3 },
  ];
  return (
    <div role="status" aria-busy="true">
      <span className="sr-only">대시보드를 불러오는 중입니다</span>
      {rows.map((row) => (
        <div key={row.id} className="section" aria-hidden="true">
          <SectionHead id={`skeleton-${row.id}`} icon={row.icon} title={row.title} />
          <div className="stat-grid">
            {Array.from({ length: row.cards }, (_, i) => (
              <StatCardSkeleton key={i} index={i} />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
