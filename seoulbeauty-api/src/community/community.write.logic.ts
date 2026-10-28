/** Pure helpers for writing: post validation, upload sniffing, report reasons (ports of community_write.php / report_reasons.php). */
import { randomBytes } from 'node:crypto';

export const COMMUNITY_CATEGORIES = ['일상', '시술/수술고민', '의사Q&A', '정보공유', '시술 추천', '병원 추천', '기타'];
export const MAX_PHOTOS = 4;
export const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

export function categoriesFor(existing?: string | null): string[] {
  return existing && !COMMUNITY_CATEGORIES.includes(existing) ? [...COMMUNITY_CATEGORIES, existing] : COMMUNITY_CATEGORIES;
}

/** First error message (PHP wording) or null. Lengths count characters like mb_strlen. */
export function validatePost(category: string, title: string, content: string, allowed: string[]): string | null {
  if (!title) return '제목을 입력해주세요.';
  if (Array.from(title).length > 200) return '제목은 200자 이내로 입력해주세요.';
  if (Array.from(content).length < 10) return '내용은 최소 10자 이상 입력해주세요.';
  if (!allowed.includes(category)) return '카테고리를 선택해주세요.';
  return null;
}

/** Image type from the first bytes (never trust the client's mime/extension). SVG is not accepted. */
export function sniffImage(buf: Buffer): { mime: string; ext: string } | null {
  if (buf.length >= 3 && buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return { mime: 'image/jpeg', ext: 'jpg' };
  if (buf.length >= 8 && buf.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return { mime: 'image/png', ext: 'png' };
  if (buf.length >= 6 && ['GIF87a', 'GIF89a'].includes(buf.subarray(0, 6).toString('latin1'))) return { mime: 'image/gif', ext: 'gif' };
  if (buf.length >= 12 && buf.subarray(0, 4).toString('latin1') === 'RIFF' && buf.subarray(8, 12).toString('latin1') === 'WEBP') return { mime: 'image/webp', ext: 'webp' };
  return null;
}

export function uploadName(index: number, ext: string, now = Date.now()): string {
  return `community_${Math.floor(now / 1000)}_${index}_${randomBytes(4).toString('hex')}.${ext}`;
}

/** includes/report_reasons.php ugc_report_reason_options(): id -> [label, enum stored in `reason`]. */
export const REPORT_REASONS: Record<string, [string, string]> = {
  spam: ['영리성 홍보 목적', 'spam'], sexual: ['음란성/선정성', 'sexual'], harassment: ['욕설/인신공격', 'harassment'],
  privacy: ['개인정보노출', 'other'], repeat: ['같은 내용 반복게시', 'spam'], other: ['기타', 'other'],
};

/** ugc_encode_report_reasons(): [reason enum of the first choice, JSON stored in reason_detail] or null when invalid. */
export function encodeReportReasons(reasons: unknown, detail: unknown): { reason: string; reasonDetail: string } | null {
  if (!Array.isArray(reasons) || !reasons.length || reasons.length > 6) return null;
  const d = detail == null ? '' : detail;
  if (typeof d !== 'string' || Array.from(d).length > 1000) return null;
  if (!reasons.every((r) => typeof r === 'string' && Object.prototype.hasOwnProperty.call(REPORT_REASONS, r))) return null;
  const ids = [...new Set(reasons as string[])];
  return {
    reason: REPORT_REASONS[ids[0]][1],
    reasonDetail: JSON.stringify({ version: 1, reasons: ids, detail: ids.includes('other') ? d.trim() : '' }),
  };
}
