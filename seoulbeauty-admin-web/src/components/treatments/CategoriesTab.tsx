"use client";

import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";
import Icon from "../Icon";
import ConfirmDialog, { type ConfirmSpec } from "../hospitals/ConfirmDialog";
import { ActivePill } from "../hospitals/parts";
import CategoryDialog from "./CategoryDialog";
import { failureKey } from "./writeErrors";
import { TABLE_MODES, deleteCategory, reorderCategories, saveTableMode, setCategoryActive, type CategoryChange, type TableMode, type TreatmentCategory, type TreatmentsData, type WriteResult } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { treatmentsHref } from "@/lib/treatments";

const MODE_LABELS: Record<(typeof TABLE_MODES)[number], MessageKey> = { excel: "tm.cat.mode.excel", row: "tm.cat.mode.row", hybrid: "tm.cat.mode.hybrid" };

/** The categories tab: the app's table layout setting and the category list. With `canWrite` both can be changed; `refresh` reads the list again. */
export default function CategoriesTab({ data, canWrite, refresh }: { data: TreatmentsData; canWrite: boolean; refresh: () => Promise<void> }) {
  const t = useT();
  const active = data.categories.filter((c) => c.isActive).length;
  // null = closed, "new" = add form, a category = edit form.
  const [editing, setEditing] = useState<TreatmentCategory | "new" | null>(null);
  const [deleting, setDeleting] = useState<TreatmentCategory | null>(null);
  const [busyId, setBusyId] = useState(0);
  const [busy, setBusy] = useState(false);
  const nextOrder = data.categories.reduce((m, c) => Math.max(m, c.sortOrder), 0) + 1;

  const run = async (id: number, action: () => Promise<WriteResult<CategoryChange>>, done: string) => {
    setBusyId(id);
    const res = await action();
    if (res.ok) {
      toast.success(done);
      await refresh();
    } else toast.error(t(failureKey(res)));
    setBusyId(0);
  };
  const toggle = (c: TreatmentCategory, next: boolean) => run(c.id, () => setCategoryActive(c.id, next), t(next ? "tmc.ok.on" : "tmc.ok.off"));
  const move = (c: TreatmentCategory, dir: -1 | 1) => {
    const ids = data.categories.map((x) => x.id);
    const at = ids.indexOf(c.id);
    const to = at + dir;
    if (at < 0 || to < 0 || to >= ids.length) return;
    [ids[at], ids[to]] = [ids[to], ids[at]];
    return run(c.id, () => reorderCategories(ids), t("tmc.ok.moved"));
  };
  const changeMode = async (mode: TableMode) => {
    if (mode === data.tableMode || busyId !== 0) return;
    setBusyId(-1);
    const res = await saveTableMode(mode);
    if (res.ok) {
      toast.success(t("tmc.ok.mode"));
      await refresh();
    } else toast.error(t(failureKey(res)));
    setBusyId(0);
  };
  const removeOne = async () => {
    if (!deleting) return;
    setBusy(true);
    const res = await deleteCategory(deleting.id);
    if (res.ok) {
      toast.success(t("tmc.ok.deleted"));
      await refresh();
    } else if (res.status === 409 && res.error === "in_use") toast.error(t("tmc.del.inUse", { n: formatNumber(res.hospitalCount ?? 0) }));
    else toast.error(t(failureKey(res)));
    setBusy(false);
    setDeleting(null);
  };
  const deleteSpec: ConfirmSpec | null = deleting
    ? { title: t("tmc.del.title"), name: deleting.name, body: t("tmc.del.body"), confirmLabel: t("tmw.del.confirm"), cancelLabel: t("tmw.cancel"), focus: "cancel" }
    : null;

  return (
    <>
      <section className="panel tm__mode" aria-labelledby="tm-mode-title">
        <div className="tm__mode-head">
          <h3 id="tm-mode-title">{t("tm.cat.tableMode")}</h3>
          <span>{t("tm.cat.tableModeHint")}</span>
        </div>
        <div className="seg tm__seg" role="radiogroup" aria-label={t("tm.cat.tableMode")}>
          {TABLE_MODES.map((mode) => (
            <label key={mode} className={data.tableMode === mode ? "is-on" : undefined}>
              <input className="sr-only" type="radio" name="tm-table-mode" value={mode} checked={data.tableMode === mode} disabled={!canWrite || busyId !== 0} onChange={() => void changeMode(mode)} />
              <span>{t(MODE_LABELS[mode])}</span>
            </label>
          ))}
        </div>
      </section>

      <div className="list-head">
        <div className="list-head__main">
          <p className="list-head__count" role="status">{t("tm.cat.count", { n: formatNumber(data.categories.length), active: formatNumber(active) })}</p>
        </div>
        {canWrite ? <button type="button" className="btn btn--primary tm__new" onClick={() => setEditing("new")}>{t("tmc.new")}</button> : null}
      </div>

      {data.categories.length === 0 ? (
        <div className="empty tm__empty">
          <span className="state__ring"><Icon name="tag" size={22} /></span>
          <p className="tm__empty-title">{t("tm.cat.empty")}</p>
          <p>{t("tm.cat.emptyBody")}</p>
        </div>
      ) : (
        <table className="tbl tbl--tmcat" role="table">
          <caption className="sr-only">{t("tm.cat.caption")}</caption>
          <thead role="rowgroup">
            <tr role="row">
              <th role="columnheader" scope="col" className="tm__cc-order">{t("tm.cat.col.order")}</th>
              <th role="columnheader" scope="col" className="tbl__name tm__cc-name">{t("tm.cat.col.name")}</th>
              <th role="columnheader" scope="col" className="tm__cc-icon">{t("tm.cat.col.icon")}</th>
              <th role="columnheader" scope="col" className="tm__cc-count">{t("tm.cat.col.treatments")}</th>
              <th role="columnheader" scope="col" className="tm__cc-active">{t("tm.cat.col.active")}</th>
              {canWrite ? <th role="columnheader" scope="col" className="tm__cc-act"><span className="sr-only">{t("tmw.edit")}</span></th> : null}
            </tr>
          </thead>
          <tbody role="rowgroup">
            {data.categories.map((c, index) => (
              <tr key={c.id} role="row" className={c.isActive ? undefined : "is-off"}>
                <td role="cell" className={canWrite ? "tm__cc-order tm__cc-order--w" : "tm__cc-order"} data-label={t("tm.cat.col.order")}>
                  <span className="tm__order">{formatNumber(index + 1)}</span>
                  {canWrite ? (
                    <span className="tm__move">
                      <button type="button" className="tm__move-btn" disabled={index === 0 || busyId !== 0} onClick={() => void move(c, -1)} aria-label={t("tmc.move.up", { name: c.name })}>
                        <Icon name="chevronUp" size={14} />
                      </button>
                      <button type="button" className="tm__move-btn tm__move-btn--down" disabled={index === data.categories.length - 1 || busyId !== 0} onClick={() => void move(c, 1)} aria-label={t("tmc.move.down", { name: c.name })}>
                        <Icon name="chevronUp" size={14} />
                      </button>
                    </span>
                  ) : null}
                </td>
                <td role="cell" className="tbl__name tm__cc-name">
                  <span className="tm__cc-pos" aria-hidden="true">{formatNumber(index + 1)}</span>
                  <b className="tm__cc-title" translate="no">{c.name}</b>
                  <span className="tbl__pill"><ActivePill active={c.isActive} /></span>
                </td>
                <td role="cell" className="tm__cc-icon" data-label={t("tm.cat.col.icon")}>
                  <span className="tm__icobox" aria-hidden="true"><Icon name="tag" size={14} /></span>
                  {c.icon ? <code className="tm__icocode">{c.icon}</code> : <span className="muted">{t("tm.cat.noIcon")}</span>}
                </td>
                <td role="cell" className="tm__cc-count" data-label={t("tm.cat.col.treatments")}>
                  {c.treatmentCount > 0 ? (
                    <Link className="tm__hosp-link" href={treatmentsHref({ tab: "treatments", cat: c.id })} replace scroll={false} aria-label={t("tm.cat.treatmentsOf", { name: c.name, n: c.treatmentCount })}>
                      <span className="tm__table-only">{formatNumber(c.treatmentCount)}</span>
                      <span className="tm__card-only">{t("tm.cat.treatmentCount", { n: formatNumber(c.treatmentCount) })}</span>
                    </Link>
                  ) : (
                    <span className="tm__link-zero">
                      <span className="tm__table-only">0</span>
                      <span className="tm__card-only">{t("tm.cat.treatmentCount", { n: 0 })}</span>
                    </span>
                  )}
                </td>
                <td role="cell" className={canWrite ? "tm__cc-active tm__cc-active--w" : "tm__cc-active tm__table-only"} data-label={t("tm.cat.col.active")}>
                  {canWrite ? (
                    <button type="button" role="switch" aria-checked={c.isActive} className="tm__switch" disabled={busyId !== 0} onClick={() => void toggle(c, !c.isActive)} aria-label={t("tmc.toggle", { name: c.name })}>
                      <span className="tm__switch-knob" />
                    </button>
                  ) : (
                    <ActivePill active={c.isActive} />
                  )}
                </td>
                {canWrite ? (
                  <td role="cell" className="tm__cc-act">
                    <span className="tm__acts">
                      <button type="button" className="ibtn" onClick={() => setEditing(c)} aria-label={t("tmc.edit", { name: c.name })} title={t("tmw.edit")}>
                        <Icon name="edit" size={16} />
                      </button>
                      <button
                        type="button"
                        className="ibtn"
                        disabled={c.treatmentCount > 0}
                        onClick={() => setDeleting(c)}
                        aria-label={t("tmc.delete", { name: c.name })}
                        title={c.treatmentCount > 0 ? t("tmc.del.inUse", { n: formatNumber(c.treatmentCount) }) : t("tmw.delete")}
                      >
                        <Icon name="trash" size={16} />
                      </button>
                    </span>
                  </td>
                ) : null}
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {editing ? (
        <CategoryDialog
          category={editing === "new" ? null : editing}
          nextOrder={nextOrder}
          onClose={() => setEditing(null)}
          onSaved={(_, created) => {
            setEditing(null);
            toast.success(t(created ? "tmc.ok.created" : "tmc.ok.updated"));
            void refresh();
          }}
        />
      ) : null}
      <ConfirmDialog spec={deleteSpec} busy={busy} onConfirm={() => void removeOne()} onClose={() => { if (!busy) setDeleting(null); }} />
    </>
  );
}
