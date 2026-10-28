import { cookies } from "next/headers";
import { ApiError, type ServerCtx } from "./api";

/** Server-side load of a member-only endpoint: data, `anon` (401 → show the login prompt) or nothing (the client retries). */
export async function loadMine<T>(fn: (ctx: ServerCtx) => Promise<T>): Promise<{ data?: T; anon?: boolean }> {
  const cookie = (await cookies()).toString();
  try {
    return { data: await fn({ cookie }) };
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) return { anon: true };
    return {};
  }
}
