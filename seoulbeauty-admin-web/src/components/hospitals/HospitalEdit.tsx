"use client";

import { useEffect, useMemo, useRef, useState, type FormEvent, type KeyboardEvent, type ReactNode } from "react";
import Icon from "../Icon";
import ConfirmDialog from "./ConfirmDialog";
import { GradePill, Notice, Pill, Section } from "./parts";
import { patchHospital, type HospitalDetail } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import {
  DAY_KEYS,
  DAY_STATUSES,
  DAY_STATUS_KEYS,
  FIELD_ORDER,
  buildPatch,
  draftFromHospital,
  failureKey,
  validateDraft,
  type DayDraft,
  type DayStatus,
  type Draft,
  type HoursDraft,
  type ListKey,
  type TextKey,
} from "@/lib/hospitalEdit";
import { WEEKDAYS, type Weekday } from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

const ERROR_KEYS = new Map<string, MessageKey>([
  ["required", "h.e.err.required"],
  ["too_long", "h.e.err.too_long"],
  ["bad_format", "h.e.err.bad_format"],
  ["bad_url", "h.e.err.bad_url"],
  ["duplicate_phone", "h.e.err.duplicate_phone"],
  ["not_allowed", "h.e.err.not_allowed"],
  ["range", "h.e.err.range"],
  ["pair", "h.e.err.pair"],
]);

const focusField = (key: string): void => {
  document.getElementById(`f-${key}`)?.focus();
};

type Props = {
  hospital: HospitalDetail["hospital"];
  /** The change was stored. */
  onSaved: () => void;
  /** The admin left the form without saving. */
  onCancel: () => void;
  /** Throw the typed values away and read the hospital again. */
  onReload: () => void;
};

/**
 * The edit form of one hospital. It starts from the loaded values, sends only the fields that differ,
 * and reports a clash with a change made elsewhere instead of overwriting it.
 */
