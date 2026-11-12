import { Controller, HttpCode, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Request, Response } from 'express';
import { SameOriginGuard } from '../common/same-origin.guard';
import { AuthService } from './auth.service';
import { NestSessionService } from './nest-session.service';

/**
 * Sign-out of Nest's own session (sb_sid). The page calls this first and then goes on to the PHP logout (bbs/logout.php), which
 * still ends the PHP session, the remember cookies and the app's tokens. Nothing to do = still 200: signing out twice is not an error.
 */
@Controller('v1/auth')
export class LogoutController {
  constructor(private readonly sessions: NestSessionService, private readonly auth: AuthService) {}

  @Post('logout')
  @HttpCode(200)
  @UseGuards(SameOriginGuard)
  async logout(@Req() req: Request, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    for (const c of await this.sessions.destroy(req.headers.cookie, String(req.headers['x-forwarded-host'] || req.headers.host || ''))) res.append('Set-Cookie', c);
    this.auth.forget(req.headers.cookie);
    return { success: true };
  }
}
