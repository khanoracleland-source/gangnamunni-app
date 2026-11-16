"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { Notice, Pill, Section } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { fetchArchiveList, fetchMe, type ArchiveItem, type ArchiveList as ArchiveListData, type Me } from "@/lib/api";
import { announceArchiveDue, archiveHref, parseArchiveQuery, type ArchiveQuery } from "@/lib/archive";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatDay, formatNumber } from "@/lib/format";
import { pageWindow } from "@/lib/hospitals";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "unavailable" | "forbidden" | "redirecting" | "error";

type State = {
  phase: Phase;
  errorKind: ErrorKind;
  me: Me | null;
  list: ArchiveListData | null;
  settled: string;
};

const INITIAL: State = { phase: "loading", errorKind: "load", me: null, list: null, settled: "" };

/** Hospitals deleted for good, newest first. Super admin only; the server decides, this page only shows its answer. */
export default function HospitalArchiveList() {
  const t = useT();
  const searchParams = useSearchParams();
  const queryString = searchParams.toString();
  const query = useMemo(() => parseArchiveQuery(new URLSearchParams(queryString)), [queryString]);
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  const halted = useRef(false);

  const requestId = `${archiveHref(query)}#${attempt}`;
  const busy = state.settled !== requestId && state.phase !== "forbidden" && state.phase !== "redirecting";

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const [me, list] = await Promise.all([fetchMe(controller.signal), fetchArchiveList(query.page, query.perPage, controller.signal)]);
      if (controller.signal.aborted) return;
      const statuses = [me, list].map((r) => (r.ok ? 200 : r.status));

      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      if (statuses.includes(403) || (me.ok && me.data.admin.scope !== "all")) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok && !me.data.features.archive) {
        setState({ ...INITIAL, phase: "error", errorKind: "archiveOff", me: me.data, settled: requestId });
        return;
      }
      if (me.ok && list.ok) {
        announceArchiveDue(list.data.dueCount);
        setState({ phase: "ready", errorKind: "load", me: me.data, list: list.data, settled: requestId });
        return;
      }
      if (me.ok && !list.ok && list.status === 503) {
        setState({ ...INITIAL, phase: "unavailable", me: me.data, settled: requestId });
        return;
      }
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !list.ok && list.status === 404 ? "archiveOff" : "load";
      setState({ ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : null, settled: requestId });
    };
    void run();
    return () => controller.abort();
  }, [query, attempt, requestId]);

  const retry = () => setAttempt((n) => n + 1);
  const { phase, me, list } = state;

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("ha.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="super" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={busy} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("ha.title")} description={t("ha.desc")} screen={screen}>
      {phase === "unavailable" ? (
        <section className="state state--inline" role="status">
          <div className="state__in">
            <div className="state__ring"><Icon name="unplug" size={22} /></div>
            <h2 className="state__h">{t("ha.unavailable.title")}</h2>
            <p>{t("ha.unavailable.body")}</p>
            <div className="state__acts">
              <button type="button" className="btn btn--primary" onClick={retry} disabled={busy}>{t("state.retry")}</button>
            </div>
          </div>
        </section>
      ) : null}
      {list ? (
        <>
          {list.dueCount > 0 ? <Notice tone="warn" icon="alert">{t("ha.due.notice", { n: formatNumber(list.dueCount) })}</Notice> : null}
          <Section id="sec-a-list" title={t("ha.list.title")} desc={t("ha.list.desc")}>
            <div className="list-head">
              <p className="list-head__count" role="status" aria-live="polite">{t("ha.list.count", { n: formatNumber(list.total) })}</p>
            </div>
            <ArchiveTable list={list} busy={busy} />
            <Pager query={query} total={list.total} />
          </Section>
        </>
      ) : null}
    </AdminShell>
  );
}

function ArchiveTable({ list, busy }: { list: ArchiveListData; busy: boolean }) {
  const t = useT();
  if (list.items.length === 0) {
    return (
      <div className="empty" aria-busy={busy}>
        <Icon name="collection" size={22} />
        <p>{list.total > 0 ? t("ha.list.emptyPage") : t("ha.list.empty")}</p>
        {list.total > 0 ? <Link className="txtbtn" href="/hospital-archive">{t("ha.list.first")}</Link> : null}
      </div>
    );
  }
  // Same table-to-cards pattern as the hospital list: each cell carries its column name in data-label.
  return (
    <table className={busy ? "tbl tbl--arch is-busy" : "tbl tbl--arch"} role="table" aria-busy={busy}>
      <caption className="sr-only">{t("ha.list.caption")}</caption>
      <thead role="rowgroup">
        <tr role="row">
          <th role="columnheader" scope="col" className="tbl__id">#</th>
          <th role="columnheader" scope="col" className="tbl__name">{t("ha.col.name")}</th>
          <th role="columnheader" scope="col" className="tbl__date">{t("ha.col.deletedAt")}</th>
          <th role="columnheader" scope="col" className="tbl__who">{t("ha.col.by")}</th>
          <th role="columnheader" scope="col" className="tbl__sum">{t("ha.col.bookings")}</th>
          <th role="columnheader" scope="col" className="tbl__sum">{t("ha.col.net")}</th>
          <th role="columnheader" scope="col" className="tbl__st">{t("ha.col.status")}</th>
          <th role="columnheader" scope="col" className="tbl__go">{t("ha.col.detail")}</th>
        </tr>
      </thead>
      <tbody role="rowgroup">
        {list.items.map((item) => (
          <Row key={item.id} item={item} />
        ))}
      </tbody>
    </table>
  );
}

function Row({ item }: { item: ArchiveItem }) {
  const t = useT();
  const name = item.name || t("h.noName");
  const href = `/hospital-archive/${item.id}`;
  return (
    <tr role="row">
      <td role="cell" className="tbl__id" data-label="#">{item.hospitalId}</td>
      <td role="cell" className="tbl__name" data-label={t("ha.col.name")}>
        <Link href={href} className="tbl__link" translate="no">{name}</Link>
      </td>
      <td role="cell" className="tbl__date" data-label={t("ha.col.deletedAt")}>{formatDay(item.deletedAt)}</td>
      <td role="cell" className="tbl__who" data-label={t("ha.col.by")}><span translate="no">{item.deletedByName || "—"}</span></td>
      <td role="cell" className="tbl__sum" data-label={t("ha.col.bookings")}>
        {t("ha.bookingsValue", { n: formatNumber(item.bookingsTotal), cancelled: formatNumber(item.bookingsCancelled) })}
      </td>
      <td role="cell" className="tbl__sum" data-label={t("ha.col.net")}>{t("hs.money", { n: formatNumber(item.salesNet) })}</td>
      <td role="cell" className="tbl__st" data-label={t("ha.col.status")}>
        {item.noticeDue ? <Pill tone="warn">{t("ha.due.pill")}</Pill> : item.noticeAckedAt ? <Pill tone="plain">{t("ha.acked.pill")}</Pill> : "—"}
      </td>
      <td role="cell" className="tbl__go">
        <div className="tbl__acts">
          <Link href={href} className="ibtn tbl__open" aria-label={t("ha.col.detailOf", { name })} title={t("ha.col.detailOf", { name })}>
            <Icon name="eye" size={18} />
          </Link>
        </div>
      </td>
    </tr>
  );
}

function Pager({ query, total }: { query: ArchiveQuery; total: number }) {
  const t = useT();
  const pages = Math.ceil(total / query.perPage);
  if (pages <= 1) return null;
  const page = Math.min(query.page, pages);
  const href = (p: number) => archiveHref({ ...query, page: p });
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
