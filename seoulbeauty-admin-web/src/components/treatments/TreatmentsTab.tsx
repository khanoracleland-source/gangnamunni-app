"use client";

import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import Icon from "../Icon";
import { Pill } from "../hospitals/parts";
import AliasDialog from "./AliasDialog";
import TreatmentTable from "./TreatmentTable";
import type { TreatmentItem, TreatmentsData } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import {
  SEARCH_MAX_LENGTH,
  TREATMENT_SORT_KEYS,
  countByCategory,
  deriveStats,
  filterTreatments,
  hasTreatmentFilters,
  nextSort,
  sortTreatments,
  treatmentsHref,
  type ActiveFilter,
  type SortDir,
  type TreatmentSortKey,
  type TreatmentsQuery,
} from "@/lib/treatments";

/** Typing pauses this long before the address (and so the list) follows. */
const SEARCH_DEBOUNCE_MS = 150;

const STAT_CARDS: readonly { key: ActiveFilter; label: MessageKey; note: MessageKey }[] = [
  { key: "all", label: "tm.stats.total", note: "tm.stats.totalNote" },
  { key: "active", label: "tm.stats.active", note: "tm.stats.activeNote" },
  { key: "inactive", label: "tm.stats.inactive", note: "tm.stats.inactiveNote" },
];

export const SORT_LABELS: Record<TreatmentSortKey, MessageKey> = {
  order: "tm.col.order",
  hashtag: "tm.col.hashtag",
  name: "tm.col.name",
  category: "tm.col.category",
  duration: "tm.col.duration",
  recovery: "tm.col.recovery",
  price: "tm.col.priceShort",
  aliases: "tm.col.links",
  hospitals: "tm.col.links",
  active: "tm.col.active",
};

/** The treatments tab: count cards (they narrow the list), search and category chips, the sortable list. */
export default function TreatmentsTab({ data, query }: { data: TreatmentsData; query: TreatmentsQuery }) {
  const t = useT();
  const router = useRouter();
  // The count cards narrow the list without touching the address; the address keeps the rest.
  const [active, setActive] = useState<ActiveFilter>("all");
  const [aliasesOf, setAliasesOf] = useState<TreatmentItem | null>(null);

  const stats = useMemo(() => deriveStats(data.items, data.categories), [data]);
  const perCategory = useMemo(() => countByCategory(data.items), [data]);
  const rows = useMemo(() => sortTreatments(filterTreatments(data.items, query, active), query.sort, query.dir), [data, query, active]);
  const filtered = hasTreatmentFilters(query, active);
  const categoryChips = data.categories.filter((c) => c.isActive || c.id === query.cat);
  const selectedCategory = data.categories.find((c) => c.id === query.cat) ?? null;

  const replace = (patch: Partial<TreatmentsQuery>) => router.replace(treatmentsHref({ ...query, ...patch }), { scroll: false });
  const reset = () => {
    setActive("all");
    router.replace(treatmentsHref({ tab: "treatments" }), { scroll: false });
  };
  const sortBy = (key: TreatmentSortKey) => replace(nextSort(query, key));

  return (
    <>
      <ul className="stats tm__stats" role="group" aria-label={t("tm.stats.group")}>
        {STAT_CARDS.map((card) => (
          <li key={card.key}>
            <button type="button" className="stat stat--btn" aria-pressed={active === card.key} onClick={() => setActive(card.key)}>
              <span className="stat__label">{t(card.label)}</span>
              <span className="stat__value">{t("tm.count", { n: formatNumber(stats[card.key === "all" ? "total" : card.key]) })}</span>
              <span className="stat__note">{t(card.note)}</span>
            </button>
          </li>
        ))}
        <li>
          <button type="button" className="stat stat--btn" onClick={() => router.replace(treatmentsHref({ tab: "categories" }), { scroll: false })}>
            <span className="stat__label">{t("tm.stats.categories")}</span>
            <span className="stat__value">{t("tm.count", { n: formatNumber(stats.categories) })}</span>
            <span className="stat__note">{t("tm.stats.categoriesNote")}</span>
          </button>
        </li>
      </ul>

      <div className="filters tm__filters">
        <div className="tm__filters-row">
          <SearchBox q={query.q} onChange={(q) => replace({ q })} />
          <p className="filters__hint tm__filters-hint">{t("tm.find.hint")}</p>
        </div>
        <div className="tm__chips" role="group" aria-label={t("tm.find.category")}>
          <button type="button" className="tm__chip" aria-pressed={query.cat === 0} onClick={() => replace({ cat: 0 })}>
            {t("tm.find.all")}
            <span className="tm__chip-n">{formatNumber(data.items.length)}</span>
          </button>
          {categoryChips.map((c) => (
            <button key={c.id} type="button" className="tm__chip" aria-pressed={query.cat === c.id} onClick={() => replace({ cat: c.id })}>
              {c.name}
              <span className="tm__chip-n">{formatNumber(perCategory.get(c.id) ?? 0)}</span>
            </button>
          ))}
        </div>
      </div>

      <div className="list-head">
        <div className="list-head__main">
          <p className="list-head__count" role="status" aria-live="polite">
            {t("tm.list.count", { n: formatNumber(rows.length) })}
            {query.sort === "order" ? <span className="tm__list-order"> · {t("tm.list.order")}</span> : null}
          </p>
          {active !== "all" ? <Pill tone="info">{t(active === "active" ? "tm.stats.active" : "tm.stats.inactive")}</Pill> : null}
          {selectedCategory ? <Pill tone="info">{selectedCategory.name}</Pill> : null}
          {filtered ? <button type="button" className="txtbtn" onClick={reset}>{t("tm.find.reset")}</button> : null}
        </div>
        <p className="tm__sort-hint">
          <Icon name="sort" size={14} />
          <span>{t("tm.list.sortHint")}</span>
        </p>
        <SortSelect sort={query.sort} dir={query.dir} onChange={(sort, dir) => replace({ sort, dir })} />
      </div>

      {rows.length === 0 ? (
        <div className="empty tm__empty">
          <span className="state__ring"><Icon name={filtered ? "search" : "heart"} size={22} /></span>
          <p className="tm__empty-title">{t(filtered ? "tm.list.emptyFiltered" : "tm.list.empty")}</p>
          <p>{t(filtered ? "tm.list.emptyFilteredBody" : "tm.list.emptyBody")}</p>
          {filtered ? <button type="button" className="btn btn--ghost" onClick={reset}>{t("tm.find.reset")}</button> : null}
        </div>
      ) : (
        <TreatmentTable rows={rows} sort={query.sort} dir={query.dir} onSort={sortBy} onAliases={setAliasesOf} />
      )}

      {aliasesOf ? <AliasDialog item={aliasesOf} onClose={() => setAliasesOf(null)} /> : null}
    </>
  );
}

