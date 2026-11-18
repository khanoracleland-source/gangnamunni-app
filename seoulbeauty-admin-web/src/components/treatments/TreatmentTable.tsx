"use client";

import Link from "next/link";
import Icon from "../Icon";
import { ActivePill, Pill } from "../hospitals/parts";
import type { TreatmentItem } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { descriptionPreview, hospitalsOfTreatmentHref, imageUrl, priceRange, type SortDir, type TreatmentSortKey } from "@/lib/treatments";

type Column = { key: TreatmentSortKey; label: MessageKey; className: string; numeric?: boolean };

const COLUMNS: readonly Column[] = [
  { key: "order", label: "tm.col.order", className: "tm__c-order" },
  { key: "hashtag", label: "tm.col.hashtag", className: "tm__c-hashtag" },
  { key: "name", label: "tm.col.name", className: "tbl__name tm__c-name" },
  { key: "category", label: "tm.col.category", className: "tm__c-cat" },
  { key: "duration", label: "tm.col.duration", className: "tm__c-num tm__c-duration", numeric: true },
  { key: "recovery", label: "tm.col.recovery", className: "tm__c-num tm__c-recovery", numeric: true },
  { key: "price", label: "tm.col.price", className: "tm__c-num tm__c-price", numeric: true },
  { key: "aliases", label: "tm.col.links", className: "tm__c-links" },
  { key: "active", label: "tm.col.active", className: "tm__c-active" },
];

/** What the list can do when changes are switched on; absent = read only. */
export type RowActions = {
  selected: Set<number>;
  /** Moving is offered only in the stored order with no search or card filter. */
  canMove: boolean;
  onSelect: (id: number) => void;
  onSelectAll: (all: boolean) => void;
  onEdit: (item: TreatmentItem) => void;
  onDelete: (item: TreatmentItem) => void;
  onActive: (item: TreatmentItem, active: boolean) => void;
  onMove: (item: TreatmentItem, dir: -1 | 1) => void;
  /** Id of the treatment whose change is being saved; its controls wait. */
  busyId: number;
};

type Props = {
  rows: TreatmentItem[];
  sort: TreatmentSortKey;
  dir: SortDir;
  onSort: (key: TreatmentSortKey) => void;
  onAliases: (item: TreatmentItem) => void;
  actions?: RowActions;
};

/**
 * The treatment list: a dense table from 900px up (every column heading sorts), one card per treatment below.
 * Below 900px the cells become blocks, so their table roles are written out to keep them a table for assistive
 * technology, and each cell carries its column name in data-label (drawn by CSS).
 */
export default function TreatmentTable({ rows, sort, dir, onSort, onAliases, actions }: Props) {
  const t = useT();
  const selectedCount = actions ? rows.filter((r) => actions.selected.has(r.id)).length : 0;
  const allSelected = rows.length > 0 && selectedCount === rows.length;
  return (
    <table className="tbl tbl--tm" role="table">
      <caption className="sr-only">{t("tm.list.caption")}</caption>
      <thead role="rowgroup">
        <tr role="row">
          {actions ? (
            <th role="columnheader" scope="col" className="tm__c-sel">
              <input
                type="checkbox"
                checked={allSelected}
                ref={(el) => {
                  if (el) el.indeterminate = selectedCount > 0 && !allSelected;
                }}
                onChange={(e) => actions.onSelectAll(e.target.checked)}
                aria-label={t("tmw.bulk.all")}
              />
            </th>
          ) : null}
          {COLUMNS.map((col) => {
            const sorted = sort === col.key;
            return (
              <th key={col.key} role="columnheader" scope="col" className={col.className} aria-sort={sorted ? (dir === "asc" ? "ascending" : "descending") : "none"}>
                <button type="button" className={col.numeric ? "tm__sort tm__sort--num" : "tm__sort"} onClick={() => onSort(col.key)} aria-label={t("tm.sort.of", { name: t(col.label) })}>
                  <span>{t(col.label)}</span>
                  <Icon name="chevronUp" size={12} className={sorted ? (dir === "asc" ? "tm__sort-icon is-asc" : "tm__sort-icon is-desc") : "tm__sort-icon"} />
                </button>
              </th>
            );
          })}
          {actions ? <th role="columnheader" scope="col" className="tm__c-act"><span className="sr-only">{t("tmw.edit")}</span></th> : null}
        </tr>
      </thead>
      <tbody role="rowgroup">
        {rows.map((item, index) => (
          <Row
            key={item.id}
            item={item}
            position={index + 1}
            onAliases={onAliases}
            actions={actions}
            first={actions?.canMove ? rows.findIndex((r) => r.categoryId === item.categoryId) === index : false}
            last={actions?.canMove ? rows.map((r) => r.categoryId).lastIndexOf(item.categoryId) === index : false}
          />
        ))}
      </tbody>
    </table>
  );
}

