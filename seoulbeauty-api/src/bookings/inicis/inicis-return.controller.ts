import { Body, Controller, Header, Post, Res } from '@nestjs/common';
import { Throttle } from '@nestjs/throttler';
import type { Response } from 'express';
import { InicisPaymentService } from './inicis-payment.service';

/**
 * P_NEXT_URL. Inicis makes the customer's browser POST the authentication result here, cross-site, so there is no session
 * cookie and no X-Requested-With: the guard is the ledger (order by P_OID, amount, MID) and Inicis' own approval call.
 * The answer is always a 303 back to the site; no Inicis text is ever echoed.
 */
@Controller('v1/payments/inicis')
export class InicisReturnController {
  constructor(private readonly inicis: InicisPaymentService) {}

  /** Stricter than the global limit: this public endpoint writes evidence rows and calls Inicis. A real customer posts once per payment. */
  @Throttle({ default: { limit: Number(process.env.INICIS_RETURN_RATE_PER_MIN || 20), ttl: 60_000 } })
  @Post('return')
  @Header('Cache-Control', 'no-store')
  async ret(@Body() body: Record<string, unknown>, @Res() res: Response) {
    const to = await this.inicis.handleReturn(body ?? {});
    res.redirect(303, to);
  }
}
