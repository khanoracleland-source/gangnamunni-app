import { Body, Controller, Get, Param, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { idParam } from '../common/parse';
import { MemberCtx, MypageService } from './mypage.service';

function member(req: AuthedRequest): MemberCtx {
  const u = req.session!.user!;
  return { id: u.id, no: Number(u.no) || 0, nickname: u.nickname || u.name || u.id, phone: u.phone || '' };
}

/** 마이페이지 data. Everything is the member's own, so it is never cached. */
@Controller('v1/mypage')
@UseGuards(RequireAuthGuard)
export class MypageController {
  constructor(private readonly mypage: MypageService) {}

  @Get()
  summary(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.mypage.summary(member(req)); }

  @Get('wishlist')
  wishlist(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.mypage.wishlist(member(req)); }

  @Get('consultations')
  consultations(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.mypage.consultations(member(req)); }

  @Get('consultations/:id')
  consultation(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.mypage.consultation(member(req), idParam(id)); }

  @Post('consultations/:id/rerequest')
  @UseGuards(SameOriginGuard)
  rerequest(@Param('id') id: string, @Body() body: { slot?: unknown }, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.mypage.rerequest(member(req), idParam(id), body?.slot); }

  @Get('reviews')
  reviews(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { res.setHeader('Cache-Control', 'private, no-store'); return this.mypage.reviews(member(req)); }
}
