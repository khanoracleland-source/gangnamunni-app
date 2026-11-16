"use client";

import { useRouter } from "next/navigation";
import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import Icon from "../Icon";
import { Notice } from "./parts";
import {
  deleteHospital,
  fetchDeleteCheck,
  setHospitalActive,
  type DeleteBlocker,
  type DeleteCheck,
} from "@/lib/api";
import { isFutureKst, minutePrecision } from "@/lib/archive";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import { failureKey } from "@/lib/hospitalEdit";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Phase =
  | { kind: "checking" }
  | { kind: "failed"; key: MessageKey }
  | { kind: "ready"; check: DeleteCheck }
  | { kind: "blocked"; name: string; blockers: DeleteBlocker[]; deletableAfter: string; stale: boolean };

/**
 * Deleting a hospital for good, in two steps: first the server says whether it may be deleted (and what that would
 * remove); then the name has to be typed. Mount it when the button is pressed and unmount it in `onClose`.
 */
export default function DeleteHospitalDialog({
  hospitalId,
  hospitalName,
  isActive,
  updatedAt,
  onClose,
  onDeactivated,
  onReload,
}: {
  hospitalId: number;
  hospitalName: string;
  isActive: boolean;
  /** The `updatedAt` the page was loaded with. */
  updatedAt: string;
  onClose: () => void;
  onDeactivated: () => void;
  onReload: () => void;
}) {
  const t = useT();
  const router = useRouter();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const nameId = useId();
  const [phase, setPhase] = useState<Phase>({ kind: "checking" });
  const [typed, setTyped] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [conflict, setConflict] = useState(false);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const dialog = ref.current;
    if (dialog && !dialog.open) dialog.showModal();
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchDeleteCheck(hospitalId, controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        const c = res.data;
        setPhase(
          c.canDelete && c.blockers.length === 0
            ? { kind: "ready", check: c }
            : { kind: "blocked", name: c.name, blockers: c.blockers, deletableAfter: c.deletableAfter, stale: false },
        );
        return;
      }
      if (res.status === 401) {
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      setPhase({ kind: "failed", key: failureKey(res.status) });
    };
    void run();
    return () => controller.abort();
  }, [hospitalId, attempt]);

  // Each step puts the cursor where the next action is: the name field, or the safe button.
  const stage = phase.kind;
  useEffect(() => {
    if (stage === "ready" || stage === "blocked") ref.current?.querySelector<HTMLElement>("[data-autofocus]")?.focus();
  }, [stage]);

  const checkedName = phase.kind === "ready" ? phase.check.name : "";
  const nameMatches = phase.kind === "ready" && typed === (checkedName || hospitalName);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (phase.kind !== "ready" || !nameMatches || busy) return;
    setBusy(true);
    setError(null);
    const res = await deleteHospital(hospitalId, typed, updatedAt || null);
    if (res.ok) {
      router.push(`/hospital-archive/${res.data.archiveId}?deleted=1`);
      return;
    }
    setBusy(false);
    if (res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.status === 400 && res.fields.confirmName) {
      setError(t("hd.err.mismatch"));
    } else if (res.status === 409 && res.error === "blocked") {
      setPhase({ kind: "blocked", name: phase.check.name, blockers: res.blockers, deletableAfter: res.deletableAfter, stale: true });
    } else if (res.status === 409) {
      setConflict(true);
      setError(t("hd.err.conflict"));
    } else {
      setError(t(failureKey(res.status)));
    }
  };

  const deactivate = async () => {
    if (busy) return;
    setBusy(true);
    setError(null);
    const res = await setHospitalActive(hospitalId, false);
    setBusy(false);
    if (res.ok) {
      onDeactivated();
      return;
    }
    if (res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    setError(t(failureKey(res.status)));
  };

  return (
    <dialog
      ref={ref}
      className="dialog dialog--wide"
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
          <h2 id={titleId} className="dialog__title">{t("hd.title")}</h2>
          <button type="button" className="ibtn dialog__close" onClick={() => ref.current?.close()} disabled={busy} aria-label={t("m.close")} title={t("m.close")}>
            <Icon name="close" />
          </button>
        </div>

        {phase.kind === "checking" ? <p className="dialog__text" role="status">{t("hd.checking")}</p> : null}

        {phase.kind === "failed" ? (
          <>
            <Notice tone="warn" icon="alert">{t(phase.key)}</Notice>
            <div className="dialog__foot">
              <button type="button" className="btn btn--ghost" onClick={() => ref.current?.close()}>{t("m.close")}</button>
              <button type="button" className="btn btn--primary" onClick={() => { setPhase({ kind: "checking" }); setAttempt((n) => n + 1); }}>{t("state.retry")}</button>
            </div>
          </>
        ) : null}

        {phase.kind === "blocked" ? (
          <>
            <p className="dialog__name" translate="no">{phase.name || hospitalName}</p>
            {phase.stale ? <Notice tone="warn" icon="alert">{t("hd.err.blocked")}</Notice> : null}
            <p className="dialog__text dialog__text--strong">{t("hd.blocked.lead")}</p>
            <ul className="blockers">
              {phase.blockers.map((b, i) => (
                <li key={`${b.code}-${i}`} className="blockers__item">
                  <BlockerText blocker={b} />
                </li>
              ))}
            </ul>
            {isFutureKst(phase.deletableAfter) ? (
              <p className="dialog__text dialog__text--strong">{t("hd.after", { at: minutePrecision(phase.deletableAfter) })}</p>
            ) : phase.blockers.some((b) => b.code === "upcoming_bookings" || b.code === "open_consultations") ? (
              <p className="dialog__text dialog__text--strong">{t("hd.finishFirst")}</p>
            ) : null}
            {isActive ? <p className="dialog__text">{t("hd.blocked.hint")}</p> : <Notice>{t("hd.alreadyInactive")}</Notice>}
            {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
            <div className="dialog__foot">
              <button type="button" className="btn btn--ghost" onClick={() => ref.current?.close()} disabled={busy} data-autofocus="">{t("m.close")}</button>
              {isActive ? (
                <button type="button" className="btn btn--primary" onClick={deactivate} disabled={busy}>
                  {busy ? t("h.act.working") : t("hd.deactivate")}
                </button>
              ) : null}
            </div>
          </>
        ) : null}

        {phase.kind === "ready" ? (
          <>
            <p className="dialog__name" translate="no">{phase.check.name || hospitalName}</p>
            <p className="dialog__text">{t("hd.ready.lead")}</p>

            <h3 className="dialog__sub">{t("hd.will.title")}</h3>
            <ul className="dialog__bullets">
              <li>{t("hd.will.hospital")}</li>
              <li>{t("hd.will.images", { n: formatNumber(phase.check.willDelete.images) })}</li>
              <li>{t("hd.will.doctors", { n: formatNumber(phase.check.willDelete.doctors) })}</li>
              <li>{t("hd.will.treatments", { n: formatNumber(phase.check.willDelete.treatments) })}</li>
            </ul>

            <h3 className="dialog__sub">{t("hd.keep.title")}</h3>
            <p className="dialog__text">{t("hd.keep.body")}</p>

            <h3 className="dialog__sub">{t("hd.sum.title")}</h3>
            <ul className="dialog__bullets">
              <li>{t("hd.sum.bookings", { n: formatNumber(phase.check.stats.bookings), cancelled: formatNumber(phase.check.stats.cancelled) })}</li>
              <li>
                {t("hd.sum.sales", {
                  net: t("hs.money", { n: formatNumber(phase.check.stats.sales.net) }),
                  paid: t("hs.money", { n: formatNumber(phase.check.stats.sales.paid) }),
                  refunded: t("hs.money", { n: formatNumber(phase.check.stats.sales.refunded) }),
                })}
              </li>
            </ul>

            <div className="field dialog__confirm">
              <label className="field__label" htmlFor={nameId}>{t("hd.confirm.label")}</label>
              <input
                id={nameId}
                className="input"
                type="text"
                value={typed}
                onChange={(e) => setTyped(e.target.value)}
                placeholder={t("hd.confirm.placeholder")}
                autoComplete="off"
                autoCapitalize="off"
                spellCheck={false}
                disabled={busy || conflict}
                data-autofocus=""
              />
            </div>
            {error ? <div className="dialog__err"><Notice tone="warn" icon="alert">{error}</Notice></div> : null}
            <div className="dialog__foot">
              <button type="button" className="btn btn--ghost" onClick={() => ref.current?.close()} disabled={busy}>{t("h.c.cancel")}</button>
              {conflict ? (
                <button type="button" className="btn btn--primary" onClick={onReload}>{t("hd.err.reload")}</button>
              ) : (
                <button type="submit" className="btn btn--danger" disabled={!nameMatches || busy}>
                  {busy ? t("h.act.working") : t("hd.confirm.btn")}
                </button>
              )}
            </div>
          </>
        ) : null}
      </form>
    </dialog>
  );
}

function BlockerText({ blocker: b }: { blocker: DeleteBlocker }) {
  const t = useT();
  if (b.code === "upcoming_bookings") {
    return <>{b.lastAt ? t("hd.b.upcomingLast", { n: formatNumber(b.count), at: minutePrecision(b.lastAt) }) : t("hd.b.upcoming", { n: formatNumber(b.count) })}</>;
  }
  if (b.code === "open_consultations") {
    return <>{b.lastAt ? t("hd.b.consultLast", { n: formatNumber(b.count), at: minutePrecision(b.lastAt) }) : t("hd.b.consult", { n: formatNumber(b.count) })}</>;
  }
  if (b.code === "unsettled") {
    return (
      <>
        <span className="blockers__main">{t("hd.b.unsettled")}</span>
        <span className="blockers__sub">{t("hd.b.unsettledCounts", { settlements: formatNumber(b.settlements), payments: formatNumber(b.unsettledPayments) })}</span>
      </>
    );
  }
  return <>{t("hd.b.other", { code: b.raw || "?" })}</>;
}
