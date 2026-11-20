import { Body, Controller, Get, HttpCode, Param, Post, Query, Req, Res, StreamableFile, UseGuards, Header } from '@nestjs/common';
import { AdminGuard, BookingsEnabledGuard, BookingsWriteEnabledGuard, CsrfGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { BookingsService } from './bookings.service';
import { BookingsWriteService } from './bookings.write.service';

const scopeOfReq = (req: ScopedAdminRequest) => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });
const actorOf = (req: ScopedAdminRequest) => ({ id: req.admin.id, name: req.admin.name });

/** Booking management. A super admin works on every hospital, a hospital account only on its own (404 for anything else). */
@Controller('v1/bookings')
export class BookingsController {
  constructor(private readonly bookings: BookingsService, private readonly writes: BookingsWriteService) {}

  @Get()
  @UseGuards(BookingsEnabledGuard, AdminGuard)
  list(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.bookings.list(scopeOfReq(req), query);
  }

  @Get('hospitals')
  @UseGuards(BookingsEnabledGuard, AdminGuard)
  hospitals(@Req() req: ScopedAdminRequest) {
    return this.bookings.hospitals(scopeOfReq(req));
  }

  /** The Excel file of the chosen hospitals and dates (visit day). */
  @Get('export')
  @UseGuards(BookingsEnabledGuard, AdminGuard)
  @Header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
  @Header('X-Content-Type-Options', 'nosniff')
  async export(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>, @Res({ passthrough: true }) res: { setHeader(k: string, v: string): void }) {
    const out = await this.bookings.exportXlsx(scopeOfReq(req), query);
    res.setHeader('Content-Disposition', `attachment; filename="${out.name}"`);
    return new StreamableFile(out.file);
  }

  @Get(':id')
  @UseGuards(BookingsEnabledGuard, AdminGuard)
  detail(@Req() req: ScopedAdminRequest, @Param('id') id: string) {
    return this.bookings.detail(scopeOfReq(req), id);
  }

  /** Guard order: write switch (404) -> CSRF (403 csrf) -> signed in / role (401, 403). */
  @Post(':id/doctor')
  @HttpCode(200)
  @UseGuards(BookingsWriteEnabledGuard, CsrfGuard, AdminGuard)
  assignDoctor(@Req() req: ScopedAdminRequest, @Param('id') id: string, @Body() body: unknown) {
    return this.writes.assignDoctor(actorOf(req), scopeOfReq(req), id, body);
  }

  @Post(':id/status')
  @HttpCode(200)
  @UseGuards(BookingsWriteEnabledGuard, CsrfGuard, AdminGuard)
  changeStatus(@Req() req: ScopedAdminRequest, @Param('id') id: string, @Body() body: unknown) {
    return this.writes.changeStatus(actorOf(req), scopeOfReq(req), id, body);
  }
}
