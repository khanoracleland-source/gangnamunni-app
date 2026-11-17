import * as path from 'path';
import {
  buildImages, buildTreatmentImagePath, canReviewImages, checkTreatmentImage, detectTreatmentImageKind, MAX_TREATMENT_IMAGE_BYTES, parseCaption, parseImageUrl, parseMoveBody, parseReviewBody,
  removablePath, reviewerName, moveApproved,
} from './hospitals.treatments.images.logic';

const jpg = Buffer.from([0xff, 0xd8, 0xff, 0xe0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const png = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0, 0, 0, 0, 0, 0, 0, 0]);
const gif = Buffer.from('GIF89a\0\0\0\0\0\0\0\0\0\0', 'latin1');
const gif87 = Buffer.from('GIF87a\0\0\0\0\0\0\0\0\0\0', 'latin1');
const webp = Buffer.concat([Buffer.from('RIFF', 'latin1'), Buffer.from([0, 0, 0, 0]), Buffer.from('WEBP', 'latin1')]);

describe('treatment images logic', () => {
  it('detects the real type from the first bytes (GIF added to JPEG / PNG / WebP)', () => {
    expect(detectTreatmentImageKind(jpg)).toBe('jpg');
    expect(detectTreatmentImageKind(png)).toBe('png');
    expect(detectTreatmentImageKind(gif)).toBe('gif');
    expect(detectTreatmentImageKind(gif87)).toBe('gif');
    expect(detectTreatmentImageKind(webp)).toBe('webp');
    expect(detectTreatmentImageKind(Buffer.from('<svg xmlns=', 'latin1'))).toBeNull();
    expect(detectTreatmentImageKind(Buffer.from('GIF', 'latin1'))).toBeNull();
    expect(detectTreatmentImageKind(Buffer.alloc(0))).toBeNull();
  });

  it('checkTreatmentImage: extension whitelist, real type, agreement, size', () => {
    expect(checkTreatmentImage('a.jpg', 100, jpg)).toEqual({ ok: true, kind: 'jpg' });
    expect(checkTreatmentImage('A.JPEG', 100, jpg)).toEqual({ ok: true, kind: 'jpg' });
    expect(checkTreatmentImage('a.png', 100, png)).toEqual({ ok: true, kind: 'png' });
    expect(checkTreatmentImage('a.gif', 100, gif)).toEqual({ ok: true, kind: 'gif' });
    expect(checkTreatmentImage('a.webp', 100, webp)).toEqual({ ok: true, kind: 'webp' });
    for (const [name, head] of [['a.svg', jpg], ['a', jpg], ['a.jpg.php', jpg], ['a.jpg', png], ['a.gif', jpg], ['a.png', Buffer.from('hello world!!!!!')], ['', jpg]] as const) {
      expect(checkTreatmentImage(name, 100, head)).toEqual({ ok: false, error: 'invalid_image' });
    }
    expect(checkTreatmentImage('a.jpg', 0, jpg)).toEqual({ ok: false, error: 'invalid_image' });
    expect(checkTreatmentImage('a.jpg', MAX_TREATMENT_IMAGE_BYTES, jpg).ok).toBe(true);
    expect(checkTreatmentImage('a.jpg', MAX_TREATMENT_IMAGE_BYTES + 1, jpg)).toEqual({ ok: false, error: 'too_large' });
  });

  it('parseImageUrl: blank = none, http(s) only, 2048 bytes, no white space', () => {
    expect(parseImageUrl(undefined)).toEqual({ ok: true, url: null });
    expect(parseImageUrl(null)).toEqual({ ok: true, url: null });
    expect(parseImageUrl('   ')).toEqual({ ok: true, url: null });
    expect(parseImageUrl('  https://cdn.example.com/a.jpg ')).toEqual({ ok: true, url: 'https://cdn.example.com/a.jpg' });
    expect(parseImageUrl('http://example.com/a b.jpg')).toEqual({ ok: false });
    for (const bad of ['ftp://example.com/a.jpg', 'javascript:alert(1)', 'data:image/png;base64,AAAA', '//example.com/a.jpg', 'example.com/a.jpg', 'https://', 'https:///a.jpg', 'uploads/treatments/a.jpg', 12, {}, ['https://a.com']]) {
      expect(parseImageUrl(bad)).toEqual({ ok: false });
    }
    const base = 'https://example.com/';
    expect(parseImageUrl(base + 'a'.repeat(2048 - base.length))).toMatchObject({ ok: true });
    expect(parseImageUrl(base + 'a'.repeat(2049 - base.length))).toEqual({ ok: false });
  });

  it('parseCaption: trimmed, 200 code points, text only', () => {
    expect(parseCaption(undefined)).toEqual({ ok: true, caption: '' });
    expect(parseCaption(null)).toEqual({ ok: true, caption: '' });
    expect(parseCaption('  시술 전  ')).toEqual({ ok: true, caption: '시술 전' });
    expect(parseCaption('가'.repeat(200))).toMatchObject({ ok: true });
    expect(parseCaption('😀'.repeat(200))).toMatchObject({ ok: true });
    expect(parseCaption('가'.repeat(201))).toEqual({ ok: false, error: 'caption_too_long' });
    expect(parseCaption(['a', 'b'])).toEqual({ ok: false, error: 'upload_failed' });
    expect(parseCaption(5)).toEqual({ ok: false, error: 'upload_failed' });
  });

  it('buildTreatmentImagePath: <root>/uploads/treatments/treat_<time>_<8 hex>.<ext>, nothing from the client', () => {
    const p = buildTreatmentImagePath('/srv/g', 1800000000, 'a1b2c3d4', 'png');
    expect(p).toEqual({ url: 'uploads/treatments/treat_1800000000_a1b2c3d4.png', absolute: '/srv/g/uploads/treatments/treat_1800000000_a1b2c3d4.png', dir: '/srv/g/uploads/treatments' });
    expect(() => buildTreatmentImagePath('/srv/g', 0, 'a1b2c3d4', 'png')).toThrow();
    expect(() => buildTreatmentImagePath('/srv/g', 1.5, 'a1b2c3d4', 'png')).toThrow();
    for (const hex of ['../../etc', 'A1B2C3D4', 'a1b2c3d', 'a1b2c3d45', '']) expect(() => buildTreatmentImagePath('/srv/g', 1, hex, 'jpg')).toThrow();
  });

  it('removablePath only accepts our own uploads inside the root', () => {
    const root = '/srv/g';
    expect(removablePath(root, 'uploads/treatments/treat_1_a1b2c3d4.jpg')).toBe(path.resolve(root, 'uploads/treatments/treat_1_a1b2c3d4.jpg'));
    for (const url of ['', 'https://cdn.example.com/uploads/treatments/a.jpg', '/etc/passwd', 'uploads/hospitals/1/a.jpg', 'uploads/treatments/../hospitals/1/a.jpg', 'uploads/treatments/..', 'uploads/treatments/', 'uploads/treatments', 'uploads/treatments\\a.jpg', 'uploads/treatments/a\0.jpg']) {
      expect(removablePath(root, url)).toBeNull();
    }
    expect(removablePath('', 'uploads/treatments/a.jpg')).toBeNull();
    expect(removablePath('relative/root', 'uploads/treatments/a.jpg')).toBeNull();
  });

  it('parseMoveBody / moveApproved', () => {
    expect(parseMoveBody({ direction: 'up' })).toEqual({ ok: true, direction: 'up' });
    expect(parseMoveBody({ direction: 'down' })).toEqual({ ok: true, direction: 'down' });
    for (const bad of [{}, null, [], 'up', { direction: 'left' }, { direction: 1 }, { direction: 'UP' }]) expect(parseMoveBody(bad)).toEqual({ ok: false, body: { error: 'invalid_field', field: 'direction' } });
    const R = (...p: [number, string][]) => p.map(([id, status]) => ({ id, status }));
    const mixed = R([1, 'approved'], [2, 'pending'], [3, 'approved'], [4, 'rejected'], [5, 'approved']);
    expect(moveApproved(mixed, 3, 'up')).toEqual({ ok: true, order: [3, 2, 1, 4, 5] });
    expect(moveApproved(mixed, 3, 'down')).toEqual({ ok: true, order: [1, 2, 5, 4, 3] });
    expect(moveApproved(mixed, 5, 'up')).toEqual({ ok: true, order: [1, 2, 5, 4, 3] });
    expect(moveApproved(mixed, 1, 'up')).toEqual({ ok: false, error: 'cannot_move' });
    expect(moveApproved(mixed, 5, 'down')).toEqual({ ok: false, error: 'cannot_move' });
    expect(moveApproved(mixed, 2, 'up')).toEqual({ ok: false, error: 'not_approved' });
    expect(moveApproved(mixed, 4, 'down')).toEqual({ ok: false, error: 'not_approved' });
    expect(moveApproved(mixed, 9, 'down')).toEqual({ ok: false, error: 'not_found' });
    expect(moveApproved(R([1, 'approved']), 1, 'up')).toEqual({ ok: false, error: 'cannot_move' });
  });

  it('parseReviewBody: decision, reason required on reject, 1000 code points', () => {
    expect(parseReviewBody({ decision: 'approve' })).toEqual({ ok: true, decision: 'approve', reason: '' });
    expect(parseReviewBody({ decision: 'reject', reason: '  흐릿함  ' })).toEqual({ ok: true, decision: 'reject', reason: '흐릿함' });
    expect(parseReviewBody({ decision: 'reject', reason: '가'.repeat(1000) })).toMatchObject({ ok: true });
    expect(parseReviewBody({ decision: 'reject' })).toEqual({ ok: false, body: { error: 'reason_required' } });
    expect(parseReviewBody({ decision: 'reject', reason: '   ' })).toEqual({ ok: false, body: { error: 'reason_required' } });
    expect(parseReviewBody({ decision: 'reject', reason: null })).toEqual({ ok: false, body: { error: 'reason_required' } });
    expect(parseReviewBody({ decision: 'reject', reason: '가'.repeat(1001) })).toEqual({ ok: false, body: { error: 'reason_too_long' } });
    expect(parseReviewBody({ decision: 'approve', reason: '가'.repeat(1001) })).toEqual({ ok: false, body: { error: 'reason_too_long' } });
    for (const bad of [{}, null, [], 'approve', { decision: 'maybe' }, { decision: true }]) expect(parseReviewBody(bad)).toEqual({ ok: false, body: { error: 'invalid_field', field: 'decision' } });
    expect(parseReviewBody({ decision: 'reject', reason: 5 })).toEqual({ ok: false, body: { error: 'invalid_field', field: 'reason' } });
  });

  it('reviewerName: at most 100 characters, never empty; only a super admin reviews', () => {
    expect(reviewerName('  김관리 ')).toBe('김관리');
    expect(reviewerName('')).toBe('super_admin');
    expect(Array.from(reviewerName('가'.repeat(150)))).toHaveLength(100);
    expect(canReviewImages('super_admin')).toBe(true);
    expect(canReviewImages('hospital_super_admin')).toBe(false);
    expect(canReviewImages('hospital_admin')).toBe(false);
  });

  it('buildImages: shape, unknown status = approved, blanks = null, order of the rows kept', () => {
    const rows = [
      { id: '3', image_url: 'uploads/treatments/a.jpg', caption: '전', sort_order: 0, approval_status: 'pending', uploaded_by: 'hospital', reject_reason: null, reviewed_at: null, reviewed_by: null },
      { id: 4, image_url: 'https://x.com/a.png', caption: null, sort_order: '1', approval_status: 'rejected', uploaded_by: 'admin', reject_reason: ' 흐림 ', reviewed_at: '2026-11-17 10:00:00', reviewed_by: '김관리' },
      { id: 5, image_url: 'u', caption: '', sort_order: 2, approval_status: null, uploaded_by: 'other', reject_reason: '', reviewed_at: '0000-00-00 00:00:00', reviewed_by: '' },
    ];
    expect(buildImages(rows)).toEqual([
      { id: 3, url: 'uploads/treatments/a.jpg', caption: '전', status: 'pending', uploadedBy: 'hospital', rejectReason: null, reviewedAt: null, reviewedBy: null, sortOrder: 0 },
      { id: 4, url: 'https://x.com/a.png', caption: '', status: 'rejected', uploadedBy: 'admin', rejectReason: '흐림', reviewedAt: '2026-11-17 10:00:00', reviewedBy: '김관리', sortOrder: 1 },
      { id: 5, url: 'u', caption: '', status: 'approved', uploadedBy: 'admin', rejectReason: null, reviewedAt: null, reviewedBy: null, sortOrder: 2 },
    ]);
    expect(buildImages([])).toEqual([]);
  });
});
