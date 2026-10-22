import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  const origins = (process.env.CORS_ORIGINS || '').split(',').map((s) => s.trim()).filter(Boolean);
  app.enableCors({ origin: origins.length ? origins : false, methods: ['GET'], maxAge: 600 });
  const ex = app.getHttpAdapter().getInstance();
  ex.disable('x-powered-by');
  // Behind nginx/Apache: use the real client IP for rate limiting.
  if (process.env.TRUST_PROXY) ex.set('trust proxy', Number(process.env.TRUST_PROXY) || process.env.TRUST_PROXY);
  await app.listen(Number(process.env.PORT || 3100), process.env.HOST || '127.0.0.1');
}
bootstrap();
