import { Controller, Get, Param, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthGuard, AuthedRequest } from '../auth/auth.guard';
import { idParam } from '../common/parse';
import { ReviewsService } from './reviews.service';

@Controller('v1/reviews')
export class ReviewsController {
  constructor(private readonly reviews: ReviewsService) {}

  /** review_detail.php — carries the viewer's like and hides blocked authors, so never cached. */
  @Get(':id')
  @UseGuards(AuthGuard)
  detail(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.reviews.detail(req.session?.loggedIn ? req.session.user?.id ?? null : null, idParam(id));
  }
}
