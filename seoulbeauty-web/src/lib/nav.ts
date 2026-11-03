"use client";
/**
 * In-app (client-side) navigations since this document was loaded. `document.referrer` only describes the first full page load,
 * so after "Google -> site -> bell" it still says Google; this counter says the visitor has moved inside the site since then.
 */
let spaNavs = 0;
export const countSpaNav = (): void => { spaNavs++; };
export const hasSpaHistory = (): boolean => spaNavs > 0;
