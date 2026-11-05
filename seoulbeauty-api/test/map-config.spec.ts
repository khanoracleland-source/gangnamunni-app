import { HospitalContentService } from '../src/hospitals/hospital-content.service';

/** includes/kakao_map.php provider + key resolution, mirrored for the Next detail page. */
const svc = (settings: Record<string, string>, fail = false) => new HospitalContentService({
  query: async (sql: string) => {
    if (fail) throw new Error('db down');
    if (sql.includes('map_provider')) return [Object.entries(settings).map(([setting_key, setting_value]) => ({ setting_key, setting_value }))];
    return [[]];
  },
} as any);

describe('map config', () => {
  it('kakao with a rotated key list', async () => {
    const m = await svc({ map_provider: 'kakao', kakao_map_api_keys: '["a","b"]', kakao_map_api_key: 'single' }).mapConfig();
    expect(m.provider).toBe('kakao'); expect(['a', 'b']).toContain(m.key);
  });
  it('kakao single key when the list is empty or broken', async () => {
    expect(await svc({ kakao_map_api_keys: 'not json', kakao_map_api_key: ' single ' }).mapConfig()).toEqual({ provider: 'kakao', key: 'single' });
    expect(await svc({ kakao_map_api_keys: '[]', kakao_map_api_key: 'single' }).mapConfig()).toEqual({ provider: 'kakao', key: 'single' });
  });
  it('falls back kakao → google → osm, google → kakao → osm', async () => {
    expect(await svc({ map_provider: 'kakao', google_maps_api_key: 'g' }).mapConfig()).toEqual({ provider: 'google', key: 'g' });
    expect(await svc({ map_provider: 'kakao' }).mapConfig()).toEqual({ provider: 'osm', key: null });
    expect(await svc({ map_provider: 'google', kakao_map_api_key: 'k' }).mapConfig()).toEqual({ provider: 'kakao', key: 'k' });
    expect(await svc({ map_provider: 'google', google_maps_api_key: 'g' }).mapConfig()).toEqual({ provider: 'google', key: 'g' });
    expect(await svc({ map_provider: 'weird', kakao_map_api_key: 'k' }).mapConfig()).toEqual({ provider: 'osm', key: null });
  });
  it('no settings or a failing query → osm, and the answer is cached', async () => {
    const s = svc({}, true);
    expect(await s.mapConfig()).toEqual({ provider: 'osm', key: null });
    expect(await s.mapConfig()).toEqual({ provider: 'osm', key: null });
  });
});
