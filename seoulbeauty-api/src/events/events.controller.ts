import { Body, Controller, Get, Param, Post, Query, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthGuard, AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { idParam, str } from '../common/parse';
import { EntryInput, EventsService, Member } from './events.service';

function memberOf(req: AuthedRequest): Member | null {
  const u = req.session?.loggedIn ? req.session.user : null;
  return u?.id ? { id: u.id, no: u.no } : null;
}

@Controller('v1/events')
export class EventsController {
  constructor(private readonly events: EventsService) {}

  /** events.php?category=&filter=raffle. Public, briefly cacheable. */
  @Get()
  async list(@Query('category') category: unknown, @Query('filter') filter: unknown, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30, stale-while-revalidate=120');
    return this.events.list(str(category, 100), filter === 'raffle');
  }

  /** event_detail.php?id= — carries the member's own 응모 state, so never cached. */
  @Get(':id')
  @UseGuards(AuthGuard)
  async detail(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.events.detail(idParam(id), memberOf(req));
  }

  /** event_api.php?action=enter. 401 for anonymous (the web client sends the user to the PHP login). */
  @Post(':id/entries')
  @UseGuards(SameOriginGuard, RequireAuthGuard)
  async enter(@Param('id') id: string, @Body() body: Partial<EntryInput>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.events.enter(idParam(id), memberOf(req)!, body ?? {});
  }
}
