"use client";
import { useSyncExternalStore } from "react";

/**
 * Where the PHP site lives relative to the current origin.
 * Default "/" for every host (seoulbeauty.app and the app's hms.seoulbeauty.app).
 * Override per host with NEXT_PUBLIC_PHP_PATHS='{"host":"/path/"}' if that ever changes.
 * Same-origin links keep the WebView on its trusted host and keep PHP session cookies.
 */
// Both hosts serve the PHP site at the root (the app's /hairwang/gangnamunni/ start URL
// redirects to "/"), so no override is needed by default.
const PHP_PATHS: Record<string, string> = JSON.parse(process.env.NEXT_PUBLIC_PHP_PATHS || "{}");

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
