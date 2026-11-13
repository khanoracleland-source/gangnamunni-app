import { THEME_STORAGE_KEY } from "./config";

export type Theme = "light" | "dark";

// The current theme lives on <html data-theme>: the inline script in layout.tsx sets it before
// first paint, and the toggle changes it here. Components read it through useSyncExternalStore.
const listeners = new Set<() => void>();

export function subscribeTheme(listener: () => void): () => void {
  listeners.add(listener);
  // Another tab (or the PHP admin on the same origin) changed the setting: follow it.
  const onStorage = (e: StorageEvent) => {
    if (e.key !== THEME_STORAGE_KEY) return;
    applyTheme(e.newValue === "dark" ? "dark" : "light");
  };
  window.addEventListener("storage", onStorage);
  return () => {
    listeners.delete(listener);
    window.removeEventListener("storage", onStorage);
  };
}

export function getTheme(): Theme {
  return document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
}

/** Server render and hydration use the default; the real value follows right after. */
export function getServerTheme(): Theme {
  return "light";
}

function applyTheme(theme: Theme): void {
  document.documentElement.setAttribute("data-theme", theme);
  listeners.forEach((l) => l());
}

export function setTheme(theme: Theme): void {
  applyTheme(theme);
  try {
    localStorage.setItem(THEME_STORAGE_KEY, theme);
  } catch {
    // Storage blocked (private mode, policy): the choice still holds for this page view.
  }
}
