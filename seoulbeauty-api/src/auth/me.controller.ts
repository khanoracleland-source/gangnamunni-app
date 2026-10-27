import { Controller, Get, Inject, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { DB, Db } from '../db/db.module';
import { AuthGuard, AuthedRequest } from './auth.guard';

@Controller('v1/me')
export class MeController {
  constructor(@Inject(DB) private readonly db: Db) {}

  /** Login state for the current cookie session. Never cached by proxies. */
  @Get()
  @UseGuards(AuthGuard)
  async me(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    res.setHeader('Vary', 'Cookie');
    // phone is for the member's own forms; never echo it to anonymous callers.
    const session = req.session;
    // includes/global_nav.php: unread notification dot (member_id holds mb_id). Table may not exist -> 0.
    let unreadNotifications = 0;
    if (session.loggedIn && session.user) {
      try {
        const [rows] = (await this.db.query(
          `SELECT COUNT(*) AS cnt FROM gangnamunni_user_notifications WHERE member_id = ? AND is_read = 0`,
          [session.user.id],
        )) as any;
        unreadNotifications = Number(rows?.[0]?.cnt ?? 0);
      } catch {
        unreadNotifications = 0;
      }
    }
    return { ...session, unreadNotifications };
  }
}
