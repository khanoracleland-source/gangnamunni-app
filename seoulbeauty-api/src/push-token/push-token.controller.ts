import { Body, CanActivate, Controller, HttpCode, HttpException, Injectable, NotFoundException, Post, Req, Res, UseGuards } from '@nestjs/common';
import { Throttle } from '@nestjs/throttler';
import type { Response } from 'express';
import { AuthGuard, AuthedRequest } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { cleanToken, normalizePushToken } from './push-token.logic';
import { PushTokenService } from './push-token.service';

const fail = (status: number, error: string) => new HttpException({ success: false, error }, status);

/** First guard: with the switch off the endpoints do not exist (404), whatever else the request carries. */
@Injectable()
export class PushTokenEnabledGuard implements CanActivate {
  constructor(private readonly tokens: PushTokenService) {}
  canActivate(): boolean {
    if (!this.tokens.enabled()) throw new NotFoundException();
    return true;
  }
}

/**
 * The app's FCM token for the signed-in member (APP_PUSH_TOKEN_NEST=1; otherwise 404). Called by the Next pages inside the app's
 * WebView (window.onTokenReceived), replacing PHP push_fcm/get_android_regid.php. The member is the session's, never the body's.
 */
@Controller('v1/app/push-token')
@UseGuards(PushTokenEnabledGuard, SameOriginGuard, AuthGuard)
@Throttle({ default: { limit: Number(process.env.PUSH_TOKEN_RATE_PER_MIN || 30), ttl: 60_000 } })
export class PushTokenController {
  constructor(private readonly tokens: PushTokenService) {}

  private member(req: AuthedRequest, res: Response): string {
    res.setHeader('Cache-Control', 'private, no-store');
    const id = req.session.loggedIn && req.session.user ? String(req.session.user.id || '').trim() : '';
    if (id === '') throw fail(401, 'login_required');
    return id;
  }

  @Post()
  @HttpCode(200)
  register(@Body() body: unknown, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    const mbId = this.member(req, res);
    const input = normalizePushToken(body, String(req.headers['user-agent'] || ''));
    if (!input) throw fail(400, 'invalid_token');
    return this.tokens.register(mbId, input);
  }

  @Post('unlink')
  @HttpCode(200)
  unlink(@Body() body: unknown, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    const mbId = this.member(req, res);
    const token = cleanToken(body && typeof body === 'object' ? (body as Record<string, unknown>).token : '');
    if (token === '') throw fail(400, 'invalid_token');
    return this.tokens.unlink(mbId, token);
  }
}
