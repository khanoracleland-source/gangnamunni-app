import { Controller, Get, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { LegalService } from './legal.service';

@Controller('v1/legal')
export class LegalController {
  constructor(private readonly legal: LegalService) {}

  /** Public and the same for everybody: briefly cacheable. */
  @Get('register-terms')
  registerTerms(@Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=60, stale-while-revalidate=300');
    return this.legal.registerTerms();
  }

  @Get('points-policy')
  pointsPolicy(@Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30');
    return this.legal.pointsPolicy();
  }

  @Post('agree')
  @UseGuards(RequireAuthGuard, SameOriginGuard)
  agree(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.legal.agree(req.session!.user!.id, req.ip || '', String(req.headers['user-agent'] || ''));
  }
}
