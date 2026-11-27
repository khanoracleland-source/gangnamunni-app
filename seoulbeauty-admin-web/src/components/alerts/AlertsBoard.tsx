"use client";

import { useEffect, useMemo, useState, type KeyboardEvent, type MouseEvent } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import AlertRow from "./AlertRow";
import { ALERT_TABS, ALERT_UNREAD_EVENT, dayGroup, TAB_TYPE_LIST, alertMeta, resolveAlertLink, type AlertTab } from "@/lib/alerts";
import { fetchAlerts, markAlertRead, type AlertCounts, type AlertItem, type AlertsList } from "@/lib/alertsApi";
import { pageButtons } from "@/lib/bookings";
import type { Features, Scope } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { newPageFor } from "@/lib/menu";

const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);
const TAB_LABEL: Record<AlertTab, MessageKey> = { all: "alerts.tab.all", activity: "alerts.tab.activity", pending: "alerts.tab.pending", system: "alerts.tab.system" };
/** `now`: the clock when the answer arrived (the times of the rows are counted from it). */
type Loaded = { key: string; list: AlertsList | null; status: number; now: number };

/** The inbox: tabs with their unread numbers, filters, the list grouped by day, pages. */
export default function AlertsBoard({ tab, onTab, tick, onReload, onCounts, notice, features, scope }: {
  tab: AlertTab;
  onTab: (tab: AlertTab) => void;
  /** Goes up whenever the page asks for a fresh answer (after a change). */
  tick: number;
  onReload: () => void;
  /** Tells the page the unread numbers of the last answer (the heading buttons depend on them). */
  onCounts: (counts: AlertCounts) => void;
  notice: { tone: "ok" | "warn"; text: string } | null;
  features: Features | null;
  scope: Scope | null;
}) {
  const t = useT();
  /** The page of this panel when the link names an old-admin page that already exists here; else the old page. */
  const hrefOf = (link: string): string => {
    const old = resolveAlertLink(link);
    return old === "" ? "" : newPageFor(old, features, scope) ?? old;
  };
  const [type, setType] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [page, setPage] = useState(1);
  const [loaded, setLoaded] = useState<Loaded | null>(null);
  const [markFailed, setMarkFailed] = useState(false);

  const key = `${tab}|${type}|${from}|${to}|${page}|${tick}`;
  useEffect(() => {
    const c = new AbortController();
    void fetchAlerts({ tab, type, from, to, page }, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) { leaveToLogin(); return; }
      if (res.ok) {
        window.dispatchEvent(new CustomEvent<number>(ALERT_UNREAD_EVENT, { detail: res.data.counts.unread }));
        onCounts(res.data.counts);
      }
      setLoaded({ key, list: res.ok ? res.data : null, status: res.ok ? 200 : res.status, now: Date.now() });
    });
    return () => c.abort();
  }, [key, tab, type, from, to, page, onCounts]);

  const list = loaded?.list ?? null;
  const busy = loaded?.key !== key;
  const failed = !busy && loaded !== null && loaded.list === null;
  const now = loaded?.now ?? 0;
  const filtered = type !== "" || from !== "" || to !== "";

  const changeTab = (next: AlertTab) => { onTab(next); setType(""); setPage(1); };
  const onTabKey = (e: KeyboardEvent<HTMLElement>) => {
    const index = ALERT_TABS.indexOf(tab);
    let next = index;
    if (e.key === "ArrowRight") next = (index + 1) % ALERT_TABS.length;
    else if (e.key === "ArrowLeft") next = (index - 1 + ALERT_TABS.length) % ALERT_TABS.length;
    else if (e.key === "Home") next = 0;
    else if (e.key === "End") next = ALERT_TABS.length - 1;
    else return;
    e.preventDefault();
    document.getElementById(`al-tab-${ALERT_TABS[next]}`)?.focus();
    changeTab(ALERT_TABS[next]);
  };
  useEffect(() => {
    document.getElementById(`al-tab-${tab}`)?.scrollIntoView({ inline: "nearest", block: "nearest" });
  }, [tab]);

  const reset = () => { setType(""); setFrom(""); setTo(""); setPage(1); };

  // Opening an unread alert marks it read first and then follows the link; with a modifier key (new tab) the link opens as usual.
  const onOpen = (item: AlertItem, href: string, e: MouseEvent<HTMLAnchorElement>) => {
    if (item.read) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { void markAlertRead(item.id).then((res) => { if (res.ok) onReload(); }); return; }
    e.preventDefault();
    void markAlertRead(item.id).finally(() => window.location.assign(href));
  };
  const onMarkRead = (item: AlertItem) => {
    setMarkFailed(false);
    void markAlertRead(item.id).then((res) => {
      if (res.ok) onReload();
      else if (res.status === 401) leaveToLogin();
      else setMarkFailed(true);
    });
  };

  const groups = useMemo(() => {
    const out: { group: 0 | 1 | 2; items: AlertItem[] }[] = [];
    for (const it of list?.items ?? []) {
      const g = dayGroup(it.createdAt, now);
      const last = out[out.length - 1];
      if (last && last.group === g) last.items.push(it);
      else out.push({ group: g, items: [it] });
    }
    return out;
  }, [list, now]);

  const counts = list?.counts;
  const typeOptions = TAB_TYPE_LIST[tab];
  const ready = list !== null;

  return (
    <div className="al-page">
      {notice ? <Notice icon={notice.tone === "ok" ? "check" : "alert"} tone={notice.tone}>{notice.text}</Notice> : null}
      {markFailed ? <Notice icon="alert" tone="warn">{t("alerts.markFailed")}</Notice> : null}

      <div className="al-tabs" role="tablist" aria-label={t("alerts.tabs")} onKeyDown={onTabKey}>
        {ALERT_TABS.map((k) => {
          const selected = tab === k;
          const n = counts ? counts[k] : 0;
          return (
            <button key={k} type="button" role="tab" id={`al-tab-${k}`} aria-selected={selected} aria-controls="al-panel" tabIndex={selected ? 0 : -1} className="al-tab" onClick={() => changeTab(k)}>
              {t(TAB_LABEL[k])}
              {n > 0 ? <span className="al-tab__n">{formatNumber(n)}<span className="sr-only"> {t("alerts.unreadSr")}</span></span> : null}
            </button>
          );
        })}
      </div>

      <div id="al-panel" role="tabpanel" aria-labelledby={`al-tab-${tab}`} aria-busy={busy}>
        <div className="al-filter">
          <label className="field al-filter__type">
            <span className="field__label">{t("alerts.filter.type")}</span>
            <select className="sel sel--field" value={type} onChange={(e) => { setType(e.target.value); setPage(1); }}>
              <option value="">{t("alerts.filter.allTypes")}</option>
              {typeOptions.map((v) => <option key={v} value={v}>{t(alertMeta(v).label)}</option>)}
            </select>
          </label>
          <label className="field al-filter__date">
            <span className="field__label">{t("alerts.filter.from")}</span>
            <input className="input" type="date" value={from} max={to || undefined} onChange={(e) => { setFrom(e.target.value); setPage(1); }} />
          </label>
          <label className="field al-filter__date">
            <span className="field__label">{t("alerts.filter.to")}</span>
            <input className="input" type="date" value={to} min={from || undefined} onChange={(e) => { setTo(e.target.value); setPage(1); }} />
          </label>
          {filtered ? <button type="button" className="txtbtn al-filter__reset" onClick={reset}>{t("alerts.filter.reset")}</button> : null}
          {counts ? (
            <p className="al-stats">
              <span><i className="al-dot al-dot--total" aria-hidden="true" />{t("alerts.stat.total", { n: formatNumber(counts.total) })}</span>
              <span><i className="al-dot al-dot--unread" aria-hidden="true" /><b>{t("alerts.stat.unread", { n: formatNumber(counts.unread) })}</b></span>
              <span><i className="al-dot al-dot--read" aria-hidden="true" />{t("alerts.stat.read", { n: formatNumber(counts.read) })}</span>
            </p>
          ) : null}
        </div>

        {failed ? (
          <div className="al-empty" role="alert">
            <Icon name="unplug" size={24} />
            <p>{t("alerts.error")}</p>
            <button type="button" className="btn btn--ghost" onClick={() => onReload()}>{t("alerts.retry")}</button>
          </div>
        ) : !ready ? (
          <ul className="al-list al-skel" aria-hidden="true">
            {Array.from({ length: 6 }, (_, i) => <li key={i} className="al-rw"><span className="al-chip" /><div className="al-rw__main"><i /><i /><i /></div></li>)}
          </ul>
        ) : list.total === 0 ? (
          <div className="al-empty">
            <Icon name="bell" size={24} />
            <h3>{filtered ? t("alerts.empty.filtered.title") : t("alerts.empty.inbox.title")}</h3>
            <p>{filtered ? t("alerts.empty.filtered.body") : t("alerts.empty.inbox.body")}</p>
            {filtered ? <button type="button" className="btn btn--ghost" onClick={reset}>{t("alerts.filter.reset")}</button> : null}
          </div>
        ) : (
          <div className={busy ? "al-groups al-groups--busy" : "al-groups"}>
            {groups.map((g) => (
              <section key={`${g.group}-${g.items[0].id}`} className="al-group" aria-label={t(`alerts.group.${g.group}` as MessageKey)}>
                <h2>{t(`alerts.group.${g.group}` as MessageKey)}</h2>
                <ul className="al-list">
                  {g.items.map((it) => <AlertRow key={it.id} item={it} href={hrefOf(it.link)} now={now} onOpen={onOpen} onMarkRead={onMarkRead} />)}
                </ul>
              </section>
            ))}

            <nav className="pager" aria-label={t("alerts.pager.label")}>
              {list.pages > 1 ? (
                <>
                  <button type="button" className="pager__btn pager__prev" disabled={list.page === 1} onClick={() => setPage(list.page - 1)} aria-label={t("alerts.prev")}><Icon name="chevron" size={16} /></button>
                  {pageButtons(list.page, list.pages).map((n, i) => n === null ? (
                    <span key={`gap${i}`} className="muted" aria-hidden="true">…</span>
                  ) : (
                    <button key={n} type="button" className="pager__btn" aria-current={n === list.page ? "page" : undefined} aria-label={t("alerts.page", { n: String(n) })} onClick={() => setPage(n)}>{n}</button>
                  ))}
                  <button type="button" className="pager__btn" disabled={list.page === list.pages} onClick={() => setPage(list.page + 1)} aria-label={t("alerts.next")}><Icon name="chevron" size={16} /></button>
                </>
              ) : null}
              <p className="pager__info">{t("alerts.pager", { from: formatNumber((list.page - 1) * list.perPage + 1), to: formatNumber(Math.min(list.page * list.perPage, list.total)), total: formatNumber(list.total) })}</p>
            </nav>
          </div>
        )}
      </div>
    </div>
  );
}
