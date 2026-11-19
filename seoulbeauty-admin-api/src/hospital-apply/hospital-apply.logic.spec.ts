import { buildApplyPath, parseHead, todayKst, FILE_FIELD_RE, insertHospitalQuery, judgeFile, kindOfField, normalizeBusinessHours, parsePayload } from './hospital-apply.logic';

const parse = (o: unknown) => parsePayload(JSON.stringify(o));
const ok = (o: unknown) => {
  const r = parse(o);
  if (!r.ok) throw new Error(`expected ok, got ${JSON.stringify(r.body)}`);
  return r.value;
};
const fails = (o: unknown) => {
  const r = parse(o);
  if (r.ok) throw new Error('expected an error');
  return r.body;
};

describe('hospital apply: payload', () => {
  it('only the name is required', () => {
    const v = ok({ name: '  서울의원 ' });
    expect(v.name).toBe('서울의원');
    expect(v.phone).toBe('');
    expect(v.treatments).toEqual([]);
    expect(v.doctors).toEqual([]);
    expect(v.lat).toBeNull();
    expect(v.businessHours).toBe('[]');
  });

  it.each([{}, { name: '' }, { name: '   ' }, { name: 5 }, { name: 'x'.repeat(256) }])('bad name %p -> invalid_field name', (p) => {
    expect(fails(p)).toEqual({ error: 'invalid_field', field: 'name' });
  });

  it.each([undefined, '', 'not json', '[]', '"x"', 'null'])('body %p -> invalid_body', (raw) => {
    expect(parsePayload(raw)).toEqual({ ok: false, body: { error: 'invalid_body' } });
  });

  it('too large payload -> invalid_body', () => {
    expect(parsePayload(JSON.stringify({ name: 'a', introduction: 'x'.repeat(700 * 1024) })).ok).toBe(false);
  });

  it('e-mail, website and SNS are checked only when filled', () => {
    expect(ok({ name: 'a', email: '', website: '', snsUrl: '' }).email).toBe('');
    expect(fails({ name: 'a', email: 'nope' })).toEqual({ error: 'invalid_field', field: 'email' });
    expect(fails({ name: 'a', website: 'javascript:alert(1)' })).toEqual({ error: 'invalid_field', field: 'website' });
    expect(ok({ name: 'a', snsUrl: '@seoulbeauty' }).snsUrl).toBe('@seoulbeauty');
    expect(ok({ name: 'a', snsUrl: 'instagram.com/abc' }).snsUrl).toBe('instagram.com/abc');
    expect(fails({ name: 'a', snsUrl: 'x'.repeat(256) }).field).toBe('snsUrl');
    expect(ok({ name: 'a', website: 'https://a.example/x', snsUrl: 'http://b.example' }).website).toBe('https://a.example/x');
  });

  it('the phone is formatted', () => {
    expect(ok({ name: 'a', phone: '0212345678' }).phone).toBe('02-1234-5678');
    expect(fails({ name: 'a', phone: '1'.repeat(21) })).toEqual({ error: 'invalid_field', field: 'phone' });
  });

  it('text limits', () => {
    expect(fails({ name: 'a', ownerName: 'x'.repeat(101) }).field).toBe('ownerName');
    expect(fails({ name: 'a', businessNumber: 'x'.repeat(21) }).field).toBe('businessNumber');
    expect(fails({ name: 'a', address: 'x'.repeat(501) }).field).toBe('address');
    expect(fails({ name: 'a', introduction: 'x'.repeat(2001) }).field).toBe('introduction');
    expect(fails({ name: 'a', contactName: 5 }).field).toBe('contactName');
  });

  it('coordinates: both or none, in range', () => {
    expect(ok({ name: 'a', lat: 37.5, lng: 127 })).toMatchObject({ lat: 37.5, lng: 127 });
    expect(ok({ name: 'a', lat: null, lng: null }).lat).toBeNull();
    expect(fails({ name: 'a', lat: 37.5 }).field).toBe('lat');
    expect(fails({ name: 'a', lat: 91, lng: 0 }).field).toBe('lat');
    expect(fails({ name: 'a', lat: 0, lng: 181 }).field).toBe('lat');
    expect(fails({ name: 'a', lat: '37', lng: 127 }).field).toBe('lat');
  });

  it('doctors: a row is kept only with a name, the rest is optional', () => {
    const v = ok({ name: 'a', doctors: [{ name: '' }, { name: ' 김의사 ', experienceYears: 99 }, { specialty: 'x' }, { name: 'B', specialty: '피부' }] });
    expect(v.doctors.map((d) => [d.index, d.name, d.experience, d.specialty])).toEqual([[1, '김의사', 80, ''], [3, 'B', 0, '피부']]);
    expect(fails({ name: 'a', doctors: [{ name: 'x'.repeat(101) }] })).toEqual({ error: 'invalid_field', field: 'doctors', index: 0 });
    expect(fails({ name: 'a', doctors: [{ name: 'a', experienceYears: 'abc' }] }).field).toBe('doctors');
    expect(fails({ name: 'a', doctors: Array.from({ length: 21 }, () => ({ name: 'a' })) }).field).toBe('doctors');
  });

  it('treatments: ids must be unique positive integers', () => {
    expect(ok({ name: 'a', treatments: [{ treatmentId: 1 }, { treatmentId: 2, prices: [] }] }).treatments.map((t) => t.treatmentId)).toEqual([1, 2]);
    expect(fails({ name: 'a', treatments: [{ treatmentId: 1 }, { treatmentId: 1 }] })).toEqual({ error: 'invalid_field', field: 'treatments', index: 1 });
    expect(fails({ name: 'a', treatments: [{ treatmentId: 0 }] }).field).toBe('treatments');
    expect(fails({ name: 'a', treatments: [{ treatmentId: '3' }] }).field).toBe('treatments');
    expect(fails({ name: 'a', treatments: 'x' }).field).toBe('treatments');
  });

  it('price rows: incomplete rows are dropped silently and counted, complete ones kept', () => {
    const v = ok({
      name: 'a',
      treatments: [{ treatmentId: 1, prices: [
        { optionName: '', dosageValue: 1, dosageUnit: '회', price: 10000 },
        { optionName: 'x', dosageValue: 0, dosageUnit: '회', price: 10000 },
        { optionName: 'x', dosageValue: 1, dosageUnit: '', price: 10000 },
        { optionName: 'x', dosageValue: 1, dosageUnit: '회', price: 0 },
        {},
        { optionName: ' 옵션 ', dosageValue: 1.006, dosageUnit: 'cc', price: 5000 },
      ] }],
    });
    expect(v.skippedPriceRows).toBe(4);
    expect(v.treatments[0].prices).toEqual([
      { optionName: '', dosageValue: 1, dosageUnit: '회', price: 10000 },
      { optionName: '옵션', dosageValue: 1.01, dosageUnit: 'cc', price: 5000 },
    ]);
  });

  it('price rows: wrong types and out of range values are errors, not skipped', () => {
    const row = (r: unknown) => fails({ name: 'a', treatments: [{ treatmentId: 1, prices: [r] }] });
    expect(row({ optionName: 5 }).field).toBe('treatments');
    expect(row({ dosageValue: '1', dosageUnit: '회', price: 1 }).field).toBe('treatments');
    expect(row({ dosageValue: 1, dosageUnit: '회', price: 1.5 }).field).toBe('treatments');
    expect(row({ dosageValue: 1, dosageUnit: '회', price: 3_000_000_000 }).field).toBe('treatments');
    expect(row({ optionName: 'x'.repeat(101) }).field).toBe('treatments');
    expect(row({ dosageUnit: 'x'.repeat(21) }).field).toBe('treatments');
  });
});

