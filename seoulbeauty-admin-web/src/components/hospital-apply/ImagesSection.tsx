"use client";

import Icon from "../Icon";
import { Thumb, useErrorText } from "./fields";
import { FILE_RULES, INTERIOR_MAX, acceptOf, sortFiles, type Draft, type Errors, type FieldError } from "@/lib/hospitalApply";
import { useT } from "@/lib/locale";

/** 병원 이미지: one main image and up to ten interior images. All optional. */
export default function ImagesSection({
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

  const pickMain = (files: FileList | null) => {
    const file = files?.[0];
    if (!file) return;
    const { accepted, error } = sortFiles([file], FILE_RULES.main, { key: "file:main", section: "img", focusId: "f-main", label: "hap.fn.main" });
    setFileError("file:main", error);
    if (accepted[0]) set({ profileImage: accepted[0] });
  };

  const pickInner = (files: FileList | null) => {
    if (!files || files.length === 0) return;
    const ctx = { key: "file:inner", section: "img" as const, focusId: "f-inner", label: "hap.fn.inner" as const };
    const { accepted, error } = sortFiles(Array.from(files), FILE_RULES.inner, ctx);
    const room = INTERIOR_MAX - draft.interiorImages.length;
    const tooMany = accepted.length > room;
    setFileError(
      "file:inner",
      error ?? (tooMany ? { section: "img", focusId: "f-inner", label: "hap.fn.inner", message: { key: "hap.err.innerMax" } } : null),
    );
    if (room > 0 && accepted.length > 0) set({ interiorImages: [...draft.interiorImages, ...accepted.slice(0, room)] });
  };

  const mainError = text(errors["file:main"]);
  const innerError = text(errors["file:inner"]);
  const full = draft.interiorImages.length >= INTERIOR_MAX;

  return (
    <div className="ha-groups">
      <div className="ha-group">
        <h3 className="ha-group__h">{t("hap.i.main")}</h3>
        <p className="field__hint">{t("hap.i.mainHint")}</p>
        {draft.profileImage ? (
          <ul className="ha-thumbs">
            <Thumb file={draft.profileImage} variant="main" onRemove={() => {
              setFileError("file:main", null);
              set({ profileImage: null });
            }} />
          </ul>
        ) : null}
        <label className="btn btn--ghost ha-filebtn">
          <input id="f-main" className="ha-sr" type="file" accept={acceptOf(FILE_RULES.main)} aria-describedby={mainError ? "f-main-e" : undefined} onChange={(e) => { pickMain(e.target.files); e.target.value = ""; }} />
          <Icon name="image" size={16} />
          {draft.profileImage ? t("hap.i.change") : t("hap.i.pick")}
        </label>
        {mainError ? (
          <p className="ha-err" id="f-main-e" role="alert">
            <Icon name="alert" size={16} />
            <span>{mainError}</span>
          </p>
        ) : null}
      </div>

      <div className="ha-group">
        <div className="ha-group__hrow">
          <h3 className="ha-group__h">{t("hap.i.inner")}</h3>
          <span className="ha-count">{t("hap.i.count", { n: draft.interiorImages.length })}</span>
        </div>
        <p className="field__hint">{t("hap.i.innerHint")}</p>
        <ul className="ha-thumbs">
          {draft.interiorImages.map((file, i) => (
            <Thumb key={`${file.name}-${file.size}-${i}`} file={file} onRemove={() => {
              setFileError("file:inner", null);
              set({ interiorImages: draft.interiorImages.filter((_, j) => j !== i) });
            }} />
          ))}
          {!full ? (
            <li className="ha-thumb ha-thumb--add">
              <label>
                <input id="f-inner" className="ha-sr" type="file" multiple accept={acceptOf(FILE_RULES.inner)} aria-describedby={innerError ? "f-inner-e" : undefined} onChange={(e) => { pickInner(e.target.files); e.target.value = ""; }} />
                <Icon name="plus" size={20} />
                <span>{t("hap.i.add")}</span>
              </label>
            </li>
          ) : null}
        </ul>
        {innerError ? (
          <p className="ha-err" id="f-inner-e" role="alert">
            <Icon name="alert" size={16} />
            <span>{innerError}</span>
          </p>
        ) : null}
      </div>
    </div>
  );
}
