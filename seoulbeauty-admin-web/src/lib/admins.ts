// Pure helpers of the admins page. No DOM.

import type { AdminAccount } from "./api";

/** Roles that have a name in the dictionary; any other stored role is shown as it is. */
export const KNOWN_ROLES = ["super_admin", "hospital_super_admin", "hospital_admin"] as const;

export const isKnownRole = (role: string): role is (typeof KNOWN_ROLES)[number] => (KNOWN_ROLES as readonly string[]).includes(role);

export type AdminGroups = { active: AdminAccount[]; inactive: AdminAccount[]; ready: number };

/** Splits the accounts into the two lanes of the page. Inactive accounts that may be deleted come first, then the longest waiting. */
export function groupAccounts(items: AdminAccount[]): AdminGroups {
  const active = items.filter((a) => a.isActive);
  const inactive = items
    .filter((a) => !a.isActive)
    .sort((a, b) => Number(b.deleteReady) - Number(a.deleteReady) || (b.inactiveDays ?? -1) - (a.inactiveDays ?? -1) || a.id - b.id);
  return { active, inactive, ready: inactive.filter((a) => a.deleteReady).length };
}

/** How many of the `total` waiting days have passed, kept inside 0..total. */
export const passedDays = (days: number | null, total: number): number => Math.min(total, Math.max(0, days ?? 0));
