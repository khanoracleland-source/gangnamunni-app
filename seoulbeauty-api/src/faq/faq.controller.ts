import { Controller, Get, Res } from '@nestjs/common';
import type { Response } from 'express';
import { FaqService } from './faq.service';

@Controller('v1/faq')
export class FaqController {
  constructor(private readonly faq: FaqService) {}

  /** Public and the same for everybody: briefly cacheable. */
  @Get()
  list(@Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=60, stale-while-revalidate=300');
    return this.faq.list();
  }
}
