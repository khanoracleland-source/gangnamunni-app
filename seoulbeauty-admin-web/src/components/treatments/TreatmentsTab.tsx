"use client";

import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import Icon from "../Icon";
import { Pill } from "../hospitals/parts";
import ConfirmDialog, { type ConfirmSpec } from "../hospitals/ConfirmDialog";
import AliasDialog from "./AliasDialog";
import TreatmentDialog from "./TreatmentDialog";
import TreatmentTable, { type RowActions } from "./TreatmentTable";
import { failureKey } from "./writeErrors";
import { bulkTreatments, deleteTreatment, reorderTreatments, setTreatmentActive, type BulkAction, type TreatmentItem, type TreatmentsData } from "@/lib/api";
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
export default function TreatmentsTab({ data, query, canWrite, refresh }: { data: TreatmentsData; query: TreatmentsQuery; canWrite: boolean; refresh: () => Promise<void> }) {
  const t = useT();
  const router = useRouter();
  // The count cards narrow the list without touching the address; the address keeps the rest.
  const [active, setActive] = useState<ActiveFilter>("all");
  const [aliasesId, setAliasesId] = useState(0);
  // null = closed, "new" = add form, an item = edit form.
  const [editing, setEditing] = useState<TreatmentItem | "new" | null>(null);
  const [deleting, setDeleting] = useState<TreatmentItem | null>(null);
  const [bulkDelete, setBulkDelete] = useState(false);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [busyId, setBusyId] = useState(0);
  const [busy, setBusy] = useState(false);

  const stats = useMemo(() => deriveStats(data.items, data.categories), [data]);
  const perCategory = useMemo(() => countByCategory(data.items), [data]);
  const rows = useMemo(() => sortTreatments(filterTreatments(data.items, query, active), query.sort, query.dir), [data, query, active]);
  const filtered = hasTreatmentFilters(query, active);
  const categoryChips = data.categories.filter((c) => c.isActive || c.id === query.cat);
  const selectedCategory = data.categories.find((c) => c.id === query.cat) ?? null;

  const aliasesOf = data.items.find((i) => i.id === aliasesId) ?? null;
  // Only treatments that are on screen count as selected, so a filter never leaves hidden ones in the bulk action.
  const picked = useMemo(() => rows.filter((r) => selected.has(r.id)), [rows, selected]);
  const canMove = canWrite && query.sort === "order" && query.q === "" && active === "all";

  const fail = (res: Parameters<typeof failureKey>[0]) => toast.error(t(failureKey(res)));
  const toggleActive = async (item: TreatmentItem, next: boolean) => {
    setBusyId(item.id);
    const res = await setTreatmentActive(item.id, next);
    if (res.ok) {
      toast.success(t(next ? "tmw.ok.on" : "tmw.ok.off"));
      await refresh();
    } else fail(res);
    setBusyId(0);
  };
  const move = async (item: TreatmentItem, dir: -1 | 1) => {
    // The ids of the category in the order shown, with this one swapped with its neighbour.
    const ids = rows.filter((r) => r.categoryId === item.categoryId).map((r) => r.id);
    const at = ids.indexOf(item.id);
    const to = at + dir;
    if (at < 0 || to < 0 || to >= ids.length) return;
    [ids[at], ids[to]] = [ids[to], ids[at]];
    setBusyId(item.id);
    const res = await reorderTreatments(ids);
    if (res.ok) {
      toast.success(t("tmw.ok.moved"));
      await refresh();
    } else fail(res);
    setBusyId(0);
  };
  const runBulk = async (action: BulkAction) => {
    setBusy(true);
    const res = await bulkTreatments(action, picked.map((r) => r.id));
    if (res.ok) {
      const skipped = res.data.skipped.length;
      if (skipped > 0) toast.warning(t("tmw.ok.bulkSkipped", { n: formatNumber(res.data.done), m: formatNumber(skipped) }));
      else toast.success(t("tmw.ok.bulk", { n: formatNumber(res.data.done) }));
      setSelected(new Set());
      await refresh();
    } else fail(res);
    setBusy(false);
    setBulkDelete(false);
  };
  const removeOne = async () => {
    if (!deleting) return;
    setBusy(true);
    const res = await deleteTreatment(deleting.id);
    if (res.ok) {
      toast.success(t("tmw.ok.deleted"));
      setSelected((s) => new Set([...s].filter((id) => id !== deleting.id)));
      await refresh();
    } else if (res.status === 409 && res.error === "in_use") toast.error(t("tmw.del.inUse", { n: formatNumber(res.hospitalCount ?? 0) }));
    else fail(res);
    setBusy(false);
    setDeleting(null);
  };
  const actions: RowActions | undefined = canWrite
    ? {
        selected,
        canMove,
        busyId,
        onSelect: (id) => setSelected((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n; }),
        onSelectAll: (all) => setSelected(all ? new Set(rows.map((r) => r.id)) : new Set()),
        onEdit: setEditing,
        onDelete: setDeleting,
        onActive: (item, next) => void toggleActive(item, next),
        onMove: (item, dir) => void move(item, dir),
      }
    : undefined;
  const deleteSpec: ConfirmSpec | null = deleting
    ? { title: t("tmw.del.title"), name: deleting.name, body: t("tmw.del.body"), note: t("tmw.del.impact", { a: formatNumber(deleting.aliasCount), h: formatNumber(deleting.hospitalCount) }), confirmLabel: t("tmw.del.confirm"), cancelLabel: t("tmw.cancel"), focus: "cancel" }
    : bulkDelete
      ? { title: t("tmw.bulk.deleteTitle"), body: t("tmw.bulk.deleteBody", { n: formatNumber(picked.length) }), confirmLabel: t("tmw.del.confirm"), cancelLabel: t("tmw.cancel"), focus: "cancel" }
      : null;

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
        {canWrite ? <button type="button" className="btn btn--primary tm__new" onClick={() => setEditing("new")}>{t("tmw.new")}</button> : null}
      </div>
      {canWrite && picked.length > 0 ? (
        <div className="tm__bulk" role="group" aria-label={t("tmw.bulk.group")}>
          <span className="tm__bulk-n" role="status">{t("tmw.bulk.selected", { n: formatNumber(picked.length) })}</span>
          <button type="button" className="btn btn--ghost" disabled={busy} onClick={() => void runBulk("activate")}>{t("tmw.bulk.on")}</button>
          <button type="button" className="btn btn--ghost" disabled={busy} onClick={() => void runBulk("deactivate")}>{t("tmw.bulk.off")}</button>
          <button type="button" className="btn btn--ghost btn--reject" disabled={busy} onClick={() => setBulkDelete(true)}>{t("tmw.bulk.delete")}</button>
          <button type="button" className="txtbtn" disabled={busy} onClick={() => setSelected(new Set())}>{t("tmw.bulk.clear")}</button>
        </div>
      ) : null}
      {canWrite && !canMove ? <p className="filters__hint tm__move-hint">{t("tmw.move.hint")}</p> : null}

      {rows.length === 0 ? (
        <div className="empty tm__empty">
          <span className="state__ring"><Icon name={filtered ? "search" : "heart"} size={22} /></span>
          <p className="tm__empty-title">{t(filtered ? "tm.list.emptyFiltered" : "tm.list.empty")}</p>
          <p>{t(filtered ? "tm.list.emptyFilteredBody" : "tm.list.emptyBody")}</p>
          {filtered ? <button type="button" className="btn btn--ghost" onClick={reset}>{t("tm.find.reset")}</button> : null}
        </div>
      ) : (
        <TreatmentTable rows={rows} sort={query.sort} dir={query.dir} onSort={sortBy} onAliases={(item) => setAliasesId(item.id)} actions={actions} />
      )}

      {aliasesOf ? <AliasDialog item={aliasesOf} canWrite={canWrite} onChanged={refresh} onClose={() => setAliasesId(0)} /> : null}
      {editing ? (
        <TreatmentDialog
          item={editing === "new" ? null : editing}
          data={data}
          onClose={() => setEditing(null)}
          onSaved={(_, created) => {
            setEditing(null);
            toast.success(t(created ? "tmw.ok.created" : "tmw.ok.updated"));
            void refresh();
          }}
        />
      ) : null}
      <ConfirmDialog
        spec={deleteSpec}
        busy={busy}
        onConfirm={() => void (deleting ? removeOne() : runBulk("delete"))}
        onClose={() => {
          if (!busy) {
            setDeleting(null);
            setBulkDelete(false);
          }
        }}
      />
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
