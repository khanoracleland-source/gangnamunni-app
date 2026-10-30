import { Body, Controller, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Request, Response } from 'express';
import { SameOriginGuard } from '../common/same-origin.guard';
import { PasswordLostService } from './password-lost.service';

/** 비밀번호 찾기: public (the member has no session), same-origin only, never cached. */
@Controller('v1/auth')
export class PasswordLostController {
  constructor(private readonly svc: PasswordLostService) {}

  @Post('password-lost')
  @UseGuards(SameOriginGuard)
  request(@Body() body: { email?: unknown }, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.svc.request(body?.email, req.ip || 'unknown');
  }
}
