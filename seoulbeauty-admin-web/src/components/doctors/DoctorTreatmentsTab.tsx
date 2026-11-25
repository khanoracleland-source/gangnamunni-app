"use client";

import { useEffect, useId, useRef, useState, useSyncExternalStore } from "react";
import Icon from "../Icon";
import type { DoctorLevel, DoctorTreatmentOption } from "@/lib/api";
import { DOCTOR_CERTIFICATION_MAX, levelLabelKey, levelSummary, type TreatmentRow, type TreatmentRowErrors } from "@/lib/doctors";
import { useT } from "@/lib/locale";

export type TreatmentOptions = { state: "loading" | "ready" | "error"; hospitalId: number; items: DoctorTreatmentOption[] };

/** The order of the level boxes: from the lowest up. */
const LEVEL_CHOICES: readonly DoctorLevel[] = ["basic", "advanced", "expert"];

function useMq(query: string): boolean {
  return useSyncExternalStore(
    (notify) => {
      const m = window.matchMedia(query);
      m.addEventListener("change", notify);
      return () => m.removeEventListener("change", notify);
    },
    () => window.matchMedia(query).matches,
    () => false,
  );
}

/** One, two or three bars: how far up the levels a treatment is (shape and text carry it, not colour). */
export function LevelMark({ level }: { level: DoctorLevel }) {
  return (
    <span className={`dc-bars dc-bars--${level}`} aria-hidden="true">
      <i />
      <i />
      <i />
    </span>
  );
}

type Patch = Partial<Pick<TreatmentRow, "level" | "cases" | "certification">>;

type Props = {
  editable: boolean;
  saving: boolean;
  hospitalId: number;
  opts: TreatmentOptions;
  onRetryOpts: () => void;
  /** Every row of the table: the removed ones stay in place until Save. */
  rows: TreatmentRow[];
  removed: number[];
  errors: Record<number, TreatmentRowErrors>;
  onToggle: (o: DoctorTreatmentOption) => void;
  onPatch: (id: number, p: Patch) => void;
  onRemove: (id: number) => void;
  onUndo: (id: number) => void;
};

