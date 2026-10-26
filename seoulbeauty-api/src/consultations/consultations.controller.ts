import { BadRequestException, Body, Controller, HttpException, Inject, Post, Req, Res, UseGuards } from '@nestjs/common';
import type { Response } from 'express';
import { createHash } from 'node:crypto';
import { DB, Db } from '../db/db.module';
import { AuthGuard, AuthedRequest } from '../auth/auth.guard';
import { SameOriginGuard } from '../common/same-origin.guard';

export interface ConsultationInput {
  hospitalId: number;
  type: 'general' | 'doctor' | 'kakao';
  name: string;
  phone: string;
  /** Free text; the client prepends the chip answers like detail.php does. */
  message: string;
}

const TYPES = new Set(['general', 'doctor', 'kakao']);
const PHONE = /^[0-9+()\-\s]{7,30}$/;

/**
 * Mirrors consultation_submit.php: guests may submit (name+phone), members get mb_id linked,
 * 5 requests / 10 min per session, status 'pending'. Photo upload is NOT ported yet (see docs).
 */
@Controller('v1/consultations')
export class ConsultationsController {
  private attempts = new Map<string, number[]>();
  private mbIdColumn: boolean | null = null;

  constructor(@Inject(DB) private readonly db: Db) {}

  private rateKey(req: AuthedRequest): string {
    const base = req.session.user?.id || req.headers.cookie || req.ip || 'anon';
    return createHash('sha256').update(String(base)).digest('hex');
  }

  private checkRate(key: string) {
    const now = Date.now();
    const list = (this.attempts.get(key) ?? []).filter((t) => t > now - 600_000);
    if (list.length >= 5) throw new HttpException({ success: false, message: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' }, 429);
    list.push(now);
    this.attempts.set(key, list);
    if (this.attempts.size > 10_000) this.attempts.clear();
  }

  private async hasMbIdColumn(): Promise<boolean> {
    if (this.mbIdColumn === null) {
      const [r] = (await this.db.query("SHOW COLUMNS FROM gangnamunni_consultations LIKE 'mb_id'")) as any;
      this.mbIdColumn = r.length > 0;
    }
    return this.mbIdColumn;
  }

  @Post()
  @UseGuards(SameOriginGuard, AuthGuard)
  async create(@Body() body: Partial<ConsultationInput>, @Req() req: AuthedRequest, @Res({ passthrough: true }) res: Response) {
    res.setHeader('Cache-Control', 'private, no-store');
    const hospitalId = Number(body?.hospitalId);
    const type = String(body?.type ?? 'doctor');
    const name = String(body?.name ?? '').trim();
    const phone = String(body?.phone ?? '').trim();
    const message = String(body?.message ?? '').trim();
    if (!Number.isInteger(hospitalId) || hospitalId <= 0) throw new BadRequestException('병원 정보가 없습니다.');
    if (!name || !phone) throw new BadRequestException('이름과 연락처를 입력해주세요.');
    if (!TYPES.has(type) || name.length > 100 || phone.length > 30 || message.length > 3000 || !PHONE.test(phone)) {
      throw new BadRequestException('입력한 내용을 확인해주세요.');
    }
    this.checkRate(this.rateKey(req));

    const [h] = (await this.db.query('SELECT name FROM gangnamunni_hospitals WHERE id = ? LIMIT 1', [hospitalId])) as any;
    const hospitalName = h[0]?.name;
    if (!hospitalName) throw new BadRequestException('병원 정보를 확인할 수 없습니다.');

    const [ins] = (await this.db.query(
      `INSERT INTO gangnamunni_consultations (hospital_id, hospital_name, consultation_type, name, phone, message, photo_urls, photo_consent, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, NULL, 0, 'pending', NOW())`,
      [hospitalId, hospitalName, type, name, phone, message],
    )) as any;
    const id = Number(ins?.insertId ?? 0);

    const mbId = req.session.loggedIn ? req.session.user?.id : undefined;
    if (id && mbId && (await this.hasMbIdColumn())) {
      await this.db.query('UPDATE gangnamunni_consultations SET mb_id = ? WHERE id = ?', [mbId, id]);
    }
    return { success: true, id, message: '상담 신청이 접수되었습니다.' };
  }
}
