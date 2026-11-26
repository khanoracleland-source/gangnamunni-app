"use client";

import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { showUnitToast } from "../treatment-units/UnitToast";
import { changeMemberEmail, changeMemberStatus, resetMemberPassword, type MemberDetail } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { PASSWORD_RE, writeErrorKey } from "@/lib/members";

/** A modal dialog; Escape and the backdrop close it unless a request is running. */
function Modal({ title, titleId, busy, onClose, children }: { title: string; titleId: string; busy: boolean; onClose: () => void; children: ReactNode }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);
  return createPortal(
    <dialog
      ref={ref}
      className="dialog"
      aria-labelledby={titleId}
      onCancel={(e) => { if (busy) e.preventDefault(); }}
      onClose={() => { if (!busy) onClose(); }}
      onClick={(e) => { if (e.target === e.currentTarget && !busy) onClose(); }}
    >
      <div className="dialog__box">
        <div className="dialog__head">
          <h2 className="dialog__title" id={titleId}>{title}</h2>
          <button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("mb.d.close")}><Icon name="close" size={18} /></button>
        </div>
        {children}
      </div>
    </dialog>,
    document.body,
  );
}

/** Changes the e-mail of one member. A refusal stays inside the dialog; the toast comes after it closes. */
export function EmailDialog({ m, onClose, onDone }: { m: MemberDetail; onClose: () => void; onDone: (fresh: MemberDetail) => void }) {
  const t = useT();
  const titleId = useId();
  const [value, setValue] = useState(m.email);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const save = async () => {
    if (busy) return;
    const email = value.trim();
    if (email === "") return setError(t("mb.em.err.required"));
    setBusy(true);
    setError(null);
    const res = await changeMemberEmail(m.no, email);
    if (!res.ok && res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.ok) {
      showUnitToast({ tone: "ok", message: t("mb.em.ok"), closeLabel: t("mb.d.close") });
      onDone(res.data);
      return;
    }
    setError(t(writeErrorKey(res, "em")));
    setBusy(false);
  };

  return (
    <Modal title={t("mb.em.title")} titleId={titleId} busy={busy} onClose={onClose}>
      <form onSubmit={(e) => { e.preventDefault(); void save(); }} noValidate>
        <p className="dialog__text">{t("mb.em.body", { id: m.id })}</p>
        <label className="mb-fld">
          <span className="mb-fld__l">{t("mb.em.label")}</span>
          <input className="input" type="email" inputMode="email" autoComplete="off" value={value} onChange={(e) => setValue(e.target.value)} aria-invalid={error !== null} disabled={busy} data-autofocus="" />
        </label>
        {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
        <div className="dialog__foot mb-foot">
          <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("mb.cancel")}</button>
          <button type="submit" className="btn btn--primary" disabled={busy} aria-busy={busy}>{busy ? t("cs.a.working") : t("mb.em.save")}</button>
        </div>
      </form>
    </Modal>
  );
}

/** Sets a new password the admin chose (the member asked by phone). The password is never shown again, kept or logged. */
export function PasswordDialog({ m, onClose, onDone }: { m: MemberDetail; onClose: () => void; onDone: () => void }) {
  const t = useT();
  const titleId = useId();
  const [value, setValue] = useState("");
  const [show, setShow] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const save = async () => {
    if (busy) return;
    if (value === "") return setError(t("mb.pw.err.required"));
    if (!PASSWORD_RE.test(value)) return setError(t("mb.pw.err.bad_format"));
    setBusy(true);
    setError(null);
    const res = await resetMemberPassword(m.no, value);
    if (!res.ok && res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.ok) {
      setValue("");
      showUnitToast({ tone: "ok", message: t("mb.pw.ok"), closeLabel: t("mb.d.close") });
      onDone();
      return;
    }
    setError(t(writeErrorKey(res, "pw")));
    setBusy(false);
  };

  return (
    <Modal title={t("mb.pw.title")} titleId={titleId} busy={busy} onClose={onClose}>
      <form onSubmit={(e) => { e.preventDefault(); void save(); }} noValidate autoComplete="off">
        <p className="dialog__text">{t("mb.pw.body")}</p>
        <label className="mb-fld">
          <span className="mb-fld__l">{t("mb.pw.label")}</span>
          <span className="mb-pw">
            <input className="input" type={show ? "text" : "password"} autoComplete="new-password" autoCapitalize="off" autoCorrect="off" spellCheck={false} value={value} onChange={(e) => setValue(e.target.value)} aria-invalid={error !== null} aria-describedby={`${titleId}-hint`} disabled={busy} data-autofocus="" />
            <button type="button" className="btn btn--ghost mb-pw__eye" onClick={() => setShow((s) => !s)} aria-pressed={show} disabled={busy}>{t(show ? "mb.pw.hide" : "mb.pw.show")}</button>
          </span>
          <span className="mb-fld__h" id={`${titleId}-hint`}>{t("mb.pw.hint")}</span>
        </label>
        {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
        <div className="dialog__foot mb-foot">
          <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("mb.cancel")}</button>
          <button type="submit" className="btn btn--primary" disabled={busy} aria-busy={busy}>{busy ? t("cs.a.working") : t("mb.pw.save")}</button>
        </div>
      </form>
    </Modal>
  );
}

/** Confirms one status change (block, unblock, leave). No reason is asked. A refusal stays inside the dialog; the toast comes after it closes. */
export function StatusDialog({ m, action, onClose, onDone }: { m: MemberDetail; action: "block" | "unblock" | "leave"; onClose: () => void; onDone: (fresh: MemberDetail) => void }) {
  const t = useT();
  const titleId = useId();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const target = action === "block" ? "blocked" : action === "unblock" ? "active" : "left";

  const confirm = async () => {
    if (busy) return;
    setBusy(true);
    setError(null);
    const res = await changeMemberStatus(m.no, target);
    if (!res.ok && res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.ok) {
      showUnitToast({ tone: "ok", message: t(`mb.st.${action}.ok` as MessageKey), closeLabel: t("mb.d.close") });
      onDone(res.data);
      return;
    }
    setError(t(writeErrorKey(res, "st")));
    setBusy(false);
  };

  return (
    <Modal title={t(`mb.st.${action}.title` as MessageKey)} titleId={titleId} busy={busy} onClose={onClose}>
      <p className="dialog__text">{t(`mb.st.${action}.body` as MessageKey, { id: m.id })}</p>
      {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
      <div className="dialog__foot mb-foot">
        <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy} data-autofocus="">{t("mb.cancel")}</button>
        <button type="button" className={action === "unblock" ? "btn btn--primary" : "btn btn--reject"} onClick={() => void confirm()} disabled={busy} aria-busy={busy}>{busy ? t("cs.a.working") : t(`mb.a.${action}` as MessageKey)}</button>
      </div>
    </Modal>
  );
}
