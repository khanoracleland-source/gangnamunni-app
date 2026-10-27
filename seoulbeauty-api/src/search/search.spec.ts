import { Test } from '@nestjs/testing';
import { Global, INestApplication, Module } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../db/db.module';
import { SearchModule } from './search.module';
import { HospitalsModule } from '../hospitals/hospitals.module';
import { AuthService } from '../auth/auth.service';
import { AuthGuard } from '../auth/auth.guard';
import { addrDistrict, dosageLabel, normalizeKeyword, SearchService } from './search.service';

describe('search helpers', () => {
  it('dosageLabel trims zeros like PHP', () => {
    expect(dosageLabel(2, 'cc')).toBe('2cc');
    expect(dosageLabel(0.5, 'cc')).toBe('0.5cc');
    expect(dosageLabel(100, 'U')).toBe('100U');
  });
  it('normalizeKeyword', () => expect(normalizeKeyword('  보톡스   Botox ')).toBe('보톡스 botox'));
  it('addrDistrict prefers the 2nd token', () => {
    expect(addrDistrict('서울 강남구 테헤란로 1')).toBe('강남');
    expect(addrDistrict('서울특별시 서초구')).toBe('서초');
    expect(addrDistrict('부산')).toBe('');
  });
  it('dosageSelected is a whitelist and needs >1 option', () => {
    const s = new SearchService({} as any);
    const opts = [{ value: 1, unit: 'cc', label: '1cc' }, { value: 2, unit: 'cc', label: '2cc' }];
    expect(s.dosageSelected(opts, '2cc')?.value).toBe(2);
    expect(s.dosageSelected(opts, '3cc')).toBeNull();
    expect(s.dosageSelected([opts[0]], '1cc')).toBeNull();
  });
});

