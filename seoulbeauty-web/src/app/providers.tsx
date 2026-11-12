"use client";
import { QueryClient, useQueryClient } from "@tanstack/react-query";
import { PersistQueryClientProvider } from "@tanstack/react-query-persist-client";
import { usePathname, useRouter } from "next/navigation";
import { Suspense, useCallback, useEffect, useRef, useState } from "react";
import { api, qk } from "@/lib/api";
import { fetchAllHospitals, hospitalsAllKey } from "@/lib/hospitalsAll";
import { announceLogoutFromUrl, notifyPageChanged } from "@/lib/bridge";
import { meKey, useMe } from "@/lib/me";
import { countSpaNav } from "@/lib/nav";
import { ToastProvider } from "@/components/Toast";
import { CACHE_BUSTER, CACHE_MAX_AGE, createIdbPersister, isMemberQuery, restorePrivate, shouldPersist } from "@/lib/persist";

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

/**
 * Whose cache is this? Another member (or a logout) on this device: drop everything cached, memory and disk. The same member: the
 * private entries (bookings, consultations…) held back on the disk are put into the cache only now. `me` cannot be asked at all
 * (offline, API down): nobody is confirmed, so whatever carries a member's marks leaves the memory and the private entries stay
 * unseen on the disk; when `me` answers later, the rules above apply.
 */
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
      restorePrivate(qc, same);
      localStorage.setItem("sb-cache-uid", uid);
    } catch {
      // storage blocked: the previous member is unknown, so nothing of a member may stay
      restorePrivate(qc, false);
      void qc.resetQueries({ predicate: isMemberQuery });
    }
  }, [uid, qc, clearDisk]);
  const unknown = me.isError && !me.data;
  useEffect(() => {
    if (unknown) void qc.resetQueries({ predicate: isMemberQuery });
  }, [unknown, qc]);
  return null;
}

/**
 * App start: refresh the caches in three steps, each starting as soon as the one before has answered:
 *   1. the home screen;
 *   2. the four tab screens (home is done; /hospitals, /community, /mypage);
 *   3. every other screen, all at once.
 * So the screens most likely to be opened next are never queued behind the rest. Fills the query cache (which persists to disk by
 * itself) and loads each route's JS. Runs once per page load, right after the first paint; skipped on Data Saver.
 * staleTime keeps it from re-fetching what the current screen has just fetched. A failed request does not stop the chain
 * (prefetchQuery never rejects). Screens tied to one id or one action (a hospital, a post, payment / cancel) load when opened.
 */
const TAB_ROUTES = ["/", "/hospitals", "/community", "/mypage"];
const OTHER_ROUTES = [
  "/events", "/faq", "/qna", "/login", "/terms", "/privacy", "/community-guidelines", "/points-policy",
  "/mypage/bookings", "/mypage/notifications", "/mypage/wishlist", "/mypage/consultations", "/mypage/reviews", "/mypage/points",
  "/mypage/progress", "/mypage/community", "/mypage/profile", "/mypage/settings",
];
const WARM_STALE = { staleTime: 30_000 };
function WarmUp() {
  const qc = useQueryClient();
  const router = useRouter();
  const done = useRef(false);
  // fire-and-forget (no cleanup on purpose: the warm-up must finish even if the screen that started it unmounts)
  useEffect(() => {
    if (done.current || (navigator as Navigator & { connection?: { saveData?: boolean } }).connection?.saveData === true) return;
    done.current = true;
    const warm = <T,>(queryKey: readonly unknown[], queryFn: () => Promise<T>) => qc.prefetchQuery({ queryKey, queryFn, ...WARM_STALE });
    const routes = (list: string[]) => { for (const r of list) { try { router.prefetch(r); } catch { /* ignore */ } } };
    const run = async () => {
      // 1. home (the sign-in state is asked alongside: step 2 needs it for /mypage)
      const me = qc.fetchQuery({ queryKey: meKey, queryFn: () => api.me(), staleTime: 20_000 }).catch(() => null);
      await warm(qk.home, () => api.home());
      // 2. the tab screens
      routes(TAB_ROUTES);
      const member = (await me)?.loggedIn === true;
      await Promise.all([
        warm(hospitalsAllKey({}), () => fetchAllHospitals({})),
        warm(qk.community({ tab: "latest" }), () => api.community({ tab: "latest" })),
        member ? warm(qk.mypage, () => api.mypage()) : null,
      ]);
      // 3. everything else, in parallel
      routes(OTHER_ROUTES);
      for (const tab of ["popular", "review"]) void warm(qk.community({ tab }), () => api.community({ tab }));
      void warm(qk.events("", false), () => api.events({ category: "", filter: "" }));
      void warm(qk.events("", true), () => api.events({ category: "", filter: "raffle" }));
      void warm(qk.faq, () => api.faq());
      if (!member) return;
      void warm(qk.myBookings, () => api.myBookings());
      void warm(qk.myWishlist, () => api.myWishlist());
      void warm(qk.myConsultations, () => api.myConsultations());
      void warm(qk.myReviews, () => api.myReviews());
      void warm(qk.myPoints, () => api.myPoints());
      void warm(qk.myProgress, () => api.myProgress());
      void warm(qk.myProfile, () => api.myProfile());
      void warm(qk.notificationSettings, () => api.notificationSettings());
      void warm(qk.myCommunity("latest", 1), () => api.myCommunity({ tab: "latest", page: 1 }));
      void qc.prefetchInfiniteQuery({ queryKey: qk.myNotifications("all"), initialPageParam: 1, ...WARM_STALE, queryFn: ({ pageParam }) => api.myNotifications("all", pageParam as number) });
    };
    window.setTimeout(() => void run(), 0);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
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
