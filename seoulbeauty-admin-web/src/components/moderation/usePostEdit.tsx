"use client";

import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { showUnitToast } from "../treatment-units/UnitToast";
import { Counter, CONTENT_MAX, FieldError, points, RemovableImages, TITLE_MAX } from "./useReviewEdit";
import { editPost, fetchPostCategories, type PostItem } from "@/lib/api";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

/**
 * The edit form of one community post (platform admin only), shown inside the detail window in place of the read-only view.
 * Category, title, text and removing pictures; the author is never sent.
 */
export function usePostEdit(p: PostItem, o: { reload: () => void; onSettled: () => void }) {
  const t = useT();
  const uid = useId();
  const formId = `${uid}-form`;
  const [editing, setEditing] = useState(false);
  const [category, setCategory] = useState(p.category);
  const [title, setTitle] = useState(p.title);
  const [content, setContent] = useState(p.content);
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

  // The categories posts use today: loaded when the form opens.
  useEffect(() => {
    if (!editing) return;
    const controller = new AbortController();
    void (async () => {
      const res = await fetchPostCategories(controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setOptions(res.data);
        setOptionsFailed(false);
      } else {
        setOptionsFailed(true);
      }
    })();
    return () => controller.abort();
  }, [editing]);

  useEffect(() => {
    if (editing) titleRef.current?.focus();
    else if (wasEditing.current) document.getElementById(openerId)?.focus();
    wasEditing.current = editing;
  }, [editing, openerId]);

  const start = () => {
    setCategory(p.category);
    setTitle(p.title);
    setContent(p.content);
    setRemoved([]);
    setFields({});
    setFormError(null);
    setEditing(true);
  };
  const cancel = () => {
    if (!saving) setEditing(false);
  };

  const norm = (x: string) => x.replace(/\r\n?/g, "\n").trim();
  const titleBad = title.trim() === "" ? "required" : points(title) > TITLE_MAX && title.trim() !== p.title.trim() ? "too_long" : undefined;
  const contentBad = content.trim() === "" ? "required" : points(content) > CONTENT_MAX && norm(content) !== norm(p.content) ? "too_long" : undefined;
  const dirty = category !== p.category || title.trim() !== p.title.trim() || norm(content) !== norm(p.content) || removed.length > 0;
  const localError = { category: category === "" ? "required" : undefined, title: titleBad, content: contentBad } as Record<string, string | undefined>;
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
      const res = await editPost(p.id, p.revision, { category, title: title.trim(), content, removeImages: removed });
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

  // The list of known categories, with the post's own one always selectable (it may no longer be in the list).
  const choices = Array.from(new Set([p.category, ...(options ?? [])])).filter((x) => x !== "");
  const toggleImage = (url: string) => setRemoved((cur) => (cur.includes(url) ? cur.filter((x) => x !== url) : [...cur, url]));

  const body: ReactNode = (
    <form id={formId} className="mod-ed" noValidate onSubmit={(e) => { e.preventDefault(); void save(); }}>
      {formError ? <p className="field__error mod-ed__form-err" role="alert">{t(formError)}</p> : null}

      <div className="field">
        <label className="field__label" htmlFor={`${uid}-cat`}>{t("mod.edit.f.category")}</label>
        <select
          id={`${uid}-cat`}
          className="input mod-ed__select"
          value={category}
          disabled={saving || options === null}
          aria-invalid={shown("category") ? true : undefined}
          aria-describedby={`${uid}-cat-h ${uid}-cat-e`}
          onChange={(e) => { setCategory(e.target.value); setFields((f) => ({ ...f, category: "" })); }}
        >
          {category === "" ? <option value="" disabled>{t("mod.edit.cat.pick")}</option> : null}
          {choices.map((x) => (
            <option key={x} value={x}>{x}</option>
          ))}
        </select>
        <p id={`${uid}-cat-h`} className="field__hint">{optionsFailed ? t("mod.edit.cat.loadFail") : t(options === null ? "mod.edit.cat.loading" : "mod.edit.cat.hint")}</p>
        <FieldError id={`${uid}-cat-e`} code={shown("category") || undefined} />
      </div>

      <div className="field">
        <label className="field__label" htmlFor={`${uid}-title`}>{t("mod.edit.f.title")}</label>
        <input id={`${uid}-title`} ref={titleRef} className="input" type="text" value={title} disabled={saving} autoComplete="off" aria-invalid={shown("title") ? true : undefined} aria-describedby={`${uid}-title-c ${uid}-title-e`} onChange={(e) => { setTitle(e.target.value); setFields((f) => ({ ...f, title: "" })); }} />
        <Counter id={`${uid}-title-c`} n={points(title)} max={TITLE_MAX} />
        <FieldError id={`${uid}-title-e`} code={shown("title") || undefined} />
      </div>

      <div className="field">
        <label className="field__label" htmlFor={`${uid}-content`}>{t("mod.edit.f.content")}</label>
        <textarea id={`${uid}-content`} className="input textarea mod-ed__ta" rows={8} value={content} disabled={saving} aria-invalid={shown("content") ? true : undefined} aria-describedby={`${uid}-content-c ${uid}-content-e`} onChange={(e) => { setContent(e.target.value); setFields((f) => ({ ...f, content: "" })); }} />
        <Counter id={`${uid}-content-c`} n={points(content)} max={CONTENT_MAX} />
        <FieldError id={`${uid}-content-e`} code={shown("content") || undefined} />
      </div>

      <RemovableImages uid={uid} images={p.images} removed={removed} saving={saving} onToggle={toggleImage} error={fields.removeImages} />
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
