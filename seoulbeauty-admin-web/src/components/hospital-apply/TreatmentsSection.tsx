"use client";

import { useMemo, useState } from "react";
import Icon from "../Icon";
import { Thumb, useErrorText } from "./fields";
import {
  FILE_RULES,
  acceptOf,
  emptyPrice,
  priceState,
  sortFiles,
  type CategoryOption,
  type Draft,
  type Errors,
  type FieldError,
  type PriceRow,
  type TreatmentDraft,
  type TreatmentOption,
} from "@/lib/hospitalApply";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

type Props = {
  draft: Draft;
  set: (patch: Partial<Draft>) => void;
  errors: Errors;
  setFileError: (key: string, error: FieldError | null) => void;
  categories: CategoryOption[];
  options: TreatmentOption[];
  units: readonly string[];
};

/** 시술·가격: pick treatments from the master list, then give each its price options and detail images. All optional. */
export default function TreatmentsSection({ draft, set, errors, setFileError, categories, options, units }: Props) {
  const t = useT();
  const [cat, setCat] = useState<number | "all">("all");
  const [query, setQuery] = useState("");

  const categoryName = useMemo(() => new Map(categories.map((c) => [c.id, c.name])), [categories]);
  const selectedIds = useMemo(() => new Set(draft.treatments.map((x) => x.id)), [draft.treatments]);
  const counts = useMemo(() => {
    const map = new Map<number, number>();
    for (const o of options) map.set(o.categoryId, (map.get(o.categoryId) ?? 0) + 1);
    return map;
  }, [options]);
  const shown = useMemo(() => {
    const q = query.trim().toLowerCase();
    return options.filter((o) => (cat === "all" || o.categoryId === cat) && (q === "" || o.name.toLowerCase().includes(q)));
  }, [options, cat, query]);

  const toggle = (id: number) => {
    if (selectedIds.has(id)) {
      setFileError(`file:treat:${id}`, null);
      set({ treatments: draft.treatments.filter((x) => x.id !== id) });
    }
    else set({ treatments: [...draft.treatments, { id, prices: [emptyPrice()], images: [] }] });
  };
  const patchTreatment = (id: number, patch: Partial<TreatmentDraft>) => set({ treatments: draft.treatments.map((x) => (x.id === id ? { ...x, ...patch } : x)) });

  return (
    <div>
      <div className="ha-pick">
        <div>
          <p className="ha-pick__t">{t("hap.t.pick")}</p>
          <p className="field__hint">{t("hap.t.pickHint")}</p>
        </div>
        <span className="pill pill--info" role="status">{t("hap.t.selected", { n: draft.treatments.length })}</span>
      </div>

      <div className="ha-cats" role="group" aria-label={t("hap.t.pick")}>
        <button type="button" className="ha-cat" aria-pressed={cat === "all"} onClick={() => setCat("all")}>
          {t("hap.t.all")}
          <b>{formatNumber(options.length)}</b>
        </button>
        {categories.map((c) => (
          <button key={c.id} type="button" className="ha-cat" aria-pressed={cat === c.id} onClick={() => setCat(c.id)}>
            {c.name}
            <b>{formatNumber(counts.get(c.id) ?? 0)}</b>
          </button>
        ))}
      </div>

      <div className="ha-tools">
        <input className="input" type="search" aria-label={t("hap.t.search")} placeholder={t("hap.t.search")} value={query} onChange={(e) => setQuery(e.target.value)} />
      </div>

      {shown.length === 0 ? (
        <p className="ha-empty">{t("hap.t.none")}</p>
      ) : (
        <ul className="ha-tiles">
          {shown.map((o) => {
            const on = selectedIds.has(o.id);
            return (
              <li key={o.id}>
                <label className={on ? "ha-tile is-on" : "ha-tile"}>
                  <input type="checkbox" className="ha-sr" checked={on} onChange={() => toggle(o.id)} />
                  <span className="ha-tile__box" aria-hidden="true"><Icon name="check" size={14} /></span>
                  <span className="ha-tile__t">
                    <b>{o.name}</b>
                    <span>{categoryName.get(o.categoryId) ?? t("hap.t.uncat")}</span>
                  </span>
                </label>
              </li>
            );
          })}
        </ul>
      )}

      {draft.treatments.map((tr) => {
        const option = options.find((o) => o.id === tr.id);
        return (
          <TreatmentEditor
            key={tr.id}
            treatment={tr}
            name={option?.name ?? `#${tr.id}`}
            units={units}
            error={errors[`file:treat:${tr.id}`]}
            onChange={(patch) => patchTreatment(tr.id, patch)}
            onRemove={() => toggle(tr.id)}
            setFileError={setFileError}
          />
        );
      })}
    </div>
  );
}

