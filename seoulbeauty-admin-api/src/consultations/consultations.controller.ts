import { Controller, Get, Header, Param, Query, Req, Res, StreamableFile, UseGuards } from '@nestjs/common';
import { createReadStream } from 'fs';
import { AdminGuard, ConsultationsEnabledGuard, ScopedAdminRequest } from '../admin-auth/admin.guard';
import { ConsultationsService } from './consultations.service';

const scopeOfReq = (req: ScopedAdminRequest) => ({ scope: req.admin.scope, hospitalIds: req.admin.hospitalIds });

/** Consultation requests. A super admin sees every hospital, a hospital account only its own. */
@Controller('v1/consultations')
export class ConsultationsController {
  constructor(private readonly consultations: ConsultationsService) {}

  @Get()
  @UseGuards(ConsultationsEnabledGuard, AdminGuard)
  list(@Req() req: ScopedAdminRequest, @Query() query: Record<string, unknown>) {
    return this.consultations.list(scopeOfReq(req), query);
  }

  @Get('hospitals')
  @UseGuards(ConsultationsEnabledGuard, AdminGuard)
  hospitals(@Req() req: ScopedAdminRequest) {
    return this.consultations.hospitals(scopeOfReq(req));
  }

  @Get(':id')
  @UseGuards(ConsultationsEnabledGuard, AdminGuard)
  detail(@Req() req: ScopedAdminRequest, @Param('id') id: string) {
    return this.consultations.detail(scopeOfReq(req), id);
  }

  /** An attached photo. Never cached, never sniffed, and it cannot run anything even if opened on its own. */
  @Get(':id/photos/:index')
  @UseGuards(ConsultationsEnabledGuard, AdminGuard)
  @Header('X-Content-Type-Options', 'nosniff')
  @Header('Cache-Control', 'private, no-store')
  @Header('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox")
  async photo(@Req() req: ScopedAdminRequest, @Param('id') id: string, @Param('index') index: string, @Res({ passthrough: true }) res: { setHeader(k: string, v: string | number): void }) {
    const p = await this.consultations.photo(scopeOfReq(req), id, index);
    res.setHeader('Content-Type', p.type);
    res.setHeader('Content-Length', p.size);
    res.setHeader('Content-Disposition', `inline; filename="${p.name}"`);
    return new StreamableFile(createReadStream(p.file));
  }
}
