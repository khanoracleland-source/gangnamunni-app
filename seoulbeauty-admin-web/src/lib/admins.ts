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

// ---- the add / edit form --------------------------------------------------------------------------------------

import type { AdminCreateBody, AdminEditBody, WriteFailure } from "./api";
import type { MessageKey } from "./i18n";

/** Same keys as the API's list; the labels are in the dictionary as `ad.nt.<key>`. */
export const NOTIFICATION_TYPES = [
  "hospital_request", "ad_request", "review_new", "consultation_new", "appointment_new", "member_join", "mail_incoming",
  "review_pending", "hospital_pending", "ad_pending", "test_fail", "login_fail", "maintenance_toggle", "admin_created", "ai_key_error",
] as const;
/** What a new account starts with. */
export const DEFAULT_NOTIFICATIONS: readonly string[] = [
  "hospital_request", "ad_request", "review_new", "consultation_new", "appointment_new", "review_pending", "hospital_pending", "ad_pending",
  "test_fail", "admin_created",
];
/** Roles a super admin may give. */
export const ASSIGNABLE_ROLES = ["super_admin", "hospital_admin"] as const;
export const MIN_PASSWORD = 8;

export type AdminForm = {
  username: string;
  password: string;
  name: string;
  email: string;
  phone: string;
  role: string;
  hospitalId: number | null;
  hospitalName: string;
  notifications: string[];
  newPassword: string;
  currentPassword: string;
};

export const emptyForm = (): AdminForm => ({
  username: "", password: "", name: "", email: "", phone: "", role: "hospital_admin", hospitalId: null, hospitalName: "",
  notifications: [...DEFAULT_NOTIFICATIONS], newPassword: "", currentPassword: "",
});

export const formFromAccount = (a: AdminAccount): AdminForm => ({
  ...emptyForm(), name: a.name, email: a.email, phone: a.phone, role: a.role, hospitalId: a.hospitalId, hospitalName: a.hospitalName,
  notifications: [...a.notifications],
});

const sameSet = (a: string[], b: string[]) => a.length === b.length && a.every((x) => b.includes(x));
const emailOf = (v: string) => v.trim().toLowerCase();
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const USERNAME = /^[a-zA-Z0-9_]{3,30}$/;
const bytes = (v: string) => new TextEncoder().encode(v).length;

/** Does saving this edit as a non-super admin need the current password? (Own email or password changing.) */
export function needsCurrentPassword(a: AdminAccount, f: AdminForm, superViewer: boolean): boolean {
  if (superViewer) return false;
  return f.newPassword !== "" || emailOf(f.email) !== a.email.toLowerCase();
}

/** Problems the browser can see before asking the server (the server checks everything again). Keys are form field names. */
export function validateForm(mode: "create" | "edit", f: AdminForm, superViewer: boolean, a?: AdminAccount): Record<string, MessageKey> {
  const e: Record<string, MessageKey> = {};
  if (mode === "create") {
    if (!USERNAME.test(f.username.trim())) e.username = "ad.err.username";
    if (f.password.length < MIN_PASSWORD) e.password = "ad.err.tooShort";
    else if (bytes(f.password) > 72) e.password = "ad.err.tooLong";
  } else if (f.newPassword !== "") {
    if (f.newPassword.length < MIN_PASSWORD) e.newPassword = "ad.err.tooShort";
    else if (bytes(f.newPassword) > 72) e.newPassword = "ad.err.tooLong";
  }
  if (f.name.trim() === "") e.name = "ad.err.required";
  else if ([...f.name.trim()].length > 100) e.name = "ad.err.tooLong";
  if (f.phone.trim().length > 30) e.phone = "ad.err.tooLong";
  if (f.email.trim() !== "" && (!EMAIL.test(f.email.trim()) || f.email.trim().length > 254)) e.email = "ad.err.email";
  if (superViewer && f.role === "hospital_admin" && f.hospitalId === null) e.hospitalId = "ad.err.hospital";
  if (mode === "edit" && a && needsCurrentPassword(a, f, superViewer) && f.currentPassword === "") e.currentPassword = "ad.err.currentRequired";
  return e;
}

export const createBody = (f: AdminForm): AdminCreateBody => ({
  username: f.username.trim(), password: f.password, name: f.name.trim(), email: f.email.trim(), phone: f.phone.trim(), role: f.role,
  hospitalId: f.role === "super_admin" ? null : f.hospitalId, notifications: f.notifications,
});

/** Only what changed. An empty result means there is nothing to save. */
export function editBody(a: AdminAccount, f: AdminForm, superViewer: boolean): AdminEditBody {
  const b: AdminEditBody = {};
  if (f.name.trim() !== a.name) b.name = f.name.trim();
  if (f.phone.trim() !== a.phone) b.phone = f.phone.trim();
  if (emailOf(f.email) !== a.email.toLowerCase()) b.email = f.email.trim();
  if (!sameSet(f.notifications, a.notifications)) b.notifications = f.notifications;
  if (f.newPassword !== "") b.newPassword = f.newPassword;
  if (superViewer && !a.isSelf) {
    if (f.role !== a.role) b.role = f.role;
    if (f.role !== "super_admin" && (f.role !== a.role || f.hospitalId !== a.hospitalId)) b.hospitalId = f.hospitalId;
  }
  if (needsCurrentPassword(a, f, superViewer)) b.currentPassword = f.currentPassword;
  return b;
}

/** True when the edit body holds something to save (the current password alone is not a change). */
export const hasChanges = (b: AdminEditBody): boolean => Object.keys(b).some((k) => k !== "currentPassword");

const CODE_KEYS: Record<string, MessageKey> = {
  required: "ad.err.required", too_long: "ad.err.tooLong", too_short: "ad.err.tooShort", bad_format: "ad.err.format", unknown_type: "ad.err.format",
  unknown_role: "ad.err.format", not_found: "ad.err.hospital", not_for_super_admin: "ad.err.format",
};

/** Field messages from a 400 answer. */
export function serverFieldErrors(fail: WriteFailure): Record<string, MessageKey> {
  const out: Record<string, MessageKey> = {};
  for (const [field, code] of Object.entries(fail.fields)) {
    if (field === "email" && code === "bad_format") out.email = "ad.err.email";
    else if (field === "username" && code === "bad_format") out.username = "ad.err.username";
    else if (field === "currentPassword" && code === "required") out.currentPassword = "ad.err.currentRequired";
    else out[field] = CODE_KEYS[code] ?? "ad.err.format";
  }
  return out;
}
