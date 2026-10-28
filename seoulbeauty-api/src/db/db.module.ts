import { Global, Module } from '@nestjs/common';
import * as mysql from 'mysql2/promise';

export const DB = Symbol('DB');
export type Db = Pick<mysql.Pool, 'query'>;
/** A pool that can also hand out a dedicated connection (needed for GET_LOCK, which is connection-scoped). */
export type DbPool = Db & Partial<Pick<mysql.Pool, 'getConnection'>>;
export type DbConn = Pick<mysql.PoolConnection, 'query' | 'release'>;

@Global()
@Module({
  providers: [
    {
      provide: DB,
      useFactory: (): mysql.Pool => {
        if (!process.env.DB_USER) throw new Error('DB_USER is not set (see .env.example)');
        const pool = mysql.createPool({
          host: process.env.DB_HOST || '127.0.0.1',
          port: Number(process.env.DB_PORT || 3306),
          user: process.env.DB_USER,
          password: process.env.DB_PASSWORD,
          database: process.env.DB_NAME || 'gangnamunni',
          charset: 'utf8mb4',
          connectionLimit: Number(process.env.DB_POOL || 10),
          waitForConnections: true,
          queueLimit: 100,
          connectTimeout: 5000,
          // Never run multiple statements per query (SQL-injection hardening).
          multipleStatements: false,
          decimalNumbers: true,
          // Return DATETIME/TIMESTAMP as plain strings; never let Node's local zone shift them.
          dateStrings: true,
          timezone: '+09:00',
        });
        // Same as PHP includes/init.php: TIMESTAMP columns are converted in the session zone (KST).
        (pool.pool as any).on('connection', (c: any) => c.query("SET time_zone = '+09:00'"));
        return pool;
      },
    },
  ],
  exports: [DB],
})
export class DbModule {}
