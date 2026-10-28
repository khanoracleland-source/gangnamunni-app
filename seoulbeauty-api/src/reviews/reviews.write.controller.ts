import { Body, Controller, Delete, Get, Param, Post, Query, Req, Res, UploadedFiles, UseGuards, UseInterceptors } from '@nestjs/common';
import { FileFieldsInterceptor } from '@nestjs/platform-express';
import type { Response } from 'express';
import { AuthedRequest, RequireAuthGuard } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';
import { idParam, intParam } from '../common/parse';
import { REVIEW_PHOTO_MAX_BYTES, REVIEW_PHOTO_SLOTS } from './reviews.write.logic';
import { Member, PhotoFiles, ReviewsWriteService } from './reviews.write.service';

const nocache = (res: Response) => res.setHeader('Cache-Control', 'private, no-store');
function member(req: AuthedRequest): Member {
  const u = req.session!.user!;
  return { id: u.id, name: u.nickname || u.id };
}
const upload = () => FileFieldsInterceptor(
  [{ name: 'photos_before', maxCount: REVIEW_PHOTO_SLOTS }, { name: 'photos_after', maxCount: REVIEW_PHOTO_SLOTS }],
  { limits: { fileSize: REVIEW_PHOTO_MAX_BYTES, files: REVIEW_PHOTO_SLOTS * 2, fields: 20 } });

/** 후기 작성/수정 (review_write.php): the member's own data, never cached. */
@Controller('v1/mypage')
@UseGuards(RequireAuthGuard)
export class ReviewsWriteController {
  constructor(private readonly write: ReviewsWriteService) {}

  @Get('review-form')
  form(@Query() q: Record<string, unknown>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.form(member(req), {
      bookingId: intParam(q.booking_id, 0, 0, 2147483647, 'booking_id'),
      hospitalId: intParam(q.hospital_id, 0, 0, 2147483647, 'hospital_id'),
      editId: intParam(q.id, 0, 0, 2147483647, 'id'),
      returnUrl: q.return_url,
    });
  }

  @Post('reviews')
  @UseGuards(SameOriginGuard)
  @UseInterceptors(upload())
  create(@Body() body: Record<string, unknown>, @UploadedFiles() files: PhotoFiles, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.submit(member(req), body ?? {}, files ?? {});
  }

  @Delete('reviews/:id')
  @UseGuards(SameOriginGuard)
  remove(@Param('id') id: string, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    nocache(res);
    return this.write.remove(member(req), idParam(id));
  }
}
