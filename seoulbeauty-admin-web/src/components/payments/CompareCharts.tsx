"use client";

import { useEffect, useRef, useState, type KeyboardEvent, type PointerEvent } from "react";
import { Notice } from "../hospitals/parts";
import { fetchPaymentsCompare, type ApiResult, type CompareMetric, type PaymentsCompare } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { changeDirection, formatPercent } from "@/lib/format";
import { useT } from "@/lib/locale";
import { COMPARE_METRICS, addDaysIso, axisIsMillions, millions, monthDay, niceMax, won } from "@/lib/payments";

const H = 232;
const M = { l: 48, r: 12, t: 14, b: 30 };

/** Width of the element in pixels, kept up to date; a first guess until it is measured. */
function useWidth(): [React.RefObject<HTMLDivElement | null>, number] {
  const ref = useRef<HTMLDivElement | null>(null);
  const [w, setW] = useState(560);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const ro = new ResizeObserver(() => setW(Math.max(280, Math.round(el.clientWidth))));
    ro.observe(el);
    setW(Math.max(280, Math.round(el.clientWidth)));
    return () => ro.disconnect();
  }, []);
  return [ref, w];
}

type Row = { label: string; now: number | null; before: number | null };

function Change({ value }: { value: number | null }) {
  if (value === null) return <span className="muted">—</span>;
  const d = changeDirection(value);
  const dir = d === "none" ? "flat" : d;
  return <span className={`pay-chip pay-chip--${dir}`}>{dir === "up" ? "▲ " : dir === "down" ? "▼ " : ""}{formatPercent(value)}</span>;
}

/** Shared frame of both charts: the readout line, the drawing, the keyboard and pointer handling and the table view. */
function Frame({ title, range, note, rows, today, labelOf, draw, totals, nowName, beforeName, points }: {
  points?: boolean; title: string; range: string; note: string; rows: Row[]; today: number; labelOf: (i: number) => string;
  draw: (w: number, top: number, active: number) => React.ReactNode; totals: React.ReactNode; nowName: string; beforeName: string;
}) {
  const t = useT();
  const [ref, w] = useWidth();
  const [active, setActive] = useState<number | null>(null);
  const idx = active ?? Math.min(Math.max(today, 0), rows.length - 1);
  const max = Math.max(1, ...rows.map((r) => Math.max(r.now ?? 0, r.before ?? 0)));
  const top = niceMax(max);
  const row = rows[idx];
  const diff = row && row.now !== null && row.before !== null ? row.now - row.before : null;

  const onKey = (e: KeyboardEvent<HTMLDivElement>) => {
    if (e.key === "ArrowRight") setActive(Math.min(rows.length - 1, idx + 1));
    else if (e.key === "ArrowLeft") setActive(Math.max(0, idx - 1));
    else if (e.key === "Escape") setActive(null);
    else return;
    e.preventDefault();
  };
  const onMove = (e: PointerEvent<HTMLDivElement>) => {
    const box = e.currentTarget.getBoundingClientRect();
    const x = e.clientX - box.left - M.l;
    const plot = box.width - M.l - M.r;
    if (plot <= 0 || rows.length === 0) return;
    const at = points && rows.length > 1 ? Math.round((x / plot) * (rows.length - 1)) : Math.floor((x / plot) * rows.length);
    setActive(Math.min(rows.length - 1, Math.max(0, at)));
  };

  return (
    <section className="pay-cmp" aria-label={title}>
      <header className="pay-cmp__head">
        <h2>{title}</h2>
        <p className="muted">{range}</p>
      </header>
      {totals}
      <p className="pay-readout">
        <strong>{row ? labelOf(idx) : "—"}</strong>
        <span><i className="pay-key pay-key--now" />{nowName} {row?.now == null ? "—" : won(row.now)}</span>
        <span><i className="pay-key pay-key--prev" />{beforeName} {row?.before == null ? "—" : won(row.before)}</span>
        {diff !== null ? <span className="muted">{diff > 0 ? "+" : diff < 0 ? "−" : ""}{won(Math.abs(diff))}</span> : null}
      </p>
      <div
        ref={ref}
        className="pay-chart"
        tabIndex={0}
        role="group"
        aria-label={title}
        onKeyDown={onKey}
        onPointerMove={onMove}
        onPointerLeave={(e) => { if (e.pointerType === "mouse") setActive(null); }}
        onBlur={() => setActive(null)}
      >
        <svg width={w} height={H} viewBox={`0 0 ${w} ${H}`} aria-hidden="true" focusable="false">
          {draw(w, top, idx)}
        </svg>
      </div>
      <p className="muted pay-cmp__note">{note}</p>
      <details className="pay-table">
        <summary>{t("pay.cmp.table")}</summary>
        <table className="pay-ptbl">
          <thead><tr><th scope="col">{t("pay.cmp.col.date")}</th><th scope="col" className="pay-n">{nowName}</th><th scope="col" className="pay-n">{beforeName}</th></tr></thead>
          <tbody>
            {rows.map((r, i) => (
              <tr key={i}><th scope="row">{labelOf(i)}</th><td className="pay-n">{r.now === null ? "—" : won(r.now)}</td><td className="pay-n">{r.before === null ? "—" : won(r.before)}</td></tr>
            ))}
          </tbody>
        </table>
      </details>
    </section>
  );
}

