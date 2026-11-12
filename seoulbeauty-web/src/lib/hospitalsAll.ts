import { api, ApiError, qk, type Hospital, type HospitalListParams, type HospitalPage } from "./api";

/** A paid slot that is not in the filtered list (shown only where the API pins slots: every sort except price). */
export type HospitalRow = Hospital & { slotOnly?: boolean };

/** One cache entry per filter (keyword / category / treatment / region / dosage): the sort is NOT part of the key, it is applied in the browser. */
export const hospitalsAllKey = (p: HospitalListParams) => [...qk.hospitals({ ...p, sort: "" }), "all"] as const;

/**
 * Every page of the list for one filter, fetched in parallel, in the API's default order (`sort` empty: price when a treatment is
 * effective — rows then carry the cheapest price line plus `treatmentAlt` — otherwise the manual order). The answer's `sort` says which.
 * Paid slots: the API pins them only on page 1 of a non-price sort, so one extra call (sort=rating) supplies them and they are marked
 * on the rows here. Slots exist only for a treatment, so the plain list skips that call.
 */
export async function fetchAllHospitals(p: HospitalListParams): Promise<HospitalPage> {
  // Old API (limit max 100, no `both`) must keep working while the new one is rolling out: fall back to 100 per page.
  const run = async (size: number, maxOffset: number) => {
    const base = { ...p, sort: "" as const, limit: size, both: "1" as const };
    const first = await api.hospitals({ ...base, offset: 0 });
    const offs: number[] = [];
    for (let o = first.items.length; o < Math.min(first.total, maxOffset + 1); o += size) offs.push(o);
    const rest = await Promise.all(offs.map((offset) => api.hospitals({ ...base, offset })));
    return [first, ...rest];
  };
  const [pages, pinned] = await Promise.all([
    run(300, 5000).catch((e) => { if (e instanceof ApiError && e.status === 400) return run(100, 1000); throw e; }), // only the old API's "limit > 100 / offset > 1000" => 400
    p.treatment || p.q ? api.hospitals({ ...p, sort: "rating", limit: 20, offset: 0 }).catch(() => null) : null, // slots are optional: never fail the list
  ]);
  const first = pages[0];
  const slots = new Map((pinned?.items ?? []).filter((h) => h.adSlot).map((h) => [h.id, h]));
  const seen = new Set<number>();
  const items: HospitalRow[] = pages.flatMap((r) => r.items).filter((h) => !seen.has(h.id) && !!seen.add(h.id))
    .map((h) => (slots.has(h.id) ? { ...h, adSlot: slots.get(h.id)!.adSlot } : h));
  for (const [id, h] of slots) if (!seen.has(id)) items.push({ ...h, slotOnly: true }); // a paid slot bypasses the filters, like the API
  return { ...first, items };
}
