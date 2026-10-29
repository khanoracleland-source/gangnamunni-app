import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { AGREEMENT_TYPES, AGREEMENT_VERSION, plain, pointsVisible } from './legal.logic';

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

  /** terms_of_service.php POST agree: one consent row per document (INSERT IGNORE: a repeat is not an error). */
  async agree(mbId: string, ip: string, userAgent: string) {
    for (const type of AGREEMENT_TYPES) {
      await this.db.query(
        'INSERT IGNORE INTO gangnamunni_user_agreements (user_id, agreement_type, agreement_version, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
        [mbId, type, AGREEMENT_VERSION, ip.slice(0, 45), userAgent.slice(0, 255)]);
    }
    return { success: true };
  }

  pointsPolicy() { return { visible: pointsVisible() }; }
}