function Axis({ w, top }: { w: number; top: number }) {
  const t = useT();
  const plotW = w - M.l - M.r;
  const m = axisIsMillions(top);
  return (
    <g>
      {[0, 0.5, 1].map((f) => {
        const y = M.t + (H - M.t - M.b) * (1 - f);
        const v = top * f;
        return (
          <g key={f}>
            <line x1={M.l} x2={M.l + plotW} y1={y} y2={y} className="pay-grid" />
            <text x={M.l - 6} y={y + 4} textAnchor="end" className="pay-tick">{m ? t("pay.cmp.axisMillion", { n: String(millions(v)) }) : won(v)}</text>
          </g>
        );
      })}
    </g>
  );
}

const yOf = (v: number, top: number) => M.t + (H - M.t - M.b) * (1 - v / top);

export default function CompareCharts({ hospitalId }: { hospitalId: number }) {
  const t = useT();
  const [metric, setMetric] = useState<CompareMetric>("received");
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<{ key: string; res: ApiResult<PaymentsCompare> } | null>(null);
  const key = `${hospitalId}|${metric}|${tick}`;

  useEffect(() => {
    const c = new AbortController();
    void fetchPaymentsCompare(hospitalId, metric, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) {
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      setGot({ key, res });
    });
    return () => c.abort();
    // `key` stands for the three inputs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const data = got?.res.ok ? got.res.data : null;
  const loading = got?.key !== key;
  const failed = got?.key === key && !got.res.ok;
  const unavailable = failed && got?.res.ok === false && got.res.status === 503;

  return (
    <div className="pay-cmps">
      <div className="pay-metrics" role="group" aria-label={t("pay.metric.label")}>
        {COMPARE_METRICS.map((m) => (
          <button key={m} type="button" className="pay-metric" aria-pressed={metric === m} onClick={() => setMetric(m)}>{t(`pay.metric.${m}` as never)}</button>
        ))}
      </div>
      {failed ? (
        <Notice tone="warn" icon="alert" action={unavailable ? undefined : <button type="button" className="btn btn--ghost btn--sm" onClick={() => setTick((n) => n + 1)}>{t("pay.err.retry")}</button>}>
          {t(unavailable ? "pay.err.unavailable" : "pay.cmp.fail")}
        </Notice>
      ) : null}
      {data ? (
        <div className={`pay-cmps__grid${loading ? " bk-busy" : ""}`} aria-busy={loading}>
          <WeekPanel data={data} />
          <MonthPanel data={data} />
        </div>
      ) : !failed ? <div className="pay-skel" role="status" aria-busy="true">{t("pay.loading")}</div> : null}
    </div>
  );
}

function Totals({ rows }: { rows: { label: string; value: number; change: number | null; main?: boolean }[] }) {
  const t = useT();
  return (
    <table className="pay-totals">
      <thead><tr><th scope="col" className="sr-only">{t("pay.cmp.col.date")}</th><th scope="col" className="pay-n">{t("pay.col.amount")}</th><th scope="col" className="pay-n">{t("pay.cmp.change")}</th></tr></thead>
      <tbody>
        {rows.map((r) => (
          <tr key={r.label} className={r.main ? "pay-totals__main" : undefined}>
            <th scope="row">{r.label}</th>
            <td className="pay-n">{won(r.value)}</td>
            <td className="pay-n"><Change value={r.change} /></td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function WeekPanel({ data }: { data: PaymentsCompare }) {
  const t = useT();
  const { week, today } = data;
  const start = week.thisWeek.start;
  const rows: Row[] = Array.from({ length: 7 }, (_, i) => ({
    label: addDaysIso(start, i),
    now: week.thisWeek.days[i] ? week.thisWeek.days[i].value : null,
    before: week.lastWeek.days[i] ? week.lastWeek.days[i].value : null,
  }));
  const todayIdx = Math.min(6, Math.max(0, week.thisWeek.days.length - 1));
  const labelOf = (i: number) => `${monthDay(rows[i].label)} (${t(`pay.day.${i}` as never)})${rows[i].label === today ? ` · ${t("pay.cmp.today")}` : ""}`;
  return (
    <Frame
      title={t("pay.cmp.week")}
      range={t("pay.cmp.weekRange", { thisStart: monthDay(start), today: monthDay(today), lastStart: monthDay(week.lastWeek.start), lastEnd: monthDay(week.lastWeek.end) })}
      note={t("pay.cmp.weekNote")}
      rows={rows}
      today={todayIdx}
      labelOf={labelOf}
      nowName={t("pay.cmp.thisWeek")}
      beforeName={t("pay.cmp.lastWeek")}
      totals={<Totals rows={[
        { label: t("pay.cmp.sameDays"), value: week.totals.thisToDate, change: week.changeSameDays, main: true },
        { label: t("pay.cmp.fullWeek"), value: week.totals.lastFull, change: week.changeFull },
      ]} />}
      draw={(w, top, active) => {
        const plotW = w - M.l - M.r;
        const g = plotW / 7;
        const bw = Math.max(6, Math.min(22, g * 0.3));
        const base = yOf(0, top);
        return (
          <g>
            <Axis w={w} top={top} />
            {rows.map((r, i) => {
              const cx = M.l + g * i + g / 2;
              const isToday = r.label === today;
              return (
                <g key={i}>
                  {i === active ? <rect x={M.l + g * i} y={M.t} width={g} height={H - M.t - M.b} className="pay-guide" /> : null}
                  {r.before !== null ? <rect x={cx - bw - 1} y={yOf(r.before, top)} width={bw} height={Math.max(0, base - yOf(r.before, top))} rx={3} className="pay-bar-prev" /> : null}
                  {r.now !== null ? <rect x={cx + 1} y={yOf(r.now, top)} width={bw} height={Math.max(0, base - yOf(r.now, top))} rx={3} className="pay-bar-now" /> : <rect x={cx + 1} y={base - 22} width={bw} height={22} rx={3} className="pay-bar-future" />}
                  <text x={cx} y={H - 10} textAnchor="middle" className={isToday ? "pay-tick pay-tick--today" : "pay-tick"}>{t(`pay.day.${i}` as never)}</text>
                </g>
              );
            })}
          </g>
        );
      }}
    />
  );
}

function MonthPanel({ data }: { data: PaymentsCompare }) {
  const t = useT();
  const { month, today } = data;
  const nowPts = month.thisMonth.cumulative;
  const prevPts = month.lastMonth.cumulative;
  const len = Math.max(nowPts.length, prevPts.length, 1);
  const rows: Row[] = Array.from({ length: len }, (_, i) => ({ label: String(i + 1), now: nowPts[i] ? nowPts[i].value : null, before: prevPts[i] ? prevPts[i].value : null }));
  const todayIdx = Math.max(0, nowPts.length - 1);
  const mm = (start: string, d: number) => `${start.slice(5, 7)}-${String(d).padStart(2, "0")}`;
  const labelOf = (i: number) => `${mm(month.thisMonth.start, i + 1)}${i === todayIdx ? ` · ${t("pay.cmp.today")}` : ""}`;
  return (
    <Frame
      title={t("pay.cmp.month")}
      range={t("pay.cmp.monthRange", { thisStart: monthDay(month.thisMonth.start), today: monthDay(today), lastStart: monthDay(month.lastMonth.start), lastEnd: monthDay(month.lastMonth.end) })}
      note={t("pay.cmp.monthNote")}
      rows={rows}
      points
      today={todayIdx}
      labelOf={labelOf}
      nowName={t("pay.cmp.thisMonth")}
      beforeName={t("pay.cmp.lastMonth")}
      totals={<Totals rows={[
        { label: t("pay.cmp.sameDate"), value: month.totals.thisToDate, change: month.changeSameDay, main: true },
        { label: t("pay.cmp.fullMonth"), value: month.totals.lastFull, change: month.changeFull },
      ]} />}
      draw={(w, top, active) => {
        const plotW = w - M.l - M.r;
        const x = (i: number) => M.l + (len === 1 ? 0 : (plotW * i) / (len - 1));
        const line = (pts: { value: number }[]) => pts.map((p, i) => `${i === 0 ? "M" : "L"}${x(i).toFixed(1)} ${yOf(p.value, top).toFixed(1)}`).join(" ");
        const ticks = [1, 10, 20, len].filter((d, i, a) => d <= len && a.indexOf(d) === i);
        const a = Math.min(active, len - 1);
        return (
          <g>
            <Axis w={w} top={top} />
            {a >= 0 ? <line x1={x(a)} x2={x(a)} y1={M.t} y2={H - M.b} className="pay-guide-line" /> : null}
            {prevPts.length ? <path d={line(prevPts)} className="pay-line-prev" /> : null}
            {nowPts.length ? <path d={line(nowPts)} className="pay-line-now" /> : null}
            {prevPts[a] ? <circle cx={x(a)} cy={yOf(prevPts[a].value, top)} r={4} className="pay-dot pay-dot--prev" /> : null}
            {nowPts[a] ? <circle cx={x(a)} cy={yOf(nowPts[a].value, top)} r={4} className="pay-dot pay-dot--now" /> : null}
            {ticks.map((d) => <text key={d} x={x(d - 1)} y={H - 10} textAnchor={d === 1 ? "start" : d === len ? "end" : "middle"} className="pay-tick">{d}</text>)}
          </g>
        );
      }}
    />
  );
}
