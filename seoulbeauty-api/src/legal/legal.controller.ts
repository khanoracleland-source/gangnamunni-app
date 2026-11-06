import { Controller, Get, Res } from '@nestjs/common';
import type { Response } from 'express';
import { LegalService } from './legal.service';

@Controller('v1/legal')
export class LegalController {
  constructor(private readonly legal: LegalService) {}

  /** Public and the same for everybody: briefly cacheable. */
  @Get('register-terms')
  registerTerms(@Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=60');
    return this.legal.registerTerms();
  }

  @Get('points-policy')
  pointsPolicy(@Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30');
    return this.legal.pointsPolicy();
  }
}
