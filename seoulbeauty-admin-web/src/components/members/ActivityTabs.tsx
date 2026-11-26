"use client";

import { useEffect, useState, type ReactNode } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import {
  fetchMemberBookings, fetchMemberConsultations, fetchMemberHistory, fetchMemberPoints,
  type ApiResult, type MemberBooking, type MemberConsultation, type MemberHistoryEntry, type MemberPage, type MemberPointEntry, type MemberPoints,
} from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { BOOKING_STATUSES, POINT_TYPES, dayOf, minuteOf } from "@/lib/members";

/** Reads one page of a member's tab; the last answer stays on screen while the next page loads. */
function usePaged<T>(no: number, load: (no: number, page: number, signal: AbortSignal) => Promise<ApiResult<T>>) {
  const [page, setPage] = useState(1);
  const [retry, setRetry] = useState(0);
  const key = `${no}:${page}:${retry}`;
  const [got, setGot] = useState<{ key: string; res: ApiResult<T> } | null>(null);
  useEffect(() => {
    const c = new AbortController();
    void load(no, page, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) window.location.assign(PHP_ADMIN_URL);
      setGot({ key, res });
    });
    return () => c.abort();
    // `load` is a module function; `key` stands for the rest.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);
  const data = got?.res.ok ? got.res.data : null;
  return { data, page, setPage, loading: got?.key !== key, failed: got?.key === key && !got.res.ok, retry: () => setRetry((n) => n + 1) };
}

function Frame<T extends MemberPage<unknown>>({ s, empty, children }: { s: ReturnType<typeof usePaged<T>>; empty: MessageKey; children: (d: T) => ReactNode }) {
  const t = useT();
  if (s.failed) return <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={s.retry}>{t("state.retry")}</button>}>{t("mb.err.tab")}</Notice>;
  if (!s.data) return <div className="mb-skel" role="status" aria-label={t("mb.loading")}>{[0, 1, 2].map((i) => <span key={i} className="mb-skel__row" aria-hidden="true" />)}</div>;
  const d = s.data;
  if (d.items.length === 0) return <p className="mb-tabempty"><Icon name="info" size={16} />{t(empty)}</p>;
  return (
    <div className={s.loading ? "mb-act is-busy" : "mb-act"}>
      {children(d)}
      {d.totalPages > 1 ? (
        <div className="mb-actpager">
          <button type="button" className="pager__btn" disabled={d.page === 1} onClick={() => s.setPage(d.page - 1)} aria-label={t("mb.prev")}><Icon name="chevron" size={16} /></button>
          <span className="muted">{d.page} / {d.totalPages}</span>
          <button type="button" className="pager__btn" disabled={d.page === d.totalPages} onClick={() => s.setPage(d.page + 1)} aria-label={t("mb.next")}><Icon name="chevron" size={16} /></button>
        </div>
      ) : null}
    </div>
  );
}

const CS_STATUS = ["pending", "approved", "completed", "rejected", "contact_failed"];
const CS_TYPES: Record<string, MessageKey> = { general: "cs.type.general", doctor: "cs.type.doctor", kakao: "cs.type.kakao" };

export function ConsultationsTab({ no }: { no: number }) {
  const t = useT();
  const s = usePaged<MemberPage<MemberConsultation>>(no, fetchMemberConsultations);
  return (
    <Frame s={s} empty="mb.cs.empty">
      {(d) => (
        <ul className="mb-rows">
          {d.items.map((c) => (
            <li key={c.id} className="mb-row">
              <div className="mb-row__top"><b className="mb-ell">{c.hospitalName || "—"}</b>{CS_STATUS.includes(c.status) ? <span className={`cs-st cs-st--${c.status}`}>{t(`cs.st.${c.status}` as MessageKey)}</span> : <span className="mb-row__muted">{c.status}</span>}</div>
              <div className="mb-row__sub">{minuteOf(c.createdAt)}{CS_TYPES[c.type] ? ` · ${t(CS_TYPES[c.type])}` : ""}</div>
              {c.message ? <p className="mb-row__msg">{c.message}</p> : null}
            </li>
          ))}
        </ul>
      )}
    </Frame>
  );
}

