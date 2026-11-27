/**
 * The reason column of a content report holds the app's JSON ({"version":1,"reasons":[..],"labels":[..],"detail":".."}).
 * For the admin screens that becomes plain text: the chosen labels, then the typed detail. Older rows hold plain text and pass through.
 */
export function reportDetailText(raw: unknown, max = 300): string {
  const text = raw == null ? '' : String(raw);
  const trimmed = text.trim();
  if (trimmed.startsWith('{')) {
    try {
      const o = JSON.parse(trimmed) as { labels?: unknown; detail?: unknown };
      if (o && typeof o === 'object' && Array.isArray(o.labels)) {
        const labels = o.labels.filter((x): x is string => typeof x === 'string' && x.trim() !== '').map((x) => x.trim());
        const detail = typeof o.detail === 'string' ? o.detail.trim() : '';
        return [labels.join(', '), detail].filter(Boolean).join(' — ').slice(0, max);
      }
    } catch { /* not JSON after all: shown as stored */ }
  }
  return text.slice(0, max);
}
