import { Body, Controller, Get, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Request, Response } from 'express';
import { SameOriginGuard } from '../common/same-origin.guard';
import { LoginService } from './login.service';

/** 이메일 로그인: public, same-origin only, never cached. The PHP session cookies are relayed from the bridge. */
@Controller('v1/auth')
export class LoginController {
  constructor(private readonly svc: LoginService) {}

  @Get('login-options')
  options(@Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, s-maxage=30');
    return this.svc.options();
  }

  @Post('login')
  @UseGuards(SameOriginGuard)
  async login(@Body() body: { email?: unknown; password?: unknown; remember?: unknown; url?: unknown }, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    const r = await this.svc.login(body ?? {}, req.ip || 'unknown', req.headers.cookie || '', String(req.headers['x-forwarded-host'] || req.headers.host || ''), String(req.headers['user-agent'] || ''));
    for (const c of r.cookies) res.append('Set-Cookie', c);
    return { success: true, redirect: r.redirect };
  }
}
