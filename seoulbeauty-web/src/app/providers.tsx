"use client";
import { QueryClient, useQueryClient } from "@tanstack/react-query";
import { PersistQueryClientProvider } from "@tanstack/react-query-persist-client";
import { usePathname, useRouter } from "next/navigation";
import { Suspense, useCallback, useEffect, useRef, useState } from "react";
import { api, qk } from "@/lib/api";
import { announceLogoutFromUrl, notifyPageChanged } from "@/lib/bridge";
import { useMe } from "@/lib/me";
import { CACHE_BUSTER, CACHE_MAX_AGE, createIdbPersister, restorePersonal, shouldPersist } from "@/lib/persist";

/**
 * The "Redux store": a page visited once renders instantly from cache; public data is also kept on disk (IndexedDB)
 * so a cold start of the app paints from the cache first and refreshes in the background. gcTime >= maxAge (persist rule).
 */
function makeClient() {
  return new QueryClient({
    defaultOptions: {
      // Opening a screen: paint from the cache at once AND always ask the server ("always" ignores staleTime on mount).
      // The answer is compared with the cached JSON (structural sharing): unchanged = no re-render; changed = the screen and the cache update.
      queries: { staleTime: 0, refetchOnMount: "always", gcTime: CACHE_MAX_AGE, retry: 1, refetchOnWindowFocus: false },
    },
  });
}

/** Another member (or a logout) on this device: drop everything cached, memory and disk. */
function CacheGuard({ clearDisk }: { clearDisk: () => void }) {
  const qc = useQueryClient();
  const me = useMe();
  const uid = me.data ? (me.data.user?.id ?? "") : undefined;
  useEffect(() => {
    if (uid === undefined) return;
    try {
      const prev = localStorage.getItem("sb-cache-uid");
      const same = prev === null || prev === uid;
      // resetQueries (not removeQueries): the screens that are open keep their observers and refetch for the new member
      if (!same) { void qc.resetQueries({ predicate: (q) => q.queryKey[0] !== "me" }); clearDisk(); }
      restorePersonal(qc, same); // the member's own disk entries come back only now that `me` has confirmed who is signed in
      localStorage.setItem("sb-cache-uid", uid);
    } catch { /* storage blocked */ }
  }, [uid, qc, clearDisk]);
  return null;
}

/**
 * App start: warm the four tab screens (home, /hospitals, /community, /mypage) so tapping a tab paints from the cache at once.
 * Fills the query cache (which persists to disk by itself) and loads each route's JS. Runs once per page load, after the
 * current screen has had a head start; skipped on Data Saver. staleTime keeps it from re-fetching what the screen just fetched.
 */
const WARM_ROUTES = ["/", "/hospitals", "/community", "/mypage"];
function WarmUp() {
  const qc = useQueryClient();
  const router = useRouter();
  const me = useMe();
  const loggedIn = me.data ? me.data.loggedIn : undefined;
  const publicDone = useRef(false);
  const memberDone = useRef(false);
  // fire-and-forget (no cleanup on purpose: the warm-up must finish even if the screen that started it unmounts)
  const idle = (fn: () => void) => {
    const w = window as Window & { requestIdleCallback?: (cb: () => void, o?: { timeout: number }) => number };
    if (w.requestIdleCallback) w.requestIdleCallback(fn, { timeout: 1500 }); else window.setTimeout(fn, 400);
  };
  const saveData = () => (navigator as Navigator & { connection?: { saveData?: boolean } }).connection?.saveData === true;
  useEffect(() => {
    if (publicDone.current || saveData()) return;
    publicDone.current = true;
    idle(() => {
      const opt = { staleTime: 30_000 };
      void qc.prefetchQuery({ queryKey: qk.home, queryFn: () => api.home(), ...opt });
      void qc.prefetchQuery({ queryKey: qk.hospitals({}), queryFn: () => api.hospitals({}), ...opt });
      void qc.prefetchQuery({ queryKey: qk.community({ tab: "latest" }), queryFn: () => api.community({ tab: "latest" }), ...opt });
      for (const r of WARM_ROUTES) { try { router.prefetch(r); } catch { /* ignore */ } }
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    if (loggedIn !== true || memberDone.current || saveData()) return;
    memberDone.current = true;
    idle(() => { void qc.prefetchQuery({ queryKey: qk.mypage, queryFn: () => api.mypage(), staleTime: 30_000 }); });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loggedIn]);
  return null;
}

function PageChangeNotifier() {
  const pathname = usePathname();
  const first = useRef(true);
  useEffect(() => {
    if (first.current) {
      first.current = false; // initial load: Flutter already saw onPageFinished
      return;
    }
    notifyPageChanged();
  }, [pathname]);
  return null;
}

/** Landing after a logout from the app: hand the logout to the native side once. */
function LogoutAnnouncer() {
  useEffect(() => { announceLogoutFromUrl(); }, []);
  return null;
}

export default function Providers({ children }: { children: React.ReactNode }) {
  const [client] = useState(makeClient);
  const [persister] = useState(createIdbPersister);
  const clearDisk = useCallback(() => { void persister.removeClient(); }, [persister]);
  return (
    <PersistQueryClientProvider
      client={client}
      persistOptions={{ persister, maxAge: CACHE_MAX_AGE, buster: CACHE_BUSTER, dehydrateOptions: { shouldDehydrateQuery: shouldPersist } }}
    >
      <CacheGuard clearDisk={clearDisk} />
      <WarmUp />
      <Suspense fallback={null}>
        <PageChangeNotifier />
        <LogoutAnnouncer />
      </Suspense>
      {children}
    </PersistQueryClientProvider>
  );
}
