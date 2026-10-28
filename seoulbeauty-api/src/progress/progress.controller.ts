import { Body, Controller, Delete, Get, Param, Post, Req, Res, StreamableFile, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { FilesInterceptor } from '@nestjs/platform-express';
import type { Response } from 'express';
import { createReadStream } from 'node:fs';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { idParam, intParam } from '../common/parse';
import { MAX_ENTRY_PHOTOS, PHOTO_MAX_BYTES } from './progress.logic';
import { Member, ProgressService, UploadedPhoto } from './progress.service';

const nocache = (res: Response) => res.setHeader('Cache-Control', 'private, no-store');
const member = (req: AuthedRequest): Member => ({ id: req.session!.user!.id, no: Number(req.session!.user!.no) || 0 });
const upload = () => FilesInterceptor('photos', MAX_ENTRY_PHOTOS, { limits: { fileSize: PHOTO_MAX_BYTES, files: MAX_ENTRY_PHOTOS, fields: 10 } });

/** 경과 후기 (my_progress_reviews.php, progress_review.php, progress_photo.php): the member's own data, never cached. */
@Controller('v1/mypage/progress')
@UseGuards(RequireAuthGuard)
export class ProgressController {
  constructor(private readonly progress: ProgressService) {}

  @Get()
  list(@Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { nocache(res); return this.progress.list(member(req)); }

  @Post()
  @UseGuards(SameOriginGuard)
  create(@Body() body: Record<string, unknown>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res); return this.progress.create(member(req), body ?? {});
  }

  /** Fixed path before `:id` so "photo" is never parsed as an id. */
  @Get('photo/:entry/:i')
  async photo(@Param('entry') entry: string, @Param('i') i: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    const f = await this.progress.photo(member(req), idParam(entry), intParam(i, 0, 0, 9, 'i'));
    res.setHeader('Cache-Control', 'private, no-store');
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    return new StreamableFile(createReadStream(f.path), { type: f.mime, disposition: `inline; filename="${f.name}"` });
  }

  @Get(':id')
  detail(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { nocache(res); return this.progress.detail(member(req), idParam(id)); }

  @Post(':id/entries')
  @UseGuards(SameOriginGuard)
  @UseInterceptors(upload())
  addEntry(@Param('id') id: string, @Body() body: Record<string, unknown>, @UploadedFiles() files: UploadedPhoto[] | undefined, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res); return this.progress.addEntry(member(req), idParam(id), body ?? {}, files ?? []);
  }

  @Delete(':id/entries/:eid')
  @UseGuards(SameOriginGuard)
  deleteEntry(@Param('id') id: string, @Param('eid') eid: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res); return this.progress.deleteEntry(member(req), idParam(id), idParam(eid));
  }

  @Post(':id/submit')
  @UseGuards(SameOriginGuard)
  submit(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { nocache(res); return this.progress.submit(member(req), idParam(id)); }

  @Delete(':id')
  @UseGuards(SameOriginGuard)
  remove(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) { nocache(res); return this.progress.remove(member(req), idParam(id)); }
}
