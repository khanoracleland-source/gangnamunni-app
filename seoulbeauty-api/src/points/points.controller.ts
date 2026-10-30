import { Controller, Get, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { PointsService } from './points.service';

/** 내 포인트: my_points.php (member's own: never cached) and my_points_policy.php's one dynamic value. */
@Controller('v1')
export class PointsController {
  constructor(private readonly points: PointsService) {}

  @Get('mypage/points')
  @UseGuards(RequireAuthGuard)
  mine(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.points.mine(req.session!.user!.no);
  }

  @Get('points/policy')
  policy(@Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30');
    return this.points.policy();
  }
}
