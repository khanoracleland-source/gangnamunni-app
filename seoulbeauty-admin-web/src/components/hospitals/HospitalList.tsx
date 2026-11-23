"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import ConfirmDialog, { type ConfirmSpec } from "./ConfirmDialog";
import HospitalReorder from "./HospitalReorder";
import { ActivePill, Chips, GradePill, Notice, Pill, Section } from "./parts";
import {
  PER_PAGE_OPTIONS,
  fetchHospitals,
  fetchMe,
  fetchTreatmentOptions,
  setHospitalActive,
  type HospitalList as HospitalListData,
  type HospitalListItem,
  type HospitalListQuery,
  type Me,
  type TreatmentGroup,
} from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatDay, formatNumber } from "@/lib/format";
import { failureKey } from "@/lib/hospitalEdit";
import { SEARCH_MAX_LENGTH, hasFilters, listHref, pageWindow, parseListQuery } from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";

type State = {
  phase: Phase;
  errorKind: ErrorKind;
  me: Me | null;
  list: HospitalListData | null;
  /** The last request for a new list failed: the rows on screen belong to an earlier search. */
  stale: boolean;
  /** Which request (address + retry count) the state above answers. */
  settled: string;
};

const INITIAL: State = { phase: "loading", errorKind: "load", me: null, list: null, stale: false, settled: "" };

/** What the count cards filter by. Exactly one is chosen; "all" is no filter. The pending card is for a super admin only. */
type StatFilter = "all" | "active" | "inactive" | "pending";

const STAT_FILTERS: readonly { key: StatFilter; label: MessageKey; note: MessageKey }[] = [
  { key: "all", label: "h.stats.total", note: "h.stats.totalNote" },
  { key: "active", label: "h.stats.active", note: "h.stats.activeNote" },
  { key: "inactive", label: "h.stats.inactive", note: "h.stats.inactiveNote" },
  { key: "pending", label: "h.stats.pending", note: "h.stats.pendingNote" },
];

const statFilterOf = (query: HospitalListQuery): StatFilter =>
  query.pending ? "pending" : query.active === "1" ? "active" : query.active === "0" ? "inactive" : "all";

/** The hospital whose active state is being asked about, and the state it would get. */
type ActiveAsk = { id: number; name: string; next: boolean };

