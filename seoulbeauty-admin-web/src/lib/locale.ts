"use client";

import { useMemo, useSyncExternalStore } from "react";
import { DEFAULT_LOCALE, LOCALE_STORAGE_KEY, isLocale, makeTranslate, type Locale, type Translate } from "./i18n";

// The chosen language lives on <html lang>: the inline script in layout.tsx sets it before first
// paint, and setLocale changes it here. Components read it through useSyncExternalStore.
const listeners = new Set<() => void>();

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  const onStorage = (e: StorageEvent) => {
    if (e.key !== LOCALE_STORAGE_KEY) return;
    apply(isLocale(e.newValue) ? e.newValue : DEFAULT_LOCALE);
  };
  window.addEventListener("storage", onStorage);
  return () => {
    listeners.delete(listener);
    window.removeEventListener("storage", onStorage);
  };
}

function getSnapshot(): Locale {
  const v = document.documentElement.getAttribute("lang");
  return isLocale(v) ? v : DEFAULT_LOCALE;
}

const getServerSnapshot = (): Locale => DEFAULT_LOCALE;

function apply(locale: Locale): void {
  document.documentElement.setAttribute("lang", locale);
  listeners.forEach((l) => l());
}

export function setLocale(locale: Locale): void {
  apply(locale);
  try {
    localStorage.setItem(LOCALE_STORAGE_KEY, locale);
  } catch {
    // Storage blocked: the choice still holds for this page view.
  }
}

export function useLocale(): Locale {
  return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}

export function useT(): Translate {
  const locale = useLocale();
  return useMemo(() => makeTranslate(locale), [locale]);
}
