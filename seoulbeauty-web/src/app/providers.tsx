"use client";
import { QueryClient, useQueryClient } from "@tanstack/react-query";
import { PersistQueryClientProvider } from "@tanstack/react-query-persist-client";
import { usePathname, useRouter } from "next/navigation";
import { Suspense, useCallback, useEffect, useRef, useState } from "react";
import { api, qk } from "@/lib/api";
import { fetchAllHospitals, hospitalsAllKey } from "@/lib/hospitalsAll";
import { announceLogoutFromUrl, notifyPageChanged } from "@/lib/bridge";
import { useMe } from "@/lib/me";
import { countSpaNav } from "@/lib/nav";
import { ToastProvider } from "@/components/Toast";
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
 * App start: warm EVERY screen at once, so whichever one is opened next paints from the cache immediately (and then quietly asks the
 * server again, as every screen does). Fills the query cache (which persists to disk by itself) and loads each route's JS.
 * Runs once per page load, right after the first paint, all requests in parallel; skipped on Data Saver.
 * staleTime keeps it from re-fetching what the current screen has just fetched. Screens tied to one id or one action
 * (a hospital, a post, payment / cancel) cannot be known in advance and load when opened.
 */
const WARM_ROUTES = [
  "/", "/hospitals", "/community", "/events", "/faq", "/qna", "/login", "/terms", "/privacy", "/community-guidelines", "/points-policy",
  "/mypage", "/mypage/bookings", "/mypage/notifications", "/mypage/wishlist", "/mypage/consultations", "/mypage/reviews", "/mypage/points",
  "/mypage/progress", "/mypage/community", "/mypage/profile", "/mypage/settings",
];
const WARM_STALE = { staleTime: 30_000 };
function WarmUp() {
  const qc = useQueryClient();
  const router = useRouter();
  const me = useMe();
  const loggedIn = me.data ? me.data.loggedIn : undefined;
  const publicDone = useRef(false);
  const memberDone = useRef(false);
  const saveData = () => (navigator as Navigator & { connection?: { saveData?: boolean } }).connection?.saveData === true;
  // fire-and-forget (no cleanup on purpose: the warm-up must finish even if the screen that started it unmounts)
  useEffect(() => {
    if (publicDone.current || saveData()) return;
    publicDone.current = true;
    window.setTimeout(() => {
      const warm = <T,>(queryKey: readonly unknown[], queryFn: () => Promise<T>) => void qc.prefetchQuery({ queryKey, queryFn, ...WARM_STALE });
      warm(qk.home, () => api.home());
      warm(hospitalsAllKey({}), () => fetchAllHospitals({}));
      for (const tab of ["latest", "popular", "review"]) warm(qk.community({ tab }), () => api.community({ tab }));
      warm(qk.events("", false), () => api.events({ category: "", filter: "" }));
      warm(qk.events("", true), () => api.events({ category: "", filter: "raffle" }));
      warm(qk.faq, () => api.faq());
      for (const r of WARM_ROUTES) { try { router.prefetch(r); } catch { /* ignore */ } }
    }, 0);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    if (loggedIn !== true || memberDone.current || saveData()) return;
    memberDone.current = true;
    const warm = <T,>(queryKey: readonly unknown[], queryFn: () => Promise<T>) => void qc.prefetchQuery({ queryKey, queryFn, ...WARM_STALE });
    warm(qk.mypage, () => api.mypage());
    warm(qk.myBookings, () => api.myBookings());
    warm(qk.myWishlist, () => api.myWishlist());
    warm(qk.myConsultations, () => api.myConsultations());
    warm(qk.myReviews, () => api.myReviews());
    warm(qk.myPoints, () => api.myPoints());
    warm(qk.myProgress, () => api.myProgress());
    warm(qk.myProfile, () => api.myProfile());
    warm(qk.notificationSettings, () => api.notificationSettings());
    warm(qk.myCommunity("latest", 1), () => api.myCommunity({ tab: "latest", page: 1 }));
    void qc.prefetchInfiniteQuery({ queryKey: qk.myNotifications("all"), initialPageParam: 1, ...WARM_STALE, queryFn: ({ pageParam }) => api.myNotifications("all", pageParam as number) });
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
    countSpaNav();
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
      <ToastProvider>{children}</ToastProvider>
    </PersistQueryClientProvider>
  );
}
