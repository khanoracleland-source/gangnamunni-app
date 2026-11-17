"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import Icon, { type IconName } from "./Icon";
import { isLongValue, isXLongValue } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

export type MetricId =
  | "all" | "general" | "withdrawn" | "today" | "signup"
  | "bookCount" | "bookAmount" | "bookPaid" | "bookRefund" | "bookCancel" | "bookPending" | "consult"
  | "hospitals" | "doctors" | "treatments";

export const metricKey = (id: MetricId, part: "name" | "what" | "period" | "extra"): MessageKey => `m.${id}.${part}` as MessageKey;

export type MetricUnit = "person" | "case" | "won" | "place" | "item";

type Kind = "mem" | "book" | "pay" | "cons" | "hosp";

/** Kind of number: decides the colour of the left stripe and the icon in front of the name. */
const KIND: Record<MetricId, { kind: Kind; icon: IconName }> = {
  all: { kind: "mem", icon: "people" },
  general: { kind: "mem", icon: "people" },
  withdrawn: { kind: "mem", icon: "people" },
  today: { kind: "mem", icon: "people" },
  signup: { kind: "mem", icon: "people" },
  bookCount: { kind: "book", icon: "calendar" },
  bookAmount: { kind: "book", icon: "calendar" },
  bookCancel: { kind: "book", icon: "calendar" },
  bookPending: { kind: "book", icon: "calendar" },
  bookPaid: { kind: "pay", icon: "card" },
  bookRefund: { kind: "pay", icon: "card" },
  consult: { kind: "cons", icon: "chat" },
  hospitals: { kind: "hosp", icon: "hospital" },
  doctors: { kind: "hosp", icon: "hospital" },
  treatments: { kind: "hosp", icon: "hospital" },
};

type Props = {
  id: MetricId;
  /** Already formatted, e.g. "12,345,678". */
  value: string;
  unit: MetricUnit;
  /** One line under the number: a caption or the change against the previous period. */
  note: ReactNode;
  /** When set, the whole card opens this page; the name becomes the link and the "?" button stays separate. */
  href?: string;
  hero?: boolean;
  /** Hero only: the two parts of the overall number, already formatted. */
  split?: { general: string; admins: string };
  /** This period against the one before it, drawn as two bars to scale (raw numbers; the labels are the formatted ones). */
  compare?: { current: number; previous: number; currentText: string; previousText: string };
  onHelp: (id: MetricId) => void;
};

/** One number: kind icon + name + "?" button, big value with its unit, one-line explanation. */
export default function Metric({ id, value, unit, note, href, hero, split, compare, onHelp }: Props) {
  const t = useT();
  const name = t(metricKey(id, "name"));
  const { kind, icon } = KIND[id];
  const cls = ["card", `c-${kind}`, hero ? "hero" : "", href ? "card--link" : "", isLongValue(value) ? "is-long" : "", isXLongValue(value) ? "is-xlong" : ""].filter(Boolean).join(" ");
  const unitText = t(`unit.${unit}`);
  const personUnit = t("unit.person");
  return (
    <article className={cls} aria-label={name}>
      <div className="card__main">
        <header className="card__top">
          <Icon name={icon} size={18} className="card__icon" />
          <h3 className="card__name">{href ? <Link className="card__link" href={href}>{name}</Link> : name}</h3>
          <button type="button" className="q" onClick={() => onHelp(id)} aria-label={t("m.help", { name })} title={t("m.help", { name })}>
            <Icon name="help" size={18} />
          </button>
        </header>
        <p className="num">
          <span className="num__value">{value}</span>
          <span className="num__unit">{unitText}</span>
        </p>
        <p className="card__note">{note}</p>
        {compare ? <CompareBars {...compare} /> : null}
      </div>
      {hero && split ? (
        <p className="split">
          <span className="split__item">
            <span>{t("m.general.name")}</span>
            <b>{split.general}</b>
            <span>{personUnit}</span>
          </span>
          <span className="split__item">
            <span>{t("m.all.admins")}</span>
            <b>{split.admins}</b>
            <span>{personUnit}</span>
          </span>
        </p>
      ) : null}
    </article>
  );
}

/** Two bars, this period on top of the previous one; widths are shares of the larger value, so equal numbers draw equal bars. */
function CompareBars({ current, previous, currentText, previousText }: NonNullable<Props["compare"]>) {
  const t = useT();
  const max = Math.max(current, previous, 0);
  const width = (n: number) => (max > 0 ? `${Math.max(0, Math.min(100, (n / max) * 100))}%` : "0%");
  return (
    <div className="bars" aria-hidden="true">
      <div className="bars__row bars__row--now">
        <span>{t("bars.now")}</span>
        <span className="bars__track" title={currentText}><span className="bars__fill" style={{ width: width(current) }} /></span>
      </div>
      <div className="bars__row">
        <span>{t("bars.prev")}</span>
        <span className="bars__track" title={previousText}><span className="bars__fill" style={{ width: width(previous) }} /></span>
      </div>
    </div>
  );
}
