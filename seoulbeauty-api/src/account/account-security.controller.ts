import { Body, Controller, HttpException, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { AuthService } from '../auth/auth.service';
import { SameOriginGuard } from '../common/same-origin.guard';
import { PasswordLostService } from '../password-lost/password-lost.service';
import { AccountSecurityService } from './account-security.service';

const nocache = (res: Response) => res.setHeader('Cache-Control', 'private, no-store');
const uid = (req: AuthedRequest) => req.session!.user!.id;
const cookie = (req: AuthedRequest) => req.headers.cookie || '';

/** 비밀번호 / 이메일 변경 and 회원 탈퇴: the member's own account, never cached; every write needs the same-origin header. */
@Controller('v1/account')
@UseGuards(RequireAuthGuard, SameOriginGuard)
export class AccountSecurityController {
  constructor(private readonly security: AccountSecurityService, private readonly auth: AuthService, private readonly lost: PasswordLostService) {}

  @Post('password')
  async password(@Body() body: Record<string, unknown>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    const r = await this.security.changePassword(uid(req), cookie(req), body ?? {});
    this.auth.forget(req.headers.cookie); // PHP has just ended every session of this member
    return r;
  }

  /** "현재 비밀번호를 잊으셨나요?": mails a 12-character temporary password to the member's own e-mail (ends every sign-in, this one too). */
  @Post('password/temp')
  async passwordTemp(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    const r = await this.lost.requestForMember(uid(req), req.ip || 'unknown');
    if (!r.sent) throw new HttpException({ success: false, message: '임시 비밀번호를 보내지 못했어요. 잠시 후 다시 시도해주세요.' }, 503); // nothing changed: still signed in
    this.auth.forget(req.headers.cookie); // every sign-in of this member was just revoked
    return { success: true, sent: true };
  }

  @Post('email/send')
  emailSend(@Body() body: Record<string, unknown>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.security.sendEmailCode(uid(req), cookie(req), body ?? {});
  }

  @Post('email/verify')
  async emailVerify(@Body() body: Record<string, unknown>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    const r = await this.security.verifyEmailCode(uid(req), cookie(req), body ?? {});
    this.auth.forget(req.headers.cookie);
    return r;
  }

  @Post('withdraw')
  async withdraw(@Body() body: Record<string, unknown>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    const r = await this.security.withdraw(uid(req), cookie(req), body ?? {});
    this.auth.forget(req.headers.cookie);
    return r;
  }
}
