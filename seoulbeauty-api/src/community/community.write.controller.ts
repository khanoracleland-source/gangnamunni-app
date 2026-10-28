import { Body, Controller, Delete, Get, Param, Post, Put, Query, Req, Res, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { FilesInterceptor } from '@nestjs/platform-express';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { idParam } from '../common/parse';
import { Viewer } from './community.service';
import { MAX_PHOTOS, MAX_PHOTO_BYTES } from './community.write.logic';
import { CommunityWriteService, UploadedFile } from './community.write.service';

function me(req: AuthedRequest): Viewer & { no: number } {
  const u = req.session!.user!;
  return { id: u.id, name: u.nickname || u.name || u.id, no: Number(u.no) || 0 };
}
const upload = () => FilesInterceptor('photos', MAX_PHOTOS, { limits: { fileSize: MAX_PHOTO_BYTES, files: MAX_PHOTOS, fields: 20 } });
const nocache = (res: Response) => res.setHeader('Cache-Control', 'private, no-store');

/** Registered before CommunityController so the fixed paths win over `:id`. */
@Controller('v1/community')
@UseGuards(RequireAuthGuard)
export class CommunityWriteController {
  constructor(private readonly write: CommunityWriteService) {}

  /** Write form data; `?id=` loads the author's own post for editing. */
  @Get('form')
  async form(@Query('id') id: unknown, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.form(me(req), id == null || id === '' ? null : idParam(String(id)));
  }

  @Get('blocks')
  async blocks(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.blocks(me(req));
  }

  @Delete('blocks/:rowId')
  @UseGuards(SameOriginGuard)
  async unblock(@Param('rowId') rowId: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.unblock(me(req), idParam(rowId));
  }

  @Post('blocks')
  @UseGuards(SameOriginGuard)
  async block(@Body() body: { contentType?: unknown; contentId?: unknown }, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.block(me(req), body ?? {});
  }

  @Post('reports')
  @UseGuards(SameOriginGuard)
  async report(@Body() body: any, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.report(me(req), body ?? {});
  }

  @Post('reviews/:reviewId/like')
  @UseGuards(SameOriginGuard)
  async reviewLike(@Param('reviewId') reviewId: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.toggleReviewLike(me(req), idParam(reviewId));
  }

  @Post()
  @UseGuards(SameOriginGuard)
  @UseInterceptors(upload())
  async create(@Body() body: any, @UploadedFiles() files: UploadedFile[], @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.create(me(req), body ?? {}, files ?? []);
  }

  @Put(':id')
  @UseGuards(SameOriginGuard)
  @UseInterceptors(upload())
  async update(@Param('id') id: string, @Body() body: any, @UploadedFiles() files: UploadedFile[], @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.update(me(req), idParam(id), body ?? {}, files ?? []);
  }

  @Delete(':id')
  @UseGuards(SameOriginGuard)
  async remove(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.remove(me(req), idParam(id));
  }
}
