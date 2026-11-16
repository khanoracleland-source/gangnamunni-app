"use client";

import type { ReactNode } from "react";
import Icon, { type IconName } from "../Icon";
import type { AdGrade } from "@/lib/api";
import { useT } from "@/lib/locale";

/** A titled block of the page: heading, one plain sentence saying what it holds, then the content. */
export function Section({ id, title, desc, children }: { id: string; title: string; desc: string; children: ReactNode }) {
  return (
    <section className="section" aria-labelledby={id}>
      <h2 id={id}>{title}</h2>
      <p className="section__desc">{desc}</p>
      {children}
    </section>
  );
}

export type PillTone = "on" | "off" | "info" | "warn" | "plain";

/** Small rounded label. The wording always carries the meaning; the colour only repeats it. */
export function Pill({ tone, children }: { tone: PillTone; children: string }) {
  return <span className={`pill pill--${tone}`}>{children}</span>;
}

export function ActivePill({ active }: { active: boolean }) {
  const t = useT();
  return <Pill tone={active ? "on" : "off"}>{active ? t("h.active.on") : t("h.active.off")}</Pill>;
}

/** Ad grade as a short label ("S"); nothing to show for hospitals without one. */
export function GradePill({ grade, long }: { grade: AdGrade; long?: boolean }) {
  const t = useT();
  if (grade === "none") return <span className="muted">{long ? t("h.grade.none") : "—"}</span>;
  return <Pill tone="plain">{long ? t(`h.grade.${grade}`) : grade}</Pill>;
}

/** One line of guidance above the content (who sees what, read-only reminder). */
export function Notice({ icon = "info", tone = "info", children, action }: { icon?: IconName; tone?: "info" | "warn"; children: string; action?: ReactNode }) {
  return (
    <div className={tone === "warn" ? "notice notice--warn" : "notice"} role={tone === "warn" ? "alert" : "note"}>
      <Icon name={icon} size={18} />
      <p className="notice__text">{children}</p>
      {action}
    </div>
  );
}

/** A list of short labels (categories, tags); a dash when there are none. */
export function Chips({ items, max }: { items: string[]; max?: number }) {
  const t = useT();
  if (items.length === 0) return <span className="muted">—</span>;
  const shown = max && items.length > max ? items.slice(0, max) : items;
  const hidden = items.length - shown.length;
  return (
    <ul className="chips">
      {shown.map((item, index) => (
        <li key={`${index}-${item}`} className="chip">{item}</li>
      ))}
      {hidden > 0 ? <li className="chip chip--more" title={items.slice(shown.length).join(", ")}>{t("h.more", { n: hidden })}</li> : null}
    </ul>
  );
}
