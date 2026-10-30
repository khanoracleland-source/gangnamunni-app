import { Body, Controller, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Request, Response } from 'express';
import { SameOriginGuard } from '../common/same-origin.guard';
import { RegisterService, RegisterInput } from './register.service';

/** 회원가입: public, same-origin only, never cached. */
@Controller('v1/auth/register')
export class RegisterController {
  constructor(private readonly svc: RegisterService) {}

  @Post('code')
  @UseGuards(SameOriginGuard)
  code(@Body() body: { email?: unknown }, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.svc.sendCode(body?.email, req.ip || 'unknown');
  }

  @Post()
  @UseGuards(SameOriginGuard)
  async register(@Body() body: RegisterInput, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    const r = await this.svc.register(body ?? {}, req.ip || 'unknown', req.headers.cookie || '', String(req.headers['user-agent'] || ''));
    for (const c of r.cookies) res.append('Set-Cookie', c);
    return { success: true, loggedIn: r.loggedIn };
  }
}
