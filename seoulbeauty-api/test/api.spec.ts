import { Test } from '@nestjs/testing';
import { INestApplication } from '@nestjs/common';
import request from 'supertest';
import { DB } from '../src/db/db.module';
import { HospitalsModule } from '../src/hospitals/hospitals.module';
import { HealthController } from '../src/health/health.controller';
import { AuthService } from '../src/auth/auth.service';

const hospitalRow = {
  id: 3, name: 'A Clinic', address: 'Seoul Gangnam-gu', main_image_url: 'uploads/h3/main.png', rating: '4.50', review_count: 12, min_price: 10000,
  categories: '["skin"]', badges: null, treatment_tags: '["botox","filler"]', subway_names: 'bad json',
  business_hours: '{"mon":"9-18"}', active_general_consultation: 1, wishlist_count: 2, latitude: '37.5', longitude: '127.0',
};

describe('HTTP API (mock DB)', () => {
  let app: INestApplication;
  const calls: { sql: string; values?: unknown[] }[] = [];
  const db = {
    query: jest.fn(async (sql: string, values?: unknown[]) => {
      calls.push({ sql, values });
      if (sql.includes('SHOW COLUMNS')) return [[{ Field: 'display_order' }]];
      if (sql.includes('information_schema')) return [[{ 1: 1 }]];
      if (sql.includes('FROM gangnamunni_hospitals h WHERE h.id = ?')) return [values![0] === 3 ? [{ 1: 1 }] : []];
      if (sql.includes('COUNT(*) AS total')) return [[{ total: 1 }]];
      if (sql.includes('FROM gangnamunni_hospitals h') && sql.includes('h.id = ?')) return [values![0] === 3 ? [hospitalRow] : []];
      if (sql.includes('FROM gangnamunni_hospitals h')) return [[hospitalRow]];
      if (sql.includes('FROM hospital_features')) return [[{ feature_icon: '🅿', feature_label: '주차' }]];
      if (sql.includes('FROM gangnamunni_hospital_treatments ht')) return [[
        { hospital_treatment_id: 1, is_promoted: 1, treatment_id: 20, name: '주름 보톡스', description: null, id: 11, option_name: '', body_part: '', dosage_value: 50, dosage_unit: 'U', regular_price: 100000, price: 80000 },
        { hospital_treatment_id: 1, is_promoted: 1, treatment_id: 20, name: '주름 보톡스', description: null, id: 12, option_name: '', body_part: '', dosage_value: 100, dosage_unit: 'U', regular_price: 0, price: 150000 },
        { hospital_treatment_id: 2, is_promoted: 0, treatment_id: 30, name: '필러', description: 'd', id: 13, option_name: '', body_part: '', dosage_value: 0, dosage_unit: '', regular_price: 0, price: 200000 },
      ]];
      if (sql.includes('FROM gangnamunni_doctors')) return [[]];
      if (sql.includes('FROM gangnamunni_doctor_treatments')) return [[]];
      if (sql.includes('FROM crawled_doctors')) return [[{ id: 7, name: '김의사', specialty: '피부과', description: '', experience: 0 }]];
      if (sql.includes('FROM hospital_images') && sql.includes("image_type <> 'logo'")) return [[{ image_url: 'uploads/h3/in1.jpg' }, { image_url: 'uploads/h3/main.png' }, { image_url: '/uploads/h3/logo.png' }]];
      if (sql.includes('FROM hospital_images') && sql.includes("image_type = 'logo'")) return [[{ image_url: 'uploads/h3/logo.png' }]];
      if (sql.includes('FROM hospital_images')) return [[{ hospital_id: 3, image_url: 'uploads/h3/logo.png', image_type: 'logo' }, { hospital_id: 3, image_url: 'uploads/h3/main.png', image_type: 'main' }]];
      if (sql.includes('FROM hospital_reviews')) return [[{ id: 1, hospital_id: 3, user_name: 'u', rating: 5, images: '["a.jpg"]', treatments: 'x, y', created_at: '2026-01-01 09:00:00' }]];
      return [[]];
    }),
  };

  beforeAll(async () => {
    const mod = await Test.createTestingModule({ imports: [HospitalsModule], controllers: [HealthController], providers: [{ provide: AuthService, useValue: { ping: async () => true } }] })
      .useMocker((t) => (t === DB ? db : undefined))
      .overrideProvider(DB).useValue(db)
      .compile();
    app = mod.createNestApplication();
    await app.init();
  });
  afterAll(() => app.close());

  it('GET /v1/hospitals maps rows to clean JSON', async () => {
    const r = await request(app.getHttpServer()).get('/v1/hospitals?q=bo&sort=rating&limit=5').expect(200);
    expect(r.body.total).toBe(1);
    expect(r.body.items[0]).toMatchObject({ id: 3, rating: 4.5, treatmentTags: ['botox', 'filler'], subwayNames: [], businessHours: { mon: '9-18' } });
    expect(r.body.items[0].raw_data).toBeUndefined();
    expect(r.body.items[0].thumbnailUrl).toBe('uploads/h3/main.png'); // hospital_images main wins
    expect(r.headers['cache-control']).toContain('s-maxage');
  });

  it('rejects bad params', async () => {
    await request(app.getHttpServer()).get('/v1/hospitals?sort=hack').expect(400);
    await request(app.getHttpServer()).get('/v1/hospitals?limit=1000').expect(400);
    await request(app.getHttpServer()).get('/v1/hospitals/abc').expect(400);
  });

  it('detail 200/404', async () => {
    const d = await request(app.getHttpServer()).get('/v1/hospitals/3').expect(200);
    expect(d.body.features).toEqual([{ icon: '🅿', label: '주차' }]);
    expect(d.body.images).toEqual(['uploads/h3/in1.jpg']); // main image and logo (matched by path) excluded
    await request(app.getHttpServer()).get('/v1/hospitals/99').expect(404);
  });

  it('reviews only published+active', async () => {
    const r = await request(app.getHttpServer()).get('/v1/hospitals/3/reviews').expect(200);
    expect(r.body.items[0]).toMatchObject({ images: ['a.jpg'], treatments: ['x', 'y'], createdAt: '2026-01-01T09:00:00+09:00' });
    expect(calls.some((c) => c.sql.includes("review_status = 'published'") && c.sql.includes('is_active = 1'))).toBe(true);
  });

  it('reviews of hidden/unknown hospital => 404 and no review query', async () => {
    calls.length = 0;
    await request(app.getHttpServer()).get('/v1/hospitals/99/reviews').expect(404);
    expect(calls.some((c) => c.sql.includes('FROM hospital_reviews'))).toBe(false);
  });

  it('rejects offset > 1000', async () => {
    await request(app.getHttpServer()).get('/v1/hospitals?offset=1001').expect(400);
  });

  it('hides rating when show_rating = 0', async () => {
    const { toHospitalDto } = await import('../src/hospitals/hospitals.service');
    const d = toHospitalDto({ ...hospitalRow, show_rating: 0 });
    expect(d.rating).toBeNull();
    expect(d.showRating).toBe(false);
  });

  it('treatments grouped per treatment, doctors fall back to crawled', async () => {
    const t = await request(app.getHttpServer()).get('/v1/hospitals/3/treatments').expect(200);
    expect(t.body.items).toHaveLength(2);
    expect(t.body.items[0]).toMatchObject({ treatmentId: 20, promoted: true });
    expect(t.body.items[0].options.map((o: any) => o.displayName)).toEqual(['주름 보톡스 · 50U', '주름 보톡스 · 100U']);
    expect(t.body.items[0].options[0].discountRate).toBe(20);
    const d = await request(app.getHttpServer()).get('/v1/hospitals/3/doctors').expect(200);
    expect(d.body.items).toEqual([{ id: 7, name: '김의사', specialty: '피부과', experience: null, description: null, qualification: null, photoUrl: null, treatments: [] }]);
    await request(app.getHttpServer()).get('/v1/hospitals/99/treatments').expect(404);
  });

  it('health ok', async () => {
    await request(app.getHttpServer()).get('/health').expect(200).expect({ status: 'ok', phpSession: 'ok' });
  });
});

describe('flags probe retry', () => {
  it('a failed probe is not cached', async () => {
    const { HospitalsService } = await import('../src/hospitals/hospitals.service');
    let fail = true;
    const db = {
      query: jest.fn(async (sql: string) => {
        if (sql.includes('SHOW COLUMNS') || sql.includes('information_schema')) {
          if (fail) throw new Error('down');
          return [[{ x: 1 }]];
        }
        if (sql.includes('COUNT(*)')) return [[{ total: 0 }]];
        return [[]];
      }),
    };
    const svc = new HospitalsService(db as any);
    const params = { q: '', category: '', treatment: '', regions: [], sort: 'manual' as const, limit: 1, offset: 0 };
    await expect(svc.list(params)).rejects.toThrow('down');
    fail = false;
    await svc.list(params);
    const order = db.query.mock.calls.map((c: any[]) => c[0]).find((s: string) => s.includes('ORDER BY'));
    expect(order).toContain('display_order');
  });
});