/** The approved hospitals the admin may see: counts, search and filters, a table (cards on narrow screens), pages. */
export default function HospitalList() {
  const t = useT();
  const router = useRouter();
  const searchParams = useSearchParams();
  // The address is the single source of the list state, so back, forward and reload all work.
  const queryString = searchParams.toString();
  const query = useMemo(() => parseListQuery(new URLSearchParams(queryString)), [queryString]);
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  const [reorder, setReorder] = useState(false);
  const [groups, setGroups] = useState<TreatmentGroup[]>([]);
  const meRef = useRef<Me | null>(null);
  // 401 / 403 are final for this page view: stop asking.
  const halted = useRef(false);

  const requestId = `${listHref(query)}#${attempt}`;
  const busy = state.settled !== requestId && state.phase !== "forbidden" && state.phase !== "redirecting";

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const known = meRef.current;
      const [me, list] = await Promise.all([
        known ? Promise.resolve({ ok: true as const, data: known }) : fetchMe(controller.signal),
        fetchHospitals(query, controller.signal),
      ]);
      // A newer search replaced this one while it was on its way.
      if (controller.signal.aborted) return;
      const statuses = [me, list].map((r) => (r.ok ? 200 : r.status));

      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      if (statuses.includes(403)) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok) meRef.current = me.data;
      // The server says the page is switched off: show that, whatever the list request answered.
      if (me.ok && !me.data.features.hospitals) {
        setState({ ...INITIAL, phase: "error", errorKind: "hospitalsOff", me: me.data, settled: requestId });
        return;
      }
      if (me.ok && list.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, list: list.data, stale: false, settled: requestId });
        return;
      }
      // 404 on "who am I" means the whole panel is off; 404 on the list alone means only this page is.
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !list.ok && list.status === 404 ? "hospitalsOff" : !list.ok && list.status === 503 ? "session" : "load";
      setState((s) =>
        s.list && s.phase === "ready" && kind !== "disabled" && kind !== "hospitalsOff"
          ? { ...s, stale: true, settled: requestId }
          : { ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : s.me, settled: requestId },
      );
    };
    void run();
    return () => controller.abort();
  }, [query, attempt, requestId]);

  // The treatment filter's options: fetched once. Without them the list still works (the filter just has no choices).
  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchTreatmentOptions(controller.signal);
      if (!controller.signal.aborted && res.ok) setGroups(res.data);
    };
    void run();
    return () => controller.abort();
  }, []);

  const go = (patch: Partial<HospitalListQuery>) => router.push(listHref({ ...query, page: 1, ...patch }));
  const retry = () => setAttempt((n) => n + 1);

  const { phase, me, list, stale } = state;
  const selectedStat = statFilterOf(query);
  const pickStat = (key: StatFilter) => {
    if (key === selectedStat) return;
    go({ active: key === "active" ? "1" : key === "inactive" ? "0" : "all", pending: key === "pending" });
  };

  // Active switch: ask first, then the row is updated from the answer (the list is not loaded again).
  const [ask, setAsk] = useState<ActiveAsk | null>(null);
  const [working, setWorking] = useState(false);
  const [askError, setAskError] = useState<MessageKey | null>(null);
  const askActive = (target: ActiveAsk) => {
    setAskError(null);
    setAsk(target);
  };
  const confirmActive = async () => {
    if (!ask || working) return;
    setWorking(true);
    setAskError(null);
    const res = await setHospitalActive(ask.id, ask.next);
    setWorking(false);
    if (res.ok) {
      const { id } = ask;
      const isActive = res.data.isActive;
      setState((s) => {
        const row = s.list?.items.find((item) => item.id === id);
        if (!s.list || !row || row.isActive === isActive) return s;
        const delta = isActive ? 1 : -1;
        // With the active or inactive card chosen, a row that no longer matches leaves the list at once.
        const leaves = (query.active === "1" && !isActive) || (query.active === "0" && isActive);
        return {
          ...s,
          list: {
            ...s.list,
            total: leaves ? Math.max(0, s.list.total - 1) : s.list.total,
            stats: { ...s.list.stats, active: Math.max(0, s.list.stats.active + delta), inactive: Math.max(0, s.list.stats.inactive - delta) },
            items: leaves ? s.list.items.filter((item) => item.id !== id) : s.list.items.map((item) => (item.id === id ? { ...item, isActive } : item)),
          },
        };
      });
      setAsk(null);
      return;
    }
    if (res.status === 401) {
      halted.current = true;
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    setAskError(res.status === 403 || res.status === 404 ? failureKey(res.status) : "h.act.fail");
  };
  const askSpec: ConfirmSpec | null = ask
    ? {
        title: t(ask.next ? "h.act.activate.title" : "h.act.deactivate.title"),
        name: ask.name,
        body: t(ask.next ? "h.act.activate.body" : "h.act.deactivate.body"),
        confirmLabel: t(ask.next ? "h.act.activate" : "h.act.deactivate"),
        cancelLabel: t("h.c.cancel"),
        focus: "cancel",
      }
    : null;
  // Only a super admin may change it (the server refuses anyone else) and only while changes are switched on.
  const canToggle = me?.features.hospitalsWrite === true && me.admin.role === "super_admin";
  // The reorder screen: super admin only, while the order switch is on (the server refuses anyone else).
  const canOrder = canToggle && me?.features.hospitalsOrder === true;
  const reordering = canOrder && reorder;

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("h.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={busy} />;

  const scope = list?.scope ?? me?.admin.scope ?? "all";
  const isSuper = me?.admin.role === "super_admin";
  const statCards = STAT_FILTERS.filter((f) => f.key !== "pending" || isSuper);
  const knownTreatment = groups.some((g) => g.items.some((item) => item.id === query.treatmentId));

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("h.title")}
      description={t("h.desc")}
      screen={screen}
      actions={
        canOrder ? (
          <button type="button" className={reordering ? "btn btn--primary reo-toggle" : "btn btn--ghost reo-toggle"} onClick={() => setReorder((on) => !on)}>
            {t(reordering ? "h.reo.end" : "h.reo.start")}
          </button>
        ) : null
      }
    >
      {scope === "own" ? <Notice>{t("h.scope.own")}</Notice> : null}

      {list ? (
        <>
          <Section id="sec-h-stats" title={t("h.stats.title")} desc={scope === "own" ? t("h.stats.descOwn") : t("h.stats.desc")}>
            <ul className="stats" role="group" aria-label={t("h.stats.group")}>
              {statCards.map((f) => {
                const value = list.stats[f.key === "all" ? "total" : f.key];
                const pendingCard = f.key === "pending";
                return (
                  <Stat
                    key={f.key}
                    label={t(f.label)}
                    value={value}
                    note={t(pendingCard && value === 0 ? "h.stats.pendingNoteZero" : f.note)}
                    pressed={selectedStat === f.key}
                    dot={pendingCard && value > 0 && selectedStat !== f.key}
                    dim={pendingCard && value === 0}
                    onSelect={() => pickStat(f.key)}
                  />
                );
              })}
            </ul>
          </Section>

          {reordering ? <HospitalReorder onExit={() => setReorder(false)} onShowInactive={() => {
            setReorder(false);
            router.push(listHref({ ...query, active: "0", pending: false, search: "", treatmentId: 0, page: 1 }));
          }} /> : null}

          {reordering ? null : <Section id="sec-h-find" title={t("h.find.title")} desc={t("h.find.desc")}>
            <div className="filters">
              <SearchForm key={query.search} initial={query.search} onSearch={(search) => go({ search })} />
              <div className="filters__row">
                <label className="field">
                  <span className="field__label">{t("h.find.treatment")}</span>
                  <select className="sel sel--field" value={query.treatmentId} onChange={(e) => go({ treatmentId: Number(e.target.value) || 0 })}>
                    <option value={0}>{t("h.find.treatmentAll")}</option>
                    {query.treatmentId > 0 && !knownTreatment ? <option value={query.treatmentId}>{t("h.find.treatmentUnknown")}</option> : null}
                    {groups.map((group) => (
                      <optgroup key={group.category} label={group.category || t("h.find.treatmentOther")}>
                        {group.items.map((item) => (
                          <option key={item.id} value={item.id}>{item.name}</option>
                        ))}
                      </optgroup>
                    ))}
                  </select>
                </label>
                <label className="field field--narrow">
                  <span className="field__label">{t("h.find.perPage")}</span>
                  <select className="sel sel--field" value={query.perPage} onChange={(e) => go({ perPage: Number(e.target.value) })}>
                    {PER_PAGE_OPTIONS.map((n) => (
                      <option key={n} value={n}>{t("h.find.perPageValue", { n })}</option>
                    ))}
                  </select>
                </label>
              </div>
              <p className="filters__hint">{t("h.find.hint")}</p>
            </div>
          </Section>}

          {reordering ? null : <Section id="sec-h-list" title={t("h.list.title")} desc={t("h.list.desc")}>
            {stale ? (
              <Notice tone="warn" icon="alert" action={<button type="button" className="txtbtn" onClick={retry} disabled={busy}>{t("state.retry")}</button>}>
                {t("h.list.stale")}
              </Notice>
            ) : null}
            <div className="list-head">
              <div className="list-head__main">
                <p className="list-head__count" role="status" aria-live="polite">
                  {busy ? t("h.list.busy") : t("h.list.count", { n: formatNumber(list.total) })}
                </p>
                {selectedStat !== "all" ? <Pill tone="info">{t(STAT_FILTERS.find((f) => f.key === selectedStat)?.label ?? "h.stats.total")}</Pill> : null}
              </div>
              {hasFilters(query) ? <Link className="txtbtn" href="/hospitals">{t("h.find.reset")}</Link> : null}
            </div>
            <ListTable
              list={list}
              busy={busy}
              filtered={hasFilters(query)}
              pendingOnly={query.pending && query.search === "" && query.treatmentId === 0}
              pendingList={query.pending}
              canEdit={me?.features.hospitalsWrite === true}
              canToggle={canToggle}
              askingId={ask && working ? ask.id : null}
              onToggle={askActive}
            />
            <Pager query={query} total={list.total} />
          </Section>}
          <ConfirmDialog
            spec={askSpec}
            busy={working}
            error={askError ? t(askError) : null}
            onConfirm={() => void confirmActive()}
            onClose={() => {
              if (!working) setAsk(null);
            }}
          />
        </>
      ) : null}
    </AdminShell>
  );
}

