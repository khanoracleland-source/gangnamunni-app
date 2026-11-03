import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { Logger } from '@nestjs/common';
import { AppModule } from './app.module';
import { resetLimitsFlagSet, resetLimitsOff } from './password-lost/password-lost.logic';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  const origins = (process.env.CORS_ORIGINS || '').split(',').map((s) => s.trim()).filter(Boolean);
  // Browser writes come same-origin through the Next.js /next/api proxy; CORS stays GET-only on purpose
  // (a cross-site POST with X-Requested-With needs a preflight, which this refuses).
  app.enableCors({ origin: origins.length ? origins : false, methods: ['GET'], maxAge: 600 });
  const ex = app.getHttpAdapter().getInstance();
  ex.disable('x-powered-by');
  // Behind nginx/Apache: use the real client IP for rate limiting.
  if (process.env.TRUST_PROXY) ex.set('trust proxy', Number(process.env.TRUST_PROXY) || process.env.TRUST_PROXY);
  if (resetLimitsFlagSet()) {
    new Logger('Bootstrap').warn(resetLimitsOff()
      ? 'PASSWORD_LOST_RATE_LIMIT=off is ACTIVE: password-reset limits are disabled (testing only, remove it from .env)'
      : `PASSWORD_LOST_RATE_LIMIT=off is set but IGNORED (works only with NODE_ENV=development|test, now ${process.env.NODE_ENV || 'unset'})`);
  }
  await app.listen(Number(process.env.PORT || 3100), process.env.HOST || '127.0.0.1');
}
bootstrap();
