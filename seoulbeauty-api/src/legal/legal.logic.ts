/** Gnuboard get_text(): the admin-entered text is shown escaped; the page renders it as plain text (white-space: pre-wrap). */
export const plain = (v: unknown): string => (v == null ? '' : String(v).replace(/\r\n?/g, '\n'));

/** The points menu switch of the PHP site (includes/points_pause.php POINTS_MENU_HIDDEN, inverted); same env as /v1/mypage. */
export const pointsVisible = (): boolean => process.env.MYPAGE_POINTS_VISIBLE === 'true';
