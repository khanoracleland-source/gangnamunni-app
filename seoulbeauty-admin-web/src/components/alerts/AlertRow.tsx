"use client";

import type { MouseEvent } from "react";
import Icon from "../Icon";
import { alertMeta, alertTime, resolveAlertLink } from "@/lib/alerts";
import type { AlertItem } from "@/lib/alertsApi";
import { useT } from "@/lib/locale";

type Props = {
  item: AlertItem;
  now: number;
  /** An unread alert with a link: marks it read, then follows the link (the click handler of the title link). */
  onOpen: (item: AlertItem, href: string, e: MouseEvent<HTMLAnchorElement>) => void;
  /** An unread alert without a link: marks it read in place. */
  onMarkRead: (item: AlertItem) => void;
};

/** One alert: type chip, label and time, title, message. A row with a link is clickable as a whole (the title link covers it). */
export default function AlertRow({ item, now, onOpen, onMarkRead }: Props) {
  const t = useT();
  const meta = alertMeta(item.type);
  const href = resolveAlertLink(item.link);
  const when = alertTime(item.createdAt, now);
  return (
    <li className={`al-rw ${item.read ? "al-rw--r" : "al-rw--u"}${href ? " al-rw--link" : ""}`}>
      <span className={`al-chip al-chip--${meta.tone}`} aria-hidden="true"><Icon name={meta.icon} size={18} /></span>
      <div className="al-rw__main">
        <div className="al-rw__meta">
          <span className={`al-rw__label al-rw__label--${meta.tone}`}>{t(meta.label)}</span>
          <span className="al-rw__time">{t(when.key, when.vars)}</span>
          {item.read ? null : <span className="sr-only">{t("alerts.unreadSr")}</span>}
        </div>
        <p className="al-rw__title">
          {href ? <a href={href} onClick={(e) => onOpen(item, href, e)}>{item.title}</a> : item.title}
        </p>
        {item.message ? <p className="al-rw__text">{item.message}</p> : null}
      </div>
      <div className="al-rw__side">
        {href ? <Icon name="chevron" size={20} /> : item.read ? null : (
          <button type="button" className="txtbtn" onClick={() => onMarkRead(item)}>{t("alerts.markRead")}</button>
        )}
      </div>
    </li>
  );
}
