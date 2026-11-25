"use client";

import { useEffect, useId, useMemo, useRef, useState, type FormEvent, type KeyboardEvent } from "react";
import Icon from "../Icon";
import ConfirmDialog, { type ConfirmSpec } from "../hospitals/ConfirmDialog";
import { Notice, Pill } from "../hospitals/parts";
import DeleteDoctorDialog from "./DeleteDoctorDialog";
import HospitalCombo from "../payments/HospitalCombo";
import DoctorPhoto from "./DoctorPhoto";
import DoctorTreatmentsTab, { type TreatmentOptions } from "./DoctorTreatmentsTab";
import {
  deleteDoctor,
  editDoctor,
  fetchDoctor,
  fetchDoctorTreatmentOptions,
  retireDoctor,
  setDoctorActive,
  type DoctorFull,
  type DoctorHospitalOption,
  type DoctorTreatmentOption,
} from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import {
  DOCTOR_PHOTO_ACCEPT,
  SPECIALTY_CHOICES,
  actionFailureKey,
  editFailure,
  newRow,
  openingOrder,
  rowFromTreatment,
  rowsChanged,
  rowsToInput,
  sortRows,
  validateDoctorForm,
  validateRows,
  type DoctorFormErrors,
  type TreatmentRow,
  type TreatmentRowErrors,
} from "@/lib/doctors";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Load = "loading" | "ready" | "error";

