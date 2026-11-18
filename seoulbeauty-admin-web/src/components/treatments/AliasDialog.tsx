"use client";

import { useEffect, useId, useRef } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { Pill, type PillTone } from "../hospitals/parts";
import type { TreatmentItem } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

/** One colour per language so the list scans quickly; the code itself is always written out. */
const LANG_TONES: Record<string, PillTone> = { ko: "info", en: "plain", zh: "warn", ja: "off" };

/** The aliases of one treatment: native <dialog>, a bottom sheet below 640px. View only. */
export default function AliasDialog({ item, onClose }: { item: TreatmentItem; onClose: () => void }) {
  const t = useT();
  const uid = useId();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = `${uid}-title`;

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
                </li>
              ))}
            </ul>
          )}
        </div>
        <div className="tm__alias-foot">
          <button type="button" className="btn btn--ghost" onClick={onClose}>{t("tm.alias.close")}</button>
        </div>
      </div>
    </dialog>,
    document.body,
  );
}
