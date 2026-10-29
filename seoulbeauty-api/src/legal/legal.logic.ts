/** terms_of_service.php: the three documents a member agrees to at once, and the version stored with the consent. */
export const AGREEMENT_TYPES = ['terms_of_service', 'privacy_policy', 'community_guidelines'] as const;
export const AGREEMENT_VERSION = '1.0';

/** Gnuboard get_text(): the admin-entered text is shown escaped; the page renders it as plain text (white-space: pre-wrap). */
export const plain = (v: unknown): string => (v == null ? '' : String(v).replace(/\r\n?/g, '\n'));

/** The points menu switch of the PHP site (includes/points_pause.php POINTS_MENU_HIDDEN, inverted); same env as /v1/mypage. */
export const pointsVisible = (): boolean => process.env.MYPAGE_POINTS_VISIBLE === 'true';