function Stat({
  label,
  value,
  note,
  pressed,
  dot = false,
  dim = false,
  onSelect,
}: {
  label: string;
  value: number;
  note: string;
  pressed: boolean;
  /** A red dot after the label: something here waits for the admin. */
  dot?: boolean;
  /** Nothing to show: the number is drawn in the muted colour. */
  dim?: boolean;
  onSelect: () => void;
}) {
  const t = useT();
  return (
    <li>
      <button type="button" className={dim ? "stat stat--btn stat--dim" : "stat stat--btn"} aria-pressed={pressed} onClick={onSelect}>
        <span className={dot ? "stat__label stat__label--dot" : "stat__label"}>
          {label}
          {dot ? <span className="stat__dot" aria-hidden="true" /> : null}
        </span>
        <span className="stat__value">{t("h.count", { n: formatNumber(value) })}</span>
        <span className="stat__note">{note}</span>
      </button>
    </li>
  );
}

/** The search box keeps its own text until it is submitted; it is recreated whenever the address changes the search. */
function SearchForm({ initial, onSearch }: { initial: string; onSearch: (search: string) => void }) {
  const t = useT();
  const [value, setValue] = useState(initial);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    onSearch(value.trim().slice(0, SEARCH_MAX_LENGTH));
  };
  return (
    <form className="filters__search" role="search" onSubmit={submit}>
      <label className="field field--grow">
        <span className="field__label">{t("h.find.search")}</span>
        <input
          className="input"
          type="search"
          name="search"
          value={value}
          maxLength={SEARCH_MAX_LENGTH}
          placeholder={t("h.find.searchPlaceholder")}
          autoComplete="off"
          enterKeyHint="search"
          onChange={(e) => setValue(e.target.value)}
        />
      </label>
      <button type="submit" className="btn btn--primary">{t("h.find.submit")}</button>
    </form>
  );
}

