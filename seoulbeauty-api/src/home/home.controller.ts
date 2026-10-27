import { Controller, Get, Res } from '@nestjs/common';
import type { Response } from 'express';
import { HomeService } from './home.service';

@Controller('v1/home')
export class HomeController {
  constructor(private readonly home: HomeService) {}

  /** Everything index.php needs, in one round trip. Public, briefly cacheable. */
  @Get()
  async get(@Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30, stale-while-revalidate=300');
    return this.home.load();
  }
}
