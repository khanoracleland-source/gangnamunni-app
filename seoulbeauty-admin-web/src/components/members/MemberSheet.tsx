"use client";

import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { BlocksTab, BookingsTab, ConsultationsTab, HistoryTab, PointsTab } from "./ActivityTabs";
import { EmailDialog, PasswordDialog, StatusDialog } from "./MemberDialogs";
import StatusPill from "./StatusPill";
import { fetchMember, type ApiResult, type Features, type MemberDetail } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { dayOf, minuteOf } from "@/lib/members";

type Tab = "info" | "consultations" | "bookings" | "points" | "blocks" | "history";

/** One member: a side sheet on wide screens, a bottom sheet on phones. Info plus the activity tabs; e-mail and password are the only changes. */
export default function MemberSheet({ no, features, onClose, onChanged }: { no: number; features: Features; onClose: () => void; onChanged: () => void }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const tabsId = useId();
  const [tab, setTab] = useState<Tab>("info");
  const [retry, setRetry] = useState(0);
  const [changes, setChanges] = useState(0);
  const [got, setGot] = useState<{ key: string; res: ApiResult<MemberDetail> } | null>(null);
  const [dialog, setDialog] = useState<"email" | "password" | "block" | "unblock" | "leave" | null>(null);
  const key = `${no}#${retry}`;

  useEffect(() => {
    const dialogEl = ref.current;
    if (!dialogEl) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialogEl.open) dialogEl.showModal();
    dialogEl.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialogEl.open) dialogEl.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);

  useEffect(() => {
    const c = new AbortController();
    void fetchMember(no, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) window.location.assign(PHP_ADMIN_URL);
      setGot({ key, res });
    });
    return () => c.abort();
    // `key` stands for the member number and the retry counter.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const m = got?.res.ok ? got.res.data : null;
  const failed = got?.key === key && !got.res.ok;
  const tabs: Tab[] = features.membersActivity ? ["info", "consultations", "bookings", "points", "blocks", "history"] : ["info"];
  const canWrite = !!m && m.status !== "left";
  const showEmail = canWrite && features.membersWrite;
  const showPassword = canWrite && features.membersPassword;
  const showBlock = !!m && m.status === "active" && features.membersStatus;
  const showUnblock = !!m && m.status === "blocked" && features.membersStatus;
  const showLeave = showBlock;

  const done = (fresh?: MemberDetail) => {
    setDialog(null);
    setChanges((n) => n + 1);
    if (fresh) setGot({ key, res: { ok: true, data: fresh } });
    onChanged();
  };

  let body: ReactNode;
  if (m) {
    body = tab === "info" ? <InfoPane m={m} /> : tab === "consultations" ? <ConsultationsTab no={m.no} /> : tab === "bookings" ? <BookingsTab no={m.no} /> : tab === "points" ? <PointsTab no={m.no} /> : tab === "blocks" ? <BlocksTab key={changes} no={m.no} /> : <HistoryTab key={changes} no={m.no} />;
  } else if (failed) {
    body = <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost btn--sm" onClick={() => setRetry((n) => n + 1)}>{t("state.retry")}</button>}>{t("mb.err.detail")}</Notice>;
  } else {
    body = <div className="mb-skel" role="status" aria-label={t("mb.loading")}>{[0, 1, 2, 3].map((i) => <span key={i} className="mb-skel__row" aria-hidden="true" />)}</div>;
  }

  return createPortal(
    <dialog ref={ref} className="dialog mb-sheet" aria-label={t("mb.d.title")} onClose={onClose} onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <span className="mb-sheet__handle" aria-hidden="true" />
      <header className="mb-sheet__head">
        <div className="mb-sheet__who">
          <h2 className="mb-sheet__name">{m ? m.name || m.id : t("mb.d.title")}</h2>
          {m ? <p className="mb-sheet__id">{m.id} · #{m.no}</p> : null}
        </div>
        {m ? <StatusPill status={m.status} /> : null}
        <button type="button" className="ibtn mb-sheet__close" onClick={onClose} aria-label={t("mb.d.close")} data-autofocus=""><Icon name="close" size={18} /></button>
      </header>
      {tabs.length > 1 ? (
        <div className="mb-tabs" role="tablist" aria-label={t("mb.tabs")}>
          {tabs.map((k) => (
            <button key={k} type="button" role="tab" id={`${tabsId}-${k}`} aria-selected={tab === k} aria-controls={`${tabsId}-panel`} className="mb-tab" onClick={() => setTab(k)}>{t(`mb.tab.${k}` as MessageKey)}</button>
          ))}
        </div>
      ) : null}
      <div className="mb-sheet__body" id={`${tabsId}-panel`} role={tabs.length > 1 ? "tabpanel" : undefined} aria-labelledby={tabs.length > 1 ? `${tabsId}-${tab}` : undefined}>{body}</div>
      {m && m.status === "left" ? <p className="mb-sheet__note"><Icon name="info" size={15} />{t("mb.a.left")}</p> : null}
      {showEmail || showPassword || showBlock || showUnblock ? (
        <footer className="mb-sheet__foot">
          {showBlock ? <button type="button" className="btn btn--reject" onClick={() => setDialog("block")}>{t("mb.a.block")}</button> : null}
          {showLeave ? <button type="button" className="btn btn--reject" onClick={() => setDialog("leave")}>{t("mb.a.leave")}</button> : null}
          {showUnblock ? <button type="button" className="btn btn--ghost" onClick={() => setDialog("unblock")}>{t("mb.a.unblock")}</button> : null}
          {showEmail ? <button type="button" className="btn btn--ghost" onClick={() => setDialog("email")}>{t("mb.a.email")}</button> : null}
          {showPassword ? <button type="button" className="btn btn--primary" onClick={() => setDialog("password")}>{t("mb.a.password")}</button> : null}
        </footer>
      ) : null}
      {m && dialog === "email" ? <EmailDialog m={m} onClose={() => setDialog(null)} onDone={done} /> : null}
      {m && (dialog === "block" || dialog === "unblock" || dialog === "leave") ? <StatusDialog m={m} action={dialog} onClose={() => setDialog(null)} onDone={done} /> : null}
      {m && dialog === "password" ? <PasswordDialog m={m} onClose={() => setDialog(null)} onDone={() => done()} /> : null}
    </dialog>,
    document.body,
  );
}

