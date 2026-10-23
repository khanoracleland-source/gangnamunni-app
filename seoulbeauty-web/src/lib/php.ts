"use client";
import { useSyncExternalStore } from "react";

/**
 * Where the PHP site lives relative to the current origin.
 * - seoulbeauty.app        → "/"                      (public site)
 * - hms.seoulbeauty.app    → "/hairwang/gangnamunni/" (the Flutter app's trusted host)
 * Same-origin links keep the WebView on its trusted host and keep PHP session cookies.
 */
const PHP_PATHS: Record<string, string> = JSON.parse(
  process.env.NEXT_PUBLIC_PHP_PATHS || '{"hms.seoulbeauty.app":"/hairwang/gangnamunni/"}',
);

export function phpBase(host: string): string {
  return PHP_PATHS[host.toLowerCase()] ?? "/";
}

const noop = () => () => {};
const clientHost = () => window.location.host;
const serverHost = () => "";

/** Host-dependent, so SSR renders "/" and the client re-renders with the real host (no effect/setState). */
export function usePhpBase(): string {
  const host = useSyncExternalStore(noop, clientHost, serverHost);
  return host ? phpBase(host) : "/";
}

export function phpUrl(base: string, page: string): string {
  return `${base.replace(/\/$/, "")}/${page.replace(/^\//, "")}`;
}
