"use client";

import type { ReactNode } from "react";
import Icon from "./Icon";
import { isLongValue } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

export type MetricId =
  | "all" | "general" | "withdrawn" | "today" | "signup"
  | "bookCount" | "bookAmount" | "bookPaid" | "bookRefund" | "bookCancel" | "bookPending" | "consult"
  | "hospitals" | "doctors" | "treatments";

export const metricKey = (id: MetricId, part: "name" | "what" | "period" | "extra"): MessageKey => `m.${id}.${part}` as MessageKey;

export type MetricUnit = "person" | "case" | "won" | "place" | "item";

type Props = {
  id: MetricId;
  /** Already formatted, e.g. "12,345,678". */
  value: string;
  unit: MetricUnit;
  /** One line under the number: a caption or the change against the previous period. */
  note: ReactNode;
  hero?: boolean;
  onHelp: (id: MetricId) => void;
};

/** One number: name, "?" button, big value with its unit, one-line explanation. */
export default function Metric({ id, value, unit, note, hero, onHelp }: Props) {
  const t = useT();
  const name = t(metricKey(id, "name"));
  const long = isLongValue(value);
  const cls = ["metric", hero ? "metric--hero" : "", long ? "is-long" : ""].filter(Boolean).join(" ");
  return (
    <article className={cls} aria-label={name}>
      <header className="metric__head">
        <h3 className="metric__name">{name}</h3>
        <button type="button" className="help-btn" onClick={() => onHelp(id)} aria-label={t("m.help", { name })} title={t("m.help", { name })}>
          <Icon name="help" size={16} />
        </button>
      </header>
      <p className="metric__figure">
        <span className="metric__value">{value}</span>
        <span className="metric__unit">{t(`unit.${unit}`)}</span>
      </p>
      <p className="metric__note">{note}</p>
    </article>
  );
}

export function MetricSkeleton({ hero }: { hero?: boolean }) {
  return (
    <div className={hero ? "metric metric--hero" : "metric"} aria-hidden="true">
      <div className="metric__head"><span className="skeleton" style={{ width: "40%", height: 16 }} /></div>
      <div className="metric__figure"><span className="skeleton" style={{ width: "55%", height: hero ? 44 : 32 }} /></div>
      <div className="metric__note"><span className="skeleton" style={{ width: "70%", height: 13 }} /></div>
    </div>
  );
}
