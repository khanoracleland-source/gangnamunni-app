"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import ConfirmDialog from "../hospitals/ConfirmDialog";
import { SearchForm } from "../moderation/ReviewsTab";
import { showUnitToast } from "../treatment-units/UnitToast";
import BannerDialog from "./BannerDialog";
import { deleteBanner, fetchBanners, setBannerHome, type BannerItem, type BannerPageData, type BannerView, type DoctorHospitalOption } from "@/lib/api";
import { BANNER_PER_PAGE, BANNER_VIEWS, bannerHref, bannerImageHref, bannerLinkHref, hasBannerFilters, type BannerListView } from "@/lib/banners";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import { pageWindow } from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "error";
type State = { phase: Phase; data: BannerPageData | null; stale: boolean; settled: string };
const VIEW_LABEL: Record<BannerView, MessageKey> = { all: "ban.st.all", visible: "ban.st.visible", hidden: "ban.st.hidden" };

/** Toolbar, cards, pages and the add / edit / delete flows of the banners. Writing controls only with the write switch. */
export default function BannerList({ view, hospitals, canWrite }: { view: BannerListView; hospitals: DoctorHospitalOption[]; canWrite: boolean }) {
  const t = useT();
  const router = useRouter();
  const [state, setState] = useState<State>({ phase: "loading", data: null, stale: false, settled: "" });
  const [attempt, setAttempt] = useState(0);
  const requestId = `${bannerHref(view)}#${attempt}`;
  const busy = state.settled !== requestId;
  const [editing, setEditing] = useState<BannerItem | "new" | null>(null);
  const [asking, setAsking] = useState<BannerItem | null>(null);
  const [working, setWorking] = useState(false);
  const [delError, setDelError] = useState<string | null>(null);
  const [switching, setSwitching] = useState<number | null>(null);
  const lock = useRef(false);

  useEffect(() => {
    const controller = new AbortController();
    void (async () => {
      const res = await fetchBanners(view, controller.signal);
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
    })();
    return () => controller.abort();
  }, [view, attempt, requestId]);

  const retry = () => setAttempt((n) => n + 1);
  const go = (patch: Partial<BannerListView>) => router.push(bannerHref({ ...view, page: 1, ...patch }));
  const toast = (tone: "ok" | "info" | "error", key: MessageKey) => showUnitToast({ tone, message: t(key), closeLabel: t("m.close") });
  const failKey = (status: number): MessageKey => (status === 403 ? "ban.toast.forbidden" : status === 503 ? "ban.toast.unavailable" : "ban.toast.failed");

  const { phase, data, stale } = state;
  const total = data ? data.counts[view.view] : 0;
  const pages = Math.ceil(total / BANNER_PER_PAGE);
  const emptyPast = !busy && data !== null && data.items.length === 0 && view.page > 1 && pages >= 1 && view.page > pages;
  useEffect(() => {
    if (emptyPast) router.replace(bannerHref({ ...view, page: pages }), { scroll: false });
  }, [emptyPast, pages, view, router]);

  const toggle = async (b: BannerItem) => {
    if (lock.current) return;
    lock.current = true;
    setSwitching(b.id);
    try {
      const next = !b.homeEnabled;
      const res = await setBannerHome(b.id, next);
      if (res.ok) {
        toast("ok", next ? "ban.toast.homeOn" : "ban.toast.homeOff");
        retry();
      } else if (res.status === 401) {
        window.location.reload();
      } else if (res.error === "not_approved") {
        toast("error", "ban.toast.notApproved");
      } else if (res.status === 404) {
        toast("error", "ban.toast.missing");
        retry();
      } else {
        toast("error", failKey(res.status));
      }
    } finally {
      lock.current = false;
      setSwitching(null);
    }
  };

  const confirmDelete = async () => {
    if (!asking || lock.current) return;
    lock.current = true;
    setWorking(true);
    setDelError(null);
    try {
      const res = await deleteBanner(asking.id, asking.revision);
      if (res.ok) toast("ok", "ban.toast.deleted");
      else if (res.status === 401) {
        window.location.reload();
        return;
      } else if (res.error === "changed") toast("error", "ban.toast.changed");
      else if (res.status === 404) toast("error", "ban.toast.missing");
      else {
        setDelError(t(failKey(res.status)));
        return;
      }
      setAsking(null);
      retry();
    } finally {
      lock.current = false;
      setWorking(false);
    }
  };

  return (
    <>
      <div className="mod-tool ban-tool">
        <div className="field mod-seg-wrap">
          <span className="field__label" id="ban-view-l">{t("ban.st.label")}</span>
          <div className="seg mod-seg" role="group" aria-labelledby="ban-view-l">
            {BANNER_VIEWS.map((v) => (
              <button key={v} type="button" aria-pressed={view.view === v} onClick={() => view.view !== v && go({ view: v })}>
                <span>{t(VIEW_LABEL[v])}</span>
                {data ? <span className="mod-seg__n">{formatNumber(data.counts[v])}</span> : null}
              </button>
            ))}
          </div>
        </div>
        <SearchForm key={view.search} initial={view.search} placeholderKey="ban.f.searchPh" onSearch={(search) => go({ search })} />
        {canWrite ? (
          <button type="button" className="btn btn--primary ban-add" onClick={() => setEditing("new")}>
            <Icon name="plus" size={16} />
            {t("ban.add")}
          </button>
        ) : null}
      </div>

      {phase === "loading" ? <Skeleton /> : null}
      {phase === "error" ? (
        <div className="empty mod-empty ban-error" role="alert">
          <Icon name="alert" size={22} />
          <p className="reo-state__t">{t("ban.error")}</p>
          <button type="button" className="btn btn--ghost mod-retry" onClick={retry} disabled={busy}>{t("state.retry")}</button>
        </div>
      ) : null}
      {data ? (
        <>
          {stale ? (
            <Notice tone="warn" icon="alert" action={<button type="button" className="txtbtn mod-retry" onClick={retry} disabled={busy}>{t("state.retry")}</button>}>
              {t("ban.stale")}
            </Notice>
          ) : null}
          <div className="list-head">
            <p className="list-head__count mod-meta" role="status" aria-live="polite">
              {busy ? t("ban.loading") : t("ban.count", { status: t(VIEW_LABEL[view.view]), n: formatNumber(total) })}
            </p>
            {hasBannerFilters(view) ? <Link className="txtbtn" href={bannerHref({ ...view, search: "", page: 1 })}>{t("ban.f.reset")}</Link> : null}
          </div>
          {data.items.length === 0 ? (
            <div className="empty mod-empty">
              <Icon name="image" size={22} />
              <p className="reo-state__t">{t(hasBannerFilters(view) ? "ban.none.title" : "ban.empty.title")}</p>
              <p>{t(hasBannerFilters(view) ? "ban.none.body" : "ban.empty.body")}</p>
              {!hasBannerFilters(view) && canWrite ? <button type="button" className="btn btn--primary" onClick={() => setEditing("new")}>{t("ban.add")}</button> : null}
            </div>
          ) : (
            <ul className={busy ? "ban-grid is-busy" : "ban-grid"}>
              {data.items.map((b, i) => (
                <BannerCard key={b.id} b={b} first={i < 2} canWrite={canWrite} switching={switching === b.id} onToggle={() => void toggle(b)} onEdit={() => setEditing(b)} onDelete={() => { setDelError(null); setAsking(b); }} />
              ))}
            </ul>
          )}
          <Pager view={view} pages={pages} />
        </>
      ) : null}

      {editing !== null ? (
        <BannerDialog
          item={editing === "new" ? null : editing}
          hospitals={hospitals}
          onClose={() => setEditing(null)}
          onSaved={(created) => {
            toast("ok", created ? "ban.toast.created" : "ban.toast.saved");
            retry();
          }}
          onStale={() => {
            toast("error", "ban.toast.changed");
            retry();
          }}
        />
      ) : null}
      <ConfirmDialog
        spec={
          asking
            ? {
                title: t("ban.del.title"),
                name: asking.title,
                body: t("ban.del.body"),
                confirmLabel: t("ban.act.delete"),
                cancelLabel: t("ban.dlg.cancel"),
                focus: "cancel",
                confirmTone: "reject",
                busyLabel: t("ban.del.working"),
              }
            : null
        }
        busy={working}
        error={delError}
        onConfirm={() => void confirmDelete()}
        onClose={() => {
          if (!working) setAsking(null);
        }}
      />
    </>
  );
}

