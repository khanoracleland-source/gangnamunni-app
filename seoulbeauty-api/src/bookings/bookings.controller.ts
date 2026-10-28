import { Controller, Get, Param, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { idParam } from '../common/parse';
import { SameOriginGuard } from '../common/same-origin.guard';
import { BookingMember, BookingsService } from './bookings.service';

function member(req: AuthedRequest): BookingMember {
  const u = req.session!.user!;
  return { id: u.id, phone: u.phone || '' };
}

/** 내 예약 (read-only). Changing, cancelling and paying stay in PHP. Everything is the member's own: never cached. */
@Controller('v1/mypage/bookings')
@UseGuards(RequireAuthGuard)
export class BookingsController {
  constructor(private readonly bookings: BookingsService) {}

  @Get()
  list(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.bookings.list(member(req)); }

  @Get(':id')
  detail(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.bookings.detail(member(req), idParam(id)); }

  @Post(':id/expire-seen')
  @UseGuards(SameOriginGuard)
  expireSeen(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.bookings.expireSeen(member(req), idParam(id)); }
}
