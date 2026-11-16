"use client";

import { useSyncExternalStore } from "react";
import type { Locale } from "@/lib/i18n";
import { setLocale, useLocale, useT } from "@/lib/locale";

const NARROW = "(max-width: 576px)";

const subscribeNarrow = (onChange: () => void) => {
  const query = window.matchMedia(NARROW);
  query.addEventListener("change", onChange);
  return () => query.removeEventListener("change", onChange);
};
const isNarrow = () => window.matchMedia(NARROW).matches;
const notNarrow = () => false;

/** Language switch. On a narrow screen the options are the short codes "KO" / "EN" so the top bar fits. */
export default function LangSelect() {
  const t = useT();
  const locale = useLocale();
  const narrow = useSyncExternalStore(subscribeNarrow, isNarrow, notNarrow);
  return (
    <label className="lang">
      <span className="sr-only">{t("top.language")}</span>
      <select className="sel" value={locale} onChange={(e) => setLocale(e.target.value as Locale)}>
        <option value="ko">{narrow ? "KO" : "한국어"}</option>
        <option value="en">{narrow ? "EN" : "English"}</option>
      </select>
    </label>
  );
}
