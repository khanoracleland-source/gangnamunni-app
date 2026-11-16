"use client";

import { useEffect, useMemo, useRef, useState, type FormEvent, type KeyboardEvent, type ReactNode } from "react";
import Icon, { type IconName } from "../Icon";
import ConfirmDialog from "./ConfirmDialog";
import HospitalDoctors from "./HospitalDoctors";
import HospitalImages from "./HospitalImages";
import LocationPicker from "./LocationPicker";
import { GradePill, Notice, Pill } from "./parts";
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
import { WEEKDAYS, legacyEditUrl, type Weekday } from "@/lib/hospitals";
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

type CardId = "basic" | "tags" | "hours" | "loc" | "docs" | "images" | "doctors" | "treatments" | "ai";

/** Cards in page order, like the old panel. The first four are edited here; the rest open the old panel. */
const CARD_IDS: readonly CardId[] = ["basic", "tags", "hours", "loc", "docs", "images", "doctors", "treatments", "ai"];

/** The card that holds each editable field: a card with an error must be open before the field can be focused. */
const FIELD_CARD: Record<string, CardId> = {
  name: "basic", email: "basic", ownerName: "basic", businessNumber: "basic", phone: "basic", contactName: "basic",
  contactPhone: "basic", address: "basic", website: "basic", snsUrl: "basic", introduction: "basic",
  categories: "tags", badges: "tags", treatmentTags: "tags", subwayNames: "tags",
  businessHours: "hours", showRating: "hours",
  latitude: "loc", longitude: "loc",
};

/** Only the first card is open at first (quick edits); the rest are folded, as in the old panel. */
const initialOpen = (): Record<CardId, boolean> =>
  Object.fromEntries(CARD_IDS.map((id) => [id, id === "basic"])) as Record<CardId, boolean>;

const CARD_TITLE_KEYS: Record<CardId, MessageKey> = {
  basic: "h.e.c.basic",
  tags: "h.e.c.tags",
  hours: "h.e.c.hours",
  loc: "h.e.c.loc",
  docs: "h.e.c.docs",
  images: "h.e.c.images",
  doctors: "h.e.c.doctors",
  treatments: "h.e.c.treatments",
  ai: "h.e.c.ai",
};
const CARD_DESC_KEYS: Partial<Record<CardId, MessageKey>> = {
  docs: "h.e.c.docs.desc",
  images: "h.e.c.images.desc",
  doctors: "h.e.c.doctors.desc",
  treatments: "h.e.c.treatments.desc",
  ai: "h.e.c.ai.desc",
};
const CARD_ICONS: Record<CardId, IconName> = {
  basic: "hospital",
  tags: "tag",
  hours: "clock",
  loc: "pin",
  docs: "sheet",
  images: "image",
  doctors: "userPlus",
  treatments: "clipboard",
  ai: "sparkle",
};

type Props = {
  hospital: HospitalDetail["hospital"];
  /** The change was stored. */
  onSaved: () => void;
  /** The admin left the form without saving. */
  onCancel: () => void;
  /** Throw the typed values away and read the hospital again. */
  onReload: () => void;
  /** How many images / doctors the hospital has, when known: shown on the cards that lead to the old panel. */
  imageCount?: number;
  doctorCount?: number;
  /** The server lets this panel upload images itself: the images card is then a working card, not a link to the old panel. */
  imagesEnabled?: boolean;
  /** The server lets this panel add doctors itself: the doctors card is then a working card, not a link to the old panel. */
  doctorsEnabled?: boolean;
};

/**
 * The edit form of one hospital. It starts from the loaded values, sends only the fields that differ,
 * and reports a clash with a change made elsewhere instead of overwriting it.
 */
