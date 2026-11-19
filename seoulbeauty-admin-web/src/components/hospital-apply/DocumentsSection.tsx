"use client";

import Icon from "../Icon";
import { useErrorText } from "./fields";
import { FILE_RULES, acceptOf, sortFiles, type Draft, type Errors, type FieldError } from "@/lib/hospitalApply";
import { useT } from "@/lib/locale";

type DocKey = "certBusiness" | "certMedical";

/** 서류: the business registration certificate and the medical institution licence. Both optional. */
export default function DocumentsSection({
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

  const row = (field: DocKey, title: string, focusId: string, label: "hap.fn.business" | "hap.fn.medical") => {
    const file = draft[field];
    const errorKey = `file:${field}`;
    const error = text(errors[errorKey]);
    const pick = (files: FileList | null) => {
      const chosen = files?.[0];
      if (!chosen) return;
      const { accepted, error: problem } = sortFiles([chosen], FILE_RULES.doc, { key: errorKey, section: "file", focusId, label });
      setFileError(errorKey, problem);
      if (accepted[0]) set({ [field]: accepted[0] } as Partial<Draft>);
    };
    return (
      <div className="ha-rowdoc">
        <div className="ha-rowdoc__t">
          <b>{title}</b>
          <span>{file ? file.name : t("hap.fl.hint")}</span>
        </div>
        <span className={file ? "pill pill--on" : "pill pill--off"}>{file ? t("hap.fl.picked") : t("hap.fl.none")}</span>
        <div className="ha-rowdoc__acts">
          <label className="btn btn--ghost ha-filebtn">
            <input id={focusId} className="ha-sr" type="file" accept={acceptOf(FILE_RULES.doc)} aria-describedby={error ? `${focusId}-e` : undefined} onChange={(e) => { pick(e.target.files); e.target.value = ""; }} />
            <Icon name="sheet" size={16} />
            {file ? t("hap.fl.change") : t("hap.fl.pick")}
          </label>
          {file ? (
            <button type="button" className="txtbtn ha-danger" onClick={() => {
                setFileError(errorKey, null);
                set({ [field]: null } as Partial<Draft>);
              }}>
              <Icon name="trash" size={16} />
              {t("hap.fl.remove")}
            </button>
          ) : null}
        </div>
        {error ? (
          <p className="ha-err ha-rowdoc__err" id={`${focusId}-e`} role="alert">
            <Icon name="alert" size={16} />
            <span>{error}</span>
          </p>
        ) : null}
      </div>
    );
  };

  return (
    <div className="ha-docs">
      {row("certBusiness", t("hap.fl.business"), "f-cert-business", "hap.fn.business")}
      {row("certMedical", t("hap.fl.medical"), "f-cert-medical", "hap.fn.medical")}
    </div>
  );
}
