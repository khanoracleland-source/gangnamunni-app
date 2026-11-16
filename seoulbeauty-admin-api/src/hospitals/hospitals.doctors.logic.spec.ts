import * as path from 'path';
import { BadRequestException, PayloadTooLargeException } from '@nestjs/common';
import { buildDoctorPhotoPath, DOCTOR_ADD_LIMIT, MAX_DOCTORS_FIELD_BYTES, parseDoctors, parseExperience, summarizeDoctors } from './hospitals.doctors.logic';
import { mapDoctorUploadError } from './hospitals.doctors.upload';
import { checkImage } from './hospitals.images.logic';

const jpeg = () => Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(60, 1)]);
const webp = () => Buffer.concat([Buffer.from('RIFF', 'latin1'), Buffer.from([1, 2, 3, 4]), Buffer.from('WEBP', 'latin1'), Buffer.alloc(52, 3)]);
const parse = (v: unknown, photos = 0) => parseDoctors(JSON.stringify(v), photos);
const err = (v: unknown, photos = 0) => {
  const r = parse(v, photos);
  if (r.ok) throw new Error('expected an error');
  return r.body;
};

describe('doctors: submitted rows', () => {
  it('trims, fills defaults and keeps the position of every stored row', () => {
    const r = parse([{ name: '  김서울 ', specialty: ' 피부과 ', experience: 10, qualification: 'a\nb', bio: ' x ', photo: 0 }, { name: '이' }], 1);
    expect(r).toEqual({ ok: true, doctors: [
      { index: 0, name: '김서울', specialty: '피부과', experience: 10, qualification: 'a\nb', bio: 'x', photo: 0 },
      { index: 1, name: '이', specialty: '', experience: 0, qualification: '', bio: '', photo: null },
    ] });
  });

  it('rows without a name are skipped whole (photo and wrong other fields too), at least one named row is needed', () => {
    const r = parse([{ name: '' , photo: 0, experience: 'x' }, { name: '  ' }, { specialty: 'a' }, { name: 'A' }], 1);
    expect(r).toEqual({ ok: true, doctors: [{ index: 3, name: 'A', specialty: '', experience: 0, qualification: '', bio: '', photo: null }] });
    expect(err([{ name: '' }, { name: ' ' }])).toEqual({ error: 'no_doctors' });
    expect(err([])).toEqual({ error: 'no_doctors' });
    expect(parseDoctors(undefined, 0)).toEqual({ ok: false, body: { error: 'no_doctors' } });
    expect(parseDoctors('  ', 0)).toEqual({ ok: false, body: { error: 'no_doctors' } });
  });

  it('more than 20 rows (blank ones counted) -> too_many; exactly 20 is fine', () => {
    expect(err(Array.from({ length: DOCTOR_ADD_LIMIT + 1 }, (_, i) => ({ name: i === 0 ? 'A' : '' })))).toEqual({ error: 'too_many' });
    expect(parse(Array.from({ length: DOCTOR_ADD_LIMIT }, () => ({ name: 'A' }))).ok).toBe(true);
  });

  it('length limits: name 100, specialty 255, qualification and bio 2000 (characters, not bytes)', () => {
    expect(parse([{ name: '가'.repeat(100), specialty: '나'.repeat(255), qualification: 'q'.repeat(2000), bio: 'b'.repeat(2000) }]).ok).toBe(true);
    expect(err([{ name: 'A' }, { name: '가'.repeat(101) }])).toEqual({ error: 'invalid_field', index: 1, field: 'name' });
    expect(err([{ name: 'A', specialty: 's'.repeat(256) }])).toEqual({ error: 'invalid_field', index: 0, field: 'specialty' });
    expect(err([{ name: 'A', qualification: 'q'.repeat(2001) }])).toEqual({ error: 'invalid_field', index: 0, field: 'qualification' });
    expect(err([{ name: 'A', bio: 'b'.repeat(2001) }])).toEqual({ error: 'invalid_field', index: 0, field: 'bio' });
  });

  it('wrong types name the row and field', () => {
    expect(err([{ name: 5 }])).toEqual({ error: 'invalid_field', index: 0, field: 'name' });
    expect(err([{ name: 'A', specialty: 1 }])).toEqual({ error: 'invalid_field', index: 0, field: 'specialty' });
    expect(err([{ name: 'A', qualification: [] }])).toEqual({ error: 'invalid_field', index: 0, field: 'qualification' });
    expect(err([{ name: 'A', bio: {} }])).toEqual({ error: 'invalid_field', index: 0, field: 'bio' });
    expect(err([{ name: 'A' }, 'x'])).toEqual({ error: 'invalid_field', index: 1 });
    expect(err([null])).toEqual({ error: 'invalid_field', index: 0 });
    expect(err([[]])).toEqual({ error: 'invalid_field', index: 0 });
  });

  it('experience: clamped to 0..80 like the PHP form, wrong types are refused', () => {
    expect(parseExperience(undefined)).toBe(0);
    expect(parseExperience(null)).toBe(0);
    expect(parseExperience('')).toBe(0);
    expect(parseExperience(7)).toBe(7);
    expect(parseExperience('12')).toBe(12);
    expect(parseExperience(-5)).toBe(0);
    expect(parseExperience(81)).toBe(80);
    expect(parseExperience('1000')).toBe(80);
    for (const v of [1.5, NaN, Infinity, 'abc', '1e3', '1.5', true, [], {}]) expect(parseExperience(v)).toBeNull();
    expect(err([{ name: 'A', experience: 'x' }])).toEqual({ error: 'invalid_field', index: 0, field: 'experience' });
  });

  it('photo: a position of the uploaded list, once; anything else is refused', () => {
    expect(err([{ name: 'A', photo: 0 }], 0)).toEqual({ error: 'invalid_field', index: 0, field: 'photo' });
    expect(err([{ name: 'A', photo: 2 }], 2)).toEqual({ error: 'invalid_field', index: 0, field: 'photo' });
    expect(err([{ name: 'A', photo: -1 }], 2)).toEqual({ error: 'invalid_field', index: 0, field: 'photo' });
    expect(err([{ name: 'A', photo: '0' }], 2)).toEqual({ error: 'invalid_field', index: 0, field: 'photo' });
    expect(err([{ name: 'A', photo: 0.5 }], 2)).toEqual({ error: 'invalid_field', index: 0, field: 'photo' });
    expect(err([{ name: 'A', photo: 1 }, { name: 'B', photo: 1 }], 2)).toEqual({ error: 'invalid_field', index: 1, field: 'photo' });
    expect(parse([{ name: 'A', photo: 1 }, { name: 'B', photo: null }], 2).ok).toBe(true);
  });

  it('not JSON, not a list, too big, not text -> invalid_field without row', () => {
    expect(parseDoctors('{nope', 0)).toEqual({ ok: false, body: { error: 'invalid_field' } });
    expect(parseDoctors('{"name":"A"}', 0)).toEqual({ ok: false, body: { error: 'invalid_field' } });
    expect(parseDoctors('"x"', 0)).toEqual({ ok: false, body: { error: 'invalid_field' } });
    expect(parseDoctors(JSON.stringify([{ name: 'A', bio: 'x'.repeat(MAX_DOCTORS_FIELD_BYTES) }]), 0)).toEqual({ ok: false, body: { error: 'invalid_field' } });
    expect(parseDoctors(['a'], 0)).toEqual({ ok: false, body: { error: 'invalid_field' } });
  });
});

