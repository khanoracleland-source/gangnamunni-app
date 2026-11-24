"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import Icon from "../Icon";
import { Notice, Pill } from "../hospitals/parts";
import HospitalCombo from "../payments/HospitalCombo";
import DetailDialog, { Gallery } from "./DetailDialog";
import { DecisionActions, DecisionButtons, useDecision } from "./useDecision";
import { useDelete } from "./useDelete";
import { useReviewEdit } from "./useReviewEdit";
import { fetchModerationReviews, type DoctorHospitalOption, type ModerationCounts, type ModerationPage, type ModerationState, type ReviewItem } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatDay, formatNumber } from "@/lib/format";
import { pageWindow } from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { hasModerationFilters, MODERATION_SEARCH_MAX, MODERATION_STATUSES, moderationHref, PER_PAGE, STATE_LABEL, STATE_TONE, type ModerationView } from "@/lib/moderation";

type Phase = "loading" | "ready" | "error";
type State = { phase: Phase; data: ModerationPage<ReviewItem> | null; stale: boolean; settled: string };

/** The reviews waiting for a decision (and the decided ones): status, search, hospital, cards, pages. Approve / reject only with the write switch. */
export default function ReviewsTab({ view, hospitals, isSuper, canDecide, canEdit, canDelete }: { view: ModerationView; hospitals: DoctorHospitalOption[]; isSuper: boolean; canDecide: boolean; canEdit: boolean; canDelete: boolean }) {
  const t = useT();
  const router = useRouter();
  const [state, setState] = useState<State>({ phase: "loading", data: null, stale: false, settled: "" });
  const [attempt, setAttempt] = useState(0);
  const requestId = `${moderationHref(view)}#${attempt}`;
  const busy = state.settled !== requestId;

  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchModerationReviews(view, controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setState({ phase: "ready", data: res.data, stale: false, settled: requestId });
        return;
      }
      if (res.status === 401) {
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      setState((s) => (s.data ? { ...s, phase: "ready", stale: true, settled: requestId } : { phase: "error", data: null, stale: false, settled: requestId }));
    };
    void run();
    return () => controller.abort();
  }, [view, attempt, requestId]);

  const go = (patch: Partial<ModerationView>) => router.push(moderationHref({ ...view, page: 1, ...patch }));
  const retry = () => setAttempt((n) => n + 1);
  const [detail, setDetail] = useState<ReviewItem | null>(null);
  const decision = useDecision("review", retry, () => setDetail(null));
  const { phase, data, stale } = state;
  const total = data ? data.counts[view.status] : 0;
  const pages = Math.ceil(total / PER_PAGE);

  // The last item of a page was decided: the page no longer exists, so step back to the last one that does.
  const emptyPast = !busy && data !== null && data.items.length === 0 && view.page > 1 && pages >= 1 && view.page > pages;
  useEffect(() => {
    if (emptyPast) router.replace(moderationHref({ ...view, page: pages }), { scroll: false });
  }, [emptyPast, pages, view, router]);

  return (
    <>
      <div className="mod-tool">
        <StatusSeg status={view.status} counts={data?.counts ?? null} onPick={(status) => go({ status })} />
        <SearchForm key={view.search} initial={view.search} onSearch={(search) => go({ search })} />
        {isSuper ? (
          <div className="mod-combo">
            <HospitalCombo
              key={hospitals.length}
              hospitals={hospitals}
              value={view.hospitalId}
              onChange={(id) => { if (id !== view.hospitalId) go({ hospitalId: id }); }}
              label={t("mod.f.hospital")}
              placeholder={t("mod.f.hospitalPh")}
              clearLabel={t("d.hc.clear")}
              noneLabel={t("d.hc.none")}
              allLabel={t("d.f.hospitalAll")}
            />
          </div>
        ) : null}
      </div>

      {phase === "loading" ? <Skeleton /> : null}
      {phase === "error" ? (
        <Notice tone="warn" icon="alert" action={<button type="button" className="txtbtn mod-retry" onClick={retry} disabled={busy}>{t("state.retry")}</button>}>
          {t("mod.error")}
        </Notice>
      ) : null}
      {data ? (
        <>
          {stale ? (
            <Notice tone="warn" icon="alert" action={<button type="button" className="txtbtn mod-retry" onClick={retry} disabled={busy}>{t("state.retry")}</button>}>
              {t("mod.stale")}
            </Notice>
          ) : null}
          <div className="list-head">
            <p className="list-head__count mod-meta" role="status" aria-live="polite">
              {busy ? t("mod.loading") : t("mod.count.rev", { status: t(STATE_LABEL[view.status]), n: formatNumber(total) })}
            </p>
            {hasModerationFilters(view) ? <Link className="txtbtn" href={moderationHref({ ...view, search: "", hospitalId: 0, page: 1 })}>{t("mod.f.reset")}</Link> : null}
          </div>
          {data.items.length === 0 ? (
            <div className="empty mod-empty">
              <Icon name="chat" size={22} />
              <p className="reo-state__t">{t(hasModerationFilters(view) ? "mod.none.title" : "mod.empty.title.rev")}</p>
              <p>{t(hasModerationFilters(view) ? "mod.none.body" : "mod.empty.body")}</p>
            </div>
          ) : (
            <ul className={busy ? "mod-cards is-busy" : "mod-cards"}>
              {data.items.map((r) => (
                <ReviewCard key={r.id} r={r} showNoBooking={isSuper} decision={canDecide ? decision : null} onOpen={() => setDetail(r)} />
              ))}
            </ul>
          )}
          <Pager view={view} pages={pages} />
        </>
      ) : null}
      {decision.dialog}
      {detail ? <ReviewDetail r={detail} showNoBooking={isSuper} decision={canDecide && detail.state === "pending" ? decision : null} canEdit={canEdit} canDelete={canDelete} reload={retry} onClose={() => setDetail(null)} /> : null}
    </>
  );
}

