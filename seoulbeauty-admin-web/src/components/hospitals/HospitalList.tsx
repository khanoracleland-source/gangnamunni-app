"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { ActivePill, Chips, GradePill, Notice, Pill, Section } from "./parts";
import {
  PER_PAGE_OPTIONS,
  fetchHospitals,
  fetchMe,
  fetchTreatmentOptions,
  type ActiveFilter,
  type HospitalList as HospitalListData,
  type HospitalListItem,
  type HospitalListQuery,
  type Me,
  type TreatmentGroup,
} from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatDay, formatNumber } from "@/lib/format";
import { SEARCH_MAX_LENGTH, hasFilters, listHref, pageWindow, parseListQuery } from "@/lib/hospitals";
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

const ACTIVE_FILTERS: readonly ActiveFilter[] = ["all", "1", "0"];

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

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("h.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={busy} />;

  const scope = list?.scope ?? me?.admin.scope ?? "all";
  const knownTreatment = groups.some((g) => g.items.some((item) => item.id === query.treatmentId));

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("h.title")} description={t("h.desc")} screen={screen}>
      {scope === "own" ? <Notice>{t("h.scope.own")}</Notice> : null}

      {list ? (
        <>
          <Section id="sec-h-stats" title={t("h.stats.title")} desc={scope === "own" ? t("h.stats.descOwn") : t("h.stats.desc")}>
            <dl className="stats">
              <Stat label={t("h.stats.total")} value={list.stats.total} note={t("h.stats.totalNote")} />
              <Stat label={t("h.stats.active")} value={list.stats.active} note={t("h.stats.activeNote")} />
              <Stat label={t("h.stats.inactive")} value={list.stats.inactive} note={t("h.stats.inactiveNote")} />
              <Stat label={t("h.stats.unreviewed")} value={list.stats.unreviewed} note={t("h.stats.unreviewedNote")} />
            </dl>
          </Section>

          <Section id="sec-h-find" title={t("h.find.title")} desc={t("h.find.desc")}>
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
                <div className="field" role="group" aria-labelledby="h-active-label">
                  <span id="h-active-label" className="field__label">{t("h.find.active")}</span>
                  <div className="seg">
                    {ACTIVE_FILTERS.map((value) => (
                      <button key={value} type="button" aria-pressed={query.active === value} onClick={() => go({ active: value })}>
                        {t(`h.find.active.${value}`)}
                      </button>
                    ))}
                  </div>
                </div>
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
          </Section>

          <Section id="sec-h-list" title={t("h.list.title")} desc={t("h.list.desc")}>
            {stale ? (
              <Notice tone="warn" icon="alert" action={<button type="button" className="txtbtn" onClick={retry} disabled={busy}>{t("state.retry")}</button>}>
                {t("h.list.stale")}
              </Notice>
            ) : null}
            <div className="list-head">
              <p className="list-head__count" role="status" aria-live="polite">
                {busy ? t("h.list.busy") : t("h.list.count", { n: formatNumber(list.total) })}
              </p>
              {hasFilters(query) ? <Link className="txtbtn" href="/hospitals">{t("h.find.reset")}</Link> : null}
            </div>
            <ListTable list={list} busy={busy} filtered={hasFilters(query)} canEdit={me?.features.hospitalsWrite === true} />
            <Pager query={query} total={list.total} />
          </Section>
        </>
      ) : null}
    </AdminShell>
  );
}

function Stat({ label, value, note }: { label: string; value: number; note: string }) {
  const t = useT();
  return (
    <div className="stat">
      <dt className="stat__label">{label}</dt>
      <dd className="stat__value">{t("h.count", { n: formatNumber(value) })}</dd>
      <dd className="stat__note">{note}</dd>
    </div>
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

function ListTable({ list, busy, filtered, canEdit }: { list: HospitalListData; busy: boolean; filtered: boolean; canEdit: boolean }) {
  const t = useT();
  if (list.items.length === 0) {
    const message = list.total > 0 ? t("h.list.emptyPage") : filtered ? t("h.list.emptyFiltered") : list.scope === "own" ? t("h.list.emptyOwn") : t("h.list.empty");
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
          <Row key={h.id} hospital={h} canEdit={canEdit} />
        ))}
      </tbody>
    </table>
  );
}

function Row({ hospital: h, canEdit }: { hospital: HospitalListItem; canEdit: boolean }) {
  const t = useT();
  const name = h.name || t("h.noName");
  const href = `/hospitals/${h.id}`;
  return (
    <tr role="row">
      <td role="cell" className="tbl__id" data-label="#">{h.id}</td>
      <td role="cell" className="tbl__name" data-label={t("h.col.name")}>
        <Link href={href} className="tbl__link">{name}</Link>
        {h.hasUnreviewedChanges ? <Pill tone="info">{t("h.changed")}</Pill> : null}
      </td>
      <td role="cell" className="tbl__addr" data-label={t("h.col.address")}>{h.address || "—"}</td>
      <td role="cell" className="tbl__phone" data-label={t("h.col.phone")}>{h.phone || "—"}</td>
      <td role="cell" className="tbl__cat" data-label={t("h.col.categories")}><Chips items={h.categories} max={3} /></td>
      <td role="cell" className="tbl__ad" data-label={t("h.col.ad")}><GradePill grade={h.adGrade} /></td>
      <td role="cell" className="tbl__active" data-label={t("h.col.active")}><ActivePill active={h.isActive} /></td>
      <td role="cell" className="tbl__rating" data-label={t("h.col.rating")}>
        {h.rating > 0 ? t("h.ratingValue", { rating: h.rating.toFixed(1), count: formatNumber(h.reviewCount) }) : "—"}
      </td>
      <td role="cell" className="tbl__date" data-label={t("h.col.created")}>{formatDay(h.createdAt)}</td>
      <td role="cell" className="tbl__go">
        <div className="tbl__acts">
          <Link href={href} className="ibtn tbl__open" aria-label={t("h.col.detailOf", { name })} title={t("h.col.detailOf", { name })}>
            <Icon name="eye" size={18} />
          </Link>
          {canEdit ? (
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
