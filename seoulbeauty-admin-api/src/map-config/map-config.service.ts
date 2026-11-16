import { Inject, Injectable, Logger } from '@nestjs/common';
import { DB, Db } from '../db/db.module';

export interface MapConfig {
  provider: 'kakao' | 'osm';
  key: string | null;
}

const OSM: MapConfig = { provider: 'osm', key: null };
const TTL_MS = 5 * 60_000;

/**
 * Chooses the map for the location picker from gangnamunni_settings: map_provider (default kakao) and the Kakao browser key
 * (kakao_map_api_keys, a JSON array of which one is picked at random to spread quota, otherwise kakao_map_api_key).
 * Anything but a usable Kakao setup means OpenStreetMap. The key is a JavaScript key restricted by domain in the Kakao
 * console, so sending it to the page is intended. A missing grant or table never fails the request. Cached for 5 minutes.
 */
@Injectable()
export class MapConfigService {
  private readonly log = new Logger('MapConfig');
  private cache: { value: MapConfig; until: number } | null = null;

  constructor(@Inject(DB) private readonly db: Db) {}

  async get(): Promise<MapConfig> {
    const now = Date.now();
    if (this.cache && this.cache.until > now) return this.cache.value;
    let value: MapConfig = OSM;
    try {
      const [rows] = (await this.db.query(
        `SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_key IN (?, ?, ?)`,
        ['map_provider', 'kakao_map_api_keys', 'kakao_map_api_key'],
      )) as any;
      value = pickMapConfig(Array.isArray(rows) ? rows : []);
    } catch (e) {
      this.log.warn(`map settings unavailable: ${(e as Error).message}`);
    }
    this.cache = { value, until: now + TTL_MS };
    return value;
  }
}

export function pickMapConfig(rows: { setting_key?: unknown; setting_value?: unknown }[], random: () => number = Math.random): MapConfig {
  const s = new Map<string, string>(rows.map((r) => [String(r.setting_key), String(r.setting_value ?? '')]));
  let kakao = '';
  try {
    const list = JSON.parse(s.get('kakao_map_api_keys') || '[]');
    const keys = Array.isArray(list) ? list.map((k) => String(k ?? '').trim()).filter(Boolean) : [];
    if (keys.length) kakao = keys[Math.floor(random() * keys.length)];
  } catch { /* not JSON */ }
  if (!kakao) kakao = (s.get('kakao_map_api_key') ?? '').trim();
  const provider = (s.get('map_provider') ?? 'kakao').trim();
  return provider === 'kakao' && kakao ? { provider: 'kakao', key: kakao } : { ...OSM };
}
