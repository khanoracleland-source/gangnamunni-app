import { ArgumentsHost, Catch, HttpException, Logger } from '@nestjs/common';
import { BaseExceptionFilter } from '@nestjs/core';
import type { Request, Response } from 'express';

const isV1 = (path: string): boolean => path === '/v1' || path.startsWith('/v1/');

/**
 * Every /v1 answer is uncacheable, and any 404 under /v1 (unknown path, wrong method, switch off) has the same
 * JSON body {"error":"not_found"} instead of Nest's default body. Anything that is not an HttpException (a database
 * error, a bug) is logged and answered as 500 {"error":"server_error"}, without details. Other paths (/health) keep
 * Nest's defaults. The only 404 body that differs is {"error":"no_document"} (deleting a hospital document that does not exist).
 */
@Catch()
export class V1ExceptionFilter extends BaseExceptionFilter {
  private readonly log = new Logger('V1');
  catch(exception: unknown, host: ArgumentsHost): void {
    const http = host.switchToHttp();
    const path = http.getRequest<Request>().path || '';
    if (!isV1(path)) return super.catch(exception, host);
    const res = http.getResponse<Response>();
    res.setHeader('Cache-Control', 'private, no-store');
    if (exception instanceof HttpException && exception.getStatus() === 404) {
      // One answer for every 404 so a missing page and a forbidden one look alike; the single exception is "this hospital has no such document to delete".
      const body = exception.getResponse() as { error?: unknown } | string;
      res.status(404).json({ error: typeof body === 'object' && body?.error === 'no_document' ? 'no_document' : 'not_found' });
      return;
    }
    if (!(exception instanceof HttpException)) {
      const e = exception as { message?: string; stack?: string };
      this.log.error(`unexpected error on ${path}: ${e?.message ?? String(exception)}`, e?.stack);
      res.status(500).json({ error: 'server_error' });
      return;
    }
    super.catch(exception, host);
  }
}
