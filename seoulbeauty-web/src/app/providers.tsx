"use client";
import { QueryClient, useQueryClient } from "@tanstack/react-query";
import { PersistQueryClientProvider } from "@tanstack/react-query-persist-client";
import { usePathname } from "next/navigation";
import { Suspense, useCallback, useEffect, useRef, useState } from "react";
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
      <Suspense fallback={null}>
        <PageChangeNotifier />
        <LogoutAnnouncer />
      </Suspense>
      {children}
    </PersistQueryClientProvider>
  );
}
