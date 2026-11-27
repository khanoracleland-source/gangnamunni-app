"use client";

import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";
import { useRouter, useSearchParams } from "next/navigation";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { LedgerRow } from "./NtParts";
import { useWide } from "./useWide";
import type { ApiResult } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { minuteOf } from "@/lib/members";
import { notificationsHref, parseId, typeKey } from "@/lib/notifications";
import { fetchHistory, fetchHistoryDetail, type HistoryDetail, type HistoryList } from "@/lib/notificationsApi";
import { pageButtons } from "@/lib/bookings";

const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);
const FILTERS = ["", "sent", "failed", "skipped"] as const;
const CODES = ["setting", "night", "no_token", "topic", "fcm_disabled", "apns_auth", "deadline", "credentials", "in_app_error"];

/** The history of sends: the list on the left, what happened to one send on the right (over the page below 900px). */
export default function HistoryTab() {
  const t = useT();
  const router = useRouter();
  const wide = useWide();
  const selected = parseId(useSearchParams().get("id"));
  const [page, setPage] = useState(1);
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<{ key: string; res: ApiResult<HistoryList> } | null>(null);
  const key = `${page}#${tick}`;

  useEffect(() => {
    const c = new AbortController();
    void fetchHistory(page, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      setGot({ key, res });
      // The server settles on a page that exists; follow it.
      if (res.ok && res.data.page !== page) setPage(res.data.page);
    });
    return () => c.abort();
    // `page` and `tick` are what `key` stands for.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const open = (id: number) => router.replace(notificationsHref("history", id), { scroll: false });
  const close = () => router.replace(notificationsHref("history"), { scroll: false });
  const list = got?.res.ok ? got.res.data : null;
  const loading = got?.key !== key;
  const failed = got?.key === key && !got.res.ok;
  const pages = list ? Math.max(1, Math.ceil(list.total / Math.max(1, list.perPage))) : 1;

  return (
    <div className="nt-hist">
      <div className="nt-hist__list">
        <div className="nt-toolbar">
          <p className="nt-toolbar__n" aria-live="polite">{list ? t("nt.h.count", { n: formatNumber(list.total) }) : " "}</p>
          <button type="button" className="btn btn--ghost" onClick={() => setTick((n) => n + 1)} disabled={loading}><Icon name="refresh" size={16} /> {t("nt.h.refresh")}</button>
        </div>
        {failed ? (
          <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost" onClick={() => setTick((n) => n + 1)}>{t("nt.h.retry")}</button>}>{t("nt.h.failed")}</Notice>
        ) : null}
        {list && !list.available ? <Notice tone="warn" icon="alert">{t("nt.h.unavailable")}</Notice> : null}
        {loading && !list ? <div className="nt-skel" aria-hidden="true"><i /><i /><i /><i /></div> : null}
        {list && list.available && list.items.length === 0 ? <p className="nt-empty">{t("nt.h.empty")}</p> : null}
        {list && list.items.length > 0 ? (
          <ul className="nt-li" aria-busy={loading}>
            {list.items.map((it) => (
              <li key={it.id}>
                <button type="button" className="nt-li__row" aria-current={it.id === selected ? "true" : undefined} onClick={() => open(it.id)}>
                  <span className="nt-li__t">{it.isTest ? <em className="nt-tag">{t("nt.h.test")}</em> : null}{it.title}</span>
                  <span className="nt-li__k">{t("nt.h.push", { n: formatNumber(it.pushSent) })}{it.pushFailed > 0 ? <b>{t("nt.h.failedN", { n: formatNumber(it.pushFailed) })}</b> : null}</span>
                  <span className="nt-li__m">{minuteOf(it.createdAt)} · {it.adminUsername} · {it.target} · {t(typeKey(it.type))}</span>
                </button>
              </li>
            ))}
          </ul>
        ) : null}
        {list && pages > 1 ? (
          <nav className="pager nt-pager" aria-label={t("nt.h.pager")}>
            <button type="button" className="pager__btn pager__prev" disabled={page === 1 || loading} onClick={() => setPage(page - 1)} aria-label={t("nt.h.prev")}><Icon name="chevron" size={16} /></button>
            {pageButtons(page, pages).map((n, i) => (n === null ? <span key={`g${i}`} className="muted" aria-hidden="true">…</span> : (
              <button key={n} type="button" className="pager__btn" aria-current={n === page ? "page" : undefined} aria-label={t("nt.h.page", { n })} onClick={() => setPage(n)} disabled={loading}>{n}</button>
            )))}
            <button type="button" className="pager__btn" disabled={page === pages || loading} onClick={() => setPage(page + 1)} aria-label={t("nt.h.next")}><Icon name="chevron" size={16} /></button>
          </nav>
        ) : null}
      </div>

      {wide ? (
        <aside className="nt-det" aria-label={t("nt.d.title")}>
          {selected > 0 ? <DetailView key={selected} id={selected} /> : <p className="nt-empty">{t("nt.h.pickOne")}</p>}
        </aside>
      ) : selected > 0 ? (
        <DetailSheet onClose={close}><DetailView key={selected} id={selected} /></DetailSheet>
      ) : null}
    </div>
  );
}

/** The detail below 900px: a drawer from the right (tablet) or the whole screen (phone), as a modal dialog. */
function DetailSheet({ onClose, children }: { onClose: () => void; children: ReactNode }) {
  const t = useT();
  const titleId = useId();
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);
  return createPortal(
    <dialog ref={ref} className="dialog nt-ddlg" aria-labelledby={titleId} onClose={onClose} onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="nt-ddlg__box">
        <div className="nt-ddlg__bar"><button type="button" className="btn btn--ghost" onClick={onClose} data-autofocus=""><Icon name="chevron" size={16} className="nt-back" /> {t("nt.d.close")}</button></div>
        <span id={titleId} className="sr-only">{t("nt.d.title")}</span>
        {children}
      </div>
    </dialog>,
    document.body,
  );
}