export type DoctorDialogResult = { kind: "saved" | "retired" | "deleted" | "deactivated" | "activated"; name: string };

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
  const [rows, setRows] = useState<TreatmentRow[]>([]);
  const [removed, setRemoved] = useState<number[]>([]);
  const [rowErrors, setRowErrors] = useState<Record<number, TreatmentRowErrors>>({});
  const [tab, setTab] = useState<"profile" | "treatments">("profile");
  const [picked, setPicked] = useState<{ file: File; url: string } | null>(null);
  const photo = picked?.file ?? null;
  const preview = picked?.url ?? "";
  const urlRef = useRef("");
  const [opts, setOpts] = useState<TreatmentOptions>({ state: "loading", hospitalId: 0, items: [] });
  const [optsTry, setOptsTry] = useState(0);
  const [errors, setErrors] = useState<DoctorFormErrors>({});
  const [formError, setFormError] = useState<MessageKey | null>(null);
  const [saving, setSaving] = useState(false);
  const [retireAsk, setRetireAsk] = useState(false);
  const [switchAsk, setSwitchAsk] = useState<"off" | "on" | null>(null);
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
      setRows(sortRows(d.treatments.map(rowFromTreatment), openingOrder(d.treatments)));
      setRemoved([]);
      setRowErrors({});
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
  const inactive = doc?.status === "inactive";
  const editable = canWrite && !resigned && doc !== null;
  const originalHospital = doc?.hospitalId ?? 0;
  const originalIds = useMemo(() => (doc ? doc.treatments.map((x) => x.id) : []), [doc]);
  const originalRows = useMemo(() => (doc ? sortRows(doc.treatments.map(rowFromTreatment), openingOrder(doc.treatments)) : []), [doc]);
  const activeRows = useMemo(() => rows.filter((r) => !removed.includes(r.id)), [rows, removed]);
  const selected = useMemo(() => activeRows.map((r) => r.id), [activeRows]);

  const specialtyChoices = useMemo(() => {
    const list: string[] = [...SPECIALTY_CHOICES];
    if (doc && doc.specialty !== "" && !list.includes(doc.specialty)) list.push(doc.specialty);
    return list;
  }, [doc]);

  const changeHospital = (next: number) => {
    if (next === hospitalId) return;
    setHospitalId(next);
    // The treatments belong to the hospital: switching away clears them, switching back restores what is stored.
    setRows(next === originalHospital ? originalRows : []);
    setRemoved([]);
    setRowErrors({});
    setErrors((e) => ({ ...e, hospitalId: undefined }));
  };
  const isStored = (id: number) => hospitalId === originalHospital && originalIds.includes(id);
  // A stored row stays in the table, struck through, until Save (so it can be undone); a row added just now simply goes.
  const dropRow = (id: number) => {
    if (isStored(id)) setRemoved((cur) => (cur.includes(id) ? cur : [...cur, id]));
    else setRows((cur) => cur.filter((r) => r.id !== id));
  };
  const undoRow = (id: number) => setRemoved((cur) => cur.filter((x) => x !== id));
  const toggle = (o: DoctorTreatmentOption) => {
    if (selected.includes(o.id)) dropRow(o.id);
    else if (removed.includes(o.id)) undoRow(o.id);
    else setRows((cur) => [newRow(o), ...cur]);
  };
  const patchRow = (id: number, patch: Partial<Pick<TreatmentRow, "level" | "cases" | "certification">>) => {
    setRows((cur) => cur.map((r) => (r.id === id ? { ...r, ...patch } : r)));
    setRowErrors((cur) => {
      if (!cur[id]) return cur;
      const rest = { ...cur };
      delete rest[id];
      return rest;
    });
  };

  const dirty =
    doc !== null &&
    (name.trim() !== doc.name ||
      hospitalId !== doc.hospitalId ||
      specialty !== doc.specialty ||
      qualification.trim() !== doc.qualification.trim() ||
      photo !== null ||
      selected.length !== originalIds.length ||
      selected.some((id) => !originalIds.includes(id)) ||
      rowsChanged(activeRows, doc.treatments));

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
    const rowFound = validateRows(activeRows);
    setErrors(found);
    setRowErrors(rowFound);
    setFormError(null);
    if (Object.keys(found).length > 0 || Object.keys(rowFound).length > 0) {
      const profileWrong = Object.keys(found).length > 0;
      setFormError(profileWrong ? "d.e.invalid" : "d.ex.fixFields");
      setTab(profileWrong ? "profile" : "treatments");
      return;
    }
    setSaving(true);
    const res = await editDoctor(doc.id, { name: name.trim(), hospitalId, specialty: specialty.trim(), qualification: qualification.trim(), treatmentIds: selected, treatments: rowsToInput(activeRows) }, photo);
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
    if (f.field) {
      setErrors({ [f.field]: f.key });
      setTab(f.field === "treatments" ? "treatments" : "profile");
    }
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
  const switchActive = async () => {
    if (!doc || acting || !switchAsk) return;
    const active = switchAsk === "on";
    setActing(true);
    setActError(null);
    const res = await setDoctorActive(doc.id, active);
    setActing(false);
    if (res.ok) {
      onDone({ kind: active ? "activated" : "deactivated", name: doc.name });
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

  const switchSpec: ConfirmSpec | null =
    switchAsk && doc
      ? {
          title: t(switchAsk === "off" ? "d.ac.offTitle" : "d.ac.onTitle"),
          name: doc.name,
          body: t(switchAsk === "off" ? "d.ac.offBody" : "d.ac.onBody"),
          confirmLabel: t(switchAsk === "off" ? "d.ac.offConfirm" : "d.ac.onConfirm"),
          cancelLabel: t("d.ed.cancel"),
          focus: "cancel",
          busyLabel: t("d.ed.saving"),
        }
      : null;

  const hospitalName = hospitals.find((h) => h.id === hospitalId)?.name ?? doc?.hospitalName ?? "";
  const showPhoto = preview !== "";
  const tabKeys = (e: KeyboardEvent<HTMLDivElement>) => {
    if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
    e.preventDefault();
    const next = tab === "profile" ? "treatments" : "profile";
    setTab(next);
    requestAnimationFrame(() => document.getElementById(`${titleId}-t-${next}`)?.focus());
  };

  const err = (key: keyof DoctorFormErrors) => (errors[key] ? <p className="dc-err" role="alert">{t(errors[key] as MessageKey)}</p> : null);

  const discardSpec: ConfirmSpec | null = discardAsk
    ? { title: t("d.ed.discard.title"), body: t("d.ed.discard.body"), confirmLabel: t("d.ed.discard.confirm"), cancelLabel: t("d.ed.discard.keep"), focus: "cancel", confirmTone: "reject" }
    : null;

  return (
    <>
      <dialog
        ref={ref}
        className="dialog dc-dialog dc-dialog--b"
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
            {inactive ? <Pill tone="warn">{t("d.status.inactive")}</Pill> : null}
            <button type="button" className="ibtn dialog__close" onClick={requestClose} disabled={saving || acting} aria-label={t("m.close")} title={t("m.close")}>
              <Icon name="close" />
            </button>
          </div>

          {load === "ready" && doc ? (
            <div className="dc-tabs" role="tablist" aria-label={t(editable ? "d.ed.title" : "d.ed.titleView")} onKeyDown={tabKeys}>
              {(["profile", "treatments"] as const).map((k) => (
                <button
                  key={k}
                  type="button"
                  role="tab"
                  id={`${titleId}-t-${k}`}
                  aria-selected={tab === k}
                  aria-controls={`${titleId}-p-${k}`}
                  tabIndex={tab === k ? 0 : -1}
                  className={tab === k ? "dc-tab is-on" : "dc-tab"}
                  onClick={() => setTab(k)}
                >
                  {t(k === "profile" ? "d.ex.tabProfile" : "d.ex.tabTreatments")}
                  {k === "treatments" ? <span className="dc-tab__n">{selected.length}</span> : null}
                </button>
              ))}
            </div>
          ) : null}

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
                {inactive ? <Notice>{t("d.ed.inactiveNote")}</Notice> : null}
                {formError ? <Notice tone="warn" icon="alert">{t(formError)}</Notice> : null}
                <div role="tabpanel" id={`${titleId}-p-profile`} aria-labelledby={`${titleId}-t-profile`} hidden={tab !== "profile"} className="dc-pane dc-pane--profile">
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

                    <div className="dc-hosp">
                      {isSuper && editable ? (
                        <HospitalCombo
                          hospitals={originalHospital <= 0 || hospitals.some((h) => h.id === originalHospital) ? hospitals : [{ id: originalHospital, name: doc.hospitalName }, ...hospitals]}
                          value={hospitalId}
                          onChange={changeHospital}
                          label={t("d.ed.hospital")}
                          placeholder={t("d.hc.search")}
                          clearLabel={t("d.hc.clear")}
                          noneLabel={t("d.hc.none")}
                          allLabel={null}
                          disabled={saving}
                          invalid={!!errors.hospitalId}
                        />
                      ) : (
                        <label className="field">
                          <span className="field__label">{t("d.ed.hospital")}</span>
                          <input className="input" value={hospitalName} disabled readOnly />
                        </label>
                      )}
                      {isSuper && editable && hospitalId !== originalHospital && hospitalId > 0 ? <p className="dc-move" role="status">{t("d.ed.hospitalMove")}</p> : null}
                      {err("hospitalId")}
                    </div>

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
                  </div>
                </div>

                {canWrite && (!resigned || canDelete) ? (
                  <section className="dc-danger" aria-labelledby={`${titleId}-dz`}>
                    <h3 id={`${titleId}-dz`}>{t("d.dz.title")}</h3>
                    {actError && !retireAsk && !deleteAsk && !switchAsk ? <Notice tone="warn" icon="alert">{t(actError)}</Notice> : null}
                    {!resigned ? (
                      <div className="dc-danger__row">
                        <div>
                          <strong>{t(inactive ? "d.dz.activateTitle" : "d.dz.deactivateTitle")}</strong>
                          <p className="dc-help">{t(inactive ? "d.dz.activateBody" : "d.dz.deactivateBody")}</p>
                        </div>
                        <button type="button" className="btn btn--ghost" disabled={saving || dirty} title={dirty ? t("d.ed.unsaved") : undefined} onClick={() => { setActError(null); setSwitchAsk(inactive ? "on" : "off"); }}>{t(inactive ? "d.dz.activate" : "d.dz.deactivate")}</button>
                      </div>
                    ) : null}
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
                </div>
                <div role="tabpanel" id={`${titleId}-p-treatments`} aria-labelledby={`${titleId}-t-treatments`} hidden={tab !== "treatments"} className="dc-pane">
                  <DoctorTreatmentsTab
                    editable={editable}
                    saving={saving}
                    hospitalId={hospitalId}
                    opts={opts}
                    onRetryOpts={() => setOptsTry((n) => n + 1)}
                    rows={rows}
                    removed={removed}
                    errors={rowErrors}
                    onToggle={toggle}
                    onPatch={patchRow}
                    onRemove={dropRow}
                    onUndo={undoRow}
                  />
                </div>
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
        spec={switchSpec}
        busy={acting}
        error={actError && switchAsk ? t(actError) : null}
        onConfirm={() => void switchActive()}
        onClose={() => { if (!acting) { setSwitchAsk(null); setActError(null); } }}
      />
      <ConfirmDialog
        spec={retireSpec}
        busy={acting}
        error={actError && retireAsk ? t(actError) : null}
        onConfirm={() => void retire()}
        onClose={() => { if (!acting) { setRetireAsk(false); setActError(null); } }}
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
