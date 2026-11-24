"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import Icon from "../Icon";
import { Notice, Pill } from "../hospitals/parts";
import { Pager, SearchForm, Skeleton, StatusSeg } from "./ReviewsTab";
import Thumbs from "./Thumbs";
import { fetchModerationPosts, type ModerationPage, type PostItem } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatDay, formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";
import { hasModerationFilters, moderationHref, PER_PAGE, STATE_LABEL, STATE_TONE, type ModerationView } from "@/lib/moderation";

type State = { data: ModerationPage<PostItem> | null; failed: boolean; stale: boolean; settled: string };

/** Community posts (platform admin only): status, search, cards, pages. Read only here. */
export default function PostsTab({ view }: { view: ModerationView }) {
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
  const { data, failed, stale } = state;
  const total = data ? data.counts[view.status] : 0;
  const pages = Math.ceil(total / PER_PAGE);
  const retryBtn = <button type="button" className="txtbtn mod-retry" onClick={retry} disabled={busy}>{t("state.retry")}</button>;
  const filtered = hasModerationFilters({ ...view, hospitalId: 0 });

  return (
    <>
      <div className="mod-tool">
        <StatusSeg status={view.status} counts={data?.counts ?? null} onPick={(status) => go({ status })} />
        <SearchForm key={view.search} initial={view.search} placeholderKey="mod.f.searchPh.post" onSearch={(search) => go({ search })} />
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
            {filtered ? <Link className="txtbtn" href={moderationHref({ ...view, search: "", page: 1 })}>{t("mod.f.reset")}</Link> : null}
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
                <PostCard key={p.id} p={p} />
              ))}
            </ul>
          )}
          <Pager view={view} pages={pages} />
        </>
      ) : null}
    </>
  );
}

function PostCard({ p }: { p: PostItem }) {
  const t = useT();
  return (
    <li className="mod-card mod-card--post">
      <div className="mod-card__head">
        <Pill tone={STATE_TONE[p.state]}>{t(STATE_LABEL[p.state])}</Pill>
        {p.category !== "" ? <Pill tone="plain">{p.category}</Pill> : null}
        <span className="mod-card__date">{formatDay(p.createdAt)}</span>
      </div>
      <h3 className="mod-card__title" title={p.title}>{p.title}</h3>
      <p className="mod-card__body">{p.content}</p>
      <Thumbs images={p.images} />
      <ul className="mod-facts">
        <li title={t("mod.author.locked")}><Icon name="lock" size={14} /><span>{t("mod.author")}</span> <b translate="no">{p.author}</b></li>
      </ul>
      {p.state !== "pending" ? <p className="mod-done">{t("mod.handled")}</p> : null}
    </li>
  );
}
