import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { plain, pointsVisible } from './legal.logic';

@Injectable()
export class LegalService {
  private readonly log = new Logger(LegalService.name);
  constructor(@Inject(DB) private readonly db: Db) {}

  /** bbs/register.php?view=terms: the two texts the admin keeps in the Gnuboard config. */
  async registerTerms() {
    try {
      const [rows] = (await this.db.query('SELECT cf_stipulation, cf_privacy FROM g5_config LIMIT 1')) as any;
      const r = (rows as any[])[0] ?? {};
      return { terms: plain(r.cf_stipulation), privacy: plain(r.cf_privacy) };
    } catch (e) {
      this.log.error(`register terms: ${(e as Error).message}`);
      return { terms: '', privacy: '' };
    }
  }

  pointsPolicy() { return { visible: pointsVisible() }; }
}
