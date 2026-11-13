import { Controller, Get } from '@nestjs/common';

/** Liveness only: no auth, not behind ADMIN_PANEL_ENABLED, touches neither the database nor PHP. */
@Controller('health')
export class HealthController {
  @Get()
  check() {
    return { ok: true };
  }
}
