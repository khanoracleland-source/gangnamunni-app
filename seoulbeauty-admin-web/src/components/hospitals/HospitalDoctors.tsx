"use client";

import { useEffect, useRef, useState, type ChangeEvent } from "react";
import Icon from "../Icon";
import { addHospitalDoctors, hospitalDoctors, type HospitalDoctor, type HospitalDoctorSet } from "@/lib/api";
import {
  DOCTOR_FIELDS,
  DOCTOR_LIMIT,
  EXPERIENCE_MAX,
  NAME_MAX,
  PHOTO_ACCEPT,
  SPECIALTY_MAX,
  TEXT_MAX,
  addFailure,
  blankDoctor,
  buildRequest,
  checkDoctorPhoto,
  hasName,
  validateRow,
  type DoctorDraft,
  type DoctorField,
  type RowErrors,
} from "@/lib/hospitalDoctors";
import { legacyDoctorsUrl, mediaUrl } from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Status = { tone: "ok" | "err"; text: string } | null;

type Props = {
  hospitalId: number;
  /** The card has been opened at least once: the doctors are read then, not before. */
  opened: boolean;
  /** The stored doctors were read or changed. */
  onChange: (info: { count: number }) => void;
  /** The server says the admin session ended. */
  onUnauthorized: () => void;
};

/**
 * The body of the "add doctors" card: several doctor rows with an optional photo each, sent in one request, and the
 * list of registered doctors below. It has its own save button and does not touch the hospital form's own changes.
 */
