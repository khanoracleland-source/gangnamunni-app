import { del, delMany, get, getMany, keys, setMany } from "idb-keyval";
import type { PersistedClient, Persister } from "@tanstack/react-query-persist-client";
import { hydrate, type Query, type QueryClient } from "@tanstack/react-query";

/**
 * Bump CACHE_VERSION to drop every persisted cache: do it whenever the shape of a persisted API answer changes in a way the screens
 * cannot read (a renamed / removed field). A deploy alone does NOT drop it any more, so the first open after a release still paints
 * from the disk; every screen asks the server again anyway, which brings added fields in by itself.
 */
const CACHE_VERSION = "2";
export const CACHE_BUSTER = CACHE_VERSION;
export const CACHE_MAX_AGE = 24 * 60 * 60_000;

/**
 * What goes to disk, in three classes:
 *  - PUBLIC (home, faq, events…): the same for everyone; restored at start-up.
 *  - MARKED (hospital / community / event screens): public content that also carries the member's own marks (찜, likes, "my post",
 *    응모 state). Restored at start-up so a cold start paints the tab screens at once; CacheGuard drops it as soon as `me` shows
 *    another member or a signed-out session, and also when `me` cannot be asked at all (offline, API down).
 *  - PRIVATE (everything under "mypage": bookings, consultations, points, profile…, and the member's own post list): NEVER put back
 *    before `me` has confirmed that the signed-in member is the one who wrote it (restorePrivate, called by CacheGuard). While `me`
 *    has not answered — or fails — it stays on the disk, unseen.
 * Not persisted at all: "me" and the payment/cancel screens (short-lived, tied to one action).
 */
const PUBLIC = new Set(["home", "reviews", "treatments", "doctors", "faq", "events", "legal", "login-options"]);
const MARKED = new Set(["hospitals", "hospital", "searchView", "community", "communityPost", "event", "reviewDetail"]);
const isPrivateKey = (k: readonly unknown[]): boolean => k[0] === "mypage" || (k[0] === "community" && k[1] === "mine");
/** Carries something of the signed-in member (marks or private data): dropped from memory when the member cannot be confirmed. */
export const isMemberQuery = (q: Query): boolean => MARKED.has(String(q.queryKey[0])) || isPrivateKey(q.queryKey);

export const shouldPersist = (q: Query): boolean =>
  q.state.status === "success" && (PUBLIC.has(String(q.queryKey[0])) || MARKED.has(String(q.queryKey[0])) || isPrivateKey(q.queryKey));

type DQuery = PersistedClient["clientState"]["queries"][number];
interface Meta { timestamp: number; buster: string; hashes: string[] }
const OLD_KEY = "sb-query-cache-v1"; // the former single blob: removed on the first start
const META = "sb-qc-meta";
const entryKey = (hash: string) => `sb-qc:${hash}`;
let heldPrivate: DQuery[] = []; // read from the disk, waiting for `me`

/**
 * IndexedDB persister (async, no 5 MB localStorage cap). One record per query plus a small index (META), so a change writes only
 * the queries whose data changed instead of serialising the whole cache (the full hospital list alone is hundreds of rows).
 * Writes are coalesced to one per second.
 */
export function createIdbPersister(): Persister {
  let timer: ReturnType<typeof setTimeout> | null = null;
  let pending: PersistedClient | null = null;
  let written = new Map<string, number>(); // queryHash -> dataUpdatedAt of the copy on disk
  const flush = () => {
    timer = null;
    const c = pending; pending = null;
    if (!c) return;
    const queries = c.clientState.queries;
    // private entries still waiting for `me` are not in the cache yet: they must not be taken for removed ones and deleted from the disk
    const live = new Set([...queries, ...heldPrivate].map((q) => q.queryHash));
    const changed = queries.filter((q) => written.get(q.queryHash) !== q.state.dataUpdatedAt);
    const gone = [...written.keys()].filter((h) => !live.has(h));
    const meta: Meta = { timestamp: c.timestamp, buster: c.buster, hashes: [...live] };
    const target = written; // a clear (removeClient) in between swaps the map: late results must not mark the new one
    setMany([...changed.map((q): [string, unknown] => [entryKey(q.queryHash), q]), [META, meta]])
      .then(() => { for (const q of changed) target.set(q.queryHash, q.state.dataUpdatedAt); })
      .catch(() => {});
    if (gone.length) delMany(gone.map(entryKey)).then(() => { for (const h of gone) target.delete(h); }).catch(() => {});
  };
  return {
    persistClient: (c) => { pending = c; if (!timer) timer = setTimeout(flush, 1000); },
    restoreClient: async () => {
      try {
        del(OLD_KEY).catch(() => {});
        const meta = await get<Meta>(META);
        if (!meta) return undefined;
        const queries = (await getMany<DQuery>(meta.hashes.map(entryKey))).filter((q): q is DQuery => !!q);
        written = new Map(queries.map((q) => [q.queryHash, q.state.dataUpdatedAt]));
        heldPrivate = queries.filter((q) => isPrivateKey(q.queryKey));
        return { timestamp: meta.timestamp, buster: meta.buster, clientState: { mutations: [], queries: queries.filter((q) => !isPrivateKey(q.queryKey)) } };
      } catch { return undefined; }
    },
    removeClient: async () => {
      pending = null; written = new Map(); heldPrivate = [];
      try {
        const all = (await keys()).filter((k): k is string => typeof k === "string" && k.startsWith("sb-qc"));
        await delMany([...all, OLD_KEY]);
      } catch { /* private mode */ }
    },
  };
}

/** `me` answered and the member is the one who wrote the disk cache: put the private entries back. Anyone else: they are dropped. */
export function restorePrivate(qc: QueryClient, sameMember: boolean): void {
  const held = heldPrivate; heldPrivate = [];
  if (!sameMember || !held.length) return;
  hydrate(qc, { mutations: [], queries: held });
}
