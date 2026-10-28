import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { faqCategories, faqCategoryOf } from './faq.logic';

@Injectable()
export class FaqService {
  private readonly log = new Logger(FaqService.name);
  constructor(@Inject(DB) private readonly db: Db) {}

  /** faq.php: active entries in admin order, grouped client-side by `category`. */
  async list() {
    let rows: any[] = [];
    try {
      const [r] = (await this.db.query('SELECT id, category, question, answer FROM gangnamunni_faq WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')) as any;
      rows = r as any[];
    } catch (e) { this.log.error(`faq: ${(e as Error).message}`); }
    const items = rows.map((r) => ({ id: Number(r.id), category: faqCategoryOf(r.category), question: String(r.question ?? ''), answer: String(r.answer ?? '') }));
    return { categories: faqCategories(items.map((i) => i.category)), items };
  }
}