export function SearchForm({ initial, placeholderKey = "mod.f.searchPh.rev", onSearch }: { initial: string; placeholderKey?: MessageKey; onSearch: (search: string) => void }) {
  const t = useT();
  const [value, setValue] = useState(initial);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    onSearch(value.trim().slice(0, MODERATION_SEARCH_MAX));
  };
  return (
    <form className="mod-find" role="search" onSubmit={submit}>
      <label className="field field--grow">
        <span className="field__label">{t("mod.f.search")}</span>
        <input className="input" type="search" name="search" value={value} maxLength={MODERATION_SEARCH_MAX} placeholder={t(placeholderKey)} autoComplete="off" enterKeyHint="search" onChange={(e) => setValue(e.target.value)} />
      </label>
      <button type="submit" className="btn btn--primary">{t("d.f.submit")}</button>
    </form>
  );
}

function Stars({ n }: { n: number }) {
  const t = useT();
  const whole = Math.round(n);
  return (
    <span className="mod-stars" role="img" aria-label={t("mod.rating", { n })}>
      <span className="mod-stars__on" aria-hidden="true">{"★".repeat(whole)}</span>
      <span className="mod-stars__off" aria-hidden="true">{"★".repeat(5 - whole)}</span>
      <span className="mod-stars__n" aria-hidden="true">{n.toFixed(1)}</span>
    </span>
  );
}

function ReviewCard({ r, showNoBooking, decision, onOpen }: { r: ReviewItem; showNoBooking: boolean; decision: ReturnType<typeof useDecision> | null; onOpen: () => void }) {
  const t = useT();
  return (
    <li className="mod-card mod-card--review is-clickable" onClick={(e) => { if (!(e.target as HTMLElement).closest("button, a") && !window.getSelection()?.toString()) onOpen(); }}>
      <div className="mod-card__head">
        <Pill tone={STATE_TONE[r.state]}>{t(STATE_LABEL[r.state])}</Pill>
        {r.hasBooking ? <Pill tone="info">{t("mod.badge.booked")}</Pill> : showNoBooking ? <Pill tone="off">{t("mod.badge.unbooked")}</Pill> : null}
        <span className="mod-card__date">{formatDay(r.createdAt)}</span>
      </div>
      <h3 className="mod-card__title" title={r.title}>
        <button type="button" className="mod-card__open" aria-haspopup="dialog" onClick={onOpen}>{r.title}</button>
      </h3>
      <Stars n={r.rating} />
      <p className="mod-card__body">{r.content}</p>
      {r.treatments.length > 0 ? (
        <ul className="mod-chips">
          {r.treatments.map((x, i) => (
            <li key={`${i}-${x}`} className="mod-chip">{x}</li>
          ))}
        </ul>
      ) : null}
      {r.images.length > 0 ? <p className="mod-photos"><Icon name="image" size={14} />{t("mod.img.count", { n: r.images.length })}</p> : null}
      <ul className="mod-facts">
        <li title={r.hospitalName}><Icon name="hospital" size={14} /><span className="mod-facts__v">{r.hospitalName}</span></li>
        {r.doctorName !== "" ? <li><Icon name="people" size={14} /><span>{t("mod.doctor")}</span> <b>{r.doctorName}</b></li> : null}
        <li title={t("mod.author.locked")}><Icon name="lock" size={14} /><span>{t("mod.author")}</span> <b translate="no">{r.author}</b></li>
      </ul>
      {r.state !== "pending" ? <p className="mod-done">{t("mod.handled")}</p> : decision ? <DecisionActions busy={decision.busy} onApprove={() => decision.approve(r)} onReject={() => decision.askReject(r)} /> : null}
    </li>
  );
}

export function Skeleton() {
  const t = useT();
  return (
    <ul className="mod-cards" aria-busy="true" aria-label={t("mod.loading")}>
      {[0, 1, 2].map((i) => (
        <li key={i} className="mod-card mod-skel" aria-hidden="true">
          <span className="mod-skel__line" />
          <span className="mod-skel__line mod-skel__line--w" />
          <span className="mod-skel__line" />
        </li>
      ))}
    </ul>
  );
}

