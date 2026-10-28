import { Body, Controller, Get, Post, Req, Res, UploadedFile, UseGuards, UseInterceptors } from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { AuthService } from '../auth/auth.service';
import { SameOriginGuard } from '../common/same-origin.guard';
import { PROFILE_PHOTO_MAX_BYTES } from './account.logic';
import { AccountService } from './account.service';

const nocache = (res: Response) => res.setHeader('Cache-Control', 'private, no-store');
const uid = (req: AuthedRequest) => req.session!.user!.id;

/** 내 정보 수정 / 알림 설정: the member's own data, never cached. */
@Controller('v1/mypage')
@UseGuards(RequireAuthGuard)
export class AccountController {
  constructor(private readonly account: AccountService, private readonly auth: AuthService) {}

  @Get('profile')
  profile(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { nocache(res); return this.account.profile(uid(req)); }

  @Post('profile')
  @UseGuards(SameOriginGuard)
  @UseInterceptors(FileInterceptor('photo', { limits: { fileSize: PROFILE_PHOTO_MAX_BYTES, files: 1, fields: 10 } }))
  async save(@Body() body: Record<string, unknown>, @UploadedFile() photo: { buffer: Buffer; size: number } | undefined, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    const r = await this.account.saveProfile(uid(req), body ?? {}, photo, req.ip || '');
    this.auth.forget(req.headers.cookie); // the nickname/phone in the cached session are now stale
    return r;
  }

  @Get('notification-settings')
  settings(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { nocache(res); return this.account.settings(uid(req)); }

  @Post('notification-settings')
  @UseGuards(SameOriginGuard)
  setSetting(@Body() body: { field?: unknown; value?: unknown }, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.account.setSetting(uid(req), body?.field, body?.value);
  }
}
