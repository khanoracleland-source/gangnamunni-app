"use client";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useCallback } from "react";
import { api, ApiError } from "./api";
import { phpUrl, usePhpBase } from "./php";

export interface SessionUser { id: string; no: number; name: string; nickname: string; level: number; phone: string }
export interface Session { loggedIn: boolean; user: SessionUser | null }

export const meKey = ["me"] as const;

/** Login state of the current cookie session (PHP is the authority, via the API). */
export function useMe() {
  return useQuery({ queryKey: meKey, queryFn: () => api.me(), staleTime: 20_000, gcTime: 5 * 60_000 });
}

/** PHP login page with a return URL back to the current Next.js page. */
export function useLoginUrl(): (returnTo?: string) => string {
  const php = usePhpBase();
  return (returnTo) => {
    const back = returnTo ?? (typeof window !== "undefined" ? window.location.pathname + window.location.search : "/next/hospitals");
    return phpUrl(php, `auth/login.php?url=${encodeURIComponent(back)}`);
  };
}

/**
 * Wrap a login-required action: anonymous users go to the PHP login page (and come back here);
 * if the API answers 401 anyway (session expired meanwhile), same redirect.
 */
export function useRequireLogin() {
  const qc = useQueryClient();
  const loginUrl = useLoginUrl();
  return useCallback(
    async <T,>(action: () => Promise<T>): Promise<T | undefined> => {
      const me = await qc.fetchQuery({ queryKey: meKey, queryFn: () => api.me(), staleTime: 10_000 });
      if (!me.loggedIn) {
        window.location.assign(loginUrl());
        return undefined;
      }
      try {
        return await action();
      } catch (e) {
        if (e instanceof ApiError && e.status === 401) {
          qc.setQueryData(meKey, { loggedIn: false, user: null });
          window.location.assign(loginUrl());
          return undefined;
        }
        throw e;
      }
    },
    [qc, loginUrl],
  );
}
