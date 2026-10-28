import { Body, Controller, Get, Param, Post, Query, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { AuthGuard, AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { idParam, intParam, str } from '../common/parse';
import { normalizeTab, parseRegions } from './community.logic';
import { CommunityService, Viewer } from './community.service';

function viewerOf(req: AuthedRequest): Viewer | null {
  const u = req.session?.loggedIn ? req.session.user : null;
  return u?.id ? { id: u.id, name: u.nickname || u.name || u.id } : null;
}

@Controller('v1/community')
export class CommunityController {
  constructor(private readonly community: CommunityService) {}

  /** community.php — hides blocked authors and carries the member's likes, so never cached. */
  @Get()
  @UseGuards(AuthGuard)
  async list(
    @Query('tab') tab: unknown, @Query('category') category: unknown, @Query('region') region: unknown,
    @Query('q') q: unknown, @Query('page') page: unknown, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response,
  ) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.community.list(viewerOf(req)?.id ?? null, {
      tab: normalizeTab(tab), category: str(category, 100), regions: parseRegions(str(region, 500)),
      q: str(q, 100), page: intParam(page, 1, 1, 100000, 'page'),
    });
  }

  /** detail_community.php. `?view=1` (server render of the page only) counts one view. */
  @Get(':id')
  @UseGuards(AuthGuard)
  async detail(@Param('id') id: string, @Query('view') view: unknown, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.community.detail(viewerOf(req), idParam(id), view === '1');
  }

  @Post(':id/like')
  @UseGuards(SameOriginGuard, RequireAuthGuard)
  async like(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.community.togglePostLike(viewerOf(req)!, idParam(id));
  }

  @Post(':id/comments')
  @UseGuards(SameOriginGuard, RequireAuthGuard)
  async comment(@Param('id') id: string, @Body() body: { content?: unknown; parentId?: unknown }, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.community.addComment(viewerOf(req)!, idParam(id), body ?? {});
  }

  @Post(':id/comments/:commentId/like')
  @UseGuards(SameOriginGuard, RequireAuthGuard)
  async commentLike(
    @Param('id') id: string, @Param('commentId') commentId: string, @Body() body: { liked?: unknown },
    @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response,
  ) {
    res.setHeader('Cache-Control', 'private, no-store');
    return this.community.setCommentLike(viewerOf(req)!, idParam(id), idParam(commentId), body?.liked === true);
  }
}
