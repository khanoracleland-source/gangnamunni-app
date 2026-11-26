"use client";

import type { MemberStatus } from "@/lib/api";
import { useT } from "@/lib/locale";
import { statusKey } from "@/lib/members";

/** The state of a member: active, blocked or left (a dot and the word, never color alone). */
export default function StatusPill({ status }: { status: MemberStatus }) {
  const t = useT();
  return <span className={`mb-st mb-st--${status}`}>{t(statusKey(status))}</span>;
}
