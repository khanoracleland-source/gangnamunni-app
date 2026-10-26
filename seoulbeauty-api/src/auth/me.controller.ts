import { Controller, Get, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthGuard, AuthedRequest } from './auth.guard';

@Controller('v1/me')
export class MeController {
  /** Login state for the current cookie session. Never cached by proxies. */
  @Get()
  @UseGuards(AuthGuard)
  me(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    res.setHeader('Vary', 'Cookie');
    // phone is for the member's own forms; never echo it to anonymous callers.
    return req.session;
  }
}
