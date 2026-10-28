/** Pure helpers for review_detail.php. */

const ENTITIES: Record<string, string> = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#39;': "'", '&#039;': "'", '&nbsp;': ' ' };

/**
 * editor_content (Quill HTML) -> plain text. The page renders text only, so no markup can reach the browser
 * (PHP sanitised the HTML instead; formatting is the one thing given up).
 */
export function htmlToText(html: unknown): string {
  return String(html ?? '')
    .replace(/<\s*br\s*\/?>/gi, '\n')
    .replace(/<\/\s*(p|div|li|h[1-6])\s*>/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/&(?:amp|lt|gt|quot|nbsp|#39|#039);/g, (m) => ENTITIES[m] ?? m)
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

/** review_detail.php rating: clamp to 0..5 whole stars. */
export function starCount(v: unknown): number {
  return Math.max(0, Math.min(5, Math.trunc(Number(v) || 0)));
}
