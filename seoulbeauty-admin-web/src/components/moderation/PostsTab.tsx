"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import Icon from "../Icon";
import { Notice, Pill } from "../hospitals/parts";
import { Pager, SearchForm, Skeleton, StatusSeg } from "./ReviewsTab";
import DetailDialog, { Gallery } from "./DetailDialog";
import { DecisionActions, DecisionButtons, useDecision } from "./useDecision";
import { useDelete } from "./useDelete";
import { usePostEdit } from "./usePostEdit";
import { fetchModerationPosts, type ModerationPage, type PostItem } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatDay, formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { REPORT_REASONS, REPORT_STATUSES } from "@/lib/members";
import { hasModerationFilters, moderationHref, PER_PAGE, STATE_LABEL, STATE_TONE, type ModerationView } from "@/lib/moderation";

type State = { data: ModerationPage<PostItem> | null; failed: boolean; stale: boolean; settled: string };

/** Community posts (platform admin only): status, search, cards, pages. Approve / reject only with the write switch. */
export default function PostsTab({ view, canDecide, canEdit, canDelete }: { view: ModerationView; canDecide: boolean; canEdit: boolean; canDelete: boolean }) {
  const t = useT();
  const router = useRouter();
  const [state, setState] = useState<State>({ data: null, failed: false, stale: false, settled: "" });
  const [attempt, setAttempt] = useState(0);
  const requestId = `${moderationHref(view)}#${attempt}`;
  const busy = state.settled !== requestId;

  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchModerationPosts(view, controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setState({ data: res.data, failed: false, stale: false, settled: requestId });
        return;
      }
      if (res.status === 401) {
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      setState((s) => (s.data ? { ...s, stale: true, settled: requestId } : { data: null, failed: true, stale: false, settled: requestId }));
    };
    void run();
    return () => controller.abort();
  }, [view, attempt, requestId]);

  const go = (patch: Partial<ModerationView>) => router.push(moderationHref({ ...view, page: 1, ...patch }));
  const retry = () => setAttempt((n) => n + 1);
  const [detail, setDetail] = useState<PostItem | null>(null);
  const decision = useDecision("post", retry, () => setDetail(null));
  const { data, failed, stale } = state;
  const total = data ? data.counts[view.status] : 0;
  const pages = Math.ceil(total / PER_PAGE);

  // The last item of a page was decided: the page no longer exists, so step back to the last one that does.
  const emptyPast = !busy && data !== null && data.items.length === 0 && view.page > 1 && pages >= 1 && view.page > pages;
  useEffect(() => {
    if (emptyPast) router.replace(moderationHref({ ...view, page: pages }), { scroll: false });
  }, [emptyPast, pages, view, router]);
  const retryBtn = <button type="button" className="txtbtn mod-retry" onClick={retry} disabled={busy}>{t("state.retry")}</button>;
  const filtered = hasModerationFilters({ ...view, hospitalId: 0 });

  return (
    <>
      <div className="mod-tool">
        <StatusSeg status={view.status} counts={data?.counts ?? null} onPick={(status) => go({ status })} />
        <SearchForm key={view.search} initial={view.search} placeholderKey="mod.f.searchPh.post" onSearch={(search) => go({ search })} />
        <button type="button" className="btn btn--ghost mod-rep-only" aria-pressed={view.reported} onClick={() => go({ reported: !view.reported })}><Icon name="alert" size={16} />{t("mod.rep.only")}</button>
      </div>

      {!data && !failed ? <Skeleton /> : null}
      {failed ? <Notice tone="warn" icon="alert" action={retryBtn}>{t("mod.error.post")}</Notice> : null}
      {data ? (
        <>
          {stale ? <Notice tone="warn" icon="alert" action={retryBtn}>{t("mod.stale")}</Notice> : null}
          <div className="list-head">
            <p className="list-head__count mod-meta" role="status" aria-live="polite">
              {busy ? t("mod.loading") : t("mod.count.post", { status: t(STATE_LABEL[view.status]), n: formatNumber(total) })}
            </p>
            {filtered ? <Link className="txtbtn" href={moderationHref({ ...view, search: "", reported: false, page: 1 })}>{t("mod.f.reset")}</Link> : null}
          </div>
          {data.items.length === 0 ? (
            <div className="empty mod-empty">
              <Icon name="chat" size={22} />
              <p className="reo-state__t">{t(filtered ? "mod.none.title.post" : "mod.empty.title.post")}</p>
              <p>{t(filtered ? "mod.none.body.post" : "mod.empty.body.post")}</p>
            </div>
          ) : (
            <ul className={busy ? "mod-cards is-busy" : "mod-cards"}>
              {data.items.map((p) => (
                <PostCard key={p.id} p={p} decision={canDecide ? decision : null} onOpen={() => setDetail(p)} />
              ))}
            </ul>
          )}
          <Pager view={view} pages={pages} />
        </>
      ) : null}
      {decision.dialog}
      {detail ? <PostDetail p={detail} decision={canDecide && detail.state === "pending" ? decision : null} canEdit={canEdit} canDelete={canDelete} reload={retry} onClose={() => setDetail(null)} /> : null}
    </>
  );
}

