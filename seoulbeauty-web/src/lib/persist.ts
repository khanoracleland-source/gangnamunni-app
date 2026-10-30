import { get, set, del } from "idb-keyval";
import type { PersistedClient, Persister } from "@tanstack/react-query-persist-client";
import { hydrate, type Query, type QueryClient } from "@tanstack/react-query";

/** Bump to drop every persisted cache (shape changes). The build id also invalidates it on each deploy. */
const KEY = "sb-query-cache-v1";
export const CACHE_BUSTER = `${process.env.NEXT_PUBLIC_BUILD_ID || "dev"}`;
export const CACHE_MAX_AGE = 24 * 60 * 60_000;

/**
 * What goes to disk: public data and the member's own data. Everything that depends on WHO is signed in (the member's pages, wishlist
 * marks, likes, 응모 state, blocked authors) is PERSONAL: it is not put back into the cache at start-up. It is restored only after
 * `me` confirms that the signed-in member is the one who wrote it (restorePersonal, called by CacheGuard), so another member on the
 * same device never sees it. Not persisted at all: "me" and the payment/cancel screens (short-lived, tied to one action).
 */
const PUBLIC = new Set(["home", "reviews", "treatments", "doctors", "faq", "events", "legal"]);
const PERSONAL = new Set(["mypage", "wishlist", "hospitals", "hospital", "searchView", "community", "communityPost", "event", "reviewDetail"]);
const PERSISTED = new Set([...PUBLIC, ...PERSONAL]);

type DQuery = PersistedClient["clientState"]["queries"][number];
let heldPersonal: DQuery[] = []; // read from disk, waiting for `me`

export const shouldPersist = (q: Query): boolean => q.state.status === "success" && PERSISTED.has(String(q.queryKey[0]));

/** IndexedDB persister (async, no 5 MB localStorage cap); writes are coalesced to one per second. */
export function createIdbPersister(): Persister {
  let timer: ReturnType<typeof setTimeout> | null = null;
  let pending: PersistedClient | null = null;
  const flush = () => {
    timer = null;
    const c = pending; pending = null;
    if (c) set(KEY, c).catch(() => {});
  };
  return {
    persistClient: (c) => {
      // personal entries still waiting for `me` must not be lost from the disk by a write that happens before it answers
      if (heldPersonal.length) {
        const have = new Set(c.clientState.queries.map((q) => q.queryHash));
        c = { ...c, clientState: { ...c.clientState, queries: [...c.clientState.queries, ...heldPersonal.filter((q) => !have.has(q.queryHash))] } };
      }
      pending = c; if (!timer) timer = setTimeout(flush, 1000);
    },
    restoreClient: async () => {
      try {
        const c = await get<PersistedClient>(KEY);
        if (!c) return undefined;
        heldPersonal = c.clientState.queries.filter((q) => PERSONAL.has(String(q.queryKey[0])));
        return { ...c, clientState: { ...c.clientState, queries: c.clientState.queries.filter((q) => !PERSONAL.has(String(q.queryKey[0]))) } };
      } catch { return undefined; }
    },
    removeClient: async () => { pending = null; heldPersonal = []; try { await del(KEY); } catch { /* private mode */ } },
  };
}

/** `me` answered and the member is the one who wrote the disk cache: put the personal entries back. Anyone else: they are dropped. */
export function restorePersonal(qc: QueryClient, sameMember: boolean): void {
  const held = heldPersonal; heldPersonal = [];
  if (!sameMember || !held.length) return;
  hydrate(qc, { mutations: [], queries: held });
}
