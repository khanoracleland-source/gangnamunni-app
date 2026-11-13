import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';

async function bootstrap() {
  // No CORS on purpose: the browser only reaches this API same-origin, through the admin web app's /admin-next/api rewrite.
  const app = await NestFactory.create(AppModule);
  const ex = app.getHttpAdapter().getInstance();
  ex.disable('x-powered-by');
  // Behind Apache + Next: use the real client IP for rate limiting.
  if (process.env.TRUST_PROXY) ex.set('trust proxy', Number(process.env.TRUST_PROXY) || process.env.TRUST_PROXY);
  await app.listen(Number(process.env.PORT || 3300), process.env.HOST || '127.0.0.1');
}
bootstrap();