function ListTable({
  list,
  busy,
  filtered,
  pendingOnly,
  pendingList,
  canEdit,
  canToggle,
  askingId,
  onToggle,
}: {
  list: HospitalListData;
  busy: boolean;
  filtered: boolean;
  /** The pending card is chosen and nothing else narrows the list. */
  pendingOnly: boolean;
  /** The rows are pending applications. */
  pendingList: boolean;
  canEdit: boolean;
  canToggle: boolean;
  askingId: number | null;
  onToggle: (target: ActiveAsk) => void;
}) {
  const t = useT();
  if (list.items.length === 0) {
    const message = list.total > 0 ? t("h.list.emptyPage") : pendingOnly ? t("h.list.emptyPending") : filtered ? t("h.list.emptyFiltered") : list.scope === "own" ? t("h.list.emptyOwn") : t("h.list.empty");
    return (
      <div className="empty" aria-busy={busy}>
        <Icon name="hospital" size={22} />
        <p>{message}</p>
        {list.total > 0 || filtered ? <Link className="txtbtn" href="/hospitals">{t("h.list.showAll")}</Link> : null}
      </div>
    );
  }
  // Below 900px every row is drawn as a card (see .tbl in globals.css): the cells become blocks, so their
  // table roles are written out to keep them a table for assistive technology, and each cell carries its
  // column name in data-label (drawn by CSS, so it is not a text node of the page).
  return (
    <table className={busy ? "tbl is-busy" : "tbl"} role="table" aria-busy={busy}>
      <caption className="sr-only">{t("h.list.caption")}</caption>
      <thead role="rowgroup">
        <tr role="row">
          <th role="columnheader" scope="col" className="tbl__id">#</th>
          <th role="columnheader" scope="col" className="tbl__name">{t("h.col.name")}</th>
          <th role="columnheader" scope="col" className="tbl__addr">{t("h.col.address")}</th>
          <th role="columnheader" scope="col" className="tbl__phone">{t("h.col.phone")}</th>
          <th role="columnheader" scope="col" className="tbl__cat">{t("h.col.categories")}</th>
          <th role="columnheader" scope="col" className="tbl__ad">{t("h.col.ad")}</th>
          <th role="columnheader" scope="col" className="tbl__active">{t("h.col.active")}</th>
          <th role="columnheader" scope="col" className="tbl__rating">{t("h.col.rating")}</th>
          <th role="columnheader" scope="col" className="tbl__date">{t("h.col.created")}</th>
          <th role="columnheader" scope="col" className="tbl__go">{t("h.col.detail")}</th>
        </tr>
      </thead>
      <tbody role="rowgroup">
        {list.items.map((h) => (
          <Row key={h.id} hospital={h} pending={pendingList} canEdit={canEdit} canToggle={canToggle} switching={askingId === h.id} onToggle={onToggle} />
        ))}
      </tbody>
    </table>
  );
}