function InfoPane({ m }: { m: MemberDetail }) {
  const t = useT();
  const row = (label: MessageKey, value: string) => (
    <div className="mb-kv"><dt>{t(label)}</dt><dd>{value || "—"}</dd></div>
  );
  const addr = [m.addr1, m.addr2].filter(Boolean).join(" ");
  return (
    <dl className="mb-info">
      {row("mb.i.id", m.id)}
      {row("mb.i.name", m.name)}
      {row("mb.i.nick", m.nick)}
      {row("mb.i.email", m.email)}
      {row("mb.i.phone", m.phone)}
      {m.tel ? row("mb.i.tel", m.tel) : null}
      {m.sex ? row("mb.i.sex", t(m.sex === "M" ? "mb.i.sex.M" : "mb.i.sex.F")) : null}
      {m.birth ? row("mb.i.birth", m.birth) : null}
      {addr ? row("mb.i.addr", addr) : null}
      {row("mb.i.joined", minuteOf(m.joinedAt))}
      {row("mb.i.login", minuteOf(m.lastLoginAt))}
      {m.loginIp ? row("mb.i.ip", m.loginIp) : null}
      <div className="mb-kv"><dt>{t("mb.i.consent")}</dt><dd>{t("mb.i.sms")}: {t(m.smsConsent ? "mb.i.yes" : "mb.i.no")} · {t("mb.i.mail")}: {t(m.mailConsent ? "mb.i.yes" : "mb.i.no")}</dd></div>
      {m.blockedDate ? row("mb.i.blocked", dayOf(m.blockedDate)) : null}
      {m.leftDate ? row("mb.i.left", dayOf(m.leftDate)) : null}
    </dl>
  );
}
