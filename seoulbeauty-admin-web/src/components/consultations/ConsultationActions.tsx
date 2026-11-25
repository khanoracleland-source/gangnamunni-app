"use client";

import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { showUnitToast } from "../treatment-units/UnitToast";
import { changeConsultationStatus, saveConsultationMessage, type ConsultationDetail } from "@/lib/api";
import { ACTION_STATUS, MESSAGE_MAX, PUSH_APPROVED, PUSH_FAILED, actionsFor, messageLength, type ConsultationAction } from "@/lib/consultations";
import { PHP_ADMIN_URL } from "@/lib/config";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Failure = { ok: false; status: number; error: string };
/** The sentence for a refused change. A lost session (401) is handled by the caller before this. */
function failureKey(f: Failure): MessageKey {
  if (f.status === 403) return "cs.w.forbidden";
  if (f.status === 404) return "cs.w.notFound";
  if (f.status === 400 && f.error === "too_long") return "cs.w.tooLong";
  if (f.status === 503) return "cs.w.unavailable";
  return "cs.w.failed";
}

const LABEL: Record<ConsultationAction, MessageKey> = { approve: "cs.a.approve", reject: "cs.a.reject", contact_failed: "cs.a.failed", complete: "cs.a.complete" };
const ICON: Record<ConsultationAction, "check" | "close" | "phone"> = { approve: "check", reject: "close", contact_failed: "phone", complete: "check" };
const CLS: Record<ConsultationAction, string> = { approve: "btn btn--approve", reject: "btn btn--reject", contact_failed: "btn btn--ghost", complete: "btn btn--approve" };
const CTA_CLS: Record<ConsultationAction, string> = { approve: "btn btn--approve", reject: "btn btn--danger", contact_failed: "btn btn--primary", complete: "btn btn--approve" };

/** The buttons under the detail. Without write rights it says why there are none. */
export function ActionBar({ d, canWrite, busy, onPick }: { d: ConsultationDetail; canWrite: boolean; busy: boolean; onPick: (a: ConsultationAction) => void }) {
  const t = useT();
  if (!canWrite) return <p className="cs-ro"><Icon name="lock" size={15} />{t("cs.a.writeOff")}</p>;
  const acts = actionsFor(d.status);
  if (!acts.length) return <p className="cs-ro"><Icon name="check" size={15} />{t("cs.a.done")}</p>;
  return (
    <>
      {acts.map((a) => (
        <button key={a} type="button" className={CLS[a]} onClick={() => onPick(a)} disabled={busy}><Icon name={ICON[a]} size={16} />{t(LABEL[a])}</button>
      ))}
    </>
  );
}

function useModal(ref: React.RefObject<HTMLDialogElement | null>) {
  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialog.open) dialog.close();
      // The button that opened the dialog may be gone (the status changed): fall back to the detail's close button.
      if (returnTo?.isConnected) returnTo.focus();
      else document.querySelector<HTMLElement>(".cs-d__close")?.focus({ preventScroll: true });
    };
  }, [ref]);
}

function Modal({ title, titleId, busy, onClose, children }: { title: string; titleId: string; busy: boolean; onClose: () => void; children: ReactNode }) {
  const ref = useRef<HTMLDialogElement>(null);
  useModal(ref);
  return createPortal(
    <dialog
      ref={ref}
      className="dialog cs-confirm"
      aria-labelledby={titleId}
      onCancel={(e) => { if (busy) e.preventDefault(); }}
      onClose={onClose}
      onClick={(e) => { if (e.target === e.currentTarget && !busy) onClose(); }}
    >
      <div className="dialog__box">
        <div className="dialog__head"><h2 id={titleId} className="dialog__title">{title}</h2></div>
        {children}
      </div>
    </dialog>,
    document.body,
  );
}

