/** Pure helpers for 관리자 알림 (ports of includes/notification_helper.php, admin_notifications.php, admin_fcm.php). */

/** $DEFAULT_NOTIFICATIONS: what an admin without an explicit `permissions.notifications` list receives. */
export const DEFAULT_NOTIFICATIONS = [
  'hospital_request', 'ad_request', 'review_new', 'consultation_new', 'appointment_new',
  'review_pending', 'hospital_pending', 'ad_pending', 'test_fail', 'admin_created',
];

/** should_receive_notification(): `permissions` is the raw JSON column. */
export function shouldReceive(permissions: unknown, type: string): boolean {
  let perms: unknown = {};
  try { perms = typeof permissions === 'string' && permissions !== '' ? JSON.parse(permissions) : {}; } catch { perms = {}; }
  if (!perms || typeof perms !== 'object' || Array.isArray(perms)) perms = {};
  const notifications = (perms as Record<string, unknown>).notifications;
  if (notifications === undefined || notifications === null) return DEFAULT_NOTIFICATIONS.includes(type);
  return Array.isArray(notifications) ? notifications.includes(type) : false;
}

/** PHP htmlspecialchars() (ENT_QUOTES): the admin message is stored escaped, like the old page did. */
export function escapeHtml(v: unknown): string {
  return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

export function normalizePlatform(p: unknown): 'android' | 'ios' | 'web' {
  const v = String(p ?? '').trim().toLowerCase();
  return v === 'ios' || v === 'web' ? v : 'android';
}

/** send_fcm_v1_target(): the HTTP v1 message body. */
export function buildFcmMessage(token: string, platform: unknown, title: string, body: string, link: string | null, adminId: number) {
  const p = normalizePlatform(platform);
  const data: Record<string, string> = { type: 'admin_notification', admin_id: String(adminId) };
  if (link) { data.webview_url = link; data.url = link; }
  data.click_action = 'FLUTTER_NOTIFICATION_CLICK';
  data.source = 'gangnamunni_admin';
  const message: Record<string, unknown> = { token, notification: { title, body } };
  if (p === 'android') message.android = { priority: 'high', notification: { sound: 'default', channel_id: 'notification' } };
  else if (p === 'ios') message.apns = { payload: { aps: { sound: 'default', badge: 1 } } };
  message.data = data;
  return { message };
}

/** Tokens FCM reports as dead are switched off (404 or one of these error codes). */
const DEAD_CODES = ['UNREGISTERED', 'InvalidRegistration', 'NotRegistered', 'MismatchSenderId'];
export function isDeadToken(httpCode: number, response: any): boolean {
  const code = response?.error?.details?.[0]?.errorCode ?? '';
  return httpCode === 404 || DEAD_CODES.includes(code);
}

export const b64url = (b: Buffer | string) => Buffer.from(b).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