describe('search + treatment list (mock DB)', () => {
  let app: INestApplication;
  const calls: { sql: string; values?: unknown[] }[] = [];
  const h = (id: number, name: string) => ({ id, name, address: '서울 강남구 x', rating: '4.0', review_count: 1, min_price: 0, wishlist_count: 0 });
  const db = {
    query: jest.fn(async (sql: string, values?: unknown[]) => {
      calls.push({ sql, values });
      if (sql.includes('SHOW COLUMNS')) return [[{ Field: 'x' }]];
      if (sql.includes('information_schema')) return [[{ 1: 1 }]];
      if (sql.includes('SELECT matched.id')) return [values![0] === '보톡스' ? [{ id: 20, name: '주름 보톡스' }] : []];
      if (sql.includes('SELECT DISTINCT p.dosage_value')) return [[{ dosage_value: '100.00', dosage_unit: 'U' }, { dosage_value: '50.00', dosage_unit: 'U' }]];
      if (sql.includes('COUNT(*) AS total')) return [[{ total: 2 }]];
      if (sql.includes('FROM gangnamunni_ad_slot_bookings b') && sql.includes('rank_position')) return [[{ booking_id: 9, hospital_id: 5, rank_position: 1 }]];
      if (sql.includes('WHERE h.id IN (')) return [[h(5, 'AdClinic')]];
      if (sql.includes('AS display_sale_price')) return [[{ hospital_id: 3, treatment_name: '주름 보톡스', description: 'd', dosage_value: null, dosage_unit: null, display_dosage_value: '50.00', display_dosage_unit: 'U', default_min: 0, default_max: 0, display_sale_price: 80000, display_regular_price: 100000 }]];
      if (sql.includes('gangnamunni_settings')) return [[{ setting_key: 'ad_infeed_interval', setting_value: '3' }]];
      if (sql.includes("a.ad_type = 'infeed'")) return [[{ id: 1, title: '광고', subtitle: '서울 강남구 y', image_url: null, link_url: '#', hospital_id: 7 }]];
      if (sql.includes('FROM gangnamunni_treatment_master tm WHERE tm.is_active = 1 AND tm.name LIKE ?')) return [[{ id: 20, name: '주름 보톡스' }]];
      if (sql.includes('GROUP BY p.dosage_value, p.dosage_unit')) return [[{ v: '50.00', u: 'U', c: 3 }]];
      if (sql.includes('GROUP BY h.id, h.name, h.address')) return [[{ id: 3, name: 'A', address: '서울 강남구 x', price: 80000 }]];
      if (sql.includes('h.name LIKE ?') && sql.includes('LIMIT 3')) return [[{ id: 8, name: '보톡스의원', address: '서울 서초구 z' }]];
      if (sql.includes('FROM gangnamunni_hospitals h') && sql.includes('ORDER BY')) return [[h(3, 'A'), h(5, 'AdClinic'), h(4, 'B')]];
      return [[]];
    }),
  };
  beforeAll(async () => {
    const authStub = { ping: async () => true, fromCookie: async () => ({ loggedIn: false, user: null }) };
    @Global() @Module({ providers: [{ provide: AuthService, useValue: authStub }, AuthGuard, { provide: DB, useValue: db }], exports: [AuthService, AuthGuard, DB] }) class TestBase {}
    const mod = await Test.createTestingModule({ imports: [TestBase, SearchModule, HospitalsModule] }).compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(() => app.close());

  it('GET /v1/search/suggest mirrors search_suggest.php shape', async () => {
    const r = await request(app.getHttpServer()).get('/v1/search/suggest?q=보톡').expect(200);
    expect(r.body.treatments).toEqual([
      { name: '주름 보톡스', href: 'hospitals.php?q=%EC%A3%BC%EB%A6%84%20%EB%B3%B4%ED%86%A1%EC%8A%A4' },
      { name: '주름 보톡스 50U', href: 'hospitals.php?q=%EC%A3%BC%EB%A6%84%20%EB%B3%B4%ED%86%A1%EC%8A%A4&dosage=50U', hint: '용량 자동 선택' },
    ]);
    expect(r.body.offered).toEqual({ treatment: '주름 보톡스', items: [{ id: 3, name: 'A', district: '강남', address: '서울 강남구 x', price: 80000, href: 'hospitals.php?q=%EC%A3%BC%EB%A6%84%20%EB%B3%B4%ED%86%A1%EC%8A%A4&hid=3' }] });
    expect(r.body.hospitals[0]).toMatchObject({ id: 8, district: '서초' });
    await request(app.getHttpServer()).get('/v1/search/suggest').expect(200).expect({ treatments: [], hospitals: [], offered: { treatment: '', items: [] } });
  });

  it('keyword resolves to a treatment: default price sort, dosage options, price line', async () => {
    calls.length = 0;
    const r = await request(app.getHttpServer()).get('/v1/hospitals?q=보톡스').expect(200);
    expect(r.body.sort).toBe('price');
    expect(r.body.effectiveTreatment).toEqual({ id: 20, name: '주름 보톡스' });
    expect(r.body.dosageOptions).toEqual(['50U', '100U']);
    expect(r.body.dosage).toBeNull();
    expect(r.body.items[0].treatment).toEqual({ name: '주름 보톡스', desc: 'd', priceMin: 80000, priceMax: 100000, dosageValue: 50, dosageUnit: 'U' });
    expect(r.body.items[0].adSlot).toBeNull(); // price sort: no pinning
    expect(r.body.infeed).toEqual({ interval: 3, ads: [{ id: 1, title: '광고', subtitle: '서울 강남구 y', imageUrl: null, linkUrl: '#', hospitalId: 7 }] });
    const listSql = calls.find((c) => c.sql.includes('ORDER BY') && c.sql.includes('FROM gangnamunni_hospitals h'))!;
    expect(listSql.sql).toContain('WHERE ht.treatment_id = ?'); // resolved treatment widens the keyword match
  });

  it('dosage whitelist + ad slots pinned first on non-price sorts', async () => {
    calls.length = 0;
    const r = await request(app.getHttpServer()).get('/v1/hospitals?q=보톡스&sort=rating&dosage=50U').expect(200);
    expect(r.body.dosage).toBe('50U');
    expect(r.body.items.map((i: any) => i.id)).toEqual([5, 3, 4]);
    expect(r.body.items[0].adSlot).toEqual({ bookingId: 9, rank: 1 });
    const listSql = calls.find((c) => c.sql.includes('ORDER BY') && c.sql.includes('FROM gangnamunni_hospitals h'))!;
    expect(listSql.sql).toContain('ABS(p.dosage_value - ?)');
    expect(listSql.values).toEqual(expect.arrayContaining([50, 'U']));
    const bad = await request(app.getHttpServer()).get('/v1/hospitals?q=보톡스&dosage=7cc').expect(200);
    expect(bad.body.dosage).toBeNull();
  });
});
