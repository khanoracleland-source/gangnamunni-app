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
import { PUSH_TOKEN_EVENT, currentPushToken, forgetPushRegistration, installPushTokenSink, requestPushToken, syncPushToken } from "@/lib/pushToken";
import { ToastProvider } from "@/components/Toast";
import { CACHE_BUSTER, CACHE_MAX_AGE, createIdbPersister, isMemberQuery, restorePrivate, shouldPersist } from "@/lib/persist";

/**
 * The "Redux store": a page visited once renders instantly from cache; public data is also kept on disk (IndexedDB)
 * so a cold start of the app paints from the cache first and refreshes in the background. gcTime >= maxAge (persist rule).
 */
/**
 * When the cache is refreshed:
 *  - ONCE (the default): at app start (WarmUp) or on the first open, then never by a timer. Afterwards it changes only when the
 *    member does something: the cache is written first, then the server is told (lib/optimistic.ts).
 *  - LIVE: data an admin or a hospital can change on the server (ads, hospitals, prices, events, FAQ, booking / consultation /
 *    review status, points, notifications) and what other members write (community). The screen that is open asks again every
 *    30 seconds, and opening a screen whose copy is older than that asks at once. The answer is compared with the cached JSON
 *    (structural sharing): unchanged = no re-render.
 *    Two LIVE screens cost more than one small request, so they are asked less often:
 *      · the hospital list is the WHOLE list (several parallel requests, a count per row): no 30 s timer; a copy older than a
 *        minute is refreshed when the list is opened, and an open list asks again every 5 minutes;
 *      · notifications are an infinite list and a refetch asks for every loaded page again: the timer runs only while one page is
 *        loaded (scrolled further, it waits for the next open or for the app coming back to the foreground).
 *  - OWN: under a LIVE root, but only the member changes it (profile, notification settings, 찜 list): ONCE again.
 * A query with its own options (forms, payment, cancel: staleTime 0) keeps them.
 */
const LIVE = { staleTime: 30_000, refetchInterval: 30_000, refetchOnMount: true } as const;
const ONCE = { staleTime: Infinity, refetchInterval: false } as const;
const LIVE_HEAVY = { staleTime: 60_000, refetchInterval: 5 * 60_000, refetchOnMount: true } as const;
const onePageOnly = (q: { state: { data?: unknown } }) => (((q.state.data as { pages?: unknown[] } | undefined)?.pages?.length ?? 0) <= 1 ? 30_000 : false);
const LIVE_ROOTS = ["home", "hospitals", "hospital", "searchView", "treatments", "doctors", "reviews", "reviewDetail", "events", "event", "faq", "legal", "community", "communityPost", "mypage"];
const OWN_KEYS = [qk.myProfile, qk.notificationSettings, qk.myWishlist];
function makeClient() {
  const qc = new QueryClient({
    defaultOptions: { queries: { ...ONCE, gcTime: CACHE_MAX_AGE, retry: 1, refetchOnWindowFocus: false } },
  });
  for (const root of LIVE_ROOTS) qc.setQueryDefaults([root], LIVE);
  qc.setQueryDefaults(["hospitals"], LIVE_HEAVY);
  qc.setQueryDefaults(["mypage", "notifications"], { refetchInterval: onePageOnly });
  for (const key of OWN_KEYS) qc.setQueryDefaults(key, ONCE); // registered after the roots: the later match wins
  return qc;
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

/**
 * Inside the app: the device's FCM token (handed over by the app, caught by the <head> script in layout.tsx) is registered for the
 * signed-in member: at once when the member is known, after the sign-in when the token came first, again when another member signs
 * in or the token changes. Coming back to the foreground asks the app again (a refreshed token, a failed request). A normal browser
 * never gets a token, so nothing is sent there (lib/pushToken.ts).
 */
function PushTokenBridge() {
  const me = useMe();
  const uid = me.data ? (me.data.loggedIn ? (me.data.user?.id ?? "") : "") : undefined;
  useEffect(() => {
    installPushTokenSink();
    if (uid === undefined) return;
    if (!uid) { forgetPushRegistration(); return; }
    const sync = () => { void syncPushToken(uid); };
    const onVisible = () => { if (document.visibilityState === "visible") { requestPushToken(); sync(); } };
    window.addEventListener(PUSH_TOKEN_EVENT, sync);
    document.addEventListener("visibilitychange", onVisible);
    if (currentPushToken()) sync(); else requestPushToken();
    return () => { window.removeEventListener(PUSH_TOKEN_EVENT, sync); document.removeEventListener("visibilitychange", onVisible); };
  }, [uid]);
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
      <PushTokenBridge />
      <WarmUp />
      <Suspense fallback={null}>
        <PageChangeNotifier />
        <LogoutAnnouncer />
      </Suspense>
      <ToastProvider>{children}</ToastProvider>
    </PersistQueryClientProvider>
  );
}