/** The Treatments tab of the doctor dialog: pick treatments, then set level, cases and certification for each one. */
export default function DoctorTreatmentsTab(p: Props) {
  const t = useT();
  const stacked = useMq("(max-width: 900px)");
  const narrow = useMq("(max-width: 639px)");
  const [pickOpen, setPickOpen] = useState(false);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [filter, setFilter] = useState("");
  const sheetRef = useRef<HTMLDialogElement>(null);
  const seen = useRef(new WeakSet<HTMLDetailsElement>());
  const sheetTitle = useId();

  // The sheet exists on a phone only: widening the window closes it for good, so narrowing again does not bring it back.
  if (!narrow && sheetOpen) setSheetOpen(false);

  useEffect(() => {
    const d = sheetRef.current;
    if (!d) return;
    if (sheetOpen && narrow && !d.open) d.showModal();
    if ((!sheetOpen || !narrow) && d.open) d.close();
  }, [sheetOpen, narrow]);

  const optsReady = p.opts.state === "ready" && p.opts.hospitalId === p.hospitalId;
  const isRemoved = (id: number) => p.removed.includes(id);
  const active = p.rows.filter((r) => !isRemoved(r.id));
  const on = new Set(active.map((r) => r.id));
  const counts: Record<DoctorLevel, number> = { basic: 0, advanced: 0, expert: 0 };
  for (const r of active) counts[r.level] += 1;
  const dist = levelSummary(counts);
  const locked = !p.editable || p.saving;

  const needle = filter.trim().toLowerCase();
  const groups: [string, DoctorTreatmentOption[]][] = (() => {
    const map = new Map<string, DoctorTreatmentOption[]>();
    for (const o of p.opts.items) {
      if (needle !== "" && !o.name.toLowerCase().includes(needle) && !o.category.toLowerCase().includes(needle)) continue;
      const bucket = map.get(o.category);
      if (bucket) bucket.push(o);
      else map.set(o.category, [o]);
    }
    return Array.from(map.entries());
  })();

  const picker = (
    <div className="dc-pick__in">
      <input
        className="input"
        type="search"
        value={filter}
        placeholder={t("d.ex.filter")}
        aria-label={t("d.ex.filter")}
        autoComplete="off"
        enterKeyHint="search"
        disabled={p.opts.state !== "ready"}
        onChange={(e) => setFilter(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Enter") e.preventDefault();
        }}
      />
      {p.opts.state === "loading" || (p.opts.state === "ready" && p.opts.hospitalId !== p.hospitalId) ? (
        <div className="dc-skels" role="status" aria-busy="true">
          <span className="sr-only">{t("d.ed.treatmentsLoading")}</span>
          <span className="dc-skel" aria-hidden="true" />
          <span className="dc-skel" aria-hidden="true" />
          <span className="dc-skel" aria-hidden="true" />
          <span className="dc-skel" aria-hidden="true" />
        </div>
      ) : null}
      {p.opts.state === "error" ? (
        <p className="dc-help" role="alert">
          {t("d.ed.treatmentsFail")}{" "}
          <button type="button" className="txtbtn" onClick={p.onRetryOpts}>{t("d.ed.treatmentsRetry")}</button>
          <br />
          {t("d.ex.keepNote")}
        </p>
      ) : null}
      {optsReady ? (
        groups.length === 0 ? (
          <p className="dc-help">{t(p.opts.items.length === 0 ? "d.ed.treatmentsNone" : "d.ex.filterNone")}</p>
        ) : (
          <div className="dc-treat">
            {groups.map(([category, items]) => {
              const count = items.filter((x) => on.has(x.id)).length;
              return (
                <details
                  key={`${p.hospitalId}-${category}-${needle !== ""}`}
                  className="dc-treat__group"
                  ref={(el) => {
                    if (el && !seen.current.has(el)) {
                      seen.current.add(el);
                      el.open = !narrow || count > 0 || needle !== "";
                    }
                  }}
                >
                  <summary>
                    <span>{category || t("h.find.treatmentOther")}</span>
                    {count > 0 ? <span className="dc-treat__n">{count}</span> : null}
                  </summary>
                  <ul className="dc-treat__list">
                    {items.map((item) => (
                      <li key={item.id}>
                        <label className={on.has(item.id) ? "dc-opt is-on" : "dc-opt"}>
                          <input type="checkbox" checked={on.has(item.id)} disabled={locked} onChange={() => p.onToggle(item)} />
                          <span>{item.name}</span>
                        </label>
                      </li>
                    ))}
                  </ul>
                </details>
              );
            })}
          </div>
        )
      ) : null}
      {optsReady ? <p className="dc-help">{t("d.ed.treatmentsHelp")}</p> : null}
    </div>
  );

  const removedText = (r: TreatmentRow) => {
    const n = Number(r.cases.trim());
    return Number.isFinite(n) && n > 0 ? t("d.ex.removed", { n }) : t("d.ex.removedEmpty");
  };
  const casesText = (r: TreatmentRow) => t("d.ex.cases", { n: Number(r.cases.trim()) || 0 });
  const undo = (r: TreatmentRow) => (
    <button type="button" className="btn btn--ghost dc-undo" disabled={p.saving} onClick={() => p.onUndo(r.id)}>{t("d.ex.undo")}</button>
  );
  const other = t("h.find.treatmentOther");

  const tableRow = (r: TreatmentRow) => {
    const e = p.errors[r.id] ?? {};
    const certChars = Array.from(r.certification).length;
    if (isRemoved(r.id)) {
      return (
        <tr key={r.id} className="is-removed">
          <td>
            <span className="dc-tn" title={r.name} translate="no">{r.name}</span>
            <span className="dc-tc">{removedText(r)}</span>
          </td>
          <td colSpan={4} className="dc-tt__undo">{undo(r)}</td>
        </tr>
      );
    }
    if (!p.editable) {
      return (
        <tr key={r.id}>
          <td>
            <span className="dc-tn" title={r.name} translate="no">{r.name}</span>
            <span className="dc-tc">{r.category || other}</span>
          </td>
          <td><span className="dc-ro-lv"><LevelMark level={r.level} />{t(levelLabelKey(r.level))}</span></td>
          <td className="r">{casesText(r)}</td>
          <td><span className="dc-tn" title={r.certification}>{r.certification}</span></td>
          <td />
        </tr>
      );
    }
    return (
      <tr key={r.id} className="dc-tt__row">
        <td>
          <span className="dc-tn" title={r.name} translate="no">{r.name}</span>
          <span className="dc-tc">{r.category || other}</span>
        </td>
        <td>
          <select className="sel sel--field" value={r.level} disabled={p.saving} aria-label={`${t("d.ex.colLevel")}: ${r.name}`} onChange={(ev) => p.onPatch(r.id, { level: ev.target.value as DoctorLevel })}>
            {LEVEL_CHOICES.map((l) => (
              <option key={l} value={l}>{t(levelLabelKey(l))}</option>
            ))}
          </select>
        </td>
        <td className="r">
          <input className="input dc-cases" inputMode="numeric" autoComplete="off" value={r.cases} disabled={p.saving} aria-label={`${t("d.ex.colCases")}: ${r.name}`} aria-invalid={e.cases ? true : undefined} onChange={(ev) => p.onPatch(r.id, { cases: ev.target.value })} />
          {e.cases ? <p className="dc-err" role="alert">{t(e.cases)}</p> : null}
        </td>
        <td>
          <div className="dc-cert">
            <textarea className="input dc-cert__ta" rows={1} value={r.certification} placeholder={t("d.ex.certPlaceholder")} disabled={p.saving} aria-label={`${t("d.ex.colCert")}: ${r.name}`} aria-invalid={e.certification ? true : undefined} onChange={(ev) => p.onPatch(r.id, { certification: ev.target.value })} />
            <span className={certChars > DOCTOR_CERTIFICATION_MAX ? "dc-cert__n is-over" : "dc-cert__n"} aria-hidden="true">{certChars} / {DOCTOR_CERTIFICATION_MAX}</span>
          </div>
          {e.certification ? <p className="dc-err" role="alert">{t(e.certification)} ({certChars})</p> : null}
        </td>
        <td className="dc-tt__rm">
          <button type="button" className="ibtn" disabled={p.saving} aria-label={t("d.ex.remove", { name: r.name })} title={t("d.ex.remove", { name: r.name })} onClick={() => p.onRemove(r.id)}>
            <Icon name="close" size={18} />
          </button>
        </td>
      </tr>
    );
  };

  const accRow = (r: TreatmentRow) => {
    const e = p.errors[r.id] ?? {};
    if (isRemoved(r.id)) {
      return (
        <li key={r.id} className="dc-acc dc-acc--x">
          <div className="dc-acc__sum">
            <span className="dc-acc__n" translate="no">{r.name}</span>
            <span className="dc-acc__s">{removedText(r)}</span>
          </div>
          {undo(r)}
        </li>
      );
    }
    const summary = (
      <>
        <span className="dc-acc__n" translate="no">{r.name}</span>
        <span className="dc-acc__s"><LevelMark level={r.level} />{t(levelLabelKey(r.level))} · {casesText(r)}</span>
      </>
    );
    if (!p.editable) {
      return (
        <li key={r.id} className="dc-acc dc-acc--ro">
          <div className="dc-acc__sum">{summary}</div>
          {r.certification !== "" ? <p className="dc-help">{r.certification}</p> : null}
        </li>
      );
    }
    return (
      <li key={r.id}>
        <details className="dc-acc" open={e.cases || e.certification ? true : undefined}>
          <summary>
            <span className="dc-acc__sum">{summary}</span>
            <Icon name="chevron" size={16} className="dc-acc__chev" />
          </summary>
          <div className="dc-acc__b">
            <fieldset className="dc-lvl" disabled={p.saving}>
              <legend className="sr-only">{t("d.ex.colLevel")}</legend>
              {LEVEL_CHOICES.map((l) => (
                <label key={l} className="dc-lvl__o">
                  <input type="radio" name={`lv-${r.id}`} value={l} checked={r.level === l} onChange={() => p.onPatch(r.id, { level: l })} />
                  <span>{t(levelLabelKey(l))}</span>
                </label>
              ))}
            </fieldset>
            <label className="field">
              <span className="field__label">{t("d.ex.colCases")}</span>
              <input className="input" inputMode="numeric" autoComplete="off" value={r.cases} disabled={p.saving} aria-invalid={e.cases ? true : undefined} onChange={(ev) => p.onPatch(r.id, { cases: ev.target.value })} />
              {e.cases ? <p className="dc-err" role="alert">{t(e.cases)}</p> : null}
            </label>
            <label className="field">
              <span className="field__label">{t("d.ex.colCert")}</span>
              <textarea className="input dc-acc__ta" rows={2} value={r.certification} placeholder={t("d.ex.certPlaceholder")} disabled={p.saving} aria-invalid={e.certification ? true : undefined} onChange={(ev) => p.onPatch(r.id, { certification: ev.target.value })} />
              {e.certification ? <p className="dc-err" role="alert">{t(e.certification)} ({Array.from(r.certification).length})</p> : null}
            </label>
            <button type="button" className="btn btn--ghost dc-acc__rm" disabled={p.saving} onClick={() => p.onRemove(r.id)}>{t("d.ex.removeShort")}</button>
          </div>
        </details>
      </li>
    );
  };

  const assigned = (
    <section className="dc-asg" aria-label={t("d.ex.assigned")}>
      <div className="dc-asg__head">
        <h3 className="dc-asg__title">
          {t("d.ex.assigned")} <span className="dc-count">· {active.length}</span>
        </h3>
        {dist.length > 0 ? (
          <ul className="dc-dist" aria-label={t("d.ex.lvSummary")}>
            {dist.map((x) => (
              <li key={x.level} className="dc-dist__item">
                <LevelMark level={x.level} />
                {t(levelLabelKey(x.level))} <b>{x.n}</b>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
      {p.rows.length === 0 ? (
        <p className="dc-help">{t("d.ex.noneAssigned")}</p>
      ) : narrow ? (
        <ul className="dc-accs">{p.rows.map(accRow)}</ul>
      ) : (
        <div className="dc-tt-wrap">
          <table className="dc-tt">
            <colgroup>
              <col className="dc-tt__c1" />
              <col className="dc-tt__c2" />
              <col className="dc-tt__c3" />
              <col />
              <col className="dc-tt__c5" />
            </colgroup>
            <thead>
              <tr>
                <th scope="col">{t("d.ex.colTreatment")}</th>
                <th scope="col">{t("d.ex.colLevel")}</th>
                <th scope="col" className="r">{t("d.ex.colCases")}</th>
                <th scope="col">{t("d.ex.colCert")}</th>
                <th scope="col"><span className="sr-only">{t("d.ex.colRemove")}</span></th>
              </tr>
            </thead>
            <tbody>{p.rows.map(tableRow)}</tbody>
          </table>
        </div>
      )}
      {p.editable && p.removed.length > 0 ? <p className="dc-help">{t("d.ex.undoHint")}</p> : null}
    </section>
  );

  return (
    <div className={p.editable ? "dc-split" : "dc-split dc-split--ro"}>
      {p.editable ? (
        <div className={pickOpen ? "dc-pick is-open" : "dc-pick"}>
          {stacked ? (
            <button
              type="button"
              className="btn btn--primary dc-add"
              aria-expanded={narrow ? undefined : pickOpen}
              onClick={() => (narrow ? setSheetOpen(true) : setPickOpen((o) => !o))}
            >
              <Icon name="plus" size={16} />
              {t("d.ex.add")}
            </button>
          ) : (
            <h3 className="dc-pick__title">{t("d.ex.add")}</h3>
          )}
          {!narrow && (!stacked || pickOpen) ? picker : null}
        </div>
      ) : null}
      {assigned}
      {narrow ? (
        <dialog ref={sheetRef} className="dialog dc-dialog dc-sheet" aria-labelledby={sheetTitle} onClose={() => setSheetOpen(false)}>
          <div className="dc-dialog__head">
            <h2 id={sheetTitle} className="dialog__title">{t("d.ex.add")}</h2>
            <button type="button" className="ibtn dialog__close" onClick={() => setSheetOpen(false)} aria-label={t("m.close")} title={t("m.close")}>
              <Icon name="close" />
            </button>
          </div>
          <div className="dc-dialog__body">{picker}</div>
          <div className="dc-dialog__foot">
            <span className="dc-dirty">{t("d.ex.sheetNote", { n: active.length })}</span>
            <button type="button" className="btn btn--primary" onClick={() => setSheetOpen(false)}>{t("d.ex.done")}</button>
          </div>
        </dialog>
      ) : null}
    </div>
  );
}