describe('doctors: photo checks and paths', () => {
  it('photo rule: jpg/jpeg/png/webp, 5 MB, extension must agree with the real type', () => {
    expect(checkImage('doctor', 'a.jpg', 10, jpeg())).toEqual({ ok: true, kind: 'jpg' });
    expect(checkImage('doctor', 'a.WEBP', 10, webp())).toEqual({ ok: true, kind: 'webp' });
    expect(checkImage('doctor', 'a.jpg', 5 * 1024 * 1024, jpeg())).toEqual({ ok: true, kind: 'jpg' });
    expect(checkImage('doctor', 'a.jpg', 5 * 1024 * 1024 + 1, jpeg())).toEqual({ ok: false, error: 'too_large' });
    expect(checkImage('doctor', 'a.png', 10, jpeg())).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('doctor', 'a.gif', 10, jpeg())).toEqual({ ok: false, error: 'invalid_type' });
    expect(checkImage('doctor', 'a.jpg', 10, Buffer.from('<svg/>'))).toEqual({ ok: false, error: 'invalid_type' });
  });

  it('stored path: doctor_<id>_<32 hex>.<ext> under uploads/hospitals/<hospital>, nothing from the client', () => {
    const hex = 'a'.repeat(32);
    const p = buildDoctorPhotoPath('/srv/g', 12, 345, hex, 'webp');
    expect(p.url).toBe(`uploads/hospitals/12/doctor_345_${hex}.webp`);
    expect(p.dir).toBe(path.resolve('/srv/g/uploads/hospitals/12'));
    expect(p.absolute).toBe(path.resolve(`/srv/g/uploads/hospitals/12/doctor_345_${hex}.webp`));
    expect(() => buildDoctorPhotoPath('/srv/g', 0, 1, hex, 'jpg')).toThrow();
    expect(() => buildDoctorPhotoPath('/srv/g', 1.5, 1, hex, 'jpg')).toThrow();
    expect(() => buildDoctorPhotoPath('/srv/g', 1, 0, hex, 'jpg')).toThrow();
    expect(() => buildDoctorPhotoPath('/srv/g', 1, 1, '../../etc/passwd', 'jpg')).toThrow();
    expect(() => buildDoctorPhotoPath('/srv/g', 1, 1, 'A'.repeat(32), 'jpg')).toThrow();
  });

  it('answer: empty photo_url -> null, url as stored, ordered as given', () => {
    expect(summarizeDoctors([
      { id: 1, name: '가', specialty: null, experience: '3', qualification: null, bio: '', photo_url: '', status: 'active' },
      { id: 2, name: '나', specialty: '피부', experience: 0, qualification: 'q', bio: 'b', photo_url: 'uploads/hospitals/12/doctor_2_x.jpg', status: 'inactive' },
      { id: 3, name: '다', specialty: '', experience: 1, qualification: '', bio: '', photo_url: 'https://cdn.example/a.jpg', status: 'active' },
    ])).toEqual({
      items: [
        { id: 1, name: '가', specialty: '', experience: 3, qualification: '', bio: '', photoUrl: null, status: 'active' },
        { id: 2, name: '나', specialty: '피부', experience: 0, qualification: 'q', bio: 'b', photoUrl: 'uploads/hospitals/12/doctor_2_x.jpg', status: 'inactive' },
        { id: 3, name: '다', specialty: '', experience: 1, qualification: '', bio: '', photoUrl: 'https://cdn.example/a.jpg', status: 'active' },
      ],
      count: 3,
      addLimit: 20,
    });
    expect(summarizeDoctors([])).toEqual({ items: [], count: 0, addLimit: 20 });
  });
});

