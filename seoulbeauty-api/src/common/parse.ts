/** Small, dependency-free query parsing helpers (all inputs are untrusted). */
import { BadRequestException } from '@nestjs/common';

export function str(v: unknown, max = 100): string {
  if (typeof v !== 'string') return '';
  return v.trim().slice(0, max);
}

export function intParam(v: unknown, def: number, min: number, max: number, name = 'param'): number {
  if (v === undefined || v === null || v === '') return def;
  const n = Number(v);
  if (!Number.isInteger(n) || n < min || n > max) {
    throw new BadRequestException(`${name} must be an integer between ${min} and ${max}`);
  }
  return n;
}

export function idParam(v: string): number {
  const n = Number(v);
  if (!Number.isInteger(n) || n < 1 || n > 2147483647) throw new BadRequestException('invalid id');
  return n;
}

/** Escape LIKE wildcards so user input is matched literally. */
export function likeEscape(s: string): string {
  return s.replace(/[\\%_]/g, (m) => '\\' + m);
}

export function jsonArray(v: unknown): string[] {
  if (v == null || v === '') return [];
  try {
    const parsed = typeof v === 'string' ? JSON.parse(v) : v;
    return Array.isArray(parsed) ? parsed.map((x) => String(x)) : [];
  } catch {
    return [];
  }
}
