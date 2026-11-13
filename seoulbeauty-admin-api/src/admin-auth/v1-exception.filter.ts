import { ArgumentsHost, Catch, HttpException } from '@nestjs/common';
import { BaseExceptionFilter } from '@nestjs/core';
import type { Request, Response } from 'express';

const isV1 = (path: string): boolean => path === '/v1' || path.startsWith('/v1/');

/**
 * Every /v1 answer is uncacheable, and any 404 under /v1 (unknown path, wrong method, switch off) has the one
 * contract shape {"error":"not_found"} instead of Nest's default body. Other paths (/health) keep Nest's defaults.
 */
@Catch()
export class V1ExceptionFilter extends BaseExceptionFilter {
  catch(exception: unknown, host: ArgumentsHost): void {
    const http = host.switchToHttp();
    const path = http.getRequest<Request>().path || '';
    if (!isV1(path)) return super.catch(exception, host);
    const res = http.getResponse<Response>();
    res.setHeader('Cache-Control', 'private, no-store');
    if (exception instanceof HttpException && exception.getStatus() === 404) {
      res.status(404).json({ error: 'not_found' });
      return;
    }
    super.catch(exception, host);
  }
}
