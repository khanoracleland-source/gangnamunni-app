import { Controller, Delete, Get, Param, Post, Query, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { idParam, intParam } from '../common/parse';
import { SameOriginGuard } from '../common/same-origin.guard';
import { notiFilter } from './notifications.logic';
import { NotificationsService } from './notifications.service';

/** 내 알림. Everything is the member's own: never cached. */
@Controller('v1/mypage/notifications')
@UseGuards(RequireAuthGuard)
export class NotificationsController {
  constructor(private readonly notis: NotificationsService) {}

  @Get()
  list(@Query('type') type: string, @Query('page') page: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.notis.list(req.session!.user!.id, notiFilter(type), intParam(page, 1, 1, 10000, 'page'));
  }

  @Post('read-all')
  @UseGuards(SameOriginGuard)
  readAll(@Req() req: AuthedRequest) { return this.notis.readAll(req.session!.user!.id); }

  @Post(':id/read')
  @UseGuards(SameOriginGuard)
  read(@Param('id') id: string, @Req() req: AuthedRequest) { return this.notis.read(req.session!.user!.id, idParam(id)); }

  @Delete(':id')
  @UseGuards(SameOriginGuard)
  remove(@Param('id') id: string, @Req() req: AuthedRequest) { return this.notis.remove(req.session!.user!.id, idParam(id)); }
}
