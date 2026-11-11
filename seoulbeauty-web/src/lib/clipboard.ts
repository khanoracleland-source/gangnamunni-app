/** Copies text to the clipboard (async API first, hidden-textarea fallback for non-secure contexts / old WebViews). Resolves to whether it worked. */
export async function copyText(text: string): Promise<boolean> {
  try {
    if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); return true; }
  } catch { /* fall through */ }
  try {
    const ta = document.createElement("textarea");
    ta.value = text; ta.setAttribute("readonly", ""); ta.style.position = "fixed"; ta.style.opacity = "0";
    document.body.appendChild(ta); ta.select();
    const ok = document.execCommand("copy");
    document.body.removeChild(ta);
    return ok;
  } catch { return false; }
}

/**
 * The link to share for a page. The app's WebView runs on hms.seoulbeauty.app, but a shared link must always be on the public
 * domain (seoulbeauty.app serves the same pages), so the app host is swapped; path, query and hash are kept.
 */
export function shareUrl(href: string = window.location.href): string {
  return href.replace(/^https?:\/\/hms\.seoulbeauty\.app(?=[:/?#]|$)/i, "https://seoulbeauty.app");
}
