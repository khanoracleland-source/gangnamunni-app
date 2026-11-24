import { BadRequestException, Body, Controller, Delete, Get, HttpCode, Param, Post, Put, Query, Req, UseGuards } from '@nestjs/common';
import { AdminRequest, BannersEnabledGuard, BannersWriteEnabledGuard, CsrfGuard, SuperAdminGuard } from '../admin-auth/admin.guard';
import { FieldErrors, normalizeBannerQuery, parseBannerId, parseHomeSwitch, parseRevision, parseScroll } from './banners.logic';
import { BannersService } from './banners.service';

function revisionOf(body: unknown): string {
  const revision = parseRevision(body);
  if (!revision) throw new BadRequestException({ error: 'invalid', fields: { revision: 'required' } });
  return revision;
}

function checked<T>(parsed: { value: T; errors: FieldErrors }): T {
  if (Object.keys(parsed.errors).length > 0) throw new BadRequestException({ error: 'invalid', fields: parsed.errors });
  return parsed.value;
}

/**
 * Banners of the home page and my page. Super admin only. Guard order: switch (404) -> CSRF on writes (403 csrf) -> signed in / super admin (401, 403).
 * The fixed path `scroll` is declared before any `:id` route so it is never read as an id.
 */
@Controller('v1/banners')
export class BannersController {
  constructor(private readonly banners: BannersService) {}

  @Get()
  @UseGuards(BannersEnabledGuard, SuperAdminGuard)
  list(@Query() query: Record<string, unknown>) {
    return this.banners.list(normalizeBannerQuery(query));
  }

  @Get('scroll')
  @UseGuards(BannersEnabledGuard, SuperAdminGuard)
  scroll() {
    return this.banners.scroll();
  }

  /** Body { enabled: boolean, intervalSeconds: 1..60 }. */
  @Put('scroll')
  @HttpCode(200)
  @UseGuards(BannersWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
  saveScroll(@Req() req: AdminRequest, @Body() body: unknown) {
    return this.banners.saveScroll(req.admin.id, checked(parseScroll(body)));
  }

  /** Body { enabled: boolean }: shows or hides the banner on the home page. */
  @Post(':id/home')
  @HttpCode(200)
  @UseGuards(BannersWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
  home(@Req() req: AdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    const enabled = parseHomeSwitch(body);
    if (enabled === null) throw new BadRequestException({ error: 'invalid', fields: { enabled: 'invalid' } });
    return this.banners.setHome(req.admin.id, parseBannerId(rawId), enabled);
  }

  /** Deletes one banner for good; body { revision }. */
  @Delete(':id')
  @HttpCode(200)
  @UseGuards(BannersWriteEnabledGuard, CsrfGuard, SuperAdminGuard)
  remove(@Req() req: AdminRequest, @Param('id') rawId: string, @Body() body: unknown) {
    return this.banners.remove(req.admin.id, parseBannerId(rawId), revisionOf(body));
  }
}