/** Asks before a state change; says what the patient is told and what cannot be undone. */
export function ConfirmDialog({ d, action, onClose, onDone }: {
  d: ConsultationDetail; action: ConsultationAction; onClose: () => void; onDone: (a: ConsultationAction, wantMessage: boolean) => void;
}) {
  const t = useT();
  const titleId = useId();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [wantMsg, setWantMsg] = useState(true);
  const k = action === "contact_failed" ? "failed" : action;
  const push = action === "approve" || action === "contact_failed";
  const irrev = action === "reject" || action === "complete";
  // The notice is always sent in Korean, whatever language the admin reads.
  const pushTitle = action === "approve" ? PUSH_APPROVED.title : PUSH_FAILED.title;
  const pushBody = action === "approve" ? d.hospitalName : `${d.hospitalName} · ${PUSH_FAILED.body}`;

  const go = async () => {
    if (busy) return;
    setBusy(true);
    setError(null);
    const res = await changeConsultationStatus(d.id, ACTION_STATUS[action]);
    if (!res.ok && res.status === 401) {
      // Stay locked while the browser leaves for the login.
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.ok) {
      showUnitToast({
        tone: "ok",
        message: res.data.changed ? t(`cs.ok.${ACTION_STATUS[action]}` as MessageKey) : t("cs.ok.same"),
        closeLabel: t("cs.d.close"),
      });
      if (res.data.changed && push) showUnitToast({ tone: "info", icon: "info", message: t("cs.ok.pushed"), closeLabel: t("cs.d.close") });
      onDone(action, action === "contact_failed" && wantMsg);
      return;
    }
    if (res.status === 404) {
      showUnitToast({ tone: "error", message: t("cs.w.notFound"), closeLabel: t("cs.d.close") });
      onDone(action, false);
      return;
    }
    setError(t(failureKey(res)));
    setBusy(false);
  };

  return (
    <Modal title={t(`cs.c.${k}.t` as MessageKey)} titleId={titleId} busy={busy} onClose={onClose}>
      <p className="dialog__text">{action === "contact_failed" || action === "complete" ? t(`cs.c.${k}.lead` as MessageKey) : t(`cs.c.${k}.lead` as MessageKey, { name: d.name || "—" })}</p>
      {push ? (
        <div className="cs-push">
          <p className="cs-push__h"><Icon name="megaphone" size={15} />{t("cs.c.push")}</p>
          <div className="cs-push__card"><b>{pushTitle}</b><span>{pushBody}</span></div>
          <p className="cs-push__note">{t("cs.c.pushKo")} {t("cs.c.pushNote")}</p>
        </div>
      ) : <p className="cs-nopush"><Icon name="info" size={15} />{t("cs.c.noPush")}</p>}
      {irrev ? <p className="cs-irrev"><Icon name="alert" size={15} />{t(`cs.c.${k}.irrev` as MessageKey)}</p> : null}
      {action === "contact_failed" ? (
        <label className="cs-opt"><input type="checkbox" checked={wantMsg} onChange={(e) => setWantMsg(e.target.checked)} />{t("cs.c.failed.opt")}</label>
      ) : null}
      {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
      <div className="dialog__foot cs-confirm__foot">
        <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy} data-autofocus="">{t("cs.a.cancel")}</button>
        <button type="button" className={CTA_CLS[action]} onClick={() => void go()} disabled={busy} aria-busy={busy}>{busy ? t("cs.a.working") : t(`cs.c.${k}.cta` as MessageKey)}</button>
      </div>
    </Modal>
  );
}

/** Writes, changes or clears the message the patient sees. */
export function MessageDialog({ d, onClose, onSaved }: { d: ConsultationDetail; onClose: () => void; onSaved: () => void }) {
  const t = useT();
  const titleId = useId();
  const [value, setValue] = useState(d.hospitalMessage);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const len = messageLength(value);
  const over = len > MESSAGE_MAX;
  const unchanged = value.trim() === d.hospitalMessage.trim();

  const save = async () => {
    if (busy || over || unchanged) return;
    setBusy(true);
    setError(null);
    const res = await saveConsultationMessage(d.id, value);
    if (!res.ok && res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.ok) {
      showUnitToast({ tone: "ok", message: t(res.data.hospitalMessage ? "cs.ok.msgSaved" : "cs.ok.msgCleared"), closeLabel: t("cs.d.close") });
      onSaved();
      return;
    }
    if (res.status === 404) {
      showUnitToast({ tone: "error", message: t("cs.w.notFound"), closeLabel: t("cs.d.close") });
      onSaved();
      return;
    }
    setError(t(failureKey(res)));
    setBusy(false);
  };

  return (
    <Modal title={t("cs.m.title")} titleId={titleId} busy={busy} onClose={onClose}>
      <p className="dialog__text">{t("cs.m.lead", { name: d.name || "—" })}</p>
      <label className="cs-m">
        <span className="cs-m__label">{t("cs.m.label")}</span>
        <textarea className="input textarea" rows={5} value={value} onChange={(e) => setValue(e.target.value)} aria-invalid={over} disabled={busy} data-autofocus="" />
        <span className={over ? "cs-m__count is-over" : "cs-m__count"} aria-live="polite">{t("cs.m.count", { n: String(len), max: String(MESSAGE_MAX) })}</span>
      </label>
      {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
      <div className="dialog__foot cs-confirm__foot">
        <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("cs.a.cancel")}</button>
        <button type="button" className="btn btn--primary" onClick={() => void save()} disabled={busy || over || unchanged} aria-busy={busy}>{busy ? t("cs.a.working") : t("cs.m.save")}</button>
      </div>
    </Modal>
  );
}