describe('doctors: multipart parser errors', () => {
  it('maps parser failures to the documented 400 bodies', () => {
    const req = { __doctorField: 'photo' };
    expect(mapDoctorUploadError(new PayloadTooLargeException('File too large'), req)).toMatchObject({ response: { error: 'too_large', field: 'photo' } });
    expect(mapDoctorUploadError(new BadRequestException('Too many files'), req)).toMatchObject({ response: { error: 'too_many' } });
    expect(mapDoctorUploadError(new BadRequestException('Unexpected field'), req)).toMatchObject({ response: { error: 'too_many' } });
    expect(mapDoctorUploadError(new BadRequestException('Unexpected field - photo'), {})).toMatchObject({ response: { error: 'too_many' } });
    expect(mapDoctorUploadError(new BadRequestException('Unexpected field'), { __doctorField: 'other' })).toMatchObject({ response: { error: 'upload_failed' } });
    expect(mapDoctorUploadError(new BadRequestException('Field value too long'), req)).toMatchObject({ response: { error: 'invalid_field' } });
    expect(mapDoctorUploadError(new BadRequestException('Malformed part header'), req)).toMatchObject({ response: { error: 'upload_failed' } });
    const other = new Error('x');
    expect(mapDoctorUploadError(other, req)).toBe(other);
  });
});
