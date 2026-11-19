"use client";

import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { deleteAdmin, type AdminAccount } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";

/**
 * Deleting an account for good. Two states: still waiting (says how long, nothing to press) and ready (the login name has to be
 * typed). Mount it when the button is pressed and unmount it in `onClose`.
 */
export default function DeleteAdminDialog({
  account,
  deleteAfterDays,
  onClose,
  onDeleted,
  onStale,
}: {
  account: AdminAccount;
  deleteAfterDays: number;
  onClose: () => void;
  onDeleted: () => void;
  /** The server says the account is not deletable after all: reload the list. */
  onStale: () => void;
}) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const inputId = useId();
  const [typed, setTyped] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const ready = account.deleteReady;
  const matches = typed === account.username;

  useEffect(() => {
    const dialog = ref.current;
    if (dialog && !dialog.open) {
      dialog.showModal();
      dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus();
    }
  }, []);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (!ready || !matches || busy) return;
    setBusy(true);
    setError(null);
    const res = await deleteAdmin(account.id, typed);
    if (res.ok) {
      toast.success(t("ad.ok.deleted"));
      onDeleted();
      ref.current?.close();
      return;
    }
    setBusy(false);
    if (res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.status === 409) {
      toast.error(t("ad.del.stale"));
      onStale();
      ref.current?.close();
    } else if (res.status === 400) setError(t("ad.del.mismatch"));
    else if (res.status === 403) setError(t("ad.err.forbidden"));
    else if (res.status === 404) setError(t("ad.err.gone"));
    else if (res.status === 503) setError(t("ad.err.unavailable"));
    else if (res.status === 0) setError(t("ad.err.network"));
    else setError(t("ad.err.server"));
  };

  const days = account.inactiveDays;
  return (
    <dialog
      ref={ref}
      className="dialog dialog--sheet"
      aria-labelledby={titleId}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) ref.current?.close();
      }}
    >
      <form className="dialog__box" onSubmit={submit} noValidate>
        <div className="dialog__head">
          <h2 id={titleId} className="dialog__title">{t("ad.del.title")}</h2>
          <button type="button" className="ibtn dialog__close" onClick={() => ref.current?.close()} disabled={busy} aria-label={t("m.close")} title={t("m.close")}>
            <Icon name="close" />
          </button>
        </div>
        <p className="dialog__name" translate="no">{account.name || account.username}</p>
        <p className="dialog__text" translate="no">{account.username}</p>

        {ready ? (
          <>
            <p className="dialog__text dialog__text--strong">{t("ad.del.lead")}</p>
            <div className="field dialog__confirm">
              <label className="field__label" htmlFor={inputId}>{t("ad.del.confirm.label", { username: account.username })}</label>
              <input id={inputId} className="input" value={typed} onChange={(e) => setTyped(e.target.value)} autoComplete="off" autoCapitalize="off" spellCheck={false} disabled={busy} data-autofocus="" />
            </div>
          </>
        ) : (
          <Notice tone="warn" icon="alert">
            {days === null ? t("ad.del.waitUnknown") : t("ad.del.wait", { days, left: Math.max(0, deleteAfterDays - days) })}
          </Notice>
        )}

        {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
        <div className="dialog__foot">
          <button type="button" className="btn btn--ghost" onClick={() => ref.current?.close()} disabled={busy} data-autofocus={ready ? undefined : ""}>
            {ready ? t("h.c.cancel") : t("m.close")}
          </button>
          {ready ? (
            <button type="submit" className="btn btn--danger" disabled={!matches || busy}>{busy ? t("h.act.working") : t("ad.del.confirm.btn")}</button>
          ) : null}
        </div>
      </form>
    </dialog>
  );
}
