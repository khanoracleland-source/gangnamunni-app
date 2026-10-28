/** Pure helpers for faq.php / includes/faq_categories.php. */

export const FAQ_CATEGORIES = ['결제', '계정', '예약', '일반', '포인트', '후기'];

/** Fixed tabs first, then any legacy category found in the active rows (never dropped). */
export function faqCategories(rowCategories: unknown[]): string[] {
  const cats = [...FAQ_CATEGORIES];
  for (const c of rowCategories) {
    const t = String(c ?? '').trim();
    if (t !== '' && !cats.includes(t)) cats.push(t);
  }
  return cats;
}

/** An empty category is filed under 일반. */
export function faqCategoryOf(c: unknown): string {
  const t = String(c ?? '').trim();
  return t === '' ? '일반' : t;
}
