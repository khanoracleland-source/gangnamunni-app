"use client";

import { useEffect, useState, type CSSProperties, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import MemberSheet from "./MemberSheet";
import StatusPill from "./StatusPill";
import { fetchMe, fetchMemberSummary, fetchMembers, type ApiResult, type MemberFilter, type MemberList, type MembersQuery, type MemberSort, type MemberSummary, type Me } from "@/lib/api";
import { pageButtons } from "@/lib/bookings";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { DEFAULT_QUERY, PER_PAGE_CHOICES, STATUS_FILTERS, dayOf, minuteOf, queryKey } from "@/lib/members";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

/** App members: counters, search and filters, the list (table or cards) and the detail sheet. Super admin only. */
export default function MembersPage() {
  const t = useT();
  const [phase, setPhase] = useState<Phase>("loading");
  const [errorKind, setErrorKind] = useState<ErrorKind>("load");
  const [me, setMe] = useState<Me | null>(null);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    void (async () => {
      const res = await fetchMe(controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setMe(res.data);
        if (res.data.admin.role !== "super_admin") setPhase("forbidden");
        else if (!res.data.features.members) {
          setErrorKind("membersOff");
          setPhase("error");
        } else setPhase("ready");
        return;
      }
      if (res.status === 401) {
        setPhase("redirecting");
        leaveToLogin();
      } else if (res.status === 403) setPhase("forbidden");
      else {
        setErrorKind(res.status === 404 ? "disabled" : res.status === 503 ? "session" : "load");
        setPhase("error");
      }
    })();
    return () => controller.abort();
  }, [attempt]);

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("mb.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={errorKind} onRetry={() => { setPhase("loading"); setAttempt((n) => n + 1); }} retrying={false} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("mb.title")} description={t("mb.subtitle")} screen={screen}>
      {phase === "ready" && me ? <Board me={me} /> : null}
    </AdminShell>
  );
}

