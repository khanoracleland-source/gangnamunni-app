"use client";

import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { Pill, type PillTone } from "../hospitals/parts";
import { addTreatmentAlias, removeTreatmentAlias, type TreatmentItem } from "@/lib/api";
import { failureKey } from "./writeErrors";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

/** One colour per language so the list scans quickly; the code itself is always written out. */
const LANG_TONES: Record<string, PillTone> = { ko: "info", en: "plain", zh: "warn", ja: "off" };

const LANGS = ["ko", "en", "zh", "ja"] as const;

/** The aliases of one treatment: native <dialog>, a bottom sheet below 640px. With `canWrite` aliases can be added and removed; `onChanged` reloads the list. */
export default function AliasDialog({ item, canWrite, onChanged, onClose }: { item: TreatmentItem; canWrite: boolean; onChanged: () => Promise<void>; onClose: () => void }) {
  const t = useT();
  const uid = useId();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = `${uid}-title`;
  const [alias, setAlias] = useState("");
  const [lang, setLang] = useState<(typeof LANGS)[number]>("ko");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const add = async (e: FormEvent) => {
    e.preventDefault();
    const value = alias.trim();
    if (value === "" || busy) return;
    setBusy(true);
    setError(null);
    const res = await addTreatmentAlias(item.id, value, lang);
    if (res.ok) {
      setAlias("");
      toast.success(t("tmw.ok.aliasAdded"));
      await onChanged();
    } else setError(t(failureKey(res)));
    setBusy(false);
  };
  const remove = async (aliasId: number) => {
    if (busy) return;
    setBusy(true);
    setError(null);
    const res = await removeTreatmentAlias(item.id, aliasId);
    if (res.ok) {
      toast.success(t("tmw.ok.aliasRemoved"));
      await onChanged();
    } else setError(t(failureKey(res)));
    setBusy(false);
  };

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
      className="dialog dialog--prices dialog--alias"
      aria-labelledby={titleId}
      onClose={onClose}
      onClick={(e) => {
        // A click on the backdrop lands on the <dialog> element itself.
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="htp">
        <span className="htp__handle" aria-hidden="true" />
        <div className="htp__head">
          <div className="htp__headt">
            <h2 id={titleId} className="dialog__title htp__title">{t("tm.alias.title")}</h2>
            <span className="htp__chip" translate="no" title={item.name}>{t("tm.alias.chip", { name: item.name, n: formatNumber(item.aliases.length) })}</span>
          </div>
          <button type="button" className="ibtn dialog__close" onClick={onClose} aria-label={t("tm.alias.close")} title={t("tm.alias.close")} data-autofocus="">
            <Icon name="close" size={18} />
          </button>
        </div>
        <div className="htp__body tm__alias-body">
          <p className="tm__alias-hint">
            <Icon name="info" size={16} />
            <span>{t("tm.alias.hint")}</span>
          </p>
          {item.aliases.length === 0 ? (
            <p className="tm__alias-empty">{t("tm.alias.empty")}</p>
          ) : (
            <ul className="tm__alias-list">
              {item.aliases.map((a) => (
                <li key={a.id}>
                  <span className="tm__alias-text" translate="no">{a.alias}</span>
                  <span className="sr-only">{t("tm.alias.lang", { lang: a.lang.toUpperCase() })}</span>
                  <Pill tone={LANG_TONES[a.lang] ?? "plain"}>{a.lang.toUpperCase()}</Pill>
                  {canWrite ? (
                    <button type="button" className="ibtn" disabled={busy} onClick={() => void remove(a.id)} aria-label={t("tmw.alias.remove", { alias: a.alias })} title={t("tmw.delete")}>
                      <Icon name="trash" size={16} />
                    </button>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </div>
        {canWrite ? (
          <form className="tm__alias-add" onSubmit={add}>
            <input className="input" value={alias} maxLength={100} onChange={(e) => setAlias(e.target.value)} placeholder={t("tmw.alias.input")} aria-label={t("tmw.alias.input")} autoComplete="off" />
            <select className="sel sel--field" value={lang} onChange={(e) => setLang(e.target.value as (typeof LANGS)[number])} aria-label={t("tmw.alias.lang")}>
              {LANGS.map((l) => (
                <option key={l} value={l}>{l.toUpperCase()}</option>
              ))}
            </select>
            <button type="submit" className="btn btn--primary" disabled={busy || alias.trim() === ""}>{t("tmw.alias.add")}</button>
          </form>
        ) : null}
        {error ? <p className="field__error tm__alias-error" role="alert">{error}</p> : null}
        <div className="tm__alias-foot">
          <button type="button" className="btn btn--ghost" onClick={onClose}>{t("tm.alias.close")}</button>
        </div>
      </div>
    </dialog>,
    document.body,
  );
}
