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

type Props = {
  rows: TreatmentItem[];
  sort: TreatmentSortKey;
  dir: SortDir;
  onSort: (key: TreatmentSortKey) => void;
  onAliases: (item: TreatmentItem) => void;
};

/**
 * The treatment list: a dense table from 900px up (every column heading sorts), one card per treatment below.
 * Below 900px the cells become blocks, so their table roles are written out to keep them a table for assistive
 * technology, and each cell carries its column name in data-label (drawn by CSS).
 */
export default function TreatmentTable({ rows, sort, dir, onSort, onAliases }: Props) {
  const t = useT();
  return (
    <table className="tbl tbl--tm" role="table">
      <caption className="sr-only">{t("tm.list.caption")}</caption>
      <thead role="rowgroup">
        <tr role="row">
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
        </tr>
      </thead>
      <tbody role="rowgroup">
        {rows.map((item, index) => (
          <Row key={item.id} item={item} position={index + 1} onAliases={onAliases} />
        ))}
      </tbody>
    </table>
  );
}

function Row({ item, position, onAliases }: { item: TreatmentItem; position: number; onAliases: (item: TreatmentItem) => void }) {
  const t = useT();
  const image = imageUrl(item.defaultImageUrl);
  const preview = descriptionPreview(item.description);
  const price = priceRange(item.avgPriceMin, item.avgPriceMax);
  // Recovery of 0 days is shown as a dash, in the table and on the card alike.
  const recovery = item.recoveryDays > 0 ? t("tm.days", { n: formatNumber(item.recoveryDays) }) : <span className="muted">—</span>;
  return (
    <tr role="row" className={item.isActive ? undefined : "is-off"}>
      <td role="cell" className="tm__c-order" data-label={t("tm.col.order")}>
        <span className="tm__order">{formatNumber(position)}</span>
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
          {item.aliasCount > 0 ? (
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
      <td role="cell" className="tm__c-active tm__table-only" data-label={t("tm.col.active")}>
        <ActivePill active={item.isActive} />
      </td>
      <td role="cell" className="tbl__go tm__c-foot">
        <span className="tm__foot-order">{t("tm.orderOf", { n: formatNumber(position) })}</span>
      </td>
    </tr>
  );
}
