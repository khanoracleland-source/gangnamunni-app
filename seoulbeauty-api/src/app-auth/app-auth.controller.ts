import { Body, Controller, Get, HttpCode, HttpException, NotFoundException, Post, Query, Req, Res } from '@nestjs/common';
import type { Request, Response } from 'express';
import { isDeviceId, safeLandUrl, str } from './app-auth.logic';
import { AppAuthService } from './app-auth.service';

const bad = (error: string) => new HttpException({ success: false, error }, 400);

/**
 * The patient app's token endpoints (APP_AUTH_NEST=1; otherwise 404 and the app's PHP endpoints are the only ones). Called by the
 * app's own HTTP client, not by a page: no cookies are needed except for bootstrap and land, which the app's WebView opens.
 */
@Controller('v1/app-auth')
export class AppAuthController {
  constructor(private readonly app: AppAuthService) {}

  private open(res: Response): void {
    if (!this.app.enabled()) throw new NotFoundException();
    res.setHeader('Cache-Control', 'no-store');
  }

  @Post('exchange')
  @HttpCode(200)
  exchange(@Body() body: Record<string, unknown>, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    this.open(res);
    const code = str(body, 'code'), deviceId = str(body, 'device_id');
    if (code === '' || !isDeviceId(deviceId)) throw bad('code and device_id are required');
    return this.app.exchange(code, deviceId, req.ip || '');
  }

  @Post('verify')
  @HttpCode(200)
  verify(@Body() body: Record<string, unknown>, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    this.open(res);
    const m = /Bearer\s+(\S+)/.exec(String(req.headers.authorization || ''));
    const token = m ? m[1] : str(body, 'access_token'), deviceId = str(body, 'device_id');
    if (token === '' || !isDeviceId(deviceId)) throw bad('access_token and device_id are required');
    return this.app.verify(token, deviceId, req.ip || '');
  }

  @Post('refresh')
  @HttpCode(200)
  refresh(@Body() body: Record<string, unknown>, @Req() req: Request, @Res({ passthrough: true }) res: Response) {
    this.open(res);
    const mbId = str(body, 'mb_id'), token = str(body, 'refresh_token'), deviceId = str(body, 'device_id');
    if (mbId === '' || token === '' || !isDeviceId(deviceId)) throw bad('mb_id, refresh_token and device_id are required');
    return this.app.refresh(mbId, token, deviceId, req.ip || '');
  }

  @Post('logout')
  @HttpCode(200)
  logout(@Body() body: Record<string, unknown>, @Res({ passthrough: true }) res: Response) {
    this.open(res);
    return this.app.logout({ mbId: str(body, 'mb_id'), deviceId: str(body, 'device_id'), accessToken: str(body, 'access_token'), refreshToken: str(body, 'refresh_token') });
  }

  /** From the app's WebView only: the custom header forces a CORS preflight on any other origin, and none is answered. */
  @Post('bootstrap')
  @HttpCode(200)
  bootstrap(@Req() req: Request, @Res({ passthrough: true }) res: Response) {
    this.open(res);
    if (req.headers['x-native-auth'] !== '1') throw bad('invalid_request');
    return this.app.bootstrap(req.headers.cookie, req.ip || '');
  }

  @Get('land')
  async land(@Query('url') url: unknown, @Req() req: Request, @Res() res: Response) {
    this.open(res);
    for (const c of await this.app.land(req.headers.cookie, String(req.headers['user-agent'] || ''))) res.append('Set-Cookie', c);
    res.redirect(302, safeLandUrl(url));
  }
}
