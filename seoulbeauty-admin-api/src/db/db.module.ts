import { Global, Logger, Module, OnModuleDestroy } from '@nestjs/common';
import * as mysql from 'mysql2/promise';

export const DB = Symbol('DB');
export type Db = Pick<mysql.Pool, 'query'>;

/** Connection of the writing pool, used for the short transaction of one write. */
export interface RwConnection {
  query: mysql.PoolConnection['query'];
  beginTransaction(): Promise<void>;
  commit(): Promise<void>;
  rollback(): Promise<void>;
  release(): void;
}
/** Writing pool (sb_admin_rw). */
export interface RwDb {
  query: mysql.Pool['query'];
  getConnection(): Promise<RwConnection>;
}
/** Holder of the writing pool: it is created on first use, so a missing or broken write user never affects the read-only pool. */
export interface RwDbProvider {
  /** null when DB_RW_USER is not set. */
  get(): RwDb | null;
}
export const DB_RW = Symbol('DB_RW');

function poolOptions(user: string, password: string | undefined, limit: number): mysql.PoolOptions {
  return {
    host: process.env.DB_HOST || '127.0.0.1',
    port: Number(process.env.DB_PORT || 3306),
    user,
    password,
    database: process.env.DB_NAME || 'gangnamunni',
    charset: 'utf8mb4',
    connectionLimit: limit,
    waitForConnections: true,
    queueLimit: 100,
    connectTimeout: 5000,
    // Never run multiple statements per query (SQL-injection hardening).
    multipleStatements: false,
    decimalNumbers: true,
    // Return DATETIME/TIMESTAMP as plain strings; never let Node's local zone shift them.
    dateStrings: true,
    timezone: '+09:00',
  };
}

/** Same as PHP includes/init.php: NOW()/CURDATE() and TIMESTAMP columns are evaluated in KST. */
function kst(pool: mysql.Pool): mysql.Pool {
  (pool.pool as any).on('connection', (c: any) => c.query("SET time_zone = '+09:00'"));
  return pool;
}

class LazyRwPool implements RwDbProvider, OnModuleDestroy {
  private pool: mysql.Pool | null = null;
  get(): RwDb | null {
    if (!process.env.DB_RW_USER) return null;
    if (!this.pool) {
      this.pool = kst(mysql.createPool(poolOptions(process.env.DB_RW_USER, process.env.DB_RW_PASSWORD, Number(process.env.DB_RW_POOL || 2))));
      new Logger('DbRw').log('write pool opened');
    }
    return this.pool as unknown as RwDb;
  }
  async onModuleDestroy(): Promise<void> {
    await this.pool?.end().catch(() => undefined);
  }
}

@Global()
@Module({
  providers: [
    {
      provide: DB,
      useFactory: (): mysql.Pool => {
        if (!process.env.DB_USER) throw new Error('DB_USER is not set (see .env.example)');
        return kst(mysql.createPool(poolOptions(process.env.DB_USER, process.env.DB_PASSWORD, Number(process.env.DB_POOL || 5))));
      },
    },
    { provide: DB_RW, useClass: LazyRwPool },
  ],
  exports: [DB, DB_RW],
})
export class DbModule {}