export default function HospitalDoctors({ hospitalId, opened, onChange, onUnauthorized }: Props) {
  const t = useT();
  // null = not answered yet.
  const [load, setLoad] = useState<{ set: HospitalDoctorSet } | { failed: true } | null>(null);
  const [tries, setTries] = useState(0);
  const nextKey = useRef(2);
  const [rows, setRows] = useState<DoctorDraft[]>(() => [blankDoctor(1)]);
  const [errors, setErrors] = useState<Record<number, RowErrors>>({});
  const [formError, setFormError] = useState<MessageKey | null>(null);
  const [uploading, setUploading] = useState(false);
  const [status, setStatus] = useState<Status>(null);
  // The input to focus once the state that shows its error is drawn.
  const [focusReq, setFocusReq] = useState<{ id: string } | null>(null);

  // Object URLs of the photo previews, by row: each is released when its photo is dropped and when the card goes away.
  const held = useRef(new Set<string>());
  const [previews, setPreviews] = useState<Record<number, string>>({});
  const upload = useRef<AbortController | null>(null);
  const onChangeRef = useRef(onChange);
  const onUnauthorizedRef = useRef(onUnauthorized);
  useEffect(() => {
    onChangeRef.current = onChange;
    onUnauthorizedRef.current = onUnauthorized;
  });

  useEffect(() => {
    const urls = held.current;
    return () => {
      urls.forEach((url) => URL.revokeObjectURL(url));
      urls.clear();
      upload.current?.abort();
    };
  }, []);

  useEffect(() => {
    if (!opened) return;
    const controller = new AbortController();
    void hospitalDoctors(hospitalId, controller.signal).then((res) => {
      if (controller.signal.aborted) return;
      if (res.ok) {
        setLoad({ set: res.data });
        onChangeRef.current({ count: res.data.count });
      } else if (res.status === 401) {
        onUnauthorizedRef.current();
      } else {
        setLoad({ failed: true });
      }
    });
    return () => controller.abort();
  }, [opened, hospitalId, tries]);

  useEffect(() => {
    if (!focusReq) return;
    document.getElementById(focusReq.id)?.focus();
  }, [focusReq]);

  const retry = () => {
    setLoad(null);
    setTries((n) => n + 1);
  };

  const inputId = (key: number, field: DoctorField): string => `hdoc-${hospitalId}-${key}-${field}`;
  const releasePreview = (key: number) => {
    const url = previews[key];
    if (!url) return;
    URL.revokeObjectURL(url);
    held.current.delete(url);
    setPreviews((prev) => {
      const { [key]: _gone, ...rest } = prev;
      void _gone;
      return rest;
    });
  };

  const set = load && "set" in load ? load.set : null;
  const limit = set ? set.addLimit : DOCTOR_LIMIT;
  const busy = uploading || set === null;
  const named = rows.filter(hasName).length;

  const edit = (key: number, field: Exclude<DoctorField, "photo">, value: string) => {
    setRows((prev) => prev.map((row) => (row.key === key ? { ...row, [field]: value } : row)));
    setStatus(null);
    setFormError(null);
    setErrors((prev) => {
      if (!prev[key]?.[field]) return prev;
      const next = { ...prev[key] };
      delete next[field];
      return { ...prev, [key]: next };
    });
  };

  const addRow = () => {
    if (rows.length >= limit) return;
    setRows((prev) => [...prev, blankDoctor(nextKey.current++)]);
    setStatus(null);
  };

  const removeRow = (key: number) => {
    releasePreview(key);
    setRows((prev) => {
      const rest = prev.filter((row) => row.key !== key);
      return rest.length > 0 ? rest : [blankDoctor(nextKey.current++)];
    });
    setErrors((prev) => {
      const { [key]: _dropped, ...rest } = prev;
      void _dropped;
      return rest;
    });
    setStatus(null);
    setFormError(null);
  };

  const onPhoto = (key: number, e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = "";
    if (!file) return;
    setStatus(null);
    const problem = checkDoctorPhoto(file);
    if (problem) {
      setErrors((prev) => ({ ...prev, [key]: { ...prev[key], photo: problem === "too_large" ? "h.e.doc.err.photoLarge" : "h.e.doc.err.photoType" } }));
      return;
    }
    releasePreview(key);
    const url = URL.createObjectURL(file);
    held.current.add(url);
    setPreviews((prev) => ({ ...prev, [key]: url }));
    setRows((prev) => prev.map((row) => (row.key === key ? { ...row, photo: file } : row)));
    setErrors((prev) => {
      if (!prev[key]?.photo) return prev;
      const next = { ...prev[key] };
      delete next.photo;
      return { ...prev, [key]: next };
    });
  };

  const clearPhoto = (key: number) => {
    releasePreview(key);
    setRows((prev) => prev.map((row) => (row.key === key ? { ...row, photo: null } : row)));
    setErrors((prev) => {
      if (!prev[key]?.photo) return prev;
      const next = { ...prev[key] };
      delete next.photo;
      return { ...prev, [key]: next };
    });
  };

  const save = async () => {
    if (busy) return;
    setStatus(null);
    setFormError(null);
    if (named === 0) {
      setErrors({});
      setFormError("h.e.doc.err.none");
      setFocusReq({ id: inputId(rows[0].key, "name") });
      return;
    }
    const found: Record<number, RowErrors> = {};
    let first: string | null = null;
    for (const row of rows) {
      if (!hasName(row)) continue;
      const rowErrors = validateRow(row);
      if (Object.keys(rowErrors).length === 0) continue;
      found[row.key] = rowErrors;
      if (first === null) {
        const field = DOCTOR_FIELDS.find((f) => rowErrors[f]);
        if (field) first = inputId(row.key, field);
      }
    }
    setErrors(found);
    if (first !== null) {
      setFocusReq({ id: first });
      setStatus({ tone: "err", text: t("h.e.doc.err.short") });
      return;
    }

    const request = buildRequest(rows);
    const controller = new AbortController();
    upload.current = controller;
    setUploading(true);
    const res = await addHospitalDoctors(hospitalId, request.doctors, request.photos, controller.signal);
    if (controller.signal.aborted) return;
    upload.current = null;
    setUploading(false);
    if (res.ok) {
      held.current.forEach((url) => URL.revokeObjectURL(url));
      held.current.clear();
      setPreviews({});
      setRows([blankDoctor(nextKey.current++)]);
      setErrors({});
      setLoad({ set: res.data });
      setStatus({ tone: "ok", text: t("h.e.doc.saved", { n: res.data.added }) });
      onChangeRef.current({ count: res.data.count });
      return;
    }
    if (res.status === 401) {
      onUnauthorizedRef.current();
      return;
    }
    const failure = addFailure(res);
    const rowKey = failure.row !== null ? request.rowKeys[failure.row] : undefined;
    if (rowKey !== undefined && failure.field !== null) {
      setErrors({ [rowKey]: { [failure.field]: failure.key } });
      setFocusReq({ id: inputId(rowKey, failure.field) });
      setStatus({ tone: "err", text: t("h.e.doc.err.short") });
      return;
    }
    setErrors({});
    setFormError(failure.key);
    setStatus({ tone: "err", text: t("h.e.doc.err.short") });
  };

  if (load === null) return <p className="field__hint" role="status">{t("h.e.doc.loading")}</p>;
  if (set === null) {
    return (
      <div className="hdoc__retry">
        <p className="field__error" role="alert">{t("h.e.doc.loadFail")}</p>
        <button type="button" className="btn btn--ghost" onClick={retry}>
          <Icon name="refresh" size={18} />
          <span>{t("h.e.doc.retry")}</span>
        </button>
      </div>
    );
  }

  const fieldError = (key: number, field: DoctorField): MessageKey | undefined => errors[key]?.[field];
  const describe = (key: number, field: DoctorField, hint: boolean): string | undefined => {
    const ids = [hint ? `${inputId(key, field)}-hint` : "", fieldError(key, field) ? `${inputId(key, field)}-err` : ""].filter(Boolean);
    return ids.length > 0 ? ids.join(" ") : undefined;
  };
  const errorLine = (key: number, field: DoctorField) => {
    const problem = fieldError(key, field);
    return problem ? <p id={`${inputId(key, field)}-err`} className="field__error" role="alert">{t(problem, { max: field === "bio" || field === "qualification" ? TEXT_MAX : field === "specialty" ? SPECIALTY_MAX : field === "name" ? NAME_MAX : EXPERIENCE_MAX })}</p> : null;
  };

  return (
    <div className="hdoc">
      <p className="field__hint">{t("h.e.doc.intro")}</p>

      <div className="hdoc__rows">
        {rows.map((row, i) => {
          const preview = previews[row.key];
          return (
            <div key={row.key} className="hdoc__row" role="group" aria-labelledby={`hdoc-${hospitalId}-${row.key}-t`}>
              <div className="hdoc__rowhead">
                <Icon name="userPlus" size={18} />
                <h3 className="hdoc__rowtitle" id={`hdoc-${hospitalId}-${row.key}-t`}>{t("h.e.doc.row", { n: i + 1 })}</h3>
                <button type="button" className="btn btn--ghost hdoc__rm" onClick={() => removeRow(row.key)} disabled={uploading} aria-label={t("h.e.doc.remove", { n: i + 1 })}>
                  <Icon name="trash" size={18} />
                </button>
              </div>
              <div className="hdoc__grid">
                <div className="field hdoc__c-name">
                  <label className="field__label" htmlFor={inputId(row.key, "name")}>{t("h.e.doc.name")}</label>
                  <input
                    id={inputId(row.key, "name")}
                    className="input"
                    type="text"
                    maxLength={NAME_MAX}
                    placeholder={t("h.e.doc.name.ph")}
                    autoComplete="off"
                    value={row.name}
                    disabled={uploading}
                    aria-invalid={fieldError(row.key, "name") ? true : undefined}
                    aria-describedby={describe(row.key, "name", false)}
                    onChange={(e) => edit(row.key, "name", e.target.value)}
                  />
                  {errorLine(row.key, "name")}
                </div>
                <div className="field hdoc__c-spec">
                  <label className="field__label" htmlFor={inputId(row.key, "specialty")}>{t("h.e.doc.specialty")}</label>
                  <input
                    id={inputId(row.key, "specialty")}
                    className="input"
                    type="text"
                    maxLength={SPECIALTY_MAX}
                    placeholder={t("h.e.doc.specialty.ph")}
                    autoComplete="off"
                    value={row.specialty}
                    disabled={uploading}
                    aria-invalid={fieldError(row.key, "specialty") ? true : undefined}
                    aria-describedby={describe(row.key, "specialty", false)}
                    onChange={(e) => edit(row.key, "specialty", e.target.value)}
                  />
                  {errorLine(row.key, "specialty")}
                </div>
                <div className="field hdoc__c-exp">
                  <label className="field__label" htmlFor={inputId(row.key, "experience")}>{t("h.e.doc.experience")}</label>
                  <input
                    id={inputId(row.key, "experience")}
                    className="input"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={EXPERIENCE_MAX}
                    step={1}
                    placeholder={t("h.e.doc.experience.ph")}
                    value={row.experience}
                    disabled={uploading}
                    aria-invalid={fieldError(row.key, "experience") ? true : undefined}
                    aria-describedby={describe(row.key, "experience", false)}
                    onChange={(e) => edit(row.key, "experience", e.target.value)}
                  />
                  {errorLine(row.key, "experience")}
                </div>
                <div className="field hdoc__c-half">
                  <label className="field__label" htmlFor={inputId(row.key, "qualification")}>{t("h.e.doc.qualification")}</label>
                  <textarea
                    id={inputId(row.key, "qualification")}
                    className="input textarea hdoc__ta"
                    rows={3}
                    maxLength={TEXT_MAX}
                    placeholder={t("h.e.doc.qualification.ph")}
                    value={row.qualification}
                    disabled={uploading}
                    aria-invalid={fieldError(row.key, "qualification") ? true : undefined}
                    aria-describedby={describe(row.key, "qualification", false)}
                    onChange={(e) => edit(row.key, "qualification", e.target.value)}
                  />
                  {errorLine(row.key, "qualification")}
                </div>
                <div className="field hdoc__c-half">
                  <span className="field__label" id={`${inputId(row.key, "photo")}-l`}>{t("h.e.doc.photo")}</span>
                  <div className="hdoc__pickrow">
                    <label className={uploading ? "btn btn--ghost hdoc__pick hdoc__pick--off" : "btn btn--ghost hdoc__pick"}>
                      <input
                        id={inputId(row.key, "photo")}
                        type="file"
                        className="sr-only"
                        accept={PHOTO_ACCEPT}
                        disabled={uploading}
                        aria-labelledby={`${inputId(row.key, "photo")}-l`}
                        aria-describedby={describe(row.key, "photo", true)}
                        onChange={(e) => onPhoto(row.key, e)}
                      />
                      <span>{t("h.e.doc.pick")}</span>
                    </label>
                    {row.photo ? <span className="hdoc__fname" translate="no">{row.photo.name}</span> : <span className="hdoc__fname">{t("h.e.doc.noFile")}</span>}
                  </div>
                  <p className="field__hint" id={`${inputId(row.key, "photo")}-hint`}>{t("h.e.doc.photoHint")}</p>
                  {errorLine(row.key, "photo")}
                  {preview && row.photo ? (
                    <div className="hdoc__prevwrap">
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img className="hdoc__prev" src={preview} alt={t("h.e.doc.prevAlt", { name: row.name.trim() || String(i + 1) })} />
                      <button type="button" className="hdoc__prevrm" onClick={() => clearPhoto(row.key)} disabled={uploading} aria-label={t("h.e.doc.photoRemove")}>
                        <Icon name="close" size={16} />
                      </button>
                    </div>
                  ) : null}
                </div>
                <div className="field hdoc__c-full">
                  <label className="field__label" htmlFor={inputId(row.key, "bio")}>{t("h.e.doc.bio")}</label>
                  <textarea
                    id={inputId(row.key, "bio")}
                    className="input textarea hdoc__ta"
                    rows={3}
                    maxLength={TEXT_MAX}
                    placeholder={t("h.e.doc.bio.ph")}
                    value={row.bio}
                    disabled={uploading}
                    aria-invalid={fieldError(row.key, "bio") ? true : undefined}
                    aria-describedby={describe(row.key, "bio", false)}
                    onChange={(e) => edit(row.key, "bio", e.target.value)}
                  />
                  {errorLine(row.key, "bio")}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <p className="field__hint">{t("h.e.doc.skipHint")}</p>
      {formError ? <p className="field__error" role="alert">{t(formError, { max: limit })}</p> : null}

      <div className="hdoc__bar">
        <button type="button" className="btn btn--ghost" onClick={addRow} disabled={uploading || rows.length >= limit}>
          <Icon name="userPlus" size={18} />
          <span>{t("h.e.doc.add")}</span>
        </button>
        {rows.length >= limit ? <span className="field__hint">{t("h.e.doc.addFull", { max: limit })}</span> : null}
        <p className={status ? `hdoc__status hdoc__status--${status.tone}` : "hdoc__status"} role="status" aria-live="polite">
          {uploading ? t("h.e.doc.saving") : status ? status.text : ""}
        </p>
        <button type="button" className="btn btn--primary" onClick={() => void save()} disabled={busy}>
          {uploading ? t("h.e.doc.saving") : t("h.e.doc.save")}
        </button>
      </div>

      <div className="hdoc__saved">
        <h3 className="hdoc__rowtitle">{t("h.e.doc.registered")}</h3>
        {set.items.length > 0 ? (
          <ul className="hdoc__list">
            {set.items.map((doctor: HospitalDoctor) => {
              const photo = mediaUrl(doctor.photoUrl);
              return (
                <li key={doctor.id} className="hdoc__card">
                  {photo ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img className="hdoc__ph" src={photo} alt="" loading="lazy" decoding="async" />
                  ) : null}
                  <div className="hdoc__info">
                    <strong className="hdoc__dname" translate="no">{doctor.name}</strong>
                    <span className={doctor.specialty.trim() ? "hdoc__spec" : "hdoc__spec hdoc__spec--none"} translate="no">{doctor.specialty.trim() || t("h.e.doc.noSpecialty")}</span>
                    <span className="hdoc__small">{t("h.e.doc.years", { n: doctor.experience })}</span>
                    {doctor.qualification.trim() ? <span className="hdoc__small hdoc__text" translate="no">{doctor.qualification}</span> : null}
                    {doctor.bio.trim() ? <span className="hdoc__small hdoc__text hdoc__text--muted" translate="no">{doctor.bio}</span> : null}
                  </div>
                </li>
              );
            })}
          </ul>
        ) : (
          <p className="field__hint">{t("h.e.doc.empty")}</p>
        )}
        <a className="btn btn--ghost hdoc__manage" href={legacyDoctorsUrl(hospitalId)}>
          <Icon name="external" size={18} />
          <span>{t("h.e.doc.manage")}</span>
        </a>
      </div>
    </div>
  );
}

