import { Controller, Get, Param, Query, Req, Res } from '@nestjs/common';
import type { Request, Response } from 'express';
import { cookieValue } from '../login/login.logic';
import { SocialService, STATE_COOKIE } from './social.service';

/** 소셜 로그인 (Kakao / Google): browser redirects, never cached. */
@Controller('v1/auth/social')
export class SocialController {
  constructor(private readonly svc: SocialService) {}

  @Get(':provider/start')
  async start(@Param('provider') provider: string, @Query('url') url: string, @Req() req: Request, @Res() res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    const r = await this.svc.start(provider, url, String(req.headers['x-forwarded-host'] || req.headers.host || ''));
    for (const c of r.cookies) res.append('Set-Cookie', c);
    res.redirect(302, r.to);
  }

  /** Provider-less callback (Kakao: its console does not accept "kakao" in a redirect URI). */
  @Get('callback')
  callbackAuto(@Query() q: { code?: string; state?: string; error?: string }, @Req() req: Request, @Res() res: Response) {
    return this.callback(this.svc.providerFromState(cookieValue(req.headers.cookie || '', STATE_COOKIE)), q, req, res);
  }

  @Get(':provider/callback')
  async callback(@Param('provider') provider: string, @Query() q: { code?: string; state?: string; error?: string }, @Req() req: Request, @Res() res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    const cookie = req.headers.cookie || '';
    const r = await this.svc.callback(provider, { code: typeof q.code === 'string' ? q.code : undefined, state: typeof q.state === 'string' ? q.state : undefined, error: typeof q.error === 'string' ? q.error : undefined },
      cookieValue(cookie, STATE_COOKIE), req.ip || 'unknown', cookie, String(req.headers['user-agent'] || ''), String(req.headers['x-forwarded-host'] || req.headers.host || ''));
    for (const c of r.cookies) res.append('Set-Cookie', c);
    res.redirect(302, r.kind === 'php' ? this.svc.phpRedirect(r.provider, r.url) : r.to);
  }
}
