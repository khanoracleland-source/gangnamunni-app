import { Controller, Get, Inject, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { DB, Db } from '../db/db.module';
import { AuthGuard, AuthedRequest } from './auth.guard';
import { NOTI_WINDOW_SQL } from '../notifications/notifications.logic';
import { NestSessionService } from './nest-session.service';

@Controller('v1/me')
export class MeController {
  constructor(@Inject(DB) private readonly db: Db, private readonly sessions: NestSessionService) {}

  /** Login state for the current cookie session. Never cached by proxies. */
  @Get()
  @UseGuards(AuthGuard)
  async me(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    res.setHeader('Vary', 'Cookie');
    // phone is for the member's own forms; never echo it to anonymous callers.
    const session = req.session;
    // B2: the page asks this on every load, the answer is never cached and it comes straight from the browser: the one safe place to
    // hand a PHP-born sign-in its Nest session. Only for a same-origin browser fetch (a server-side call could not keep the cookie).
    if (session.loggedIn && session.user && req.headers['sec-fetch-site'] === 'same-origin') {
      const own = await this.sessions.adopt(session.user.id, req.headers.cookie, String(req.headers['user-agent'] || ''));
      if (own) res.append('Set-Cookie', own);
    }
    // includes/global_nav.php: unread notification dot (member_id holds mb_id). Table may not exist -> 0.
    let unreadNotifications = 0;
    if (session.loggedIn && session.user) {
      try {
        const [rows] = (await this.db.query(
          `SELECT COUNT(*) AS cnt FROM gangnamunni_user_notifications WHERE member_id = ? AND is_read = 0 AND ${NOTI_WINDOW_SQL}`,
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
