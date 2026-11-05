import * as mysql from 'mysql2/promise';
import { DbPool } from '../../db/db.module';

export const PAY_DB = Symbol('PAY_DB');

/** True when the dedicated payment DB account is configured (see deploy/README.md "Inicis"). */
export const payDbConfigured = (env: NodeJS.ProcessEnv = process.env) => !!env.DB_PAY_USER && !!env.DB_PAY_PASSWORD;

/**
 * A second pool under its own least-privilege MariaDB account: the general API user (sb_api_ro) deliberately cannot mark
 * an order paid. Only the payment finalisation uses this one (UPDATE of the verification columns, INSERT of the
 * evidence rows). null when not configured: checkout then stays unavailable.
 */
export function createPayPool(env: NodeJS.ProcessEnv = process.env): (DbPool & { end?: () => Promise<void> }) | null {
  if (!payDbConfigured(env)) return null;
  const pool = mysql.createPool({
    host: env.DB_HOST || '127.0.0.1', port: Number(env.DB_PORT || 3306),
    user: env.DB_PAY_USER, password: env.DB_PAY_PASSWORD, database: env.DB_NAME || 'gangnamunni',
    charset: 'utf8mb4', connectionLimit: Number(env.DB_PAY_POOL || 8), waitForConnections: true, queueLimit: 20,
    connectTimeout: 5000, multipleStatements: false, decimalNumbers: true, dateStrings: true, timezone: '+09:00',
  });
  (pool.pool as any).on('connection', (c: any) => c.query("SET time_zone = '+09:00'"));
  return pool as unknown as DbPool & { end?: () => Promise<void> };
}
