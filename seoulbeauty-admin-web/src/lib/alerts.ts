// The admin's own alert inbox: shared names and the refresh rhythm of the bell in the top bar.

export const ALERT_TABS = ["all", "activity", "pending", "system"] as const;
export type AlertTab = (typeof ALERT_TABS)[number];

/** Sent on `window` with the new unread count (a number) whenever a page learns it, so the bell updates without waiting for its timer. */
export const ALERT_UNREAD_EVENT = "admin:alerts-unread";

/** How often the bell asks again while the page is visible (it also asks when the tab gets focus). */
export const ALERT_POLL_MS = 60_000;

/** The page of the inbox (reached from the bell, not from the menu). */
export const ALERTS_ROUTE = "/alerts";
