"use client";

import { useEffect, useId, useMemo, useRef, useState, type FormEvent } from "react";
import Icon from "../Icon";
import ConfirmDialog, { type ConfirmSpec } from "../hospitals/ConfirmDialog";
import { Notice, Pill } from "../hospitals/parts";
import DeleteDoctorDialog from "./DeleteDoctorDialog";
import DoctorPhoto from "./DoctorPhoto";
import {
  deleteDoctor,
  editDoctor,
  fetchDoctor,
  fetchDoctorTreatmentOptions,
  retireDoctor,
  type DoctorFull,
  type DoctorHospitalOption,
  type DoctorTreatmentOption,
} from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { DOCTOR_PHOTO_ACCEPT, SPECIALTY_CHOICES, actionFailureKey, editFailure, validateDoctorForm, type DoctorFormErrors } from "@/lib/doctors";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Load = "loading" | "ready" | "error";
type Opts = { state: "loading" | "ready" | "error"; hospitalId: number; items: DoctorTreatmentOption[] };

export type DoctorDialogResult = { kind: "saved" | "retired" | "deleted"; name: string };

/**
 * Edit one doctor in a centred dialog (a bottom sheet on a phone). `features` decide what the dialog may do:
 * a retired doctor, or an account without write access, sees the same fields read-only.
 */