function Board({ me }: { me: Me }) {
  const t = useT();
  const [query, setQuery] = useState<MembersQuery>(DEFAULT_QUERY);
  const [qInput, setQInput] = useState("");
  const [tick, setTick] = useState(0);
  const [openNo, setOpenNo] = useState<number | null>(null);
  const [got, setGot] = useState<{ key: string; res: ApiResult<MemberList> } | null>(null);
  const [summary, setSummary] = useState<MemberSummary | null>(null);

  const key = `${queryKey(query)}#${tick}`;
  useEffect(() => {
    const c = new AbortController();
    void fetchMembers(query, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      setGot({ key, res });
      // The server settles on a page that exists; follow it.
      if (res.ok && res.data.page !== query.page) setQuery((q) => ({ ...q, page: res.data.page }));
    });
    return () => c.abort();
    // `query` is rebuilt on every change; `key` stands for it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  useEffect(() => {
    const c = new AbortController();
    void fetchMemberSummary(c.signal).then((res) => {
      if (!c.signal.aborted && res.ok) setSummary(res.data);
    });
    return () => c.abort();
  }, [tick]);

  // The search text is applied a moment after typing stops.
  useEffect(() => {
    if (qInput.trim() === query.search.trim()) return;
    const id = window.setTimeout(() => setQuery((q) => ({ ...q, search: qInput, page: 1 })), 350);
    return () => window.clearTimeout(id);
  }, [qInput, query.search]);

  const set = (patch: Partial<MembersQuery>) => setQuery((q) => ({ ...q, ...patch, page: "page" in patch ? (patch.page as number) : 1 }));
  const sortBy = (s: MemberSort) => set(query.sort === s ? { order: query.order === "asc" ? "desc" : "asc" } : { sort: s, order: s === "joined" || s === "lastLogin" ? "desc" : "asc" });
  const refresh = () => setTick((n) => n + 1);
  const reset = () => { setQuery(DEFAULT_QUERY); setQInput(""); };
  const dirty = query.search !== "" || query.status !== "all" || qInput !== "";

  const list = got?.res.ok ? got.res.data : null;
  const loadingList = got?.key !== key;
  const failed = got?.key === key && !got.res.ok;
  const rows = list?.items ?? [];
  const tiles: { key: string; label: MessageKey; note: MessageKey; value: number | null; filter?: MemberFilter; danger?: boolean }[] = [
    { key: "total", label: "mb.stat.total", note: "mb.stat.total.note", value: summary?.total ?? null, filter: "all" },
    { key: "active", label: "mb.stat.active", note: "mb.stat.active.note", value: summary?.active ?? null, filter: "active" },
    { key: "blocked", label: "mb.stat.blocked", note: "mb.stat.blocked.note", value: summary?.blocked ?? null, filter: "blocked", danger: true },
    { key: "login", label: "mb.stat.login", note: "mb.stat.login.note", value: summary?.todayLogin ?? null },
    { key: "join", label: "mb.stat.join", note: "mb.stat.join.note", value: summary?.todayJoin ?? null },
  ];

  return (
    <div className="mb-page">
      <div className="mb-head">
        <h2 className="mb-head__t">{t("mb.title")}</h2>
        <p className="mb-head__n">{t("mb.count", { n: list ? formatNumber(list.total) : "—" })}</p>
      </div>

      <div className="mb-stats">
        {tiles.map((tile) => {
          const body = (
            <>
              <span className="mb-stat__l">{t(tile.label)}</span>
              <b className={tile.danger ? "mb-stat__v is-danger" : "mb-stat__v"}>{tile.value === null ? "—" : formatNumber(tile.value)}</b>
              <span className="mb-stat__n">{t(tile.note)}</span>
            </>
          );
          return tile.filter ? (
            <button key={tile.key} type="button" className="mb-stat" aria-pressed={query.status === tile.filter} onClick={() => set({ status: tile.filter })}>{body}</button>
          ) : (
            <div key={tile.key} className="mb-stat mb-stat--static">{body}</div>
          );
        })}
      </div>

      <form className="mb-filters" role="search" onSubmit={(e) => { e.preventDefault(); set({ search: qInput }); }}>
        <label className="mb-filters__q">
          <span className="sr-only">{t("mb.f.search")}</span>
          <Icon name="search" size={16} />
          <input className="input" type="search" maxLength={100} placeholder={t("mb.f.searchPh")} aria-label={t("mb.f.searchPh")} value={qInput} onChange={(e) => setQInput(e.target.value)} />
        </label>
        <div className="seg mb-seg" role="group" aria-label={t("mb.f.status")}>
          {STATUS_FILTERS.map((s) => (
            <button key={s} type="button" aria-pressed={query.status === s} onClick={() => set({ status: s })}>{t(`mb.st.${s}` as MessageKey)}</button>
          ))}
        </div>
        <label className="mb-filters__r">
          <span className="sr-only">{t("mb.f.rows")}</span>
          <select className="sel" value={query.perPage} onChange={(e) => set({ perPage: Number(e.target.value) })} aria-label={t("mb.f.rows")}>
            {PER_PAGE_CHOICES.map((n) => <option key={n} value={n}>{t("mb.f.rowsN", { n })}</option>)}
          </select>
        </label>
        <button type="submit" className="btn btn--primary mb-go">{t("mb.f.go")}</button>
      </form>

      {failed ? (
        <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={refresh}>{t("state.retry")}</button>}>{t("mb.err.list")}</Notice>
      ) : null}

      {got === null || (!list && loadingList) ? (
        <div className="mb-skel" role="status" aria-label={t("mb.loading")}>
          {[0, 1, 2, 3, 4].map((i) => <span key={i} className="mb-skel__row" aria-hidden="true" />)}
        </div>
      ) : null}

      {list && rows.length === 0 && !loadingList ? (
        <div className="mb-none">
          <Icon name={dirty ? "search" : "people"} size={28} />
          <h2>{t(dirty ? "mb.none.title" : "mb.empty.title")}</h2>
          <p>{t(dirty ? "mb.none.body" : "mb.empty.body")}</p>
          {dirty ? <button type="button" className="btn btn--ghost" onClick={reset}>{t("mb.f.reset")}</button> : null}
        </div>
      ) : null}

      {list && rows.length > 0 ? (
        <div className={loadingList ? "mb-list is-busy" : "mb-list"}>
          <div className="mb-tablewrap">
            <table className="mb-table" aria-label={t("mb.list")}>
              <colgroup><col className="c-no" /><col className="c-id" /><col className="c-name" /><col className="c-nick" /><col className="c-email" /><col className="c-phone" /><col className="c-joined" /><col className="c-login" /><col className="c-st" /><col className="c-view" /></colgroup>
              <thead>
                <tr>
                  <SortTh k="no" q={query} onSort={sortBy} />
                  <SortTh k="id" q={query} onSort={sortBy} />
                  <SortTh k="name" q={query} onSort={sortBy} />
                  <th scope="col">{t("mb.col.nick")}</th>
                  <th scope="col">{t("mb.col.email")}</th>
                  <th scope="col">{t("mb.col.phone")}</th>
                  <SortTh k="joined" q={query} onSort={sortBy} label="mb.col.joined" />
                  <SortTh k="lastLogin" q={query} onSort={sortBy} label="mb.col.login" />
                  <th scope="col">{t("mb.col.status")}</th>
                  <th scope="col"><span className="sr-only">{t("mb.col.view")}</span></th>
                </tr>
              </thead>
              <tbody>
                {rows.map((m) => (
                  <tr key={m.no} className={[m.status === "left" ? "is-left" : "", openNo === m.no ? "is-open" : ""].filter(Boolean).join(" ")}>
                    <td className="muted">{m.no}</td>
                    <td><button type="button" className="mb-link" title={m.id} onClick={() => setOpenNo(m.no)}>{m.id}</button></td>
                    <td className="mb-ell" title={m.name}>{m.name || "—"}</td>
                    <td className="mb-ell" title={m.nick}>{m.nick || "—"}</td>
                    <td className="mb-ell" title={m.email}>{m.email || "—"}</td>
                    <td className="mb-nw">{m.phone || "—"}</td>
                    <td className="mb-nw">{dayOf(m.joinedAt)}</td>
                    <td className="mb-nw">{minuteOf(m.lastLoginAt)}</td>
                    <td><StatusPill status={m.status} /></td>
                    <td><button type="button" className="ibtn mb-eye" aria-label={t("mb.view", { name: m.name || m.id })} onClick={() => setOpenNo(m.no)}><Icon name="eye" size={16} /></button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <ul className="mb-cards" aria-label={t("mb.list")}>
            {rows.map((m, i) => (
              <li key={m.no} style={{ "--i": i } as CSSProperties}>
                <button type="button" className={m.status === "left" ? "mb-card is-left" : "mb-card"} aria-label={t("mb.view", { name: m.name || m.id })} onClick={() => setOpenNo(m.no)}>
                  <span className="mb-card__top"><b className="mb-ell">{m.name || m.id}</b>{m.nick ? <span className="mb-card__nick mb-ell">{m.nick}</span> : null}<StatusPill status={m.status} /></span>
                  <span className="mb-card__id mb-ell">{m.id} · #{m.no}</span>
                  <span className="mb-card__line mb-ell">{m.email || "—"}</span>
                  <span className="mb-card__line">{m.phone || "—"}</span>
                  <span className="mb-card__dates">{t("mb.col.joined")} {dayOf(m.joinedAt)} · {t("mb.col.login")} {minuteOf(m.lastLoginAt)}</span>
                </button>
              </li>
            ))}
          </ul>

          <nav className="pager mb-pager" aria-label={t("mb.pager.label")}>
            {list.totalPages > 1 ? (
              <>
                <button type="button" className="pager__btn pager__prev" disabled={list.page === 1} onClick={() => set({ page: list.page - 1 })} aria-label={t("mb.prev")}><Icon name="chevron" size={16} /></button>
                {pageButtons(list.page, list.totalPages).map((n, i) => n === null ? (
                  <span key={`gap${i}`} className="muted" aria-hidden="true">…</span>
                ) : (
                  <button key={n} type="button" className="pager__btn" aria-current={n === list.page ? "page" : undefined} aria-label={t("mb.page", { n })} onClick={() => set({ page: n })}>{n}</button>
                ))}
                <button type="button" className="pager__btn" disabled={list.page === list.totalPages} onClick={() => set({ page: list.page + 1 })} aria-label={t("mb.next")}><Icon name="chevron" size={16} /></button>
              </>
            ) : null}
            <p className="pager__info">{t("mb.pager", { from: (list.page - 1) * list.perPage + 1, to: Math.min(list.page * list.perPage, list.total), total: formatNumber(list.total) })}</p>
          </nav>
        </div>
      ) : null}

      {openNo !== null ? <MemberSheet key={openNo} no={openNo} features={me.features} onClose={() => setOpenNo(null)} onChanged={refresh} /> : null}
    </div>
  );
}

function SortTh({ k, q, onSort, label }: { k: MemberSort; q: MembersQuery; onSort: (s: MemberSort) => void; label?: MessageKey }) {
  const t = useT();
  const on = q.sort === k;
  return (
    <th scope="col" aria-sort={on ? (q.order === "asc" ? "ascending" : "descending") : "none"}>
      <button type="button" className="mb-sortbtn" onClick={() => onSort(k)}>
        {t(label ?? (`mb.col.${k}` as MessageKey))}
        {on ? <span aria-hidden="true">{q.order === "asc" ? "▲" : "▼"}</span> : null}
      </button>
    </th>
  );
}