function PostCard({ p, decision, onOpen }: { p: PostItem; decision: ReturnType<typeof useDecision> | null; onOpen: () => void }) {
  const t = useT();
  return (
    <li className="mod-card mod-card--post is-clickable" onClick={(e) => { if (!(e.target as HTMLElement).closest("button, a") && !window.getSelection()?.toString()) onOpen(); }}>
      <div className="mod-card__head">
        <Pill tone={STATE_TONE[p.state]}>{t(STATE_LABEL[p.state])}</Pill>
        {p.category !== "" ? <Pill tone="plain">{p.category}</Pill> : null}
        {p.reportCount ? <Pill tone="err">{t("mod.rep.count", { n: formatNumber(p.reportCount) })}</Pill> : null}
        <span className="mod-card__date">{formatDay(p.createdAt)}</span>
      </div>
      <h3 className="mod-card__title" title={p.title}>
        <button type="button" className="mod-card__open" aria-haspopup="dialog" onClick={onOpen}>{p.title}</button>
      </h3>
      <p className="mod-card__body">{p.content}</p>
      {p.images.length > 0 ? <p className="mod-photos"><Icon name="image" size={14} />{t("mod.img.count", { n: p.images.length })}</p> : null}
      <ul className="mod-facts">
        <li title={t("mod.author.locked")}><Icon name="lock" size={14} /><span>{t("mod.author")}</span> <b translate="no">{p.author}</b></li>
      </ul>
      {p.state !== "pending" ? <p className="mod-done">{t("mod.handled")}</p> : decision ? <DecisionActions busy={decision.busy} onApprove={() => decision.approve(p)} onReject={() => decision.askReject(p)} /> : null}
    </li>
  );
}

function PostDetail({ p, decision, canEdit, canDelete, reload, onClose }: { p: PostItem; decision: ReturnType<typeof useDecision> | null; canEdit: boolean; canDelete: boolean; reload: () => void; onClose: () => void }) {
  const t = useT();
  const edit = usePostEdit(p, { reload, onSettled: onClose });
  const del = useDelete("post", p, { reload, onSettled: onClose });
  const busy = decision?.busy === true || del.busy;
  const manage = canEdit || canDelete ? (
    <>
      {canEdit ? <button type="button" id={edit.openerId} className="btn btn--ghost" onClick={edit.start} disabled={busy}>{t("mod.act.edit")}</button> : null}
      {canDelete ? <button type="button" className="btn btn--ghost btn--reject" onClick={del.ask} disabled={busy}>{t("mod.act.delete")}</button> : null}
    </>
  ) : null;
  // Both windows sit at the same place of the tree, so switching between them keeps the open <dialog>.
  const win = edit.editing ? (
    <DetailDialog title={t("mod.edit.title.post")} onClose={onClose} foot={edit.foot} locked={edit.saving} guard={edit.dirty}>
      {edit.body}
    </DetailDialog>
  ) : (
    <DetailDialog title={t("mod.detail.title.post")} onClose={onClose} manage={manage} decide={decision ? <DecisionButtons busy={busy} onApprove={() => decision.approve(p)} onReject={() => decision.askReject(p)} /> : null}>
      <div className="mod-card__head">
        <Pill tone={STATE_TONE[p.state]}>{t(STATE_LABEL[p.state])}</Pill>
        {p.category !== "" ? <Pill tone="plain">{p.category}</Pill> : null}
        <span className="mod-card__date">{formatDay(p.createdAt)}</span>
      </div>
      <h3 className="mod-detail__title">{p.title}</h3>
      <p className="mod-detail__text">{p.content}</p>
      <ul className="mod-facts">
        <li title={t("mod.author.locked")}><Icon name="lock" size={14} /><span>{t("mod.author")}</span> <b translate="no">{p.author}</b></li>
      </ul>
      <Gallery images={p.images} />
      <PostReports p={p} />
    </DetailDialog>
  );
  return (
    <>
      {win}
      {del.dialog}
    </>
  );
}

/** Who reported the post and why (the newest few); shown to the platform admin only, like the whole tab. */
function PostReports({ p }: { p: PostItem }) {
  const t = useT();
  if (!p.reportCount || !p.reports || p.reports.length === 0) return null;
  const label = (list: readonly string[], v: string, prefix: string) => (list.includes(v) ? t(`${prefix}${v}` as MessageKey) : v);
  return (
    <section className="mod-rep" aria-label={t("mod.rep.title")}>
      <h4 className="mod-rep__title">{t("mod.rep.title")} <span className="mod-rep__n">{formatNumber(p.reportCount)}</span></h4>
      <ul className="mod-rep__list">
        {p.reports.map((r) => (
          <li key={r.id} className="mod-rep__item">
            <div className="mod-rep__top"><b>{label(REPORT_REASONS, r.reason, "mb.bl.r.")}</b><span>{label(REPORT_STATUSES, r.status, "mb.bl.s.")}</span></div>
            <div className="mod-rep__sub"><span translate="no">{r.reporter || "—"}</span> · {formatDay(r.createdAt)}</div>
            {r.detail ? <p className="mod-rep__msg">{r.detail}</p> : null}
          </li>
        ))}
      </ul>
      {p.reportCount > p.reports.length ? <p className="mod-rep__more">{t("mod.rep.more", { n: formatNumber(p.reportCount - p.reports.length) })}</p> : null}
    </section>
  );
}