export function Pager({ view, pages }: { view: ModerationView; pages: number }) {
  const t = useT();
  if (pages <= 1) return null;
  const page = Math.min(view.page, pages);
  const href = (p: number) => moderationHref({ ...view, page: p });
  return (
    <nav className="pager" aria-label={t("mod.pager.label")}>
      {page > 1 ? (
        <Link className="pager__btn" href={href(page - 1)} scroll={false} aria-label={t("mod.pager.prev")} title={t("mod.pager.prev")}>
          <Icon name="chevron" size={16} className="pager__prev" />
        </Link>
      ) : null}
      {pageWindow(page, pages).map((p) => (
        <Link key={p} className="pager__btn" href={href(p)} scroll={false} aria-current={p === page ? "page" : undefined} aria-label={t("mod.pager.page", { n: p })}>
          {p}
        </Link>
      ))}
      {page < pages ? (
        <Link className="pager__btn" href={href(page + 1)} scroll={false} aria-label={t("mod.pager.next")} title={t("mod.pager.next")}>
          <Icon name="chevron" size={16} />
        </Link>
      ) : null}
      <span className="pager__info">{t("mod.pager.info", { page: formatNumber(page), pages: formatNumber(pages) })}</span>
    </nav>
  );
}

export function StatusSeg({ status, counts, onPick }: { status: ModerationState; counts: ModerationCounts | null; onPick: (s: ModerationState) => void }) {
  const t = useT();
  return (
    <div className="field mod-seg-wrap">
      <span className="field__label" id="mod-status-l">{t("mod.st.label")}</span>
      <div className="seg mod-seg" role="group" aria-labelledby="mod-status-l">
        {MODERATION_STATUSES.map((s) => (
          <button key={s} type="button" aria-pressed={status === s} onClick={() => status !== s && onPick(s)}>
            <span>{t(STATE_LABEL[s])}</span>
            {counts ? <span className="mod-seg__n">{formatNumber(counts[s])}</span> : null}
          </button>
        ))}
      </div>
    </div>
  );
}

function ReviewDetail({ r, showNoBooking, decision, canEdit, canDelete, reload, onClose }: { r: ReviewItem; showNoBooking: boolean; decision: ReturnType<typeof useDecision> | null; canEdit: boolean; canDelete: boolean; reload: () => void; onClose: () => void }) {
  const t = useT();
  const edit = useReviewEdit(r, { reload, onSettled: onClose });
  const del = useDelete("review", r, { reload, onSettled: onClose });
  const busy = decision?.busy === true || del.busy;
  const manage = canEdit || canDelete ? (
    <>
      {canEdit ? <button type="button" id={edit.openerId} className="btn btn--ghost" onClick={edit.start} disabled={busy}>{t("mod.act.edit")}</button> : null}
      {canDelete ? <button type="button" className="btn btn--ghost btn--reject" onClick={del.ask} disabled={busy}>{t("mod.act.delete")}</button> : null}
    </>
  ) : null;
  // Both windows sit at the same place of the tree, so switching between them keeps the open <dialog>.
  const win = edit.editing ? (
    <DetailDialog title={t("mod.edit.title.rev")} onClose={onClose} foot={edit.foot} locked={edit.saving} guard={edit.dirty}>
      {edit.body}
    </DetailDialog>
  ) : (
    <DetailDialog title={t("mod.detail.title.rev")} onClose={onClose} manage={manage} decide={decision ? <DecisionButtons busy={busy} onApprove={() => decision.approve(r)} onReject={() => decision.askReject(r)} /> : null}>
      <div className="mod-card__head">
        <Pill tone={STATE_TONE[r.state]}>{t(STATE_LABEL[r.state])}</Pill>
        {r.hasBooking ? <Pill tone="info">{t("mod.badge.booked")}</Pill> : showNoBooking ? <Pill tone="off">{t("mod.badge.unbooked")}</Pill> : null}
        <span className="mod-card__date">{formatDay(r.createdAt)}</span>
      </div>
      <h3 className="mod-detail__title">{r.title}</h3>
      <Stars n={r.rating} />
      <p className="mod-detail__text">{r.content}</p>
      {r.treatments.length > 0 ? (
        <ul className="mod-chips">
          {r.treatments.map((x, i) => (
            <li key={`${i}-${x}`} className="mod-chip">{x}</li>
          ))}
        </ul>
      ) : null}
      <ul className="mod-facts">
        <li><Icon name="hospital" size={14} /><span className="mod-facts__v mod-facts__v--wrap">{r.hospitalName}</span></li>
        {r.doctorName !== "" ? <li><Icon name="people" size={14} /><span>{t("mod.doctor")}</span> <b>{r.doctorName}</b></li> : null}
        <li title={t("mod.author.locked")}><Icon name="lock" size={14} /><span>{t("mod.author")}</span> <b translate="no">{r.author}</b></li>
      </ul>
      <Gallery images={r.images} />
    </DetailDialog>
  );
  return (
    <>
      {win}
      {del.dialog}
    </>
  );
}