export default function DoctorEditDialog({
  doctorId,
  hospitals,
  isSuper,
  canWrite,
  canDelete,
  onDone,
  onClose,
}: {
  doctorId: number;
  hospitals: DoctorHospitalOption[];
  isSuper: boolean;
  canWrite: boolean;
  canDelete: boolean;
  onDone: (r: DoctorDialogResult) => void;
  onClose: () => void;
}) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const fileRef = useRef<HTMLInputElement>(null);
  const titleId = useId();
  const [load, setLoad] = useState<Load>("loading");
  const [doc, setDoc] = useState<DoctorFull | null>(null);
  const [attempt, setAttempt] = useState(0);

  const [name, setName] = useState("");
  const [hospitalId, setHospitalId] = useState(0);
  const [specialty, setSpecialty] = useState("");
  const [qualification, setQualification] = useState("");
  const [selected, setSelected] = useState<number[]>([]);
  const [picked, setPicked] = useState<{ file: File; url: string } | null>(null);
  const photo = picked?.file ?? null;
  const preview = picked?.url ?? "";
  const urlRef = useRef("");
  const seen = useRef(new WeakSet<HTMLDetailsElement>());
  const [opts, setOpts] = useState<Opts>({ state: "loading", hospitalId: 0, items: [] });
  const [optsTry, setOptsTry] = useState(0);
  const [errors, setErrors] = useState<DoctorFormErrors>({});
  const [formError, setFormError] = useState<MessageKey | null>(null);
  const [saving, setSaving] = useState(false);
  const [retireAsk, setRetireAsk] = useState(false);
  const [deleteAsk, setDeleteAsk] = useState(false);
  const [discardAsk, setDiscardAsk] = useState(false);
  const [acting, setActing] = useState(false);
  const [actError, setActError] = useState<MessageKey | null>(null);

  useEffect(() => {
    const dialog = ref.current;
    if (dialog && !dialog.open) dialog.showModal();
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchDoctor(doctorId, controller.signal);
      if (controller.signal.aborted) return;
      if (!res.ok) {
        if (res.status === 401) window.location.assign(PHP_ADMIN_URL);
        else setLoad("error");
        return;
      }
      const d = res.data;
      setDoc(d);
      setName(d.name);
      setHospitalId(d.hospitalId);
      setSpecialty(d.specialty);
      setQualification(d.qualification);
      setSelected(d.treatments.map((x) => x.id));
      setLoad("ready");
    };
    void run();
    return () => controller.abort();
  }, [doctorId, attempt]);

  // The treatments the chosen hospital offers.
  useEffect(() => {
    if (load !== "ready" || hospitalId <= 0) return;
    const controller = new AbortController();
    const run = async () => {
      setOpts((o) => ({ ...o, state: "loading", hospitalId }));
      const res = await fetchDoctorTreatmentOptions(hospitalId, controller.signal);
      if (controller.signal.aborted) return;
      setOpts(res.ok ? { state: "ready", hospitalId, items: res.data } : { state: "error", hospitalId, items: [] });
    };
    void run();
    return () => controller.abort();
  }, [load, hospitalId, optsTry]);

  useEffect(
    () => () => {
      if (urlRef.current) URL.revokeObjectURL(urlRef.current);
    },
    [],
  );

  const resigned = doc?.status === "resigned";
  const editable = canWrite && !resigned && doc !== null;
  const originalHospital = doc?.hospitalId ?? 0;
  const originalIds = useMemo(() => (doc ? doc.treatments.map((x) => x.id) : []), [doc]);

  const specialtyChoices = useMemo(() => {
    const list: string[] = [...SPECIALTY_CHOICES];
    if (doc && doc.specialty !== "" && !list.includes(doc.specialty)) list.push(doc.specialty);
    return list;
  }, [doc]);

  const groups = useMemo(() => {
    const map = new Map<string, DoctorTreatmentOption[]>();
    for (const item of opts.items) {
      const key = item.category;
      const bucket = map.get(key);
      if (bucket) bucket.push(item);
      else map.set(key, [item]);
    }
    return Array.from(map.entries());
  }, [opts.items]);

  const changeHospital = (next: number) => {
    if (next === hospitalId) return;
    setHospitalId(next);
    // The treatments belong to the hospital: switching away clears them, switching back restores what is stored.
    setSelected(next === originalHospital ? originalIds : []);
    setErrors((e) => ({ ...e, hospitalId: undefined }));
  };
  const toggle = (id: number) => setSelected((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));

  const dirty =
    doc !== null &&
    (name.trim() !== doc.name ||
      hospitalId !== doc.hospitalId ||
      specialty !== doc.specialty ||
      qualification.trim() !== doc.qualification.trim() ||
      photo !== null ||
      selected.length !== originalIds.length ||
      selected.some((id) => !originalIds.includes(id)));

  const pickPhoto = (file: File | null) => {
    if (!file) return;
    const probe = validateDoctorForm({ name: "x", hospitalId: 1, specialty: "", qualification: "" }, "", { name: file.name, type: file.type, size: file.size });
    if (probe.photo) {
      setErrors((e) => ({ ...e, photo: probe.photo }));
      return;
    }
    setErrors((e) => ({ ...e, photo: undefined }));
    if (urlRef.current) URL.revokeObjectURL(urlRef.current);
    urlRef.current = URL.createObjectURL(file);
    setPicked({ file, url: urlRef.current });
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (!doc || !editable || saving) return;
    const found = validateDoctorForm({ name, hospitalId, specialty, qualification }, doc.specialty, photo ? { name: photo.name, type: photo.type, size: photo.size } : null);
    setErrors(found);
    setFormError(null);
    if (Object.keys(found).length > 0) {
      setFormError("d.e.invalid");
      return;
    }
    setSaving(true);
    const res = await editDoctor(doc.id, { name: name.trim(), hospitalId, specialty: specialty.trim(), qualification: qualification.trim(), treatmentIds: selected }, photo);
    setSaving(false);
    if (res.ok) {
      onDone({ kind: "saved", name: res.data.name });
      return;
    }
    if (res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    const f = editFailure(res);
    setFormError(f.key);
    if (f.field) setErrors({ [f.field]: f.key });
  };

  const requestClose = () => {
    if (dirty && editable) setDiscardAsk(true);
    else ref.current?.close();
  };

  const retire = async () => {
    if (!doc || acting) return;
    setActing(true);
    setActError(null);
    const res = await retireDoctor(doc.id);
    setActing(false);
    if (res.ok) {
      onDone({ kind: "retired", name: doc.name });
      return;
    }
    if (res.status === 401) window.location.assign(PHP_ADMIN_URL);
    else setActError(actionFailureKey(res));
  };
  const remove = async () => {
    if (!doc || acting) return;
    setActing(true);
    setActError(null);
    const res = await deleteDoctor(doc.id);
    setActing(false);
    if (res.ok) {
      onDone({ kind: "deleted", name: doc.name });
      return;
    }
    if (res.status === 401) window.location.assign(PHP_ADMIN_URL);
    else setActError(actionFailureKey(res));
  };

  const retireSpec: ConfirmSpec | null =
    retireAsk && doc
      ? { title: t("d.rt.title"), name: doc.name, body: t("d.rt.body"), confirmLabel: t("d.rt.confirm"), cancelLabel: t("d.ed.cancel"), focus: "cancel", confirmTone: "reject", busyLabel: t("d.ed.saving") }
      : null;

  const hospitalName = hospitals.find((h) => h.id === hospitalId)?.name ?? doc?.hospitalName ?? "";
  const showPhoto = preview !== "";
  const wide = typeof window !== "undefined" && window.matchMedia("(min-width: 640px)").matches;

  const err = (key: keyof DoctorFormErrors) => (errors[key] ? <p className="dc-err" role="alert">{t(errors[key] as MessageKey)}</p> : null);

  const discardSpec: ConfirmSpec | null = discardAsk
    ? { title: t("d.ed.discard.title"), body: t("d.ed.discard.body"), confirmLabel: t("d.ed.discard.confirm"), cancelLabel: t("d.ed.discard.keep"), focus: "cancel", confirmTone: "reject" }
    : null;

  return (
    <>
      <dialog
        ref={ref}
        className="dialog dc-dialog"
        aria-labelledby={titleId}
        onCancel={(e) => {
          if (saving || acting) e.preventDefault();
          else if (dirty && editable) {
            e.preventDefault();
            setDiscardAsk(true);
          }
        }}
        onClose={onClose}
        onClick={(e) => {
          if (e.target === e.currentTarget && !saving && !acting && !dirty) onClose();
        }}
      >
        <form className="dc-dialog__form" onSubmit={(e) => void submit(e)} noValidate>
          <div className="dc-dialog__head">
            <h2 id={titleId} className="dialog__title">{t(editable ? "d.ed.title" : "d.ed.titleView")}</h2>
            {resigned ? <Pill tone="off">{t("d.status.resigned")}</Pill> : null}
            <button type="button" className="ibtn dialog__close" onClick={requestClose} disabled={saving || acting} aria-label={t("m.close")} title={t("m.close")}>
              <Icon name="close" />
            </button>
          </div>

          <div className="dc-dialog__body">
            {load === "loading" ? <p className="dc-dialog__state" role="status" aria-busy="true">{t("d.ed.loading")}</p> : null}
            {load === "error" ? (
              <div className="dc-dialog__state" role="alert">
                <p>{t("d.ed.loadFail")}</p>
                <button type="button" className="btn btn--ghost" onClick={() => { setLoad("loading"); setAttempt((n) => n + 1); }}>{t("d.ro.retry")}</button>
              </div>
            ) : null}
            {load === "ready" && doc ? (
              <>
                {resigned ? <Notice>{t("d.ed.readonly")}</Notice> : null}
                {formError ? <Notice tone="warn" icon="alert">{t(formError)}</Notice> : null}
                <div className="dc-grid">
                  <div className="dc-col">
                    <div className="field">
                      <span className="field__label">{t("d.ed.photo")}</span>
                      <div className="dc-photo-row">
                        {showPhoto ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img className="dc-photo dc-photo--lg" src={preview} alt="" />
                        ) : (
                          <DoctorPhoto name={name || doc.name} url={doc.photoUrl} size="lg" />
                        )}
                        <div className="dc-photo-side">
                          {editable ? (
                            <>
                              <input ref={fileRef} type="file" accept={DOCTOR_PHOTO_ACCEPT} hidden onChange={(e) => { pickPhoto(e.target.files?.[0] ?? null); e.target.value = ""; }} />
                              <button type="button" className="btn btn--ghost" onClick={() => fileRef.current?.click()} disabled={saving}>
                                {t(doc.photoUrl || photo ? "d.ed.photoChange" : "d.ed.photoPick")}
                              </button>
                              <p className="dc-help">{photo ? t("d.ed.photoNew", { name: photo.name }) : t("d.ed.photoHelp")}</p>
                            </>
                          ) : null}
                        </div>
                      </div>
                      {err("photo")}
                    </div>

                    <label className="field">
                      <span className="field__label">{t("d.ed.name")}</span>
                      <input className="input" value={name} maxLength={100} disabled={!editable || saving} aria-invalid={errors.name ? true : undefined} autoComplete="off" onChange={(e) => setName(e.target.value)} />
                      {err("name")}
                    </label>

                    <label className="field">
                      <span className="field__label">{t("d.ed.hospital")}</span>
                      {isSuper && editable ? (
                        <select className="sel sel--field" value={hospitalId} disabled={saving} aria-invalid={errors.hospitalId ? true : undefined} onChange={(e) => changeHospital(Number(e.target.value) || 0)}>
                          {hospitals.some((h) => h.id === hospitalId) ? null : <option value={hospitalId}>{hospitalName}</option>}
                          {hospitals.map((h) => (
                            <option key={h.id} value={h.id}>{h.name}</option>
                          ))}
                        </select>
                      ) : (
                        <input className="input" value={hospitalName} disabled readOnly />
                      )}
                      {isSuper && editable && hospitalId !== originalHospital ? <p className="dc-help">{t("d.ed.hospitalMove")}</p> : null}
                      {err("hospitalId")}
                    </label>

                    <label className="field">
                      <span className="field__label">{t("d.ed.specialty")}</span>
                      <select className="sel sel--field" value={specialty} disabled={!editable || saving} aria-invalid={errors.specialty ? true : undefined} onChange={(e) => setSpecialty(e.target.value)}>
                        <option value="">{t("d.ed.specialtyNone")}</option>
                        {specialtyChoices.map((s) => (
                          <option key={s} value={s}>{s}</option>
                        ))}
                      </select>
                      {err("specialty")}
                    </label>
                  </div>

                  <div className="dc-col">
                    <label className="field">
                      <span className="field__label">{t("d.ed.qualification")}</span>
                      <textarea className="input dc-textarea" rows={6} value={qualification} maxLength={2000} disabled={!editable || saving} aria-invalid={errors.qualification ? true : undefined} onChange={(e) => setQualification(e.target.value)} />
                      <span className="dc-count">{t("d.ed.qualCount", { n: Array.from(qualification).length.toLocaleString("en-US") })}</span>
                      {err("qualification")}
                    </label>

                    <div className="field">
                      <span className="field__label">
                        {t("d.ed.treatments")} <span className="dc-count">{t("d.ed.treatmentsCount", { n: opts.state === "ready" && opts.hospitalId === hospitalId ? selected.filter((id) => opts.items.some((x) => x.id === id)).length : selected.length })}</span>
                      </span>
                      {opts.state === "loading" ? <p className="dc-help" role="status">{t("d.ed.treatmentsLoading")}</p> : null}
                      {opts.state === "error" ? (
                        <p className="dc-help" role="alert">
                          {t("d.ed.treatmentsFail")}{" "}
                          <button type="button" className="txtbtn" onClick={() => setOptsTry((n) => n + 1)}>{t("d.ed.treatmentsRetry")}</button>
                        </p>
                      ) : null}
                      {opts.state === "ready" && opts.hospitalId === hospitalId ? (
                        groups.length === 0 ? (
                          <p className="dc-help">{t("d.ed.treatmentsNone")}</p>
                        ) : (
                          <div className="dc-treat">
                            {groups.map(([category, items]) => {
                              const count = items.filter((x) => selected.includes(x.id)).length;
                              return (
                                <details key={`${hospitalId}-${category}`} className="dc-treat__group" ref={(el) => { if (el && !seen.current.has(el)) { seen.current.add(el); el.open = wide || count > 0; } }}>
                                  <summary>
                                    <span>{category || t("h.find.treatmentOther")}</span>
                                    {count > 0 ? <span className="dc-treat__n">{count}</span> : null}
                                  </summary>
                                  <ul className="dc-treat__list">
                                    {items.map((item) => {
                                      const on = selected.includes(item.id);
                                      return (
                                        <li key={item.id}>
                                          <label className={on ? "dc-opt is-on" : "dc-opt"}>
                                            <input type="checkbox" checked={on} disabled={!editable || saving} onChange={() => toggle(item.id)} />
                                            <span>{item.name}</span>
                                          </label>
                                        </li>
                                      );
                                    })}
                                  </ul>
                                </details>
                              );
                            })}
                          </div>
                        )
                      ) : null}
                      {opts.state === "ready" ? <p className="dc-help">{t("d.ed.treatmentsHelp")}</p> : null}
                    </div>
                  </div>
                </div>

                {canWrite && (!resigned || canDelete) ? (
                  <section className="dc-danger" aria-labelledby={`${titleId}-dz`}>
                    <h3 id={`${titleId}-dz`}>{t("d.dz.title")}</h3>
                    {actError && !retireAsk && !deleteAsk ? <Notice tone="warn" icon="alert">{t(actError)}</Notice> : null}
                    {!resigned ? (
                      <div className="dc-danger__row">
                        <div>
                          <strong>{t("d.dz.retireTitle")}</strong>
                          <p className="dc-help">{t("d.dz.retireBody")}</p>
                        </div>
                        <button type="button" className="btn btn--ghost btn--reject" disabled={saving} onClick={() => { setActError(null); setRetireAsk(true); }}>{t("d.dz.retire")}</button>
                      </div>
                    ) : null}
                    {resigned && canDelete ? (
                      <div className="dc-danger__row">
                        <div>
                          <strong>{t("d.dz.deleteTitle")}</strong>
                          <p className="dc-help">{t("d.dz.deleteBody")}</p>
                        </div>
                        <button type="button" className="btn btn--ghost btn--reject" onClick={() => { setActError(null); setDeleteAsk(true); }}>{t("d.dz.delete")}</button>
                      </div>
                    ) : null}
                  </section>
                ) : null}
              </>
            ) : null}
          </div>

          <div className="dc-dialog__foot">
            {editable && dirty ? <span className="dc-dirty">{t("d.ed.unsaved")}</span> : null}
            <button type="button" className="btn btn--ghost" onClick={requestClose} disabled={saving || acting}>{editable ? t("d.ed.cancel") : t("d.ed.close")}</button>
            {editable ? (
              <button type="submit" className="btn btn--primary" disabled={saving || !dirty || load !== "ready"}>
                {saving ? t("d.ed.saving") : t("d.ed.save")}
              </button>
            ) : null}
          </div>
        </form>
      </dialog>
      <ConfirmDialog
        spec={discardSpec}
        onConfirm={() => { setDiscardAsk(false); ref.current?.close(); }}
        onClose={() => setDiscardAsk(false)}
      />
      <ConfirmDialog
        spec={retireSpec}
        busy={acting}
        error={actError && retireAsk ? t(actError) : null}
        onConfirm={() => void retire()}
        onClose={() => { if (!acting) setRetireAsk(false); }}
      />
      <DeleteDoctorDialog
        name={deleteAsk && doc ? doc.name : null}
        busy={acting}
        error={actError && deleteAsk ? t(actError) : null}
        onConfirm={() => void remove()}
        onClose={() => { if (!acting) setDeleteAsk(false); }}
      />
    </>
  );
}
