import { Body, Controller, Get, Param, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { idParam } from '../common/parse';
import { SameOriginGuard } from '../common/same-origin.guard';
import { BookingMember, BookingsService } from './bookings.service';
import { BookingManageService } from './booking-manage.service';

function member(req: AuthedRequest): BookingMember {
  const u = req.session!.user!;
  return { id: u.id, phone: u.phone || '' };
}

/** 내 예약 (list, detail, cancel, change). Paying stays in PHP. Everything is the member's own: never cached. */
@Controller('v1/mypage/bookings')
@UseGuards(RequireAuthGuard)
export class BookingsController {
  constructor(private readonly bookings: BookingsService, private readonly manage: BookingManageService) {}

  @Get()
  list(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.bookings.list(member(req)); }

  @Get(':id')
  detail(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.bookings.detail(member(req), idParam(id)); }

  @Post(':id/expire-seen')
  @UseGuards(SameOriginGuard)
  expireSeen(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.bookings.expireSeen(member(req), idParam(id)); }

  @Get(':id/cancel-info')
  cancelInfo(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.manage.cancelInfo(member(req), idParam(id)); }

  @Post(':id/cancel')
  @UseGuards(SameOriginGuard)
  cancel(@Param('id') id: string, @Body() body: { reason?: unknown }, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.manage.cancel(member(req), idParam(id), body?.reason); }

  @Get(':id/change-info')
  changeInfo(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.manage.changeInfo(member(req), idParam(id)); }

  @Post(':id/change')
  @UseGuards(SameOriginGuard)
  change(@Param('id') id: string, @Body() body: { date?: unknown; time?: unknown; treatment?: unknown }, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.manage.change(member(req), idParam(id), body ?? {}); }
}