function BannerCard({ b, first, canWrite, switching, onToggle, onEdit, onDelete }: { b: BannerItem; first: boolean; canWrite: boolean; switching: boolean; onToggle: () => void; onEdit: () => void; onDelete: () => void }) {
  const t = useT();
  const img = bannerImageHref(b.image);
  const link = bannerLinkHref(b.linkUrl);
  const approved = b.status === "approved";
  const pill: { tone: string; key: MessageKey } = !approved ? { tone: "warn", key: "ban.pill.review" } : b.homeEnabled ? { tone: "on", key: "ban.pill.visible" } : { tone: "off", key: "ban.pill.hidden" };
  return (
    <li className={b.visible ? "ban-card" : "ban-card is-off"}>
      <div className="ban-card__img">
        {img ? (
          // eslint-disable-next-line @next/next/no-img-element -- stored pictures come from the panel's media host, not the Next image optimizer
          <img src={img} alt="" width={320} height={100} loading={first ? "eager" : "lazy"} referrerPolicy="no-referrer" />
        ) : (
          <span className="ban-card__noimg"><Icon name="image" size={20} />{t("ban.card.noImage")}</span>
        )}
        <span className="ban-card__order"><span className="sr-only">{t("ban.card.order", { n: b.displayOrder })}</span><span aria-hidden="true">{b.displayOrder}</span></span>
        <span className={`pill pill--${pill.tone} ban-card__state`}>{t(pill.key)}</span>
      </div>
      <div className="ban-card__body">
        <h3 className="ban-card__title">{b.title}</h3>
        {b.subtitle ? <p className="ban-card__sub">{b.subtitle}</p> : null}
        <div className="ban-card__meta">
          {link ? (
            <a className="ban-card__link" href={link} target="_blank" rel="noopener noreferrer">{b.linkUrl}</a>
          ) : (
            <span className="ban-card__link is-none">{b.linkUrl || t("ban.card.noLink")}</span>
          )}
          {b.hospitalId > 0 ? <span className="ban-card__hosp">{b.hospitalName || `#${b.hospitalId}`}</span> : <span className="ban-card__hosp is-solo">{t("ban.card.standalone")}</span>}
        </div>
      </div>
      <div className="ban-card__foot">
        <label className="ban-card__sw">
          <button type="button" role="switch" className="switch" aria-checked={b.homeEnabled} aria-label={t("ban.home.switch", { name: b.title })} disabled={!canWrite || switching || !approved} aria-busy={switching || undefined} onClick={onToggle}>
            <span className="switch__thumb" aria-hidden="true" />
          </button>
          <span>{t("ban.home.label")}</span>
        </label>
        {canWrite ? (
          <div className="ban-card__acts">
            <button type="button" className="btn btn--ghost btn--sm" aria-label={t("ban.act.editFor", { name: b.title })} onClick={onEdit}>{t("ban.act.edit")}</button>
            <button type="button" className="btn btn--ghost btn--sm btn--reject" aria-label={t("ban.act.deleteFor", { name: b.title })} onClick={onDelete}>{t("ban.act.delete")}</button>
          </div>
        ) : null}
      </div>
    </li>
  );
}

