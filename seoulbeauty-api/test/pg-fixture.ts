import { PG_PROVIDERS, TEST_PROVIDERS } from '../src/bookings/bookings.logic';

/** Fixture provider values: `test_pg` is a live PG row, `test_pg_sandbox` a sandbox row. No real PG is connected. */
export const PG_LIVE = 'test_pg';
export const PG_SANDBOX = 'test_pg_sandbox';

/** Registers the fixtures the way a connected PG registers its own values (once per test file). */
export function registerFixturePg(): void {
  for (const p of [PG_LIVE, PG_SANDBOX]) if (!PG_PROVIDERS.includes(p)) PG_PROVIDERS.push(p);
  if (!TEST_PROVIDERS.includes(PG_SANDBOX)) TEST_PROVIDERS.push(PG_SANDBOX);
}