export default function HospitalEdit({ hospital, onSaved, onCancel, onReload, imageCount, doctorCount, imagesEnabled = false, doctorsEnabled = false }: Props) {
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
  const [open, setOpen] = useState<Record<CardId, boolean>>(initialOpen);
  // The field to focus once the card that holds it is open and drawn.
  const [focusReq, setFocusReq] = useState<{ key: string } | null>(null);
  // The address field was edited and left: the location picker may look the address up quietly.
  const [syncTick, setSyncTick] = useState(0);
  const [addressAtFocus, setAddressAtFocus] = useState("");
  // The change time the save is checked against. An image upload moves it forward without touching the typed values.
  const [baseUpdatedAt, setBaseUpdatedAt] = useState(hospital.updatedAt || null);
  const [imageTotal, setImageTotal] = useState<number | undefined>(imageCount);
  // The images card reads its data once it has been opened.
  const [imagesOpened, setImagesOpened] = useState(false);
  if (open.images && !imagesOpened) setImagesOpened(true);
  const [doctorTotal, setDoctorTotal] = useState<number | undefined>(doctorCount);
  // The doctors card reads its data once it has been opened.
  const [doctorsOpened, setDoctorsOpened] = useState(false);
  if (open.doctors && !doctorsOpened) setDoctorsOpened(true);

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

  useEffect(() => {
    if (focusReq) focusField(focusReq.key);
  }, [focusReq]);

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
    // A card with a problem is opened so the field can be seen and focused.
    const cards = new Set(Object.keys(found).map((key) => FIELD_CARD[key]).filter((id): id is CardId => id !== undefined));
    if (cards.size > 0) setOpen((prev) => ({ ...prev, ...Object.fromEntries([...cards].map((id) => [id, true])) }));
    const first = FIELD_ORDER.find((key) => key in found);
    if (first) setFocusReq({ key: first });
  };

  const toggle = (id: CardId) => setOpen((prev) => ({ ...prev, [id]: !prev[id] }));
  const setAll = (value: boolean) => setOpen(Object.fromEntries(CARD_IDS.map((id) => [id, value])) as Record<CardId, boolean>);
  const errorsIn = (id: CardId): number => Object.keys(errors).filter((key) => FIELD_CARD[key] === id).length;

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
    const res = await patchHospital(hospital.id, baseUpdatedAt, patch);
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

  const onAddressFocus = () => {
    setAddressAtFocus(draft.address);
  };
  const onAddressBlur = () => {
    if (draft.address.trim() !== "" && draft.address !== addressAtFocus) setSyncTick((n) => n + 1);
  };

  const text = (key: TextKey, label: string, extra?: { onFocus?: () => void; onBlur?: () => void; wide?: boolean; multiline?: boolean; type?: string; inputMode?: "text" | "tel" | "email" | "url"; required?: boolean; autoComplete?: string }): ReactNode => (
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

      <div className="section__tools">
        <button type="button" className="btn btn--ghost" onClick={() => setAll(true)}>{t("h.e.c.openAll")}</button>
        <button type="button" className="btn btn--ghost" onClick={() => setAll(false)}>{t("h.e.c.closeAll")}</button>
      </div>

      <FoldCard id="basic" open={open.basic} errors={errorsIn("basic")} onToggle={toggle}>
        <div className="form">
          {text("name", t("h.f.name"), { required: true, autoComplete: "organization" })}
          {text("email", t("h.f.email"), { type: "email", inputMode: "email", autoComplete: "email" })}
          {text("ownerName", t("h.f.owner"))}
          {text("businessNumber", t("h.f.businessNumber"), { inputMode: "text" })}
          {text("phone", t("h.f.phone"), { type: "tel", inputMode: "tel", autoComplete: "tel" })}
          {text("contactName", t("h.e.f.contactName"))}
          {text("contactPhone", t("h.e.f.contactPhone"), { type: "tel", inputMode: "tel" })}
          {text("address", t("h.f.address"), { autoComplete: "street-address", onFocus: onAddressFocus, onBlur: onAddressBlur })}
          {text("website", t("h.f.website"), { type: "url", inputMode: "url" })}
          {text("snsUrl", t("h.f.sns"), { type: "url", inputMode: "url" })}
          {text("introduction", t("h.f.intro"), { wide: true, multiline: true })}
          <div className="form__wide">
            <div className="locked">
              <span className="field__label">{t("h.status.grade")}</span>
              <GradePill grade={hospital.adGrade} long />
              <Pill tone="off">{t("h.e.f.gradeLocked")}</Pill>
            </div>
            <p className="field__hint">{t("h.e.f.gradeNote")}</p>
          </div>
        </div>
      </FoldCard>

      <FoldCard id="tags" open={open.tags} errors={errorsIn("tags")} onToggle={toggle}>
        <p className="field__hint ecard__desc">{t("h.e.tags.desc")}</p>
        <div className="form">
          <ChipEditor id="f-categories" label={t("h.tags.categories")} items={draft.categories} onChange={(v) => setList("categories", v)} error={err("categories")} />
          <ChipEditor id="f-badges" label={t("h.tags.badges")} items={draft.badges} onChange={(v) => setList("badges", v)} error={err("badges")} />
          <ChipEditor id="f-treatmentTags" label={t("h.tags.treatments")} items={draft.treatmentTags} onChange={(v) => setList("treatmentTags", v)} error={err("treatmentTags")} />
          <ChipEditor id="f-subwayNames" label={t("h.tags.subways")} items={draft.subwayNames} onChange={(v) => setList("subwayNames", v)} error={err("subwayNames")} />
        </div>
      </FoldCard>

      <FoldCard id="hours" open={open.hours} errors={errorsIn("hours")} onToggle={toggle}>
        <p className="field__hint ecard__desc">{t("h.e.hours.desc")}</p>
        <HoursEditor hours={draft.hours} onChange={setHours} error={err("businessHours")} />
        <div className="ecard__rating">
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
      </FoldCard>

      <FoldCard id="loc" open={open.loc} errors={errorsIn("loc")} onToggle={toggle}>
        <p className="field__hint ecard__desc">{t("h.e.loc.desc")}</p>
        <LocationPicker
          open={open.loc}
          address={draft.address}
          lat={draft.latitude}
          lng={draft.longitude}
          syncTick={syncTick}
          onAddress={(value) => setText("address", value)}
          onCoords={(latitude, longitude) => {
            setDraft((d) => ({ ...d, latitude, longitude }));
            clearError("latitude");
            clearError("longitude");
          }}
          onUnauthorized={() => {
            leaving.current = true;
            window.location.assign(PHP_ADMIN_URL);
          }}
        />
        <div className="form">
          <TextField id="f-latitude" label={t("h.e.f.lat")} hint={t("h.e.f.latHint")} inputMode="decimal" value={draft.latitude} error={err("latitude")} onChange={(v) => { setDraft((d) => ({ ...d, latitude: v })); clearError("latitude"); }} />
          <TextField id="f-longitude" label={t("h.e.f.lng")} hint={t("h.e.f.lngHint")} inputMode="decimal" value={draft.longitude} error={err("longitude")} onChange={(v) => { setDraft((d) => ({ ...d, longitude: v })); clearError("longitude"); }} />
        </div>
      </FoldCard>

      <FoldCard id="docs" open={open.docs} errors={0} onToggle={toggle} legacyHref={legacyEditUrl(hospital.id)} />
      {imagesEnabled ? (
        <FoldCard id="images" open={open.images} errors={0} onToggle={toggle} count={imageTotal === undefined ? undefined : t("h.e.c.imgCount", { n: imageTotal })}>
          <HospitalImages
            hospitalId={hospital.id}
            opened={imagesOpened}
            onChange={({ count, updatedAt }) => {
              setImageTotal(count);
              if (updatedAt) setBaseUpdatedAt(updatedAt);
            }}
            onUnauthorized={() => {
              leaving.current = true;
              window.location.assign(PHP_ADMIN_URL);
            }}
          />
        </FoldCard>
      ) : (
        <FoldCard id="images" open={open.images} errors={0} onToggle={toggle} legacyHref={legacyEditUrl(hospital.id)} count={imageCount === undefined ? undefined : t("h.e.c.imgCount", { n: imageCount })} />
      )}
      {doctorsEnabled ? (
        <FoldCard id="doctors" open={open.doctors} errors={0} onToggle={toggle} count={doctorTotal === undefined ? undefined : t("h.e.c.docCount", { n: doctorTotal })}>
          <HospitalDoctors
            hospitalId={hospital.id}
            opened={doctorsOpened}
            onChange={({ count }) => setDoctorTotal(count)}
            onUnauthorized={() => {
              leaving.current = true;
              window.location.assign(PHP_ADMIN_URL);
            }}
          />
        </FoldCard>
      ) : (
        <FoldCard id="doctors" open={open.doctors} errors={0} onToggle={toggle} legacyHref={legacyEditUrl(hospital.id)} count={doctorCount === undefined ? undefined : t("h.e.c.docCount", { n: doctorCount })} />
      )}
      <FoldCard id="treatments" open={open.treatments} errors={0} onToggle={toggle} legacyHref={legacyEditUrl(hospital.id)} />
      <FoldCard id="ai" open={open.ai} errors={0} onToggle={toggle} legacyHref={legacyEditUrl(hospital.id)} />

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

type FoldCardProps = {
  id: CardId;
  open: boolean;
  errors: number;
  onToggle: (id: CardId) => void;
  /** Cards that are not edited here: the old panel page that does it. */
  legacyHref?: string;
  /** A short count shown next to the title. */
  count?: string;
  children?: ReactNode;
};

/**
 * One folding block of the edit page. The heading is a button (aria-expanded) so keyboard and screen readers
 * work as with a native disclosure, and the body stays mounted while folded so typed values and the hours
 * editor's own state are kept.
 */
function FoldCard({ id, open, errors, onToggle, legacyHref, count, children }: FoldCardProps) {
  const t = useT();
  const descKey = CARD_DESC_KEYS[id];
  return (
    <section className={errors > 0 ? "ecard ecard--err" : "ecard"}>
      <h2 className="ecard__h">
        <button type="button" id={`ec-${id}-h`} className="ecard__btn" aria-expanded={open} aria-controls={`ec-${id}`} onClick={() => onToggle(id)}>
          <span className="ecard__ico"><Icon name={CARD_ICONS[id]} size={20} /></span>
          <span className="ecard__main">
            <span className="ecard__title">{t(CARD_TITLE_KEYS[id])}</span>
            {legacyHref ? <Pill tone="info">{t("menu.legacy")}</Pill> : null}
            {count ? <Pill tone="plain">{count}</Pill> : null}
            {errors > 0 ? <Pill tone="warn">{t("h.e.c.errCount", { n: errors })}</Pill> : null}
          </span>
          <Icon name="chevron" size={20} className="ecard__chev" />
        </button>
      </h2>
      <div id={`ec-${id}`} role="region" aria-labelledby={`ec-${id}-h`} className="ecard__panel" data-open={open}>
        <div className="ecard__clip">
          <div className="ecard__body">
        {legacyHref ? (
          <div className="ecard__legacy">
            {descKey ? <p className="field__hint">{t(descKey)}</p> : null}
            <a className="btn btn--ghost" href={legacyHref}>
              <Icon name="external" size={18} />
              <span>{t("h.e.c.legacyOpen")}</span>
            </a>
          </div>
        ) : (
          children
        )}
          </div>
        </div>
      </div>
    </section>
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
  onFocus?: () => void;
  onBlur?: () => void;
};

/** A label, the input, a hint and the error of this field: the error is tied to the input for screen readers. */
function TextField({ id, label, value, onChange, error, hint, wide, multiline, required, type = "text", inputMode, autoComplete, onFocus, onBlur }: TextFieldProps) {
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
          onFocus={onFocus}
          onBlur={onBlur}
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
