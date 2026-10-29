import { Body, Controller, Get, Param, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthGuard, AuthedRequest } from '../auth/auth.guard';
import { idParam } from '../common/parse';
import { SameOriginGuard } from '../common/same-origin.guard';
import { Actor, BookingInput, BookingService } from './booking.service';

const actor = (req: AuthedRequest): Actor => ({ loggedIn: !!req.session?.loggedIn, id: req.session?.user?.id ?? '' });

/** 예약하기 (booking.php). Read and write answer per member: never cached. */
@Controller('v1/booking')
@UseGuards(AuthGuard)
export class BookingController {
  constructor(private readonly booking: BookingService) {}

  @Get('context/:hospitalId')
  context(@Param('hospitalId') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.booking.context(actor(req), idParam(id));
  }

  /** Every refusal check of the real booking, nothing saved: the screen asks this before it opens the confirmation. */
  @Post('precheck')
  @UseGuards(SameOriginGuard)
  precheck(@Body() body: BookingInput, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.booking.create(actor(req), req.ip || '', body ?? {}, true);
  }

  @Post()
  @UseGuards(SameOriginGuard)
  create(@Body() body: BookingInput, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.booking.create(actor(req), req.ip || '', body ?? {}, false);
  }
}
