"use client";

import { useEffect, useId, useMemo, useRef, useState, type FormEvent, type ReactNode } from "react";
import { toast } from "sonner";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { failureKey, imageFailureKey, reasonKey } from "./writeErrors";
import {
  ANESTHESIA_TYPES,
  createTreatment,
  updateTreatment,
  uploadTreatmentImage,
  type AnesthesiaType,
  type TreatmentChange,
  type TreatmentInput,
  type TreatmentItem,
  type TreatmentsData,
} from "@/lib/api";
import { useT } from "@/lib/locale";
import { conflictIdsOf, fold, imageUrl } from "@/lib/treatments";

/** Types the server accepts; the server still checks the real type of the file. */
const IMAGE_ACCEPT = ".jpg,.jpeg,.png,.gif,.webp";
const IMAGE_MAX_BYTES = 10 * 1024 * 1024;

type Form = {
  name: string;
  categoryId: string;
  hashtag: string;
  description: string;
  precautions: string;
  /** The stored address (a path of the upload folder or an http(s) address); empty = no image. */
  defaultImageUrl: string;
  durationMin: string;
  recoveryDays: string;
  repeatCycleMonths: string;
  avgPriceMin: string;
  avgPriceMax: string;
  anesthesiaType: AnesthesiaType;
  revisitDays: string;
  revisitStrict: boolean;
  conflictIds: number[];
};

const text = (n: number): string => (n > 0 ? String(n) : "");

function initial(item: TreatmentItem | null, data: TreatmentsData): Form {
  return {
    name: item?.name ?? "",
    categoryId: item ? String(item.categoryId) : "",
    hashtag: item?.hashtag ?? "",
    description: item?.description ?? "",
    precautions: item?.precautions ?? "",
    defaultImageUrl: item?.defaultImageUrl ?? "",
    durationMin: item ? text(item.durationMin) : "",
    recoveryDays: item ? text(item.recoveryDays) : "",
    repeatCycleMonths: item ? text(item.repeatCycleMonths) : "",
    avgPriceMin: item ? text(item.avgPriceMin) : "",
    avgPriceMax: item ? text(item.avgPriceMax) : "",
    anesthesiaType: item?.anesthesiaType ?? "none",
    revisitDays: item?.revisitDays ? String(item.revisitDays) : "",
    revisitStrict: item?.revisitStrict ?? false,
    conflictIds: item ? conflictIdsOf(data.conflicts, item.id) : [],
  };
}

/** A whole number from the field: "" is 0, anything else that is not digits is NaN (shown as bad_format by the server's rule, caught here first). */
const count = (v: string): number => (v.trim() === "" ? 0 : /^\d{1,9}$/.test(v.trim()) ? Number(v.trim()) : Number.NaN);

/** Builds what is sent: everything on create, only the changed keys on edit. Returns the field that is not a number, if any. */
function payload(form: Form, item: TreatmentItem | null, data: TreatmentsData): { body: TreatmentInput } | { bad: string } {
  const numbers = { durationMin: count(form.durationMin), recoveryDays: count(form.recoveryDays), repeatCycleMonths: count(form.repeatCycleMonths), avgPriceMin: count(form.avgPriceMin), avgPriceMax: count(form.avgPriceMax) };
  for (const [k, v] of Object.entries(numbers)) if (Number.isNaN(v)) return { bad: k };
  const revisit = count(form.revisitDays);
  if (Number.isNaN(revisit)) return { bad: "revisitDays" };
  const next: TreatmentInput = {
    name: form.name.trim(),
    categoryId: Number(form.categoryId) || 0,
    hashtag: form.hashtag.trim(),
    description: form.description.trim(),
    precautions: form.precautions.trim(),
    ...numbers,
    anesthesiaType: form.anesthesiaType,
  };
  if (data.schema.revisit) {
    next.revisitDays = revisit > 0 ? revisit : null;
    next.revisitStrict = revisit > 0 && form.revisitStrict;
  }
  if (data.schema.defaultImage) next.defaultImageUrl = form.defaultImageUrl.trim();
  if (data.schema.conflicts) next.conflictIds = form.conflictIds;
  if (!item) return { body: next };
  const before = initial(item, data);
  const prev = payload(before, null, data);
  if (!("body" in prev)) return { body: next };
  const diff: TreatmentInput = {};
  for (const key of Object.keys(next) as (keyof TreatmentInput)[]) {
    if (JSON.stringify(next[key]) !== JSON.stringify(prev.body[key])) (diff as Record<string, unknown>)[key] = next[key];
  }
  return { body: diff };
}

