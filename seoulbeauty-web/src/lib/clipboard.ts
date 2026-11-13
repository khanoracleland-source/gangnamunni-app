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
 * The link to share for a page: always on the public domain (seoulbeauty.app), which is also the host the app's verified links
 * (App Links / Universal Links, /next/*) are registered for. App 2.2.0+ already runs there; older app builds (2.1.x) still run
 * on hms.seoulbeauty.app, so that host is swapped (recognised only, never requested); path, query and hash are kept.
 */
export function shareUrl(href: string = window.location.href): string {
  return href.replace(/^https?:\/\/hms\.seoulbeauty\.app(?=[:/?#]|$)/i, "https://seoulbeauty.app");
}