const statusKey = (s: string): MessageKey => (s === "sent" ? "nt.d.st.sent" : s === "failed" ? "nt.d.st.failed" : "nt.d.st.skipped");
const filterKey = (f: (typeof FILTERS)[number]): MessageKey => (f === "" ? "nt.d.f.all" : f === "sent" ? "nt.d.f.sent" : f === "failed" ? "nt.d.f.failed" : "nt.d.f.skipped");

type PlatformStat = { tokens: number; sent: number; failed: number; skipped: number };
const asObj = (v: unknown): Record<string, unknown> => (v !== null && typeof v === "object" && !Array.isArray(v) ? (v as Record<string, unknown>) : {});
const n0 = (v: unknown): number => (typeof v === "number" && Number.isFinite(v) ? v : 0);

/** What happened to one send: the totals, the push numbers per platform and the result for each recipient. */
function DetailView({ id }: { id: number }) {
  const t = useT();
  const [status, setStatus] = useState<(typeof FILTERS)[number]>("");
  const [page, setPage] = useState(1);
  const [got, setGot] = useState<{ key: string; res: ApiResult<HistoryDetail> } | null>(null);
  const key = `${id}#${status}#${page}`;

  useEffect(() => {
    const c = new AbortController();
    void fetchHistoryDetail(id, page, status, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      setGot({ key, res });
    });
    return () => c.abort();
    // `key` stands for id, status and page.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  if (got?.key !== key && !got) return <p className="nt-empty">{t("nt.d.loading")}</p>;
  const res = got?.res;
  if (res && !res.ok) return <Notice tone="warn" icon="alert">{t(res.status === 404 ? "nt.d.notfound" : "nt.d.failed")}</Notice>;
  const d = res?.ok ? res.data : null;
  if (!d) return <p className="nt-empty">{t("nt.d.loading")}</p>;
  const loading = got?.key !== key;

  const details = asObj(d.details);
  const direct = asObj(details.direct);
  const platforms = Object.entries(asObj(direct.platform_stats)).map(([name, v]): [string, PlatformStat] => {
    const o = asObj(v);
    return [name, { tokens: n0(o.tokens), sent: n0(o.sent), failed: n0(o.failed), skipped: n0(o.skipped) }];
  });
  const topic = asObj(details.android_topic);
  const dead = n0(direct.dead_tokens);
  const pages = Math.max(1, Math.ceil(d.items.total / Math.max(1, d.items.perPage)));
  const code = (c: string): string => (c === "" ? "" : CODES.includes(c) ? t(`nt.code.${c}` as MessageKey) : c);

  return (
    <div className="nt-dv" aria-busy={loading}>
      <h3 className="nt-dv__t">{d.isTest ? <em className="nt-tag">{t("nt.h.test")}</em> : null}{d.title}</h3>
      <p className="nt-dv__m">{minuteOf(d.createdAt)} · {d.adminUsername} · {d.target} · {t(typeKey(d.type))}</p>
      {d.message !== "" ? <p className="nt-dv__msg">{d.message}</p> : null}
      {d.link !== "" ? <p className="nt-dv__link">{d.link}</p> : null}
      <dl className="nt-led">
        <LedgerRow label={t("nt.led.resolved")} value={t("nt.unit.people", { n: formatNumber(d.resolved) })} />
        <LedgerRow label={t("nt.led.inapp")} value={t("nt.unit.people", { n: formatNumber(d.inApp) })} />
        <LedgerRow label={t("nt.r.pushOk")} value={formatNumber(d.pushSent)} big />
        <LedgerRow label={t("nt.r.pushFail")} value={formatNumber(d.pushFailed)} />
        <LedgerRow label={t("nt.r.pushSkip")} value={t("nt.unit.people", { n: formatNumber(d.pushSkipped) })} />
        {dead > 0 ? <LedgerRow label={t("nt.r.dead")} value={formatNumber(dead)} /> : null}
      </dl>
      {d.pushError !== "" ? <Notice tone="warn" icon="alert">{d.pushError === "sending" ? t("nt.d.sending") : d.pushError}</Notice> : null}
      {platforms.length > 0 || topic.topic ? (
        <div className="nt-plat">
          <p className="nt-dv__h">{t("nt.d.platforms")}</p>
          {platforms.map(([name, p]) => <p key={name} className="nt-plat__r"><b>{name}</b> {t("nt.d.platRow", { sent: formatNumber(p.sent), failed: formatNumber(p.failed), skipped: formatNumber(p.skipped) })}</p>)}
          {topic.topic ? <p className="nt-plat__r"><b>Android topic</b> {t("nt.d.topicRow", { sent: formatNumber(n0(topic.sent)), failed: formatNumber(n0(topic.failed)) })}</p> : null}
        </div>
      ) : null}

      <p className="nt-dv__h">{t("nt.d.rcpt")}</p>
      {!d.itemsAvailable ? <Notice icon="info">{t("nt.d.itemsOff")}</Notice> : (
        <>
          <div className="nt-fl" role="group" aria-label={t("nt.d.rcpt")}>
            {FILTERS.map((f) => <button key={f || "all"} type="button" className="nt-fl__b" aria-pressed={status === f} onClick={() => { setStatus(f); setPage(1); }}>{t(filterKey(f))}</button>)}
          </div>
          {d.items.rows.length === 0 && !loading ? <p className="nt-empty">{t("nt.d.noItems")}</p> : null}
          <ul className="nt-rc">
            {d.items.rows.map((r, i) => (
              <li key={`${r.memberId}-${r.channel}-${i}`} className="nt-rc__r">
                <span className="nt-rc__id">{r.memberId}</span>
                <span className="nt-rc__ch">{t(r.channel === "push" ? "nt.d.rc.push" : "nt.d.rc.in_app")}{r.platform !== "" ? ` · ${r.platform}` : ""}</span>
                <span className={`nt-st nt-st--${r.status}`}>{t(statusKey(r.status))}</span>
                {r.errorCode !== "" ? <span className="nt-rc__code">{code(r.errorCode)}</span> : null}
              </li>
            ))}
          </ul>
          {pages > 1 ? (
            <div className="nt-dv__pg">
              <button type="button" className="btn btn--ghost" disabled={page === 1 || loading} onClick={() => setPage(page - 1)}>{t("nt.h.prev")}</button>
              <span>{page} / {pages}</span>
              <button type="button" className="btn btn--ghost" disabled={page === pages || loading} onClick={() => setPage(page + 1)}>{t("nt.h.next")}</button>
            </div>
          ) : null}
          <p className="nt-fld__h">{t("nt.d.keep")}</p>
        </>
      )}
    </div>
  );
}