/** Add or edit one treatment: native <dialog>, a bottom sheet below 640px. The server has the last word on every rule; the form only reports its refusals next to the field. */
export default function TreatmentDialog({
  item,
  data,
  canImages,
  onClose,
  onSaved,
}: {
  item: TreatmentItem | null;
  data: TreatmentsData;
  /** Files may be uploaded (the images switch is on). Without it only an address can be typed. */
  canImages: boolean;
  onClose: () => void;
  onSaved: (change: TreatmentChange, created: boolean) => void;
}) {
  const t = useT();
  const uid = useId();
  const ref = useRef<HTMLDialogElement>(null);
  const [form, setForm] = useState<Form>(() => initial(item, data));
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [general, setGeneral] = useState<string | null>(null);
  const [pick, setPick] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState("");
  const fileInput = useRef<HTMLInputElement>(null);
  const id = (name: string) => `${uid}-${name}`;

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);

  const set = <K extends keyof Form>(key: K, value: Form[K]) => {
    setForm((f) => ({ ...f, [key]: value }));
    setErrors((e) => (key in e ? Object.fromEntries(Object.entries(e).filter(([k]) => k !== key)) : e));
  };

  // The local preview address is released when it is replaced and when the dialog goes away.
  const previewRef = useRef("");
  useEffect(() => () => {
    if (previewRef.current) URL.revokeObjectURL(previewRef.current);
  }, []);

  const chooseFile = (picked: File | null) => {
    if (picked && picked.size > IMAGE_MAX_BYTES) {
      setErrors((e) => ({ ...e, file: t("tmi.err.too_large") }));
      if (fileInput.current) fileInput.current.value = "";
      return;
    }
    setErrors((e) => Object.fromEntries(Object.entries(e).filter(([k]) => k !== "file")));
    if (previewRef.current) URL.revokeObjectURL(previewRef.current);
    previewRef.current = picked ? URL.createObjectURL(picked) : "";
    setPreview(previewRef.current);
    setFile(picked);
  };

  const candidates = useMemo(() => {
    const needle = fold(pick.trim());
    return data.items.filter((x) => x.isActive && x.id !== item?.id && (needle === "" || fold(x.name).includes(needle) || fold(x.hashtag).includes(needle)));
  }, [data.items, item, pick]);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    const local: Record<string, string> = {};
    if (form.name.trim() === "") local.name = t("tmw.err.required");
    if (form.categoryId === "") local.categoryId = t("tmw.err.required");
    const built = payload(form, item, data);
    if ("bad" in built) local[built.bad] = t("tmw.err.bad_format");
    if (Object.keys(local).length > 0) {
      setErrors(local);
      setGeneral(null);
      document.getElementById(id(Object.keys(local)[0]))?.focus();
      return;
    }
    if (!("body" in built)) return;
    const nothingToSave = item !== null && Object.keys(built.body).length === 0;
    if (nothingToSave && !file) {
      onClose();
      return;
    }
    setBusy(true);
    setGeneral(null);
    let change: TreatmentChange | null = null;
    if (!nothingToSave) {
      const res = item ? await updateTreatment(item.id, built.body) : await createTreatment(built.body);
      if (!res.ok) {
        setBusy(false);
        if (res.status === 400 && res.error === "invalid_field" && res.field) {
          setErrors({ [res.field]: t(reasonKey(res.reason)) });
          document.getElementById(id(res.field))?.focus();
          return;
        }
        setGeneral(t(failureKey(res)));
        return;
      }
      change = res.data;
    }
    // The file is sent after the treatment exists (a new treatment has no id before). When only the file was changed, the answer of the upload is the new state.
    if (file && canImages) {
      const targetId = change?.item.id ?? item?.id ?? 0;
      const up = await uploadTreatmentImage(targetId, file);
      if (up.ok) change = up.data;
      else if (change) toast.error(t("tmi.err.afterSave", { reason: t(imageFailureKey(up)) }));
      else {
        setBusy(false);
        setErrors({ file: t(imageFailureKey(up)) });
        document.getElementById(id("file"))?.focus();
        return;
      }
    }
    setBusy(false);
    if (change) onSaved(change, item === null);
  };

  const field = (name: keyof Form | "avgPriceMax" | "file", label: string, control: ReactNode, hint?: string) => (
    <div className="field">
      <label className="field__label" htmlFor={id(name)}>{label}</label>
      {control}
      {hint ? <p className="field__hint" id={id(`${name}-hint`)}>{hint}</p> : null}
      {errors[name] ? <p className="field__error" id={id(`${name}-err`)} role="alert">{errors[name]}</p> : null}
    </div>
  );
  const aria = (name: string) => ({ id: id(name), "aria-invalid": errors[name] ? true : undefined, "aria-describedby": errors[name] ? id(`${name}-err`) : undefined });
  const num = (name: "durationMin" | "recoveryDays" | "repeatCycleMonths" | "avgPriceMin" | "avgPriceMax" | "revisitDays") => (
    <input className="input" inputMode="numeric" autoComplete="off" maxLength={9} value={form[name]} onChange={(e) => set(name, e.target.value)} {...aria(name)} />
  );
  const title = item ? t("tmw.dlg.edit") : t("tmw.dlg.create");

  return createPortal(
    <dialog
      ref={ref}
      className="dialog dialog--prices dialog--tm"
      aria-labelledby={id("title")}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onClose();
      }}
    >
      <form className="htp tm__form" onSubmit={submit} noValidate>
        <span className="htp__handle" aria-hidden="true" />
        <div className="htp__head">
          <div className="htp__headt">
            <h2 id={id("title")} className="dialog__title htp__title">{title}</h2>
            {item ? <span className="htp__chip" translate="no" title={item.name}>{item.name}</span> : null}
          </div>
          <button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("tmw.close")} title={t("tmw.close")}>
            <Icon name="close" size={18} />
          </button>
        </div>

        <div className="htp__body tm__form-body">
          <section className="tm__sec" aria-labelledby={id("s1")}>
            <h3 className="tm__sec-title" id={id("s1")}>{t("tmw.sec.basic")}</h3>
            {field("name", t("tmw.f.name"), <input className="input" maxLength={200} autoComplete="off" value={form.name} onChange={(e) => set("name", e.target.value)} data-autofocus="" {...aria("name")} />)}
            <div className="tm__row2">
              {field(
                "categoryId",
                t("tmw.f.category"),
                <select className="sel sel--field" value={form.categoryId} onChange={(e) => set("categoryId", e.target.value)} {...aria("categoryId")}>
                  <option value="">{t("tmw.f.categoryPick")}</option>
                  {data.categories.filter((c) => c.isActive || String(c.id) === form.categoryId).map((c) => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </select>,
              )}
              {field("hashtag", t("tmw.f.hashtag"), <input className="input" maxLength={100} autoComplete="off" value={form.hashtag} onChange={(e) => set("hashtag", e.target.value)} {...aria("hashtag")} />, t("tmw.f.hashtagHint"))}
            </div>
            {field("description", t("tmw.f.description"), <textarea className="input tm__textarea" rows={3} maxLength={5000} value={form.description} onChange={(e) => set("description", e.target.value)} {...aria("description")} />)}
          </section>

          <section className="tm__sec" aria-labelledby={id("s2")}>
            <h3 className="tm__sec-title" id={id("s2")}>{t("tmw.sec.time")}</h3>
            <div className="tm__row3">
              {field("durationMin", t("tmw.f.duration"), num("durationMin"))}
              {field("recoveryDays", t("tmw.f.recovery"), num("recoveryDays"))}
              {field(
                "anesthesiaType",
                t("tmw.f.anesthesia"),
                <select className="sel sel--field" value={form.anesthesiaType} onChange={(e) => set("anesthesiaType", e.target.value as AnesthesiaType)} {...aria("anesthesiaType")}>
                  {ANESTHESIA_TYPES.map((a) => (
                    <option key={a} value={a}>{t(`tmw.an.${a}` as const)}</option>
                  ))}
                </select>,
              )}
            </div>
            <div className="tm__row3">
              {field("avgPriceMin", t("tmw.f.priceMin"), num("avgPriceMin"))}
              {field("avgPriceMax", t("tmw.f.priceMax"), num("avgPriceMax"))}
              {field("repeatCycleMonths", t("tmw.f.repeat"), num("repeatCycleMonths"))}
            </div>
            {data.schema.revisit ? (
              <div className="tm__row2">
                {field("revisitDays", t("tmw.f.revisit"), num("revisitDays"), t("tmw.f.revisitHint"))}
                <label className="tm__check">
                  <input type="checkbox" checked={form.revisitStrict} disabled={count(form.revisitDays) <= 0} onChange={(e) => set("revisitStrict", e.target.checked)} />
                  <span>{t("tmw.f.revisitStrict")}</span>
                </label>
              </div>
            ) : null}
          </section>

          {data.schema.defaultImage ? (
            <section className="tm__sec" aria-labelledby={id("s4")}>
              <h3 className="tm__sec-title" id={id("s4")}>{t("tmi.sec")}</h3>
              <div className="tm__imgrow">
                <figure className="tm__imgprev">
                  {preview || imageUrl(form.defaultImageUrl.trim() === "" ? null : form.defaultImageUrl.trim()) ? (
                    // eslint-disable-next-line @next/next/no-img-element -- a local preview or an address stored by the old panel, no optimizer needed
                    <img src={preview || imageUrl(form.defaultImageUrl.trim())} alt="" width={96} height={96} />
                  ) : (
                    <span className="tm__imgprev-empty"><Icon name="image" size={24} /></span>
                  )}
                  <figcaption>{t(preview ? "tmi.preview.new" : form.defaultImageUrl.trim() ? "tmi.preview.current" : "tmi.noImage")}</figcaption>
                </figure>
                <div className="tm__imgfields">
                  {canImages
                    ? field(
                        "file",
                        t("tmi.f.file"),
                        <span className="tm__filerow">
                          <input ref={fileInput} className="input" type="file" accept={IMAGE_ACCEPT} onChange={(e) => chooseFile(e.target.files?.[0] ?? null)} {...aria("file")} />
                          {file ? (
                            <button type="button" className="txtbtn" onClick={() => { chooseFile(null); if (fileInput.current) fileInput.current.value = ""; }}>{t("tmi.f.clearFile")}</button>
                          ) : null}
                        </span>,
                        t("tmi.f.fileHint"),
                      )
                    : null}
                  {field("defaultImageUrl", t("tmi.f.url"), <input className="input" maxLength={500} autoComplete="off" spellCheck={false} value={form.defaultImageUrl} onChange={(e) => set("defaultImageUrl", e.target.value)} {...aria("defaultImageUrl")} />, t("tmi.f.urlHint"))}
                  {form.defaultImageUrl.trim() !== "" ? (
                    <button type="button" className="txtbtn txtbtn--danger" onClick={() => set("defaultImageUrl", "")}>{t("tmi.f.remove")}</button>
                  ) : null}
                </div>
              </div>
            </section>
          ) : null}

          <section className="tm__sec" aria-labelledby={id("s3")}>
            <h3 className="tm__sec-title" id={id("s3")}>{t("tmw.sec.limits")}</h3>
            {field("precautions", t("tmw.f.precautions"), <textarea className="input tm__textarea" rows={3} maxLength={5000} value={form.precautions} onChange={(e) => set("precautions", e.target.value)} {...aria("precautions")} />)}
            {data.schema.conflicts ? (
              <fieldset className="tm__pick">
                <legend className="field__label">{t("tmw.f.conflicts")}</legend>
                <p className="field__hint">{t("tmw.f.conflictsHint")}</p>
                <input className="input" type="search" value={pick} onChange={(e) => setPick(e.target.value)} placeholder={t("tmw.f.conflictsSearch")} aria-label={t("tmw.f.conflictsSearch")} autoComplete="off" />
                {candidates.length === 0 ? (
                  <p className="tm__alias-empty">{t("tmw.f.conflictsNone")}</p>
                ) : (
                  <ul className="tm__pick-list">
                    {candidates.map((c) => (
                      <li key={c.id}>
                        <label className="tm__check">
                          <input
                            type="checkbox"
                            checked={form.conflictIds.includes(c.id)}
                            onChange={(e) => set("conflictIds", e.target.checked ? [...form.conflictIds, c.id] : form.conflictIds.filter((x) => x !== c.id))}
                          />
                          <span translate="no">{c.name}</span>
                        </label>
                      </li>
                    ))}
                  </ul>
                )}
              </fieldset>
            ) : null}
          </section>
        </div>

        <div className="tm__alias-foot tm__form-foot">
          {general ? <p className="field__error tm__form-error" role="alert">{general}</p> : <span />}
          <span className="tm__form-btns">
            <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("tmw.cancel")}</button>
            <button type="submit" className="btn btn--primary" disabled={busy}>{busy ? t("tmw.saving") : t("tmw.save")}</button>
          </span>
        </div>
      </form>
    </dialog>,
    document.body,
  );
}