function Row({ item, position, onAliases, actions, first, last }: { item: TreatmentItem; position: number; onAliases: (item: TreatmentItem) => void; actions?: RowActions; first: boolean; last: boolean }) {
  const t = useT();
  const image = imageUrl(item.defaultImageUrl);
  const preview = descriptionPreview(item.description);
  const price = priceRange(item.avgPriceMin, item.avgPriceMax);
  // Recovery of 0 days is shown as a dash, in the table and on the card alike.
  const recovery = item.recoveryDays > 0 ? t("tm.days", { n: formatNumber(item.recoveryDays) }) : <span className="muted">—</span>;
  return (
    <tr role="row" className={item.isActive ? undefined : "is-off"}>
      {actions ? (
        <td role="cell" className="tm__c-sel">
          <input type="checkbox" checked={actions.selected.has(item.id)} onChange={() => actions.onSelect(item.id)} aria-label={t("tmw.bulk.one", { name: item.name })} />
        </td>
      ) : null}
      <td role="cell" className={actions?.canMove ? "tm__c-order tm__c-order--w" : "tm__c-order"} data-label={t("tm.col.order")}>
        <span className="tm__order">{formatNumber(position)}</span>
        {actions?.canMove ? (
          <span className="tm__move">
            <button type="button" className="tm__move-btn" disabled={first || actions.busyId > 0} onClick={() => actions.onMove(item, -1)} aria-label={t("tmw.move.up", { name: item.name })}>
              <Icon name="chevronUp" size={14} />
            </button>
            <button type="button" className="tm__move-btn tm__move-btn--down" disabled={last || actions.busyId > 0} onClick={() => actions.onMove(item, 1)} aria-label={t("tmw.move.down", { name: item.name })}>
              <Icon name="chevronUp" size={14} />
            </button>
          </span>
        ) : null}
      </td>
      <td role="cell" className="tm__c-hashtag" data-label={t("tm.col.hashtag")}>
        <span className="tm__hashtag" title={item.hashtag}>{item.hashtag || "—"}</span>
      </td>
      <td role="cell" className="tbl__name tm__c-name">
        <span className="tm__namebox">
        <span className="tm__img">
          {image ? (
            // eslint-disable-next-line @next/next/no-img-element -- stored under the old panel's folder, no optimizer needed
            <img src={image} alt="" width={44} height={44} loading="lazy" />
          ) : (
            <Icon name="image" size={16} />
          )}
        </span>
        <span className="tm__name">
          <span className="tm__name-text" translate="no" title={item.name}>{item.name}</span>
          <span className="tm__name-tag" translate="no">{item.hashtag}</span>
          <span className="tm__desc" title={item.description}>{preview}</span>
        </span>
        </span>
        <span className="tbl__pill"><ActivePill active={item.isActive} /></span>
      </td>
      <td role="cell" className="tm__c-desc">
        {preview ? <span className="tm__desc-card" title={item.description}>{preview}</span> : null}
      </td>
      <td role="cell" className="tm__c-cat" data-label={t("tm.col.category")}>
        {item.categoryName ? <Pill tone="info">{item.categoryName}</Pill> : <span className="muted">{t("tm.noCategory")}</span>}
      </td>
      <td role="cell" className="tm__c-num tm__c-duration" data-label={t("tm.col.durationRecovery")}>
        {item.durationMin > 0 ? t("tm.minutes", { n: formatNumber(item.durationMin) }) : <span className="muted">—</span>}
        <span className="tm__card-only"> · {recovery}</span>
      </td>
      <td role="cell" className="tm__c-num tm__c-recovery tm__table-only" data-label={t("tm.col.recovery")}>
        {recovery}
      </td>
      <td role="cell" className="tm__c-num tm__c-price" data-label={t("tm.col.priceShort")}>
        {price ? (
          <b className="tm__price">
            {price}
            <span className="tm__card-only"> {t("tm.priceUnit")}</span>
          </b>
        ) : (
          <span className="muted">—</span>
        )}
      </td>
      <td role="cell" className="tm__c-links" data-label={t("tm.col.links")}>
        <span className="tm__links">
          {item.aliasCount > 0 || actions ? (
            <button type="button" className="tm__alias-btn" onClick={() => onAliases(item)} aria-label={t("tm.aliasesOf", { name: item.name, n: item.aliasCount })}>
              {formatNumber(item.aliasCount)}
            </button>
          ) : (
            <span className="tm__link-zero">0</span>
          )}
          <span className="tm__dot" aria-hidden="true">·</span>
          {item.hospitalCount > 0 ? (
            <Link className="tm__hosp-link" href={hospitalsOfTreatmentHref(item.id)} aria-label={t("tm.hospitalsOf", { name: item.name, n: item.hospitalCount })}>
              {formatNumber(item.hospitalCount)}
            </Link>
          ) : (
            <span className="tm__link-zero">0</span>
          )}
        </span>
      </td>
      <td role="cell" className={actions ? "tm__c-active tm__c-active--w" : "tm__c-active tm__table-only"} data-label={t("tm.col.active")}>
        {actions ? (
          <button
            type="button"
            role="switch"
            aria-checked={item.isActive}
            className="tm__switch"
            disabled={actions.busyId === item.id}
            onClick={() => actions.onActive(item, !item.isActive)}
            aria-label={t("tmw.toggle", { name: item.name })}
          >
            <span className="tm__switch-knob" />
          </button>
        ) : (
          <ActivePill active={item.isActive} />
        )}
      </td>
      {actions ? (
        <td role="cell" className="tm__c-act">
          <span className="tm__acts">
            <button type="button" className="ibtn" onClick={() => actions.onEdit(item)} aria-label={`${t("tmw.edit")} · ${item.name}`} title={t("tmw.edit")}>
              <Icon name="edit" size={16} />
            </button>
            <button
              type="button"
              className="ibtn"
              onClick={() => actions.onDelete(item)}
              disabled={item.hospitalCount > 0}
              aria-label={`${t("tmw.delete")} · ${item.name}`}
              title={item.hospitalCount > 0 ? t("tmw.del.inUse", { n: formatNumber(item.hospitalCount) }) : t("tmw.delete")}
            >
              <Icon name="trash" size={16} />
            </button>
          </span>
        </td>
      ) : null}
      <td role="cell" className="tbl__go tm__c-foot">
        <span className="tm__foot-order">{t("tm.orderOf", { n: formatNumber(position) })}</span>
      </td>
    </tr>
  );
}