export function BookingsTab({ no }: { no: number }) {
  const t = useT();
  const s = usePaged<MemberPage<MemberBooking>>(no, fetchMemberBookings);
  return (
    <Frame s={s} empty="mb.bk.empty">
      {(d) => (
        <ul className="mb-rows">
          {d.items.map((b) => (
            <li key={b.id} className="mb-row">
              <div className="mb-row__top"><b className="mb-ell">{b.hospitalName || "—"}</b><span className="mb-bk">{(BOOKING_STATUSES as readonly string[]).includes(b.status) ? t(`mb.bk.st.${b.status}` as MessageKey) : b.status}</span></div>
              <div className="mb-row__sub">{dayOf(b.bookingDate)}{b.bookingTime ? ` ${b.bookingTime.slice(0, 5)}` : ""}{b.treatment ? ` · ${b.treatment}` : ""}</div>
              {b.price > 0 ? <div className="mb-row__sub">{formatNumber(b.price)}</div> : null}
              {b.cancelReason ? <p className="mb-row__msg">{t("mb.bk.cancel", { reason: b.cancelReason })}</p> : null}
            </li>
          ))}
        </ul>
      )}
    </Frame>
  );
}

const unit = (t: ReturnType<typeof useT>, n: number) => t("mb.pt.unit", { n: formatNumber(n) });

export function PointsTab({ no }: { no: number }) {
  const t = useT();
  const s = usePaged<MemberPoints>(no, fetchMemberPoints);
  return (
    <>
      {s.data ? (
        <div className="mb-pts">
          <div className="mb-pts__bal"><span>{t("mb.pt.balance")}</span><b>{unit(t, s.data.balance)}</b></div>
          <dl className="mb-pts__tot">
            <div><dt>{t("mb.pt.earned")}</dt><dd>{unit(t, s.data.totals.earned + s.data.totals.adjustedIn + s.data.totals.refunded)}</dd></div>
            <div><dt>{t("mb.pt.used")}</dt><dd>{unit(t, s.data.totals.used + s.data.totals.adjustedOut)}</dd></div>
            <div><dt>{t("mb.pt.expired")}</dt><dd>{unit(t, s.data.totals.expired)}</dd></div>
          </dl>
        </div>
      ) : null}
      <Frame s={s} empty="mb.pt.empty">
        {(d) => (
          <ul className="mb-rows">
            {d.items.map((e: MemberPointEntry) => (
              <li key={e.id} className="mb-row">
                <div className="mb-row__top">
                  <b className="mb-ell">{(POINT_TYPES as readonly string[]).includes(e.type) ? t(`mb.pt.t.${e.type}` as MessageKey) : e.type}</b>
                  <span className={e.direction === "out" ? "mb-amt is-out" : "mb-amt is-in"}>{e.direction === "out" ? "−" : "+"}{unit(t, e.amount)}</span>
                </div>
                <div className="mb-row__sub">{minuteOf(e.createdAt)}{e.balance !== null ? ` · ${t("mb.pt.after", { n: formatNumber(e.balance) })}` : ""}</div>
                {e.reason ? <p className="mb-row__msg">{e.reason}</p> : null}
                {e.referenceType ? <div className="mb-row__sub">{e.referenceType}{e.referenceId ? ` #${e.referenceId}` : ""}</div> : null}
                {e.expiresAt && e.direction === "in" ? <div className="mb-row__sub">{t("mb.pt.expires", { date: dayOf(e.expiresAt) })}</div> : null}
              </li>
            ))}
          </ul>
        )}
      </Frame>
    </>
  );
}

export function HistoryTab({ no }: { no: number }) {
  const t = useT();
  const s = usePaged<MemberPage<MemberHistoryEntry>>(no, fetchMemberHistory);
  return (
    <Frame s={s} empty="mb.hi.empty">
      {(d) => (
        <ul className="mb-rows">
          {d.items.map((h) => (
            <li key={h.id} className="mb-row">
              <div className="mb-row__top"><b className="mb-ell">{h.action === "email_change" || h.action === "password_reset" ? t(`mb.hi.a.${h.action}` as MessageKey) : h.action}</b></div>
              <div className="mb-row__sub">{minuteOf(h.createdAt)} · {t("mb.hi.by", { name: h.adminUsername })}</div>
              {h.after ? <p className="mb-row__msg">{t("mb.hi.new", { email: h.after })}</p> : null}
            </li>
          ))}
        </ul>
      )}
    </Frame>
  );
}
