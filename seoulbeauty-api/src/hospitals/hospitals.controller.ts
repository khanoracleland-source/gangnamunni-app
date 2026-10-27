import { BadRequestException, Controller, Get, Param, Query, Req, Res, UseGuards } from '@nestjs/common';
import { AuthGuard, AuthedRequest } from '../auth/auth.guard';
import type { Response } from 'express';
import { idParam, intParam, str } from '../common/parse';
import { HOSPITAL_SORTS, HospitalSort } from './hospital-query';
import { HospitalsService } from './hospitals.service';
import { ReviewsService } from '../reviews/reviews.service';
import { HospitalContentService } from './hospital-content.service';

@Controller('v1/hospitals')
export class HospitalsController {
  constructor(
    private readonly hospitals: HospitalsService,
    private readonly reviews: ReviewsService,
    private readonly content: HospitalContentService,
  ) {}

  private cache(res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30, stale-while-revalidate=300');
  }

  @Get()
  @UseGuards(AuthGuard)
  async list(@Query() qs: Record<string, unknown>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    const treatment = str(qs.treatment);
    const sortRaw = str(qs.sort, 20);
    if (sortRaw && !HOSPITAL_SORTS.includes(sortRaw as HospitalSort)) {
      throw new BadRequestException(`sort must be one of ${HOSPITAL_SORTS.join(', ')}`);
    }
    const sort = sortRaw as HospitalSort | ''; // '' -> service default (price when a treatment is effective)
    const regions = str(qs.region, 300)
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .slice(0, 10);
    const memberNo = req.session.loggedIn ? req.session.user?.no : undefined;
    // Anonymous responses are public and cacheable; a member's response carries `wishlisted`.
    res.setHeader('Cache-Control', memberNo ? 'private, no-store' : 'public, max-age=0, s-maxage=30, stale-while-revalidate=300');
    res.setHeader('Vary', 'Cookie');
    return this.hospitals.list({
      memberNo,
      q: str(qs.q),
      category: str(qs.category),
      treatment,
      dosage: str(qs.dosage, 30),
      regions,
      sort,
      limit: intParam(qs.limit, 20, 1, 100, 'limit'),
      offset: intParam(qs.offset, 0, 0, 1000, 'offset'),
    });
  }

  @Get(':id')
  async detail(@Param('id') id: string, @Res({ passthrough: true }) res: Response) {
    this.cache(res);
    const hid = idParam(id);
    const h = await this.hospitals.detail(hid); // 404 for hidden/demo/unapproved
    const [images, features] = await Promise.all([this.content.images(hid, h.mainImageUrl, h.profileImageUrl), this.content.features(hid)]);
    return { ...h, images, features };
  }

  /** 시술목록: active treatments with sale prices, grouped per treatment. */
  @Get(':id/treatments')
  async treatments(@Param('id') id: string, @Res({ passthrough: true }) res: Response) {
    this.cache(res);
    const hid = idParam(id);
    await this.hospitals.detail(hid); // visibility gate
    return { items: await this.content.treatments(hid) };
  }

  /** hospitals.php?hid= view: the hospital's treatments with cheapest prices + category chips. */
  @Get(':id/search-view')
  async searchView(@Param('id') id: string, @Res({ passthrough: true }) res: Response) {
    this.cache(res);
    const hid = idParam(id);
    const h = await this.hospitals.detail(hid);
    return { hospital: h, ...(await this.content.searchView(hid)) };
  }

  @Get(':id/doctors')
  async doctors(@Param('id') id: string, @Res({ passthrough: true }) res: Response) {
    this.cache(res);
    const hid = idParam(id);
    await this.hospitals.detail(hid);
    return { items: await this.content.doctors(hid) };
  }

  @Get(':id/reviews')
  async reviewList(@Param('id') id: string, @Query() qs: Record<string, unknown>, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30, stale-while-revalidate=300');
    return this.reviews.listForHospital(idParam(id), intParam(qs.limit, 20, 1, 50, 'limit'), intParam(qs.offset, 0, 0, 1000, 'offset'));
  }
}
