import * as path from 'path';
import { buildStoredPath, checkImage, detectImageKind, extensionOf, summarizeImages } from './hospitals.images.logic';
import { mapUploadError } from './hospitals.images.upload';
import { BadRequestException, PayloadTooLargeException } from '@nestjs/common';

const jpeg = (size = 64) => Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(Math.max(0, size - 4), 1)]);
const png = (size = 64) => Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(Math.max(0, size - 8), 2)]);
const webp = (size = 64) => Buffer.concat([Buffer.from('RIFF', 'latin1'), Buffer.from([1, 2, 3, 4]), Buffer.from('WEBP', 'latin1'), Buffer.alloc(Math.max(0, size - 12), 3)]);

describe('hospital image upload: pure parts', () => {
  it('detects the real type from the first bytes', () => {
    expect(detectImageKind(jpeg())).toBe('jpg');
    expect(detectImageKind(png())).toBe('png');
    expect(detectImageKind(webp())).toBe('webp');
    expect(detectImageKind(Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"></svg>'))).toBeNull();
    expect(detectImageKind(Buffer.from('GIF89a......'))).toBeNull();
    expect(detectImageKind(Buffer.from('RIFF....WAVE'))).toBeNull();
    expect(detectImageKind(Buffer.from([0xff, 0xd8]))).toBeNull();
    expect(detectImageKind(Buffer.alloc(0))).toBeNull();
  });

  it('extension is the lower-cased text after the last dot', () => {
    expect(extensionOf('A.JPG')).toBe('jpg');
    expect(extensionOf('a.b.png')).toBe('png');
    expect(extensionOf('noext')).toBe('');
    expect(extensionOf(undefined)).toBe('');
  });

  it('main: jpg/jpeg/png only, 5 MB; interior: also webp, 10 MB; the extension must agree with the real type', () => {
    expect(checkImage('main', 'a.jpg', 10, jpeg())).toEqual({ ok: true, kind: 'jpg' });
    expect(checkImage('main', 'a.JPEG', 10, jpeg())).toEqual({ ok: true, kind: 'jpg' });
    expect(checkImage('main', 'a.png', 10, png())).toEqual({ ok: true, kind: 'png' });
    expect(checkImage('main', 'a.webp', 10, webp())).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('interior', 'a.webp', 10, webp())).toEqual({ ok: true, kind: 'webp' });
    expect(checkImage('interior', 'a.jpg', 10, png())).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('interior', 'a.png', 10, jpeg())).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('interior', 'a.gif', 10, jpeg())).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('interior', 'a.svg', 10, Buffer.from('<svg/>'))).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('interior', 'a.jpg', 10, Buffer.from('not an image at all'))).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('interior', '', 10, jpeg())).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('main', 'a.jpg', 0, jpeg())).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('main', 'a.jpg', 5 * 1024 * 1024, jpeg())).toMatchObject({ ok: true });
    expect(checkImage('main', 'a.jpg', 5 * 1024 * 1024 + 1, jpeg())).toEqual({ ok: false, error: 'too_large' });
    expect(checkImage('interior', 'a.jpg', 10 * 1024 * 1024, jpeg())).toMatchObject({ ok: true });
    expect(checkImage('interior', 'a.jpg', 10 * 1024 * 1024 + 1, jpeg())).toEqual({ ok: false, error: 'too_large' });
  });

  it('storage path: <root>/uploads/hospitals/<id>/<field>_<32 hex>.<ext>, url relative', () => {
    const hex = 'ab'.repeat(16);
    const p = buildStoredPath('/srv/gangnamunni', 12, 'main', hex, 'png');
    expect(p.url).toBe(`uploads/hospitals/12/main_${hex}.png`);
    expect(p.dir).toBe(path.resolve('/srv/gangnamunni/uploads/hospitals/12'));
    expect(p.absolute).toBe(path.resolve(`/srv/gangnamunni/uploads/hospitals/12/main_${hex}.png`));
    expect(buildStoredPath('/srv/gangnamunni/', 3, 'interior', hex, 'webp').url).toBe(`uploads/hospitals/3/interior_${hex}.webp`);
  });

  it.each([
    ['id 0', 0, 'ab'.repeat(16)],
    ['negative id', -1, 'ab'.repeat(16)],
    ['fractional id', 1.5, 'ab'.repeat(16)],
    ['NaN id', NaN, 'ab'.repeat(16)],
    ['traversal in the token', 12, '../../../../etc/passwd/........'],
    ['slash in the token', 12, 'a/'.repeat(16)],
    ['short token', 12, 'abcd'],
    ['upper-case token', 12, 'AB'.repeat(16)],
  ])('storage path refuses %s', (_n, id, hex) => {
    expect(() => buildStoredPath('/srv/gangnamunni', id as number, 'main', hex as string, 'jpg')).toThrow();
  });

  it('summary: active main, active interior count (main and logo excluded), order kept', () => {
    const s = summarizeImages([
      { id: 1, image_url: 'uploads/hospitals/1/old.jpg', image_type: 'main', sort_order: 0, is_active: 0 },
      { id: 2, image_url: 'uploads/hospitals/1/new.jpg', image_type: 'main', sort_order: 0, is_active: 1 },
      { id: 3, image_url: 'l.png', image_type: 'logo', sort_order: 1, is_active: 1 },
      { id: 4, image_url: 'a.jpg', image_type: 'interior', sort_order: 2, is_active: 1 },
      { id: 5, image_url: 'b.jpg', image_type: 'detail', sort_order: 3, is_active: 1 },
      { id: 6, image_url: 'c.jpg', image_type: 'interior', sort_order: 4, is_active: 0 },
    ]);
    expect(s.mainUrl).toBe('uploads/hospitals/1/new.jpg');
    expect(s.detailCount).toBe(2);
    expect(s.detailLimit).toBe(10);
    expect(s.items.map((i) => i.id)).toEqual([1, 2, 3, 4, 5, 6]);
    expect(s.items[0]).toEqual({ id: 1, url: 'uploads/hospitals/1/old.jpg', type: 'main', sortOrder: 0, isActive: false });
    expect(summarizeImages([]).mainUrl).toBeNull();
  });

  it('parser errors become the documented 400 bodies', () => {
    const body = (e: unknown) => (e as BadRequestException).getResponse();
    expect(body(mapUploadError(new PayloadTooLargeException('File too large'), { __imageField: 'main' }))).toEqual({ error: 'too_large', field: 'main' });
    expect(body(mapUploadError(new PayloadTooLargeException('File too large'), {}))).toEqual({ error: 'too_large', field: 'interior' });
    expect(body(mapUploadError(new BadRequestException('Too many files'), {}))).toEqual({ error: 'too_many', field: 'interior' });
    expect(body(mapUploadError(new BadRequestException('Unexpected file field - main'), {}))).toEqual({ error: 'too_many', field: 'main' });
    expect(body(mapUploadError(new BadRequestException('Multipart: Malformed part header'), {}))).toEqual({ error: 'upload_failed', field: 'interior' });
    const other = new Error('x');
    expect(mapUploadError(other, {})).toBe(other);
  });
});
