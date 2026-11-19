"use client";

import { useEffect, useRef, useState } from "react";
import { LAYOUT_PREVIEW } from "@/lib/hospitalApplyApi";
import type { ApplyCounts } from "@/lib/hospitalApply";
import { useT } from "@/lib/locale";

const SECONDS = 5;

/**
 * Shown over the page once the hospital is saved: a drawn check mark, what was stored, and a short countdown
 * to the hospital list (it can be stopped; Esc also stops it instead of closing the dialog).
 */
export default function SuccessDialog({ name, counts, onGo }: { name: string; counts: ApplyCounts; onGo: () => void }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const [remaining, setRemaining] = useState(SECONDS);
  const [paused, setPaused] = useState(false);

  useEffect(() => {
    const dialog = ref.current;
    if (dialog && !dialog.open) {
      dialog.showModal();
      dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus();
    }
  }, []);

  useEffect(() => {
    if (paused) return;
    if (remaining <= 0) {
      onGo();
      return;
    }
    const timer = window.setTimeout(() => setRemaining((n) => n - 1), 1000);
    return () => window.clearTimeout(timer);
  }, [paused, remaining, onGo]);

  const chips: string[] = [];
  if (counts.doctors > 0) chips.push(t("hap.ok.chipDoc", { n: counts.doctors }));
  if (counts.treatments > 0) chips.push(t("hap.ok.chipTreat", { n: counts.treatments }));
  if (counts.prices > 0) chips.push(t("hap.ok.chipPrice", { n: counts.prices }));
  if (counts.images > 0) chips.push(t("hap.ok.chipImg", { n: counts.images }));

  return (
    <dialog
      ref={ref}
      className="ha-dlg"
      aria-labelledby="ha-ok-title"
      onCancel={(e) => {
        e.preventDefault();
        setPaused(true);
      }}
    >
      <div className="ha-dlg__box">
        <div className="ha-okring" aria-hidden="true">
          <svg viewBox="0 0 88 88">
            <circle className="c1" cx="44" cy="44" r="36" />
            <path className="c2" d="M30 46l10 10 20-22" transform="rotate(-90 44 44)" />
          </svg>
        </div>
        <h2 id="ha-ok-title">{t("hap.ok.title")}</h2>
        <p className="ha-dlg__name">{name}</p>
        <p><span className="pill pill--off">{t("hap.ok.state")}</span></p>
        <p className="ha-dlg__sub">{t("hap.ok.note")}</p>
        {LAYOUT_PREVIEW ? <p className="ha-dlg__preview">{t("hap.ok.preview")}</p> : null}
        {chips.length > 0 ? (
          <ul className="ha-chips ha-chips--center">
            {chips.map((c) => (
              <li key={c} className="ha-chip ha-chip--plain">{c}</li>
            ))}
          </ul>
        ) : null}
        <div className="ha-cd" role="timer" aria-live="off">
          <p className="ha-cd__t">{paused ? t("hap.ok.paused") : t("hap.ok.count", { n: remaining })}</p>
          <div className="ha-bar"><i style={{ width: `${(remaining / SECONDS) * 100}%` }} /></div>
        </div>
        <div className="ha-dlg__acts">
          <button type="button" className="btn btn--primary" data-autofocus onClick={onGo}>{t("hap.ok.now")}</button>
          <button type="button" className="txtbtn" onClick={() => setPaused((p) => !p)}>{paused ? t("hap.ok.resume") : t("hap.ok.pause")}</button>
        </div>
      </div>
    </dialog>
  );
}