function Skeleton() {
  const t = useT();
  return (
    <ul className="ban-grid" aria-busy="true" aria-label={t("ban.loading")}>
      {[0, 1, 2, 3].map((i) => (
        <li key={i} className="ban-card ban-skel" aria-hidden="true">
          <span className="ban-skel__img" />
          <span className="mod-skel__line" />
          <span className="mod-skel__line mod-skel__line--w" />
        </li>
      ))}
    </ul>
  );
}

function Pager({ view, pages }: { view: BannerListView; pages: number }) {
  const t = useT();
  if (pages <= 1) return null;
  const page = Math.min(view.page, pages);
  const href = (p: number) => bannerHref({ ...view, page: p });
  return (
    <nav className="pager" aria-label={t("ban.pager.label")}>
      {page > 1 ? (
        <Link className="pager__btn" href={href(page - 1)} scroll={false} aria-label={t("ban.pager.prev")} title={t("ban.pager.prev")}>
          <Icon name="chevron" size={16} className="pager__prev" />
        </Link>
      ) : null}
      {pageWindow(page, pages).map((p) => (
        <Link key={p} className="pager__btn" href={href(p)} scroll={false} aria-current={p === page ? "page" : undefined} aria-label={t("ban.pager.page", { n: p })}>
          {p}
        </Link>
      ))}
      {page < pages ? (
        <Link className="pager__btn" href={href(page + 1)} scroll={false} aria-label={t("ban.pager.next")} title={t("ban.pager.next")}>
          <Icon name="chevron" size={16} />
        </Link>
      ) : null}
      <span className="pager__info">{t("ban.pager.info", { page: formatNumber(page), pages: formatNumber(pages) })}</span>
    </nav>
  );
}