describe('hospital apply: opening hours', () => {
  const parseHours = (raw: unknown) => JSON.parse(normalizeBusinessHours(raw));

  it('no object -> "[]" like the PHP page', () => {
    expect(normalizeBusinessHours(undefined)).toBe('[]');
    expect(normalizeBusinessHours([])).toBe('[]');
    expect(normalizeBusinessHours('x')).toBe('[]');
  });

  it('every day is a valid range or a closed word; anything else becomes 휴무', () => {
    const h = parseHours({ 월: '10:00~19:00', 화: '휴무', 수: '공사중', 목: '19:00~10:00', 금: '25:00~26:00', 토: '임시휴업', 일: '아무거나' });
    expect(h).toMatchObject({ 월: '10:00~19:00', 화: '휴무', 수: '공사중', 목: '휴무', 금: '휴무', 토: '임시휴업', 일: '휴무' });
  });

  it('lunch break: a valid range, or no_lunch_break', () => {
    expect(parseHours({ 점심: '13:00~14:00' })['점심']).toBe('13:00~14:00');
    expect(parseHours({ 점심: '14:00~13:00' })['점심']).toBeUndefined();
    const none = parseHours({ no_lunch_break: true, 점심: '13:00~14:00' });
    expect(none.no_lunch_break).toBe(true);
    expect(none['점심']).toBeUndefined();
  });

  it('closed dates: real dates after today only, sorted, no repeats', () => {
    const at = (raw: unknown) => JSON.parse(normalizeBusinessHours(raw, '2026-06-15')).special_closed_dates;
    expect(at({ special_closed_dates: ['2026-12-25', '2026-02-30', 'x', '2026-12-25', '2026-06-16', '2026-06-15', '2026-06-14', 5] })).toEqual(['2026-06-16', '2026-12-25']);
    expect(at({})).toEqual([]);
    expect(todayKst(new Date('2026-06-14T16:00:00Z'))).toBe('2026-06-15');
    expect(todayKst(new Date('2026-06-14T14:59:00Z'))).toBe('2026-06-14');
  });

  it('the first stage reads only the name and the phone', () => {
    expect(parseHead(JSON.stringify({ name: ' a ', phone: '0212345678', email: 'bad' }))).toEqual({ ok: true, value: { name: 'a', phone: '02-1234-5678' } });
    expect(parseHead(JSON.stringify({ name: '' }))).toEqual({ ok: false, body: { error: 'invalid_field', field: 'name' } });
    expect(parseHead('x')).toEqual({ ok: false, body: { error: 'invalid_body' } });
  });
});

