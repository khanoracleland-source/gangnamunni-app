"use client";

import type { ReactNode } from "react";
import Icon, { type IconName } from "../Icon";
import type { PillTone } from "../hospitals/parts";

/** One folding section of the form: a heading button with an icon, a status pill and a one-line summary, and the body below. */
export default function SectionCard({
  id,
  icon,
  title,
  pill,
  summary,
  open,
  error = false,
  onToggle,
  children,
}: {
  id: string;
  icon: IconName;
  title: string;
  pill: { tone: PillTone | "err"; text: string } | null;
  summary: string | null;
  open: boolean;
  error?: boolean;
  onToggle: () => void;
  children: ReactNode;
}) {
  return (
    <section className={error ? "ecard ecard--err ha-ec" : "ecard ha-ec"} id={`sec-${id}`}>
      <h2 className="ecard__h">
        <button type="button" id={`ec-${id}-h`} className="ecard__btn" aria-expanded={open} aria-controls={`ec-${id}`} onClick={onToggle}>
          <span className="ha-ec__ico"><Icon name={icon} size={20} /></span>
          <span className="ha-ec__main">
            <span className="ha-ec__row">
              <span>{title}</span>
              {pill ? <span className={`pill pill--${pill.tone}`}>{pill.text}</span> : null}
            </span>
            {summary ? <span className="ha-ec__sum">{summary}</span> : null}
          </span>
          <Icon name="chevron" size={18} className="ecard__chev" />
        </button>
      </h2>
      <div id={`ec-${id}`} role="region" aria-labelledby={`ec-${id}-h`} className="ecard__panel" data-open={open}>
        <div className="ecard__clip">
          <div className="ecard__body">{children}</div>
        </div>
      </div>
    </section>
  );
}
