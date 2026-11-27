"use client";

import { useSyncExternalStore } from "react";

const QUERY = "(min-width: 900px)";
const subscribe = (cb: () => void) => {
  const m = window.matchMedia(QUERY);
  m.addEventListener("change", cb);
  return () => m.removeEventListener("change", cb);
};
/** True from 900px up: the detail of a send sits beside the list; below it opens over the page. */
export const useWide = (): boolean => useSyncExternalStore(subscribe, () => window.matchMedia(QUERY).matches, () => true);