describe('hospital apply: files', () => {
  const jpeg = Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), Buffer.alloc(20, 1)]);
  const png = Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), Buffer.alloc(20, 1)]);
  const pdf = Buffer.concat([Buffer.from('%PDF-1.4'), Buffer.alloc(20, 1)]);
  const gif = Buffer.concat([Buffer.from('GIF89a'), Buffer.alloc(20, 1)]);
  const MB = 1024 * 1024;

  it('field names', () => {
    for (const ok of ['certBusiness', 'certMedical', 'profileImage', 'interiorImages', 'doctorPhoto_0', 'doctorPhoto_19', 'treatmentImages_12']) expect(FILE_FIELD_RE.test(ok)).toBe(true);
    for (const bad of ['payload', 'doctorPhoto_', 'doctorPhoto_100', 'treatmentImages_x', '../x', 'profileImage2', 'doctorPhoto_1/../']) expect(FILE_FIELD_RE.test(bad)).toBe(false);
    expect(kindOfField('doctorPhoto_3')).toBe('doctorPhoto');
    expect(kindOfField('treatmentImages_7')).toBe('treatmentImages');
    expect(kindOfField('profileImage')).toBe('profileImage');
  });

  it('main image: jpg/png up to 5 MB, the real type must match the name', () => {
    expect(judgeFile('profileImage', 'a.jpg', 100, jpeg)).toEqual({ ok: true, ext: 'jpg' });
    expect(judgeFile('profileImage', 'a.png', 100, png)).toEqual({ ok: true, ext: 'png' });
    expect(judgeFile('profileImage', 'a.png', 100, jpeg)).toEqual({ ok: false, error: 'bad_file_type' });
    expect(judgeFile('profileImage', 'a.webp', 100, jpeg)).toEqual({ ok: false, error: 'bad_file_type' });
    expect(judgeFile('profileImage', 'a.jpg', 5 * MB + 1, jpeg)).toEqual({ ok: false, error: 'file_too_large' });
  });

  it('interior images allow 10 MB, doctor photos 5 MB', () => {
    expect(judgeFile('interiorImages', 'a.jpg', 10 * MB, jpeg).ok).toBe(true);
    expect(judgeFile('interiorImages', 'a.jpg', 10 * MB + 1, jpeg)).toEqual({ ok: false, error: 'file_too_large' });
    expect(judgeFile('doctorPhoto', 'a.jpg', 5 * MB, jpeg).ok).toBe(true);
    expect(judgeFile('doctorPhoto', 'a.jpg', 5 * MB + 1, jpeg)).toEqual({ ok: false, error: 'file_too_large' });
  });

  it('certificates: jpg, png or pdf up to 5 MB', () => {
    expect(judgeFile('certBusiness', 'a.pdf', 100, pdf)).toEqual({ ok: true, ext: 'pdf' });
    expect(judgeFile('certMedical', 'a.pdf', 100, jpeg)).toEqual({ ok: false, error: 'bad_file_type' });
    expect(judgeFile('certMedical', 'a.pdf', 5 * MB + 1, pdf)).toEqual({ ok: false, error: 'file_too_large' });
  });

  it('treatment images allow gif and 10 MB', () => {
    expect(judgeFile('treatmentImages', 'a.gif', 100, gif)).toEqual({ ok: true, ext: 'gif' });
    expect(judgeFile('treatmentImages', 'a.exe', 100, gif)).toEqual({ ok: false, error: 'bad_file_type' });
    expect(judgeFile('treatmentImages', 'a.gif', 10 * MB + 1, gif)).toEqual({ ok: false, error: 'file_too_large' });
  });

  it('an empty file is refused', () => {
    expect(judgeFile('profileImage', 'a.jpg', 0, jpeg)).toEqual({ ok: false, error: 'bad_file_type' });
  });

  it('stored paths stay inside the folder of the admin and match the document viewer shape', () => {
    const hex = 'a'.repeat(32);
    const p = buildApplyPath('/srv/up', 7, 'cert_business', hex, 'pdf');
    expect(p.url).toBe(`uploads/hospital_apply/admin_7/cert_business_${hex}.pdf`);
    expect(p.absolute).toBe(`/srv/up/uploads/hospital_apply/admin_7/cert_business_${hex}.pdf`);
    expect(/^uploads\/hospital_apply\/[A-Za-z0-9_-]+\/cert_(business|medical)_[A-Za-z0-9]+\.(?:jpg|jpeg|png|pdf)$/.test(p.url)).toBe(true);
    expect(() => buildApplyPath('/srv/up', 0, 'profile', hex, 'jpg')).toThrow();
    expect(() => buildApplyPath('/srv/up', 1.5, 'profile', hex, 'jpg')).toThrow();
    expect(() => buildApplyPath('/srv/up', 7, 'profile', '../x', 'jpg')).toThrow();
    expect(() => buildApplyPath('/srv/up', 7, 'profile', hex, '../x')).toThrow();
  });
});

describe('hospital apply: the hospital row', () => {
  it('is approved and inactive, every value is a parameter', () => {
    const input = ok({ name: "x'); DROP TABLE a; --", lat: 37, lng: 127 });
    const q = insertHospitalQuery({ input, appliedBy: 'boss', reviewedBy: 'boss', profileUrl: 'p', certBusiness: 'b', certMedical: 'm', isDemo: 0 });
    expect(q.sql).toContain("'approved', 0, 'none'");
    expect(q.sql).not.toContain('DROP');
    expect(q.values).toContain("x'); DROP TABLE a; --");
    expect((q.sql.match(/\?/g) ?? []).length).toBe(q.values.length);
  });
});
