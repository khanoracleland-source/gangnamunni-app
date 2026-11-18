"use client";

import Link from "next/link";
import Icon from "../Icon";
import { ActivePill } from "../hospitals/parts";
import { TABLE_MODES, type TreatmentsData } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { treatmentsHref } from "@/lib/treatments";

const MODE_LABELS: Record<(typeof TABLE_MODES)[number], MessageKey> = { excel: "tm.cat.mode.excel", row: "tm.cat.mode.row", hybrid: "tm.cat.mode.hybrid" };

/** The categories tab: the app's table layout setting (shown, not changed here) and the category list. */
export default function CategoriesTab({ data }: { data: TreatmentsData }) {
  const t = useT();
  const active = data.categories.filter((c) => c.isActive).length;
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
              <input className="sr-only" type="radio" name="tm-table-mode" value={mode} checked={data.tableMode === mode} disabled readOnly />
              <span>{t(MODE_LABELS[mode])}</span>
            </label>
          ))}
        </div>
      </section>

      <div className="list-head">
        <div className="list-head__main">
          <p className="list-head__count" role="status">{t("tm.cat.count", { n: formatNumber(data.categories.length), active: formatNumber(active) })}</p>
        </div>
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
            </tr>
          </thead>
          <tbody role="rowgroup">
            {data.categories.map((c, index) => (
              <tr key={c.id} role="row" className={c.isActive ? undefined : "is-off"}>
                <td role="cell" className="tm__cc-order" data-label={t("tm.cat.col.order")}>
                  <span className="tm__order">{formatNumber(index + 1)}</span>
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
                <td role="cell" className="tm__cc-active tm__table-only" data-label={t("tm.cat.col.active")}>
                  <ActivePill active={c.isActive} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  );
}
