import { Controller, Get, Query, Res } from '@nestjs/common';
import type { Response } from 'express';
import { str } from '../common/parse';
import { SearchService } from './search.service';

@Controller('v1/search')
export class SearchController {
  constructor(private readonly search: SearchService) {}

  /** search_suggest.php — autocomplete for the search overlay. Public, short cache. */
  @Get('suggest')
  async suggest(@Query('q') q: unknown, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=30, s-maxage=30');
    return this.search.suggest(str(q, 40));
  }
}
