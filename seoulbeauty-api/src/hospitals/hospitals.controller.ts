import { BadRequestException, Controller, Get, Param, Query, Res } from '@nestjs/common';
import type { Response } from 'express';
import { idParam, intParam, str } from '../common/parse';
import { HOSPITAL_SORTS, HospitalSort } from './hospital-query';
import { HospitalsService } from './hospitals.service';
import { ReviewsService } from '../reviews/reviews.service';

@Controller('v1/hospitals')
export class HospitalsController {
  constructor(private readonly hospitals: HospitalsService, private readonly reviews: ReviewsService) {}

  @Get()
  async list(@Query() qs: Record<string, unknown>, @Res({ passthrough: true }) res: Response) {
    const treatment = str(qs.treatment);
    const sortRaw = str(qs.sort, 20);
    if (sortRaw && !HOSPITAL_SORTS.includes(sortRaw as HospitalSort)) {
      throw new BadRequestException(`sort must be one of ${HOSPITAL_SORTS.join(', ')}`);
    }
    const sort = (sortRaw || (treatment ? 'price' : 'manual')) as HospitalSort;
    const regions = str(qs.region, 300)
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .slice(0, 10);
    // Public, not user-specific data: let the Next.js/CDN layer cache it briefly.
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30, stale-while-revalidate=300');
    return this.hospitals.list({
      q: str(qs.q),
      category: str(qs.category),
      treatment,
      regions,
      sort,
      limit: intParam(qs.limit, 20, 1, 100, 'limit'),
      offset: intParam(qs.offset, 0, 0, 1000, 'offset'),
    });
  }

  @Get(':id')
  async detail(@Param('id') id: string, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30, stale-while-revalidate=300');
    return this.hospitals.detail(idParam(id));
  }

  @Get(':id/reviews')
  async reviewList(@Param('id') id: string, @Query() qs: Record<string, unknown>, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=30, stale-while-revalidate=300');
    return this.reviews.listForHospital(idParam(id), intParam(qs.limit, 20, 1, 50, 'limit'), intParam(qs.offset, 0, 0, 1000, 'offset'));
  }
}
