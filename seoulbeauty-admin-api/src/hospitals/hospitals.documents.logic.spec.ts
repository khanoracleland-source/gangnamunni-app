import * as path from 'path';
import { buildDetail } from './hospitals.logic';
import {
  buildDocumentPath, canWriteDocuments, checkDocument, detectDocumentKind, isRemovableStoredPath, lockDocumentsQuery, MAX_DOCUMENT_BYTES, parseDoc, storedValueOf, toDocuments,
  updateDocumentQuery,
} from './hospitals.documents.logic';
import { mapDocumentUploadError } from './hospitals.documents.upload';
import { BadRequestException, InternalServerErrorException, PayloadTooLargeException } from '@nestjs/common';

const jpeg = (size = 64) => Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(Math.max(0, size - 4), 1)]);
const png = (size = 64) => Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(Math.max(0, size - 8), 2)]);
const pdf = (size = 64) => Buffer.concat([Buffer.from('%PDF-1.7\n', 'latin1'), Buffer.alloc(Math.max(0, size - 9), 3)]);
const HEX = 'ab'.repeat(16);

describe('hospital documents: pure parts', () => {
  it('only the two exact words are documents', () => {
    expect(parseDoc('business')).toBe('business');
    expect(parseDoc('medical')).toBe('medical');
    for (const bad of ['Business', 'cert_business', 'business ', '', 'all', '__proto__', 'constructor', undefined, null, 1, ['business']]) expect(parseDoc(bad)).toBeNull();
  });

  it('real type from the first bytes: JPEG, PNG, PDF; everything else null', () => {
    expect(detectDocumentKind(jpeg())).toBe('jpg');
    expect(detectDocumentKind(png())).toBe('png');
    expect(detectDocumentKind(pdf())).toBe('pdf');
    expect(detectDocumentKind(Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"/>'))).toBeNull();
    expect(detectDocumentKind(Buffer.from('GIF89a......'))).toBeNull();
    expect(detectDocumentKind(Buffer.from('RIFF....WEBPVP8 '))).toBeNull();
    expect(detectDocumentKind(Buffer.from(' %PDF-1.4'))).toBeNull();
    expect(detectDocumentKind(Buffer.from('%PDF'))).toBeNull();
    expect(detectDocumentKind(Buffer.from([0xff, 0xd8]))).toBeNull();
    expect(detectDocumentKind(Buffer.alloc(0))).toBeNull();
  });

  it('size, extension whitelist and real type; the extension must agree with the real type', () => {
    expect(checkDocument('a.jpg', 10, jpeg())).toEqual({ ok: true, kind: 'jpg' });
    expect(checkDocument('A.JPEG', 10, jpeg())).toEqual({ ok: true, kind: 'jpg' });
    expect(checkDocument('a.png', 10, png())).toEqual({ ok: true, kind: 'png' });
    expect(checkDocument('a.PDF', 10, pdf())).toEqual({ ok: true, kind: 'pdf' });
    expect(checkDocument('a.jpg', 10, png())).toEqual({ ok: false, error: 'invalid_file' });
    expect(checkDocument('a.png', 10, pdf())).toEqual({ ok: false, error: 'invalid_file' });
    expect(checkDocument('a.pdf', 10, jpeg())).toEqual({ ok: false, error: 'invalid_file' });
    expect(checkDocument('a.pdf', 10, Buffer.from('plain text'))).toEqual({ ok: false, error: 'invalid_file' });
    for (const name of ['a.gif', 'a.webp', 'a.svg', 'a.php', 'a.jpg.php', 'a.html', 'a', '', undefined, 'a.']) expect(checkDocument(name, 10, jpeg())).toEqual({ ok: false, error: 'invalid_file' });
    expect(checkDocument('a.jpg', 0, jpeg())).toEqual({ ok: false, error: 'invalid_file' });
    expect(checkDocument('a.jpg', NaN, jpeg())).toEqual({ ok: false, error: 'invalid_file' });
    expect(checkDocument('a.pdf', MAX_DOCUMENT_BYTES, pdf())).toMatchObject({ ok: true });
    expect(checkDocument('a.pdf', MAX_DOCUMENT_BYTES + 1, pdf())).toEqual({ ok: false, error: 'too_large' });
  });

  it('storage path: <root>/uploads/hospital_apply/hospital_<id>/cert_<doc>_<32 hex>.<ext>, relative url', () => {
    const p = buildDocumentPath('/srv/gangnamunni', 12, 'business', HEX, 'pdf');
    expect(p.url).toBe(`uploads/hospital_apply/hospital_12/cert_business_${HEX}.pdf`);
    expect(p.dir).toBe(path.resolve('/srv/gangnamunni/uploads/hospital_apply/hospital_12'));
    expect(p.absolute).toBe(path.resolve(`/srv/gangnamunni/uploads/hospital_apply/hospital_12/cert_business_${HEX}.pdf`));
    expect(buildDocumentPath('/srv/gangnamunni/', 3, 'medical', HEX, 'png').url).toBe(`uploads/hospital_apply/hospital_3/cert_medical_${HEX}.png`);
  });

  it('the stored value is accepted by the PHP viewer pattern', () => {
    // pattern copied from the PHP document viewer
    const php = /^uploads\/hospital_apply\/[A-Za-z0-9_\-]+\/(cert_(?:business|medical)_[A-Za-z0-9]+\.(jpg|jpeg|png|pdf))$/;
    for (const doc of ['business', 'medical'] as const) for (const kind of ['jpg', 'png', 'pdf'] as const) {
      expect(php.test(buildDocumentPath('/srv/g', 98765, doc, HEX, kind).url)).toBe(true);
    }
  });

  it.each([
    ['id 0', 0, HEX],
    ['negative id', -4, HEX],
    ['fractional id', 1.5, HEX],
    ['NaN id', NaN, HEX],
    ['short token', 12, 'ab'],
    ['upper-case token', 12, 'AB'.repeat(16)],
    ['traversal in token', 12, '../'.repeat(10) + 'ab'],
    ['slash in token', 12, 'a/'.repeat(16)],
  ])('refuses %s', (_n, id, hex) => {
    expect(() => buildDocumentPath('/srv/g', id as number, 'business', hex as string, 'jpg')).toThrow();
  });

  it('removable stored values: exactly the viewer shape AND the file names the same document', () => {
    const ok = (v: string, d: 'business' | 'medical' = 'business') => isRemovableStoredPath(v, d);
    expect(ok(`uploads/hospital_apply/hospital_12/cert_business_${HEX}.pdf`)).toBe(true);
    expect(ok('uploads/hospital_apply/0fb9ed02f4cfc5fa029495aa0539905c/cert_business_1783654260.png')).toBe(true);
    expect(ok('uploads/hospital_apply/admin_1/cert_medical_ab12.jpeg', 'medical')).toBe(true);
    for (const bad of [
      '', '/etc/passwd', '../outside/cert_business_aa.png', 'uploads/hospital_apply/../../x/cert_business_aa.png', 'uploads/hospital_apply/../cert_business_aa.png',
      'uploads/hospital_apply/./cert_business_aa.png', 'uploads/hospitals/12/main_aa.jpg', 'uploads/hospital_apply/a/b/cert_business_aa.png', 'uploads/hospital_apply//cert_business_aa.png',
      'uploads/hospital_apply/a/cert_business_aa.php', 'uploads/hospital_apply/a/cert_business_aa.png.php', 'uploads/hospital_apply/a/cert_business_aa.gif',
      'uploads/hospital_apply/a/cert_business_a-a.png', 'uploads/hospital_apply/a/cert_medical_aa.png', 'uploads/hospital_apply/a/other_business_aa.png',
      '/srv/gangnamunni/uploads/hospital_apply/a/cert_business_aa.png', 'uploads/hospital_apply/a b/cert_business_aa.png', 'uploads/hospital_apply/a/cert_business_aa.png\n',
      'uploads\\hospital_apply\\a\\cert_business_aa.png', 'https://example.com/uploads/hospital_apply/a/cert_business_aa.png',
    ]) expect(ok(bad)).toBe(false);
    expect(isRemovableStoredPath(null, 'business')).toBe(false);
    expect(isRemovableStoredPath(42, 'business')).toBe(false);
  });

  it('who may write: a super admin with scope all, or a hospital account bound to exactly this hospital', () => {
    const all = { scope: 'all' as const, hospitalIds: [] };
    const own = { scope: 'own' as const, hospitalIds: [12] };
    expect(canWriteDocuments('super_admin', all, 12)).toBe(true);
    expect(canWriteDocuments('hospital_super_admin', own, 12)).toBe(true);
    expect(canWriteDocuments('hospital_admin', own, 12)).toBe(true);
    expect(canWriteDocuments('hospital_admin', own, 13)).toBe(false);
    expect(canWriteDocuments('hospital_admin', { scope: 'own', hospitalIds: [] }, 12)).toBe(false);
    expect(canWriteDocuments('hospital_admin', all, 12)).toBe(false);
    expect(canWriteDocuments('super_admin', own, 12)).toBe(false);
    expect(canWriteDocuments('editor', own, 12)).toBe(false);
    expect(canWriteDocuments('', all, 12)).toBe(false);
    expect(canWriteDocuments('super_admin', all, 0)).toBe(false);
  });

  it('queries: ids and values are parameters; one column per update; nothing else is set', () => {
    expect(lockDocumentsQuery(12)).toEqual({ sql: 'SELECT id, cert_business, cert_medical FROM gangnamunni_hospitals WHERE id = ? LIMIT 1 FOR UPDATE', values: [12] });
    expect(updateDocumentQuery('business', 12, 'uploads/x')).toEqual({ sql: 'UPDATE gangnamunni_hospitals SET cert_business = ? WHERE id = ?', values: ['uploads/x', 12] });
    expect(updateDocumentQuery('medical', 12, '')).toEqual({ sql: 'UPDATE gangnamunni_hospitals SET cert_medical = ? WHERE id = ?', values: ['', 12] });
  });

  it('stored values and the answer', () => {
    expect(storedValueOf({ cert_business: ' a ', cert_medical: null }, 'business')).toBe('a');
    expect(storedValueOf({ cert_business: 'a', cert_medical: null }, 'medical')).toBe('');
    expect(storedValueOf(undefined, 'medical')).toBe('');
    expect(toDocuments('x', '')).toEqual({ documents: { business: true, medical: false } });
  });

  it('the detail carries documentsEditable only when it is exactly true', () => {
    const base = { hospital: { id: 1, name: 'A', status: 'approved', has_cert_business: 1, has_cert_medical: 0 }, optional: null, images: [], doctors: [], revisions: [] };
    expect(buildDetail(base).documentsEditable).toBe(false);
    expect(buildDetail({ ...base, documentsEditable: true }).documentsEditable).toBe(true);
    expect(buildDetail({ ...base, documentsEditable: 1 as unknown as boolean }).documentsEditable).toBe(false);
  });

  it('parser failures become the documented 400 bodies; other errors pass through', () => {
    const toBody = (e: unknown) => ((e as BadRequestException).getResponse() as { error: string }).error;
    expect(toBody(mapDocumentUploadError(new PayloadTooLargeException()))).toBe('too_large');
    expect(toBody(mapDocumentUploadError(new BadRequestException('Unexpected field')))).toBe('upload_failed');
    const other = new InternalServerErrorException();
    expect(mapDocumentUploadError(other)).toBe(other);
  });
});