export default function HospitalEdit({ hospital, onSaved, onCancel, onReload }: Props) {
  const t = useT();
  const original = useMemo(() => draftFromHospital(hospital), [hospital]);
  const [draft, setDraft] = useState<Draft>(original);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [failure, setFailure] = useState<MessageKey | null>(null);
  const [conflict, setConflict] = useState(false);
  const [conflictOpen, setConflictOpen] = useState(false);
  // Set once the form is being left on purpose (saved or cancelled), so the unsaved-changes warning stays quiet.
  const leaving = useRef(false);
  const noticeRef = useRef<HTMLDivElement>(null);

  const patch = useMemo(() => buildPatch(original, draft), [original, draft]);
  const patchKeys = Object.keys(patch);
  const changed = patchKeys.length - (patchKeys.includes("latitude") && patchKeys.includes("longitude") ? 1 : 0);
  const dirty = changed > 0;
  const errorCount = Object.keys(errors).length;

  // Leaving with typed-in changes: the browser asks on reload / close, and a link inside the panel asks here.
  useEffect(() => {
    if (!dirty) return;
    const onBeforeUnload = (e: BeforeUnloadEvent) => {
      if (leaving.current) return;
      e.preventDefault();
    };
    const onClick = (e: MouseEvent) => {
      if (leaving.current || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      const link = e.target instanceof Element ? e.target.closest<HTMLAnchorElement>("a[href]") : null;
      if (!link || link.target === "_blank" || link.hasAttribute("download")) return;
      const url = new URL(link.href, window.location.href);
      if (url.origin === window.location.origin && url.pathname === window.location.pathname && url.search === window.location.search) return;
      if (!window.confirm(t("h.e.leaveConfirm"))) {
        e.preventDefault();
        e.stopPropagation();
      }
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    document.addEventListener("click", onClick, true);
    return () => {
      window.removeEventListener("beforeunload", onBeforeUnload);
      document.removeEventListener("click", onClick, true);
    };
  }, [dirty, t]);

  // A message that is not about one field is brought into view: the buttons are at the other end of the page.
  useEffect(() => {
    if (failure || conflict) noticeRef.current?.scrollIntoView({ block: "center" });
  }, [failure, conflict]);

  const clearError = (key: string) => {
    setErrors((prev) => {
      if (!(key in prev)) return prev;
      const next = { ...prev };
      delete next[key];
      return next;
    });
  };
  const setText = (key: TextKey, value: string) => {
    setDraft((d) => ({ ...d, [key]: value }));
    clearError(key);
  };
  const setList = (key: ListKey, value: string[]) => {
    setDraft((d) => ({ ...d, [key]: value }));
    clearError(key);
  };
  const setHours = (hours: HoursDraft) => {
    setDraft((d) => ({ ...d, hours }));
    clearError("businessHours");
  };

  const showErrors = (found: Record<string, string>) => {
    setErrors(found);
    const first = FIELD_ORDER.find((key) => key in found);
    if (first) window.setTimeout(() => focusField(first), 0);
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (saving || conflict || !dirty) return;
    setFailure(null);
    const local = validateDraft(original, draft);
    if (Object.keys(local).length > 0) {
      showErrors(local);
      return;
    }
    setErrors({});
    setSaving(true);
    const res = await patchHospital(hospital.id, hospital.updatedAt || null, patch);
    setSaving(false);
    if (res.ok) {
      leaving.current = true;
      onSaved();
      return;
    }
    if (res.status === 401) {
      leaving.current = true;
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.status === 409) {
      setConflict(true);
      setConflictOpen(true);
      return;
    }
    if (res.status === 400) {
      const placed = Object.fromEntries(Object.entries(res.fields).filter(([key]) => FIELD_ORDER.includes(key)));
      if (Object.keys(placed).length > 0) showErrors(placed);
      else setFailure("h.e.fail.invalid");
      return;
    }
    setFailure(failureKey(res.status));
  };

  const cancel = () => {
    if (dirty && !window.confirm(t("h.e.leaveConfirm"))) return;
    leaving.current = true;
    onCancel();
  };

  const err = (key: string): string | undefined => {
    const code = errors[key];
    if (code === undefined) return undefined;
    const textKey = ERROR_KEYS.get(code);
    return t(textKey ?? "h.e.err.invalid");
  };

  const text = (key: TextKey, label: string, extra?: { wide?: boolean; multiline?: boolean; type?: string; inputMode?: "text" | "tel" | "email" | "url"; required?: boolean; autoComplete?: string }): ReactNode => (
    <TextField id={`f-${key}`} label={label} value={draft[key]} onChange={(v) => setText(key, v)} error={err(key)} {...extra} />
  );

  return (
    <form className="editform" onSubmit={submit} noValidate aria-busy={saving}>
      <div ref={noticeRef}>
        {conflict ? (
          <Notice tone="warn" icon="alert" action={<button type="button" className="txtbtn" onClick={onReload}>{t("h.e.conflict.reload")}</button>}>
            {t("h.e.conflict.notice")}
          </Notice>
        ) : null}
        {failure ? <Notice tone="warn" icon="alert">{t(failure)}</Notice> : null}
      </div>
      {errorCount > 0 ? <Notice tone="warn" icon="alert">{t("h.e.errSummary", { n: errorCount })}</Notice> : null}

      <Section id="sec-e-basic" title={t("h.basic.title")} desc={t("h.e.basic.desc")}>
        <div className="panel form">
          {text("name", t("h.f.name"), { required: true, autoComplete: "organization" })}
          {text("ownerName", t("h.f.owner"))}
          {text("businessNumber", t("h.f.businessNumber"), { inputMode: "text" })}
          {text("introduction", t("h.f.intro"), { wide: true, multiline: true })}
        </div>
      </Section>

      <Section id="sec-e-contact" title={t("h.e.contact.title")} desc={t("h.e.contact.desc")}>
        <div className="panel form">
          {text("address", t("h.f.address"), { wide: true, autoComplete: "street-address" })}
          {text("phone", t("h.f.phone"), { type: "tel", inputMode: "tel", autoComplete: "tel" })}
          {text("email", t("h.f.email"), { type: "email", inputMode: "email", autoComplete: "email" })}
          {text("contactName", t("h.e.f.contactName"))}
          {text("contactPhone", t("h.e.f.contactPhone"), { type: "tel", inputMode: "tel" })}
          {text("website", t("h.f.website"), { type: "url", inputMode: "url" })}
          {text("snsUrl", t("h.f.sns"), { type: "url", inputMode: "url" })}
        </div>
      </Section>

      <Section id="sec-e-tags" title={t("h.tags.title")} desc={t("h.e.tags.desc")}>
        <div className="panel form">
          <ChipEditor id="f-categories" label={t("h.tags.categories")} items={draft.categories} onChange={(v) => setList("categories", v)} error={err("categories")} />
          <ChipEditor id="f-badges" label={t("h.tags.badges")} items={draft.badges} onChange={(v) => setList("badges", v)} error={err("badges")} />
          <ChipEditor id="f-treatmentTags" label={t("h.tags.treatments")} items={draft.treatmentTags} onChange={(v) => setList("treatmentTags", v)} error={err("treatmentTags")} />
          <ChipEditor id="f-subwayNames" label={t("h.tags.subways")} items={draft.subwayNames} onChange={(v) => setList("subwayNames", v)} error={err("subwayNames")} />
        </div>
      </Section>

      <Section id="sec-e-hours" title={t("h.hours.title")} desc={t("h.e.hours.desc")}>
        <HoursEditor hours={draft.hours} onChange={setHours} error={err("businessHours")} />
      </Section>

      <Section id="sec-e-location" title={t("h.e.loc.title")} desc={t("h.e.loc.desc")}>
        <div className="panel form">
          <TextField id="f-latitude" label={t("h.e.f.lat")} hint={t("h.e.f.latHint")} inputMode="decimal" value={draft.latitude} error={err("latitude")} onChange={(v) => { setDraft((d) => ({ ...d, latitude: v })); clearError("latitude"); }} />
          <TextField id="f-longitude" label={t("h.e.f.lng")} hint={t("h.e.f.lngHint")} inputMode="decimal" value={draft.longitude} error={err("longitude")} onChange={(v) => { setDraft((d) => ({ ...d, longitude: v })); clearError("longitude"); }} />
        </div>
      </Section>

      <Section id="sec-e-display" title={t("h.e.display.title")} desc={t("h.e.display.desc")}>
        <div className="panel form">
          <div className="form__wide">
            <label className="check" htmlFor="f-showRating">
              <input
                id="f-showRating"
                type="checkbox"
                checked={draft.showRating}
                aria-describedby="f-showRating-hint"
                onChange={(e) => {
                  setDraft((d) => ({ ...d, showRating: e.target.checked }));
                  clearError("showRating");
                }}
              />
              <span>{t("h.e.f.showRating")}</span>
            </label>
            <p className="field__hint" id="f-showRating-hint">{t("h.e.f.showRatingHint")}</p>
            {err("showRating") ? <p className="field__error">{err("showRating")}</p> : null}
          </div>
          <div className="form__wide">
            <div className="locked">
              <span className="field__label">{t("h.status.grade")}</span>
              <GradePill grade={hospital.adGrade} long />
              <Pill tone="off">{t("h.e.f.gradeLocked")}</Pill>
            </div>
            <p className="field__hint">{t("h.e.f.gradeNote")}</p>
          </div>
        </div>
      </Section>

      <div className="editbar">
        <p className="editbar__status" role="status" aria-live="polite">
          {saving ? t("h.e.saving") : dirty ? t("h.e.changed", { n: changed }) : t("h.e.noChanges")}
        </p>
        <button type="button" className="btn btn--ghost" onClick={cancel} disabled={saving}>{t("h.e.cancel")}</button>
        <button type="submit" className="btn btn--primary" disabled={saving || conflict || !dirty}>{saving ? t("h.e.saving") : t("h.e.save")}</button>
      </div>

      <ConfirmDialog
        spec={conflictOpen ? { title: t("h.e.conflict.title"), body: t("h.e.conflict.body"), confirmLabel: t("h.e.conflict.reload"), cancelLabel: t("h.e.conflict.close"), focus: "confirm" } : null}
        onConfirm={() => {
          leaving.current = true;
          setConflictOpen(false);
          onReload();
        }}
        onClose={() => setConflictOpen(false)}
      />
    </form>
  );
}

type TextFieldProps = {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  hint?: string;
  wide?: boolean;
  multiline?: boolean;
  required?: boolean;
  type?: string;
  inputMode?: "text" | "tel" | "email" | "url" | "decimal";
  autoComplete?: string;
};

/** A label, the input, a hint and the error of this field: the error is tied to the input for screen readers. */
function TextField({ id, label, value, onChange, error, hint, wide, multiline, required, type = "text", inputMode, autoComplete }: TextFieldProps) {
  const hintId = `${id}-hint`;
  const errorId = `${id}-err`;
  const describedBy = [hint ? hintId : "", error ? errorId : ""].filter(Boolean).join(" ") || undefined;
  return (
    <div className={wide ? "field form__wide" : "field"}>
      <label className="field__label" htmlFor={id}>
        <span>{label}</span>
        {required ? <span className="field__req" aria-hidden="true">*</span> : null}
      </label>
      {multiline ? (
        <textarea id={id} className="input textarea" rows={6} value={value} aria-invalid={error ? true : undefined} aria-describedby={describedBy} onChange={(e) => onChange(e.target.value)} />
      ) : (
        <input
          id={id}
          className="input"
          type={type}
          inputMode={inputMode}
          autoComplete={autoComplete ?? "off"}
          value={value}
          required={required}
          aria-required={required ? true : undefined}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
      {hint ? <p className="field__hint" id={hintId}>{hint}</p> : null}
      {error ? <p className="field__error" id={errorId}>{error}</p> : null}
    </div>
  );
}

/** Short labels that each have a remove button. A label is one text node; the button names it for assistive tech. */
function RemovableChips({ items }: { items: { key: string; label: string; onRemove: () => void }[] }) {
  const t = useT();
  return (
    <ul className="chips chips--edit">
      {items.map((item) => (
        <li key={item.key} className="chip chip--edit">
          <span>{item.label}</span>
          <button type="button" className="chip__x" onClick={item.onRemove} aria-label={t("h.e.chip.remove", { name: item.label })} title={t("h.e.chip.remove", { name: item.label })}>
            <Icon name="close" size={14} />
          </button>
        </li>
      ))}
    </ul>
  );
}

/** A list of short labels: type one and press Enter or a comma (or "추가"); a pasted comma-separated list is split. */
function ChipEditor({ id, label, items, onChange, error }: { id: string; label: string; items: string[]; onChange: (items: string[]) => void; error?: string }) {
  const t = useT();
  const [text, setText] = useState("");
  const errorId = `${id}-err`;

  const add = () => {
    const parts = text.split(",").map((part) => part.trim()).filter((part) => part !== "");
    if (parts.length === 0) {
      if (text !== "") setText("");
      return;
    }
    const next = [...items];
    for (const part of parts) if (!next.includes(part)) next.push(part);
    if (next.length !== items.length) onChange(next);
    setText("");
  };
  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key !== "Enter" && e.key !== ",") return;
    // A key that only confirms a Korean syllable is not "add".
    if (e.nativeEvent.isComposing) return;
    e.preventDefault();
    add();
  };

  return (
    <div className="field">
      <label className="field__label" htmlFor={id}>{label}</label>
      {items.length > 0 ? (
        <RemovableChips items={items.map((item, index) => ({ key: `${index}-${item}`, label: item, onRemove: () => onChange(items.filter((_, i) => i !== index)) }))} />
      ) : (
        <p className="field__hint">{t("h.e.chip.empty")}</p>
      )}
      <div className="chipadd">
        <input
          id={id}
          className="input"
          type="text"
          autoComplete="off"
          value={text}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={onKeyDown}
          onBlur={add}
        />
        <button type="button" className="btn btn--ghost" onClick={add}>{t("h.e.chip.add")}</button>
      </div>
      {error ? <p className="field__error" id={errorId}>{error}</p> : null}
    </div>
  );
}

const isOpenDay = (status: DayStatus): boolean => status === "normal" || status === "biweeklyEven" || status === "biweeklyOdd";

/** Weekday rows, lunch break and special dates. The result is stored in the same shape the old panel writes. */
function HoursEditor({ hours, onChange, error }: { hours: HoursDraft; onChange: (hours: HoursDraft) => void; error?: string }) {
  const t = useT();
  const [stdStart, setStdStart] = useState("09:00");
  const [stdEnd, setStdEnd] = useState("18:00");
  const [closedDate, setClosedDate] = useState("");
  const [openDate, setOpenDate] = useState("");
  const [openStart, setOpenStart] = useState("");
  const [openEnd, setOpenEnd] = useState("");

  const setDay = (day: Weekday, next: Partial<DayDraft>) => onChange({ ...hours, days: { ...hours.days, [day]: { ...hours.days[day], ...next } } });
  // A day that is closed has no times: they are cleared, as in the old panel.
  const setStatus = (day: Weekday, status: DayStatus) => setDay(day, isOpenDay(status) ? { status } : { status, start: "", end: "" });

  const applyStandard = () => {
    if (!stdStart || !stdEnd || stdStart >= stdEnd) return;
    const days = { ...hours.days };
    for (const day of WEEKDAYS) if (days[day].status === "normal") days[day] = { ...days[day], start: stdStart, end: stdEnd };
    onChange({ ...hours, days });
  };

  const addClosed = () => {
    if (!closedDate || hours.closedDates.includes(closedDate)) return;
    onChange({ ...hours, closedDates: [...hours.closedDates, closedDate].sort() });
    setClosedDate("");
  };
  const addOpen = () => {
    if (!openDate || !openStart || !openEnd || openStart >= openEnd) return;
    onChange({ ...hours, openDates: { ...hours.openDates, [openDate]: `${openStart}~${openEnd}` } });
    setOpenDate("");
  };
  const removeOpen = (date: string) => {
    const next = { ...hours.openDates };
    delete next[date];
    onChange({ ...hours, openDates: next });
  };

  const openKeys = Object.keys(hours.openDates).sort();

  return (
    <div id="f-businessHours" className="panel hed" tabIndex={-1} aria-describedby={error ? "f-businessHours-err" : undefined}>
      {error ? <p className="field__error" id="f-businessHours-err">{error}</p> : null}
      <p className="field__hint">{t("h.e.hours.biweeklyNote")}</p>

      <div className="hed__std">
        <span className="field__label">{t("h.e.hours.standard")}</span>
        <input className="input hed__time" type="time" value={stdStart} aria-label={t("h.e.hours.standardStart")} onChange={(e) => setStdStart(e.target.value)} />
        <input className="input hed__time" type="time" value={stdEnd} aria-label={t("h.e.hours.standardEnd")} onChange={(e) => setStdEnd(e.target.value)} />
        <button type="button" className="btn btn--ghost" onClick={applyStandard}>{t("h.e.hours.applyAll")}</button>
        <p className="field__hint hed__note">{t("h.e.hours.applyNote")}</p>
      </div>

      <div className="hed__head" aria-hidden="true">
        <span />
        <span>{t("h.e.hours.status")}</span>
        <span>{t("h.e.hours.start")}</span>
        <span>{t("h.e.hours.end")}</span>
      </div>
      <ul className="hed__list">
        {WEEKDAYS.map((day) => {
          const d = hours.days[day];
          const open = isOpenDay(d.status);
          const dayName = t(DAY_KEYS[day]);
          return (
            <li key={day} className="hed__row">
              <span className="hed__day">{dayName}</span>
              <select className="input hed__status" value={d.status} aria-label={t("h.e.hours.statusOf", { day: dayName })} onChange={(e) => setStatus(day, e.target.value as DayStatus)}>
                {DAY_STATUSES.map((status) => (
                  <option key={status} value={status}>{t(DAY_STATUS_KEYS[status])}</option>
                ))}
              </select>
              <label className="hed__cell">
                <span className="hed__tl">{t("h.e.hours.start")}</span>
                <input className="input hed__time" type="time" value={d.start} disabled={!open} aria-label={t("h.e.hours.startOf", { day: dayName })} onChange={(e) => setDay(day, { start: e.target.value })} />
              </label>
              <label className="hed__cell">
                <span className="hed__tl">{t("h.e.hours.end")}</span>
                <input className="input hed__time" type="time" value={d.end} disabled={!open} aria-label={t("h.e.hours.endOf", { day: dayName })} onChange={(e) => setDay(day, { end: e.target.value })} />
              </label>
            </li>
          );
        })}
      </ul>

      <div className="hed__block">
        <p className="hed__title">{t("h.hours.lunch")}</p>
        <div className="hed__line">
          <label className="check">
            <input type="checkbox" checked={hours.noLunch} onChange={(e) => onChange({ ...hours, noLunch: e.target.checked })} />
            <span>{t("h.e.hours.noLunch")}</span>
          </label>
          <input className="input hed__time" type="time" value={hours.lunchStart} disabled={hours.noLunch} aria-label={t("h.e.hours.lunchStart")} onChange={(e) => onChange({ ...hours, lunchStart: e.target.value })} />
          <input className="input hed__time" type="time" value={hours.lunchEnd} disabled={hours.noLunch} aria-label={t("h.e.hours.lunchEnd")} onChange={(e) => onChange({ ...hours, lunchEnd: e.target.value })} />
        </div>
      </div>

      <div className="hed__block">
        <p className="hed__title">{t("h.hours.closedDates")}</p>
        {hours.closedDates.length > 0 ? (
          <RemovableChips items={hours.closedDates.map((date) => ({ key: date, label: date, onRemove: () => onChange({ ...hours, closedDates: hours.closedDates.filter((v) => v !== date) }) }))} />
        ) : (
          <p className="field__hint">{t("h.hours.none")}</p>
        )}
        <div className="hed__line">
          <input className="input hed__date" type="date" value={closedDate} aria-label={t("h.e.hours.date")} onChange={(e) => setClosedDate(e.target.value)} />
          <button type="button" className="btn btn--ghost" onClick={addClosed}>{t("h.e.hours.closedAdd")}</button>
        </div>
      </div>

      <div className="hed__block">
        <p className="hed__title">{t("h.hours.openDates")}</p>
        {openKeys.length > 0 ? (
          <RemovableChips items={openKeys.map((date) => ({ key: date, label: t("h.e.hours.openChip", { date, range: hours.openDates[date] }), onRemove: () => removeOpen(date) }))} />
        ) : (
          <p className="field__hint">{t("h.hours.none")}</p>
        )}
        <div className="hed__line">
          <input className="input hed__date" type="date" value={openDate} aria-label={t("h.e.hours.date")} onChange={(e) => setOpenDate(e.target.value)} />
          <input className="input hed__time" type="time" value={openStart} aria-label={t("h.e.hours.openStart")} onChange={(e) => setOpenStart(e.target.value)} />
          <input className="input hed__time" type="time" value={openEnd} aria-label={t("h.e.hours.openEnd")} onChange={(e) => setOpenEnd(e.target.value)} />
          <button type="button" className="btn btn--ghost" onClick={addOpen}>{t("h.e.hours.openAdd")}</button>
        </div>
        <p className="field__hint">{t("h.e.hours.openHint")}</p>
      </div>
    </div>
  );
}