function TreatmentEditor({
  treatment,
  name,
  units,
  error,
  onChange,
  onRemove,
  setFileError,
}: {
  treatment: TreatmentDraft;
  name: string;
  units: readonly string[];
  error: FieldError | undefined;
  onChange: (patch: Partial<TreatmentDraft>) => void;
  onRemove: () => void;
  setFileError: (key: string, error: FieldError | null) => void;
}) {
  const t = useT();
  const text = useErrorText();
  const id = treatment.id;
  const errorKey = `file:treat:${id}`;

  const setRow = (key: string, patch: Partial<PriceRow>) => onChange({ prices: treatment.prices.map((p) => (p.key === key ? { ...p, ...patch } : p)) });
  const addRow = () => onChange({ prices: [...treatment.prices, emptyPrice()] });
  const removeRow = (key: string) => {
    const rest = treatment.prices.filter((p) => p.key !== key);
    onChange({ prices: rest.length > 0 ? rest : [emptyPrice()] });
  };
  const pickImages = (files: FileList | null) => {
    if (!files || files.length === 0) return;
    const { accepted, error: problem } = sortFiles(Array.from(files), FILE_RULES.treatment, { key: errorKey, section: "treat", focusId: `f-timg-${id}`, label: "hap.fn.treatImg" });
    setFileError(errorKey, problem);
    if (accepted.length > 0) onChange({ images: [...treatment.images, ...accepted] });
  };

  return (
    <div className="ha-tedit">
      <div className="ha-tedit__h">
        <h3>{name}</h3>
        <span className="pill pill--on">{t("hap.t.assigned")}</span>
        <span className="ha-spacer" />
        <button type="button" className="txtbtn" onClick={onRemove}>
          <Icon name="close" size={16} />
          {t("hap.t.unselect")}
        </button>
      </div>

      <p className="ha-sublabel">{t("hap.t.price")}</p>
      <div className="ha-phead" aria-hidden="true">
        <span>{t("hap.t.optName")}</span>
        <span>{t("hap.t.dosage")}</span>
        <span>{t("hap.t.unit")}</span>
        <span>{t("hap.t.amount")}</span>
        <span />
      </div>
      {treatment.prices.map((row, index) => {
        const partial = priceState(row) === "partial";
        const base = `p-${id}-${index}`;
        return (
          <div className="ha-prow" key={row.key}>
            <div className="ha-pf ha-pf--name">
              <label className="ha-pf__l" htmlFor={`${base}-n`}>{t("hap.t.optName")}</label>
              <input id={`${base}-n`} className="input" type="text" maxLength={100} placeholder={t("hap.t.optNamePh")} value={row.optionName} onChange={(e) => setRow(row.key, { optionName: e.target.value })} />
            </div>
            <div className="ha-pf">
              <label className="ha-pf__l" htmlFor={`${base}-d`}>{t("hap.t.dosage")}</label>
              <input id={`${base}-d`} className="input ha-num" type="number" inputMode="decimal" min="0.01" step="0.01" value={row.dosage} onChange={(e) => setRow(row.key, { dosage: e.target.value })} />
            </div>
            <div className="ha-pf">
              <label className="ha-pf__l" htmlFor={`${base}-u`}>{t("hap.t.unit")}</label>
              <select id={`${base}-u`} className="input" value={row.unit} onChange={(e) => setRow(row.key, { unit: e.target.value })}>
                <option value="">{t("hap.t.unitPick")}</option>
                {units.map((u) => (
                  <option key={u} value={u}>{u}</option>
                ))}
              </select>
            </div>
            <div className="ha-pf ha-pf--price">
              <label className="ha-pf__l" htmlFor={`${base}-p`}>{t("hap.t.amount")}</label>
              <input id={`${base}-p`} className="input ha-num" type="number" inputMode="numeric" min="1" step="1" value={row.price} onChange={(e) => setRow(row.key, { price: e.target.value })} />
            </div>
            <button type="button" className="ibtn ha-pdel" aria-label={t("hap.t.delRow")} onClick={() => removeRow(row.key)} disabled={treatment.prices.length === 1 && priceState(row) === "empty"}>
              <Icon name="trash" size={18} />
            </button>
            {partial ? (
              <p className="ha-soft" role="status">
                <Icon name="info" size={16} />
                <span>{t("hap.t.soft")}</span>
              </p>
            ) : null}
          </div>
        );
      })}
      <button type="button" className="btn btn--ghost ha-addrow" onClick={addRow}>
        <Icon name="plus" size={16} />
        {t("hap.t.addPrice")}
      </button>

      <p className="ha-sublabel">
        {t("hap.t.images")} <span className="ha-faint">({t("hap.t.imagesOpt")})</span>
      </p>
      {treatment.images.length > 0 ? (
        <ul className="ha-thumbs">
          {treatment.images.map((file, i) => (
            <Thumb key={`${file.name}-${file.size}-${i}`} file={file} onRemove={() => {
              setFileError(errorKey, null);
              onChange({ images: treatment.images.filter((_, j) => j !== i) });
            }} />
          ))}
        </ul>
      ) : null}
      <label className="ha-drop">
        <input id={`f-timg-${id}`} className="ha-sr" type="file" multiple accept={acceptOf(FILE_RULES.treatment)} aria-describedby={error ? `f-timg-${id}-e` : undefined} onChange={(e) => { pickImages(e.target.files); e.target.value = ""; }} />
        <Icon name="image" size={22} />
        <b>{t("hap.t.imagesPick")}</b>
        <span>{t("hap.t.imagesHint")}</span>
      </label>
      {error ? (
        <p className="ha-err" id={`f-timg-${id}-e`} role="alert">
          <Icon name="alert" size={16} />
          <span>{text(error)}</span>
        </p>
      ) : null}
    </div>
  );
}
