import { BadRequestException, Body, Controller, Get, Inject, Param, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { DB, Db } from '../db/db.module';
import { AuthGuard, AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { idParam } from '../common/parse';

/** Mirrors api_wishlist.php: member_id = mb_no, target_type 'hospital' only (for now). */
@Controller('v1/wishlist')
export class WishlistController {
  constructor(@Inject(DB) private readonly db: Db) {}

  private async count(targetId: number): Promise<number> {
    const [r] = (await this.db.query("SELECT COUNT(*) AS cnt FROM gangnamunni_wishlist WHERE target_type='hospital' AND target_id=?", [targetId])) as any;
    return Number(r[0]?.cnt ?? 0);
  }

  /** Wishlisted-by-me + total count. Anonymous → wishlisted=false (same as PHP action=check). */
  @Get('hospitals/:id')
  @UseGuards(AuthGuard)
  async check(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    const targetId = idParam(id);
    let wishlisted = false;
    if (req.session.loggedIn && req.session.user) {
      const [r] = (await this.db.query(
        "SELECT id FROM gangnamunni_wishlist WHERE member_id=? AND target_type='hospital' AND target_id=? LIMIT 1",
        [req.session.user.no, targetId],
      )) as any;
      wishlisted = r.length > 0;
    }
    return { wishlisted, count: await this.count(targetId) };
  }

  /** Toggle. 401 when anonymous (the client sends the user to the PHP login page). */
  @Post('toggle')
  @UseGuards(SameOriginGuard, RequireAuthGuard)
  async toggle(@Body() body: { type?: string; targetId?: number }, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    const targetId = Number(body?.targetId);
    if ((body?.type ?? 'hospital') !== 'hospital' || !Number.isInteger(targetId) || targetId <= 0) throw new BadRequestException('invalid target');
    const memberNo = req.session.user!.no;
    if (!memberNo) throw new BadRequestException('member number unavailable');
    const [existing] = (await this.db.query(
      "SELECT id FROM gangnamunni_wishlist WHERE member_id=? AND target_type='hospital' AND target_id=? LIMIT 1",
      [memberNo, targetId],
    )) as any;
    let wishlisted: boolean;
    if (existing.length) {
      await this.db.query("DELETE FROM gangnamunni_wishlist WHERE member_id=? AND target_type='hospital' AND target_id=?", [memberNo, targetId]);
      wishlisted = false;
    } else {
      await this.db.query("INSERT IGNORE INTO gangnamunni_wishlist (member_id, target_type, target_id) VALUES (?, 'hospital', ?)", [memberNo, targetId]);
      wishlisted = true;
    }
    return { wishlisted, count: await this.count(targetId), message: wishlisted ? '찜 목록에 추가했습니다' : '찜 목록에서 제거했습니다' };
  }
}
