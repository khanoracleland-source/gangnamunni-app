"use client";

import type { ReactNode } from "react";
import Icon, { type IconName } from "../Icon";
import type { AdGrade } from "@/lib/api";
import { legacyDocumentUrl } from "@/lib/hospitals";
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

export type PillTone = "on" | "off" | "info" | "warn" | "err" | "plain";

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
export function Notice({ icon = "info", tone = "info", children, action }: { icon?: IconName; tone?: "info" | "warn" | "ok"; children: string; action?: ReactNode }) {
  return (
    <div className={tone === "warn" ? "notice notice--warn" : tone === "ok" ? "notice notice--ok" : "notice"} role={tone === "warn" ? "alert" : "note"}>
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

/** The two submitted documents of a hospital (view only). A missing answer is shown as a warning, never as "not submitted". */
export function DocumentList({ hospitalId, documents }: { hospitalId: number; documents: { business: boolean; medical: boolean } | null }) {
  const t = useT();
  if (!documents) return <Notice tone="warn">{t("h.docs.unavailable")}</Notice>;
  return (
    <ul className="docs">
      <DocumentRow name={t("h.docs.business")} submitted={documents.business} href={legacyDocumentUrl(hospitalId, "business")} />
      <DocumentRow name={t("h.docs.medical")} submitted={documents.medical} href={legacyDocumentUrl(hospitalId, "medical")} />
    </ul>
  );
}

/** What the edit page adds to a document row: its buttons, the panel under them and which row the panel belongs to. */
export type DocumentRowEdit = { nameId: string; active: boolean; actions: ReactNode; panel: ReactNode };

export function DocumentRow({ name, submitted, href, edit }: { name: string; submitted: boolean; href: string; edit?: DocumentRowEdit }) {
  const t = useT();
  const open = submitted ? (
    <a className={edit ? "txtbtn doc__wide" : "txtbtn"} href={href} target="_blank" rel="noopener noreferrer" aria-label={t("h.docs.openOf", { name })}>
      <Icon name="external" size={16} />
      <span>{t("h.docs.open")}</span>
    </a>
  ) : null;
  const pill = <Pill tone={submitted ? "on" : "warn"}>{submitted ? t("h.docs.submitted") : t("h.docs.missing")}</Pill>;
  if (!edit) {
    return (
      <li className="panel doc">
        <div className="doc__text">
          <p className="doc__name">{name}</p>
          <p className="doc__note">{t("h.docs.required")}</p>
        </div>
        {pill}
        {open}
      </li>
    );
  }
  return (
    <li className="panel doc doc--edit" data-active={edit.active ? "true" : undefined}>
      <div className="doc__head">
        <div className="doc__text">
          <p className="doc__name" id={edit.nameId} tabIndex={-1}>{name}</p>
          <p className="doc__note">{t("h.docs.required")}</p>
        </div>
        {pill}
      </div>
      <div className="doc__acts">
        {open}
        {edit.actions}
      </div>
      {edit.panel}
    </li>
  );
}
