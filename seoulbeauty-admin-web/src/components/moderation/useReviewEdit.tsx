"use client";

import { useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from "react";
import Icon from "../Icon";
import { showUnitToast } from "../treatment-units/UnitToast";
import { editReview, fetchReviewTreatmentOptions, type ReviewItem } from "@/lib/api";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { imageHref } from "@/lib/moderation";

export const TITLE_MAX = 100;
export const CONTENT_MAX = 2000;
export const TREATMENTS_MAX = 10;

/** Length the way the server counts it: by characters, not by UTF-16 units. */
export const points = (s: string): number => Array.from(s).length;

const ERRORS: Record<string, MessageKey> = {
  required: "mod.edit.err.required",
  too_long: "mod.edit.err.too_long",
  bad_format: "mod.edit.err.bad_format",
  range: "mod.edit.err.range",
  unknown: "mod.edit.err.unknown",
  invalid: "mod.edit.err.invalid",
};

/** One field's message, if the field is wrong. */
export function FieldError({ id, code }: { id: string; code: string | undefined }) {
  const t = useT();
  if (!code) return null;
  return <p id={id} className="field__error" role="alert">{t(ERRORS[code] ?? "mod.edit.err.invalid", { title: TITLE_MAX, content: CONTENT_MAX })}</p>;
}

/** A counter under a text box; it turns into an error colour past the limit. */
export function Counter({ id, n, max }: { id: string; n: number; max: number }) {
  const t = useT();
  return <p id={id} className={n > max ? "mod-count is-over" : "mod-count"}>{t("mod.edit.count", { n, max })}</p>;
}

/** The pictures of an item, each with a remove / undo button. Removing only unlinks the picture; the file stays. */
export function RemovableImages({ uid, images, removed, saving, onToggle, error }: { uid: string; images: string[]; removed: string[]; saving: boolean; onToggle: (url: string) => void; error: string | undefined }) {
  const t = useT();
  if (images.length === 0) return null;
  return (
    <div className="field">
      <span className="field__label" id={`${uid}-img-l`}>{t("mod.edit.f.photos")}</span>
      <ul className="mod-ed__imgs" aria-labelledby={`${uid}-img-l`}>
        {images.map((url, i) => {
          const gone = removed.includes(url);
          const href = imageHref(url);
          return (
            <li key={`${i}-${url}`} className={gone ? "mod-ed__img is-gone" : "mod-ed__img"}>
              {href !== "" ? (
                // eslint-disable-next-line @next/next/no-img-element -- stored pictures come from the panel's media host, not the Next image optimizer
                <img src={href} alt="" loading="lazy" referrerPolicy="no-referrer" />
              ) : (
                <span className="mod-ed__noimg" aria-hidden="true"><Icon name="image" size={20} /></span>
              )}
              {gone ? <span className="mod-ed__tag">{t("mod.edit.img.willRemove")}</span> : null}
              <button type="button" className="btn btn--ghost mod-ed__imgbtn" disabled={saving} aria-pressed={gone} aria-label={t(gone ? "mod.edit.img.undoN" : "mod.edit.img.removeN", { n: i + 1 })} onClick={() => onToggle(url)}>
                {t(gone ? "mod.edit.img.undo" : "mod.edit.img.remove")}
              </button>
            </li>
          );
        })}
      </ul>
      <p className="field__hint">{t("mod.edit.img.note")}</p>
      <FieldError id={`${uid}-img-e`} code={error || undefined} />
    </div>
  );
}

function RatingInput({ value, onChange, disabled, labelledBy, describedBy }: { value: number; onChange: (n: number) => void; disabled: boolean; labelledBy: string; describedBy?: string }) {
  const t = useT();
  const refs = useRef<(HTMLButtonElement | null)[]>([]);
  const move = (e: KeyboardEvent, n: number) => {
    let next = n;
    if (e.key === "ArrowRight" || e.key === "ArrowUp") next = Math.min(5, n + 1);
    else if (e.key === "ArrowLeft" || e.key === "ArrowDown") next = Math.max(1, n - 1);
    else if (e.key === "Home") next = 1;
    else if (e.key === "End") next = 5;
    else return;
    e.preventDefault();
    onChange(next);
    refs.current[next - 1]?.focus();
  };
  return (
    <div className="mod-rate" role="radiogroup" aria-labelledby={labelledBy} aria-describedby={describedBy}>
      {[1, 2, 3, 4, 5].map((n) => (
        <button
          key={n}
          ref={(el) => { refs.current[n - 1] = el; }}
          type="button"
          role="radio"
          aria-checked={value === n}
          aria-label={t("mod.rating", { n })}
          tabIndex={value === n || (value === 0 && n === 1) ? 0 : -1}
          className={n <= value ? "mod-rate__star is-on" : "mod-rate__star"}
          disabled={disabled}
          onClick={() => onChange(n)}
          onKeyDown={(e) => move(e, n)}
        >
          <span aria-hidden="true">★</span>
        </button>
      ))}
    </div>
  );
}

/**
 * The edit form of one review, shown inside the detail window in place of the read-only view.
 * `body` and `foot` go to the window; `title` replaces its heading while editing.
 * Saving sends the whole editable state with the revision the page loaded; the author is never sent.
 */
export function useReviewEdit(r: ReviewItem, o: { reload: () => void; onSettled: () => void }) {
  const t = useT();
  const uid = useId();
  const formId = `${uid}-form`;
  const [editing, setEditing] = useState(false);
  const [title, setTitle] = useState(r.title);
  const [content, setContent] = useState(r.content);
  const [rating, setRating] = useState(Math.round(r.rating));
  const [treatments, setTreatments] = useState<string[]>(r.treatments);
  const [removed, setRemoved] = useState<string[]>([]);
  const [options, setOptions] = useState<string[] | null>(null);
  const [optionsFailed, setOptionsFailed] = useState(false);
  const [saving, setSaving] = useState(false);
  const [fields, setFields] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<MessageKey | null>(null);
  const lock = useRef(false);
  const titleRef = useRef<HTMLInputElement>(null);
  const openerId = `${uid}-edit`;
  const wasEditing = useRef(false);

  // The treatments this hospital offers: loaded when the form opens.
  useEffect(() => {
    if (!editing) return;
    const controller = new AbortController();
    void (async () => {
      const res = await fetchReviewTreatmentOptions(r.hospitalId, controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setOptions(res.data);
        setOptionsFailed(false);
      } else {
        setOptionsFailed(true);
      }
    })();
    return () => controller.abort();
  }, [editing, r.hospitalId]);

  useEffect(() => {
    if (editing) titleRef.current?.focus();
    else if (wasEditing.current) document.getElementById(openerId)?.focus();
    wasEditing.current = editing;
  }, [editing, openerId]);

  const start = () => {
    setTitle(r.title);
    setContent(r.content);
    setRating(Math.round(r.rating));
    setTreatments(r.treatments);
    setRemoved([]);
    setFields({});
    setFormError(null);
    setEditing(true);
  };
  const cancel = () => {
    if (!saving) setEditing(false);
  };

  const titleBad = title.trim() === "" ? "required" : points(title) > TITLE_MAX && title.trim() !== r.title.trim() ? "too_long" : undefined;
  const contentBad = content.trim() === "" ? "required" : points(content) > CONTENT_MAX && content.trim() !== r.content.trim() ? "too_long" : undefined;
  const same = (a: string[], b: string[]) => a.length === b.length && a.every((x, i) => x === b[i]);
  const dirty = title.trim() !== r.title.trim() || content.replace(/\r\n?/g, "\n").trim() !== r.content.replace(/\r\n?/g, "\n").trim() || rating !== Math.round(r.rating) || !same(treatments, r.treatments) || removed.length > 0;
  const localError = { title: titleBad, content: contentBad, rating: rating === 0 ? "range" : undefined } as Record<string, string | undefined>;
  const invalid = Object.values(localError).some((x) => x !== undefined);
  const shown = (name: string): string | undefined => fields[name] || localError[name];

  const toast = (tone: "ok" | "info" | "error", key: MessageKey) => showUnitToast({ tone, message: t(key), closeLabel: t("m.close") });

  const save = async () => {
    if (lock.current || saving || !dirty) return;
    setFields({});
    setFormError(null);
    if (invalid) return;
    lock.current = true;
    setSaving(true);
    try {
      const res = await editReview(r.id, r.revision, { title: title.trim(), content, rating, treatments, removeImages: removed });
      if (res.ok) {
        toast("ok", "mod.toast.saved");
        o.reload();
        o.onSettled();
        return;
      }
      if (res.status === 401) {
        window.location.reload();
        return;
      }
      if (res.error === "changed") {
        toast("error", "mod.toast.changed");
        o.reload();
        o.onSettled();
      } else if (res.status === 404) {
        toast("error", "mod.toast.missing");
        o.reload();
        o.onSettled();
      } else if (res.status === 400 && Object.keys(res.fields ?? {}).length > 0) {
        setFields(res.fields);
        setFormError("mod.edit.err.form");
      } else {
        setFormError(res.status === 403 ? "mod.toast.forbidden" : "mod.toast.failed");
      }
    } finally {
      lock.current = false;
      setSaving(false);
    }
  };

  const available = (options ?? []).filter((x) => !treatments.includes(x));
  const toggleImage = (url: string) => setRemoved((cur) => (cur.includes(url) ? cur.filter((x) => x !== url) : [...cur, url]));

  const body: ReactNode = (
    <form id={formId} className="mod-ed" noValidate onSubmit={(e) => { e.preventDefault(); void save(); }}>
      {formError ? <p className="field__error mod-ed__form-err" role="alert">{t(formError)}</p> : null}

      <div className="field">
        <label className="field__label" htmlFor={`${uid}-title`}>{t("mod.edit.f.title")}</label>
        <input id={`${uid}-title`} ref={titleRef} className="input" type="text" value={title} disabled={saving} autoComplete="off" aria-invalid={shown("title") ? true : undefined} aria-describedby={`${uid}-title-c ${uid}-title-e`} onChange={(e) => { setTitle(e.target.value); setFields((f) => ({ ...f, title: "" })); }} />
        <Counter id={`${uid}-title-c`} n={points(title)} max={TITLE_MAX} />
        <FieldError id={`${uid}-title-e`} code={shown("title") || undefined} />
      </div>

      <div className="field">
        <span className="field__label" id={`${uid}-rating-l`}>{t("mod.edit.f.rating")}</span>
        <RatingInput value={rating} onChange={(n) => { setRating(n); setFields((f) => ({ ...f, rating: "" })); }} disabled={saving} labelledBy={`${uid}-rating-l`} describedBy={`${uid}-rating-e`} />
        <FieldError id={`${uid}-rating-e`} code={shown("rating") || undefined} />
      </div>

      <div className="field">
        <label className="field__label" htmlFor={`${uid}-content`}>{t("mod.edit.f.content")}</label>
        <textarea id={`${uid}-content`} className="input textarea mod-ed__ta" rows={4} value={content} disabled={saving} aria-invalid={shown("content") ? true : undefined} aria-describedby={`${uid}-content-c ${uid}-content-e`} onChange={(e) => { setContent(e.target.value); setFields((f) => ({ ...f, content: "" })); }} />
        <Counter id={`${uid}-content-c`} n={points(content)} max={CONTENT_MAX} />
        <FieldError id={`${uid}-content-e`} code={shown("content") || undefined} />
      </div>

      <div className="field">
        <span className="field__label" id={`${uid}-tr-l`}>{t("mod.edit.f.treatments")}</span>
        {treatments.length > 0 ? (
          <ul className="mod-chips mod-ed__chips" aria-labelledby={`${uid}-tr-l`}>
            {treatments.map((x, i) => (
              <li key={`${i}-${x}`} className="mod-chip mod-chip--edit">
                <span>{x}</span>
                <button type="button" className="mod-chip__x" disabled={saving} aria-label={t("mod.edit.tr.remove", { name: x })} onClick={() => setTreatments((cur) => cur.filter((_, j) => j !== i))}>
                  <Icon name="close" size={12} />
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="field__hint">{t("mod.edit.tr.empty")}</p>
        )}
        <select
          className="input mod-ed__select"
          aria-label={t("mod.edit.tr.add")}
          aria-describedby={`${uid}-tr-h ${uid}-tr-e`}
          value=""
          disabled={saving || options === null || available.length === 0 || treatments.length >= TREATMENTS_MAX}
          onChange={(e) => {
            const v = e.target.value;
            if (v !== "" && !treatments.includes(v) && treatments.length < TREATMENTS_MAX) setTreatments((cur) => [...cur, v]);
            setFields((f) => ({ ...f, treatments: "" }));
          }}
        >
          <option value="">{t(options === null && !optionsFailed ? "mod.edit.tr.loading" : "mod.edit.tr.add")}</option>
          {available.map((x) => (
            <option key={x} value={x}>{x}</option>
          ))}
        </select>
        <p id={`${uid}-tr-h`} className="field__hint">
          {optionsFailed ? t("mod.edit.tr.loadFail") : treatments.length >= TREATMENTS_MAX ? t("mod.edit.tr.max", { n: TREATMENTS_MAX }) : options !== null && available.length === 0 ? t("mod.edit.tr.noMore") : t("mod.edit.tr.hint")}
        </p>
        <FieldError id={`${uid}-tr-e`} code={shown("treatments") || undefined} />
      </div>

      <RemovableImages uid={uid} images={r.images} removed={removed} saving={saving} onToggle={toggleImage} error={fields.removeImages} />

      <p className="mod-ed__locked"><Icon name="lock" size={14} />{t("mod.author")} <b translate="no">{r.author}</b> · {t("mod.author.locked")}</p>
    </form>
  );

  const foot: ReactNode = (
    <>
      <button type="button" className="btn btn--ghost" onClick={cancel} disabled={saving}>{t("mod.edit.cancel")}</button>
      <button type="submit" form={formId} className="btn btn--primary" disabled={saving || !dirty}>{t(saving ? "mod.edit.saving" : "mod.edit.save")}</button>
    </>
  );

  return { editing, start, body, foot, saving, dirty, openerId };
}