/**
 * The search box follows the address after a short pause in typing. While the admin types, the box shows the
 * typed text; once the address has caught up (or changed on its own, e.g. back button) it shows the address's text.
 */
function SearchBox({ q, onChange }: { q: string; onChange: (q: string) => void }) {
  const t = useT();
  const [draft, setDraft] = useState<{ text: string; base: string } | null>(null);
  const value = draft && draft.base === q ? draft.text : q;
  const wanted = value.trim().slice(0, SEARCH_MAX_LENGTH);

  useEffect(() => {
    if (wanted === q) return;
    const timer = window.setTimeout(() => onChange(wanted), SEARCH_DEBOUNCE_MS);
    return () => window.clearTimeout(timer);
  }, [wanted, q, onChange]);

  return (
    <div className="tm__search" role="search">
      <Icon name="search" size={16} className="tm__search-icon" />
      <input
        className="input"
        type="search"
        name="q"
        value={value}
        maxLength={SEARCH_MAX_LENGTH}
        placeholder={t("tm.find.placeholder")}
        aria-label={t("tm.find.search")}
        autoComplete="off"
        enterKeyHint="search"
        onChange={(e) => setDraft({ text: e.target.value, base: q })}
      />
    </div>
  );
}

/** On narrow screens the column headings are hidden, so the order is chosen here instead. */
function SortSelect({ sort, dir, onChange }: { sort: TreatmentSortKey; dir: SortDir; onChange: (sort: TreatmentSortKey, dir: SortDir) => void }) {
  const t = useT();
  const keys = TREATMENT_SORT_KEYS.filter((key) => key !== "hospitals");
  return (
    <label className="tm__sort-select">
      <span className="sr-only">{t("tm.list.sortBy")}</span>
      <select
        className="sel sel--field"
        value={`${sort}:${sort === "order" ? "asc" : dir}`}
        onChange={(e) => {
          const [key, d] = e.target.value.split(":");
          onChange(key as TreatmentSortKey, d === "desc" ? "desc" : "asc");
        }}
      >
        {keys.map((key) =>
          key === "order" ? (
            <option key={key} value="order:asc">{t("tm.list.order")}</option>
          ) : (
            <optgroup key={key} label={t(SORT_LABELS[key])}>
              <option value={`${key}:asc`}>{t(SORT_LABELS[key])} · {t("tm.list.sortDir.asc")}</option>
              <option value={`${key}:desc`}>{t(SORT_LABELS[key])} · {t("tm.list.sortDir.desc")}</option>
            </optgroup>
          ),
        )}
      </select>
    </label>
  );
}