function Row({
  hospital: h,
  pending,
  canEdit,
  canToggle,
  switching,
  onToggle,
}: {
  hospital: HospitalListItem;
  /** The row belongs to the pending applications list: view only. */
  pending: boolean;
  canEdit: boolean;
  canToggle: boolean;
  switching: boolean;
  onToggle: (target: ActiveAsk) => void;
}) {
  const t = useT();
  const name = h.name || t("h.noName");
  const href = `/hospitals/${h.id}`;
  return (
    <tr role="row" className={pending ? "is-pending" : undefined}>
      <td role="cell" className="tbl__id" data-label="#">{h.id}</td>
      <td role="cell" className="tbl__name" data-label={t("h.col.name")}>
        <Link href={href} className="tbl__link">{name}</Link>
        {pending ? <span className="tbl__pill"><Pill tone="warn">{t("h.pendingPill")}</Pill></span> : null}
      </td>
      <td role="cell" className="tbl__addr" data-label={t("h.col.address")}>{h.address || "—"}</td>
      <td role="cell" className="tbl__phone" data-label={t("h.col.phone")}>{h.phone || "—"}</td>
      <td role="cell" className="tbl__cat" data-label={t("h.col.categories")}><Chips items={h.categories} max={3} /></td>
      <td role="cell" className="tbl__ad" data-label={t("h.col.ad")}>{pending ? "—" : <GradePill grade={h.adGrade} />}</td>
      <td role="cell" className="tbl__active" data-label={t("h.col.active")}>{pending ? (
          <Pill tone="warn">{t("h.pendingPill")}</Pill>
        ) : canToggle ? (
          <span className="act">
            <ActivePill active={h.isActive} />
            <button
              type="button"
              role="switch"
              className="switch"
              aria-checked={h.isActive}
              aria-label={t("h.act.switch", { name })}
              aria-disabled={switching || undefined}
              aria-busy={switching || undefined}
              onClick={() => {
                if (!switching) onToggle({ id: h.id, name, next: !h.isActive });
              }}
            >
              <span className="switch__thumb" aria-hidden="true" />
            </button>
          </span>
        ) : (
          <ActivePill active={h.isActive} />
        )}
      </td>
      <td role="cell" className="tbl__rating" data-label={t("h.col.rating")}>
        {!pending && h.rating > 0 ? t("h.ratingValue", { rating: h.rating.toFixed(1), count: formatNumber(h.reviewCount) }) : "—"}
      </td>
      <td role="cell" className="tbl__date" data-label={t("h.col.created")}>{formatDay(h.createdAt)}</td>
      <td role="cell" className="tbl__go">
        <div className="tbl__acts">
          <Link href={href} className="ibtn tbl__open" aria-label={t("h.col.detailOf", { name })} title={t("h.col.detailOf", { name })}>
            <Icon name="eye" size={18} />
          </Link>
          {canEdit && !pending ? (
            <Link href={`${href}?edit=1`} className="ibtn tbl__open" aria-label={t("h.col.editOf", { name })} title={t("h.col.editOf", { name })}>
              <Icon name="edit" size={18} />
            </Link>
          ) : null}
        </div>
      </td>
    </tr>
  );
}

function Pager({ query, total }: { query: HospitalListQuery; total: number }) {
  const t = useT();
  const pages = Math.ceil(total / query.perPage);
  if (pages <= 1) return null;
  const page = Math.min(query.page, pages);
  const href = (p: number) => listHref({ ...query, page: p });
  return (
    <nav className="pager" aria-label={t("h.pager.label")}>
      {page > 1 ? (
        <Link className="pager__btn" href={href(page - 1)} scroll={false} aria-label={t("h.pager.prev")} title={t("h.pager.prev")}>
          <Icon name="chevron" size={16} className="pager__prev" />
        </Link>
      ) : null}
      {pageWindow(page, pages).map((p) => (
        <Link key={p} className="pager__btn" href={href(p)} scroll={false} aria-current={p === page ? "page" : undefined} aria-label={t("h.pager.page", { n: p })}>
          {p}
        </Link>
      ))}
      {page < pages ? (
        <Link className="pager__btn" href={href(page + 1)} scroll={false} aria-label={t("h.pager.next")} title={t("h.pager.next")}>
          <Icon name="chevron" size={16} />
        </Link>
      ) : null}
      <span className="pager__info">{t("h.pager.info", { page: formatNumber(page), pages: formatNumber(pages) })}</span>
    </nav>
  );
}
