"use client";

import { useEffect, useRef } from "react";
import Icon from "./Icon";
import { metricKey, type MetricId } from "./Metric";
import { useT } from "@/lib/locale";

/** The "?" explanation of one number: what is counted, which period, what is left out. Native <dialog>: Esc and focus handling come with it. */
export default function HelpDialog({ metric, days, onClose }: { metric: MetricId | null; days: number; onClose: () => void }) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (metric && !dialog.open) dialog.showModal();
    if (!metric && dialog.open) dialog.close();
  }, [metric]);

  const vars = { days };
  const extra = metric ? t(metricKey(metric, "extra"), vars) : "";

  return (
    <dialog
      ref={ref}
      className="dialog"
      aria-labelledby="help-title"
      onClose={onClose}
      onClick={(e) => {
        // A click on the backdrop lands on the <dialog> element itself.
        if (e.target === e.currentTarget) onClose();
      }}
    >
      {metric ? (
        <div className="dialog__box">
          <div className="dialog__head">
            <h2 id="help-title" className="dialog__title">{t(metricKey(metric, "name"))}</h2>
            <button type="button" className="icon-btn" onClick={onClose} aria-label={t("m.close")}>
              <Icon name="close" size={16} />
            </button>
          </div>
          <dl className="dialog__list">
            <dt>{t("m.whatLabel")}</dt>
            <dd>{t(metricKey(metric, "what"), vars)}</dd>
            <dt>{t("m.periodLabel")}</dt>
            <dd>{t(metricKey(metric, "period"), vars)}</dd>
            {extra ? (
              <>
                <dt>{t("m.noteLabel")}</dt>
                <dd>{extra}</dd>
              </>
            ) : null}
          </dl>
          <div className="dialog__foot">
            <button type="button" className="btn btn--primary" onClick={onClose}>{t("m.close")}</button>
          </div>
        </div>
      ) : null}
    </dialog>
  );
}
