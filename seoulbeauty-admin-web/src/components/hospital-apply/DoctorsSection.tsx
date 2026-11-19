"use client";

import Icon from "../Icon";
import { Field, Thumb, useErrorText } from "./fields";
import { FILE_RULES, acceptOf, emptyDoctor, sortFiles, type DoctorDraft, type Draft, type Errors, type FieldError } from "@/lib/hospitalApply";
import { useT } from "@/lib/locale";

/** 의사: any number of doctors; only the ones with a name are saved. All optional. */
export default function DoctorsSection({
  draft,
  set,
  errors,
  setFileError,
}: {
  draft: Draft;
  set: (patch: Partial<Draft>) => void;
  errors: Errors;
  setFileError: (key: string, error: FieldError | null) => void;
}) {
  const t = useT();
  const text = useErrorText();

  const patch = (key: string, change: Partial<DoctorDraft>) => set({ doctors: draft.doctors.map((d) => (d.key === key ? { ...d, ...change } : d)) });
  const remove = (key: string) => {
    setFileError(`file:doctor:${key}`, null);
    set({ doctors: draft.doctors.filter((d) => d.key !== key) });
  };
  const pickPhoto = (key: string, files: FileList | null) => {
    const file = files?.[0];
    if (!file) return;
    const errorKey = `file:doctor:${key}`;
    const { accepted, error } = sortFiles([file], FILE_RULES.doctor, { key: errorKey, section: "doc", focusId: `f-docphoto-${key}`, label: "hap.fn.doctorPhoto" });
    setFileError(errorKey, error);
    if (accepted[0]) patch(key, { photo: accepted[0] });
  };

  return (
    <div>
      <p className="field__hint ha-lead">{t("hap.d.hint")}</p>
      {draft.doctors.map((d, index) => {
        const n = index + 1;
        const photoError = text(errors[`file:doctor:${d.key}`]);
        return (
          <div className="ha-doctor" key={d.key}>
            <div className="ha-doctor__h">
              <h3>{t("hap.d.title", { n })}</h3>
              <span className="ha-spacer" />
              <button type="button" className="txtbtn ha-danger" onClick={() => remove(d.key)} aria-label={t("hap.d.delAria", { n })}>
                <Icon name="trash" size={16} />
                {t("hap.d.del")}
              </button>
            </div>
            <div className="form">
              <Field id={`d-${d.key}-name`} label={t("hap.d.name")}>
                <input id={`d-${d.key}-name`} className="input" type="text" maxLength={100} placeholder={t("hap.d.namePh")} value={d.name} autoComplete="off" onChange={(e) => patch(d.key, { name: e.target.value })} />
              </Field>
              <Field id={`d-${d.key}-spec`} label={t("hap.d.spec")}>
                <input id={`d-${d.key}-spec`} className="input" type="text" maxLength={255} placeholder={t("hap.d.specPh")} value={d.specialty} autoComplete="off" onChange={(e) => patch(d.key, { specialty: e.target.value })} />
              </Field>
              <Field id={`d-${d.key}-exp`} label={t("hap.d.exp")}>
                <input id={`d-${d.key}-exp`} className="input ha-num" type="number" inputMode="numeric" min="0" max="80" placeholder={t("hap.d.expPh")} value={d.experience} onChange={(e) => patch(d.key, { experience: e.target.value })} />
              </Field>
              <Field id={`f-docphoto-${d.key}`} label={t("hap.d.photo")} hint={t("hap.d.photoHint")} error={photoError}>
                <div className="ha-docphoto">
                  {d.photo ? (
                    <ul className="ha-thumbs">
                      <Thumb file={d.photo} variant="round" onRemove={() => patch(d.key, { photo: null })} />
                    </ul>
                  ) : null}
                  <label className="btn btn--ghost ha-filebtn">
                    <input id={`f-docphoto-${d.key}`} className="ha-sr" type="file" accept={acceptOf(FILE_RULES.doctor)} aria-describedby={photoError ? `f-docphoto-${d.key}-e` : `f-docphoto-${d.key}-h`} onChange={(e) => { pickPhoto(d.key, e.target.files); e.target.value = ""; }} />
                    <Icon name="image" size={16} />
                    {d.photo ? t("hap.i.change") : t("hap.d.photoPick")}
                  </label>
                </div>
              </Field>
              <Field id={`d-${d.key}-qual`} label={t("hap.d.qual")} span>
                <textarea id={`d-${d.key}-qual`} className="input textarea ha-textarea" rows={3} maxLength={2000} placeholder={t("hap.d.qualPh")} value={d.qualification} onChange={(e) => patch(d.key, { qualification: e.target.value })} />
              </Field>
              <Field id={`d-${d.key}-bio`} label={t("hap.d.bio")} span>
                <textarea id={`d-${d.key}-bio`} className="input textarea ha-textarea" rows={3} maxLength={2000} placeholder={t("hap.d.bioPh")} value={d.bio} onChange={(e) => patch(d.key, { bio: e.target.value })} />
              </Field>
            </div>
          </div>
        );
      })}
      <button type="button" className="btn btn--ghost ha-addrow" onClick={() => set({ doctors: [...draft.doctors, emptyDoctor()] })}>
        <Icon name="plus" size={16} />
        {t("hap.d.add")}
      </button>
    </div>
  );
}
