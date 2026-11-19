"use client";

import { useState } from "react";
import Icon from "../Icon";
import { DAY_STATUSES, DAY_STATUS_KEYS, canApplyBulk, daysLabel, type DayStatus, type HoursDraft } from "@/lib/hospitalApply";
import { DAY_KEYS } from "@/lib/hospitalEdit";
import { WEEKDAYS, type Weekday } from "@/lib/hospitals";
import { useT } from "@/lib/locale";

const WORKDAYS: readonly Weekday[] = ["월", "화", "수", "목", "금"];

/** Tomorrow as YYYY-MM-DD in the browser's own time zone: an extra closed day must be after today. */
const tomorrowLocal = (): string => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

type Applied = { label: string; n: number; start: string; end: string };

/**
 * Opening hours: a bulk setter (its "Apply" button exists only once the times and at least one day make sense),
 * the seven days one by one, the lunch break and extra closed dates.
 */
export default function HoursEditor({ hours, onChange }: { hours: HoursDraft; onChange: (next: HoursDraft) => void }) {
  const t = useT();
  const [bulkStart, setBulkStart] = useState("10:00");
  const [bulkEnd, setBulkEnd] = useState("19:00");
  const [bulkDays, setBulkDays] = useState<ReadonlySet<Weekday>>(new Set());
  const [applied, setApplied] = useState<Applied | null>(null);
  const [closedInput, setClosedInput] = useState("");
  const [closedError, setClosedError] = useState(false);

  const canApply = canApplyBulk(bulkStart, bulkEnd, bulkDays);
  const label = daysLabel([...bulkDays]);

  // Any change of the bulk setter makes the earlier confirmation out of date.
  const changeBulk = (apply: () => void) => {
    apply();
    setApplied(null);
  };
  const toggleDay = (day: Weekday) =>
    changeBulk(() => {
      const next = new Set(bulkDays);
      if (next.has(day)) next.delete(day);
      else next.add(day);
      setBulkDays(next);
    });
  const toggleWorkdays = () =>
    changeBulk(() => {
      const next = new Set(bulkDays);
      const all = WORKDAYS.every((d) => next.has(d));
      for (const d of WORKDAYS) {
        if (all) next.delete(d);
        else next.add(d);
      }
      setBulkDays(next);
    });

  const apply = () => {
    if (!canApply) return;
    const days = { ...hours.days };
    for (const day of bulkDays) days[day] = { status: "normal", start: bulkStart, end: bulkEnd };
    onChange({ ...hours, days });
    setApplied({ label, n: bulkDays.size, start: bulkStart, end: bulkEnd });
  };

  const setDay = (day: Weekday, patch: Partial<HoursDraft["days"][Weekday]>) => onChange({ ...hours, days: { ...hours.days, [day]: { ...hours.days[day], ...patch } } });
  const setStatus = (day: Weekday, status: DayStatus) => {
    const current = hours.days[day];
    if (status === "normal") setDay(day, { status, start: current.start || "10:00", end: current.end || "19:00" });
    else setDay(day, { status, start: "", end: "" });
  };

  const addClosed = () => {
    if (closedInput === "" || hours.closedDates.includes(closedInput)) return;
    if (closedInput < tomorrowLocal()) {
      setClosedError(true);
      return;
    }
    setClosedError(false);
    onChange({ ...hours, closedDates: [...hours.closedDates, closedInput].sort() });
    setClosedInput("");
  };

  return (
    <div className="ha-hed">
      <div className="ha-hed__std" role="group" aria-labelledby="hed-bulk-t">
        <p className="ha-hed__lead" id="hed-bulk-t">
          <Icon name="clock" size={16} />
          {t("hap.h.bulk")}
        </p>
        <div className="ha-hed__times">
          <label className="ha-hed__tf">
            <span>{t("hap.h.start")}</span>
            <input className="input" type="time" value={bulkStart} onChange={(e) => changeBulk(() => setBulkStart(e.target.value))} />
          </label>
          <label className="ha-hed__tf">
            <span>{t("hap.h.end")}</span>
            <input className="input" type="time" value={bulkEnd} onChange={(e) => changeBulk(() => setBulkEnd(e.target.value))} />
          </label>
        </div>
        <div className="ha-days" role="group" aria-label={t("hap.h.daysLabel")}>
          {WEEKDAYS.map((day) => (
            <button key={day} type="button" className="ha-dchip" aria-pressed={bulkDays.has(day)} aria-label={t(DAY_KEYS[day])} onClick={() => toggleDay(day)}>
              {day}
            </button>
          ))}
          <button type="button" className="ha-dchip ha-dchip--wk" aria-pressed={WORKDAYS.every((d) => bulkDays.has(d))} onClick={toggleWorkdays}>
            {t("hap.h.weekdays")}
          </button>
        </div>
        <div className="ha-hed__slot" aria-live="polite">
          {canApply ? (
            <button type="button" className="btn btn--primary ha-hed__apply" onClick={apply}>
              <Icon name="check" size={16} />
              {t("hap.h.apply", { label, n: bulkDays.size })}
            </button>
          ) : (
            <p className="ha-hed__guide">
              <Icon name="info" size={16} />
              <span>{t("hap.h.guide")}</span>
            </p>
          )}
        </div>
        {applied ? (
          <p className="ha-ok" role="status">
            <Icon name="check" size={16} />
            <span>{t("hap.h.applied", { label: applied.label, n: applied.n, start: applied.start, end: applied.end })}</span>
          </p>
        ) : null}
      </div>

      <div className="ha-hed__list">
        <div className="ha-hed__head" aria-hidden="true">
          <span>{t("hap.h.col.day")}</span>
          <span>{t("hap.h.col.status")}</span>
          <span>{t("hap.h.col.start")}</span>
          <span>{t("hap.h.col.end")}</span>
        </div>
        {WEEKDAYS.map((day) => {
          const d = hours.days[day];
          const closed = d.status !== "normal";
          const name = t(DAY_KEYS[day]);
          return (
            <div className="ha-hed__row" key={day}>
              <span className="ha-hed__day">{name}</span>
              <select className="input" aria-label={`${name} ${t("hap.h.col.status")}`} value={d.status} onChange={(e) => setStatus(day, e.target.value as DayStatus)}>
                {DAY_STATUSES.map((s) => (
                  <option key={s} value={s}>{t(DAY_STATUS_KEYS[s])}</option>
                ))}
              </select>
              <div className="ha-hed__cells">
                <label className="ha-hed__cell">
                  <span className="ha-hed__tl">{t("hap.h.col.start")}</span>
                  <input className="input" type="time" aria-label={`${name} ${t("hap.h.col.start")}`} value={d.start} disabled={closed} onChange={(e) => setDay(day, { start: e.target.value })} />
                </label>
                <label className="ha-hed__cell">
                  <span className="ha-hed__tl">{t("hap.h.col.end")}</span>
                  <input className="input" type="time" aria-label={`${name} ${t("hap.h.col.end")}`} value={d.end} disabled={closed} onChange={(e) => setDay(day, { end: e.target.value })} />
                </label>
              </div>
            </div>
          );
        })}
      </div>

      <div className="ha-hed__block">
        <div className="ha-hed__bh">
          <p className="ha-hed__title">{t("hap.h.lunch")}</p>
          <label className="ha-check">
            <input type="checkbox" checked={hours.noLunch} onChange={(e) => onChange({ ...hours, noLunch: e.target.checked })} />
            <span>{t("hap.h.noLunch")}</span>
          </label>
        </div>
        <div className="ha-line">
          <label className="ha-hed__tf">
            <span>{t("hap.h.lunchFrom")}</span>
            <input className="input" type="time" value={hours.lunchStart} disabled={hours.noLunch} onChange={(e) => onChange({ ...hours, lunchStart: e.target.value })} />
          </label>
          <span className="ha-line__sep" aria-hidden="true">~</span>
          <label className="ha-hed__tf">
            <span>{t("hap.h.lunchTo")}</span>
            <input className="input" type="time" value={hours.lunchEnd} disabled={hours.noLunch} onChange={(e) => onChange({ ...hours, lunchEnd: e.target.value })} />
          </label>
        </div>
      </div>

      <div className="ha-hed__block">
        <div>
          <p className="ha-hed__title">{t("hap.h.closed")}</p>
          <p className="field__hint">{t("hap.h.closedHint")}</p>
        </div>
        <div className="ha-line">
          <input className="input ha-hed__date" type="date" aria-label={t("hap.h.closedDate")} min={tomorrowLocal()} value={closedInput} aria-invalid={closedError ? true : undefined} aria-describedby={closedError ? "hed-closed-e" : undefined} onChange={(e) => { setClosedInput(e.target.value); setClosedError(false); }} />
          <button type="button" className="btn btn--ghost" onClick={addClosed} disabled={closedInput === ""}>
            <Icon name="plus" size={16} />
            {t("hap.h.closedAdd")}
          </button>
        </div>
        {closedError ? (
          <p className="ha-err" id="hed-closed-e" role="alert">
            <Icon name="alert" size={16} />
            <span>{t("hap.h.closedPast")}</span>
          </p>
        ) : null}
        {hours.closedDates.length > 0 ? (
          <ul className="ha-chips">
            {hours.closedDates.map((date) => (
              <li key={date} className="ha-chip">
                <span>{date}</span>
                <button type="button" aria-label={t("hap.h.closedRemove", { date })} onClick={() => onChange({ ...hours, closedDates: hours.closedDates.filter((x) => x !== date) })}>
                  <Icon name="close" size={14} />
                </button>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </div>
  );
}
