import { Inject, Injectable } from '@nestjs/common';
import { DB, Db } from '../db/db.module';
import { Query } from '../hospitals/hospitals.logic';
import {
  buildList, buildSummary, countQuery, detailQuery, listQuery, MemberDetail, MemberList, MemberListQuery, MemberSummary, summaryQuery, toDetail,
} from './members.logic';

type Row = Record<string, unknown>;

/** Read-only access to the members (read-only pool). */
@Injectable()
export class MembersService {
  constructor(@Inject(DB) private readonly db: Db) {}

  private async rows(q: Query): Promise<Row[]> {
    const [rows] = (await this.db.query(q.sql, q.values)) as any;
    return Array.isArray(rows) ? rows : [];
  }

  async list(q: MemberListQuery): Promise<MemberList> {
    const [count, items] = await Promise.all([this.rows(countQuery(q)), this.rows(listQuery(q))]);
    return buildList(q, Number(count[0]?.cnt) || 0, items);
  }

  async summary(): Promise<MemberSummary> {
    return buildSummary((await this.rows(summaryQuery()))[0]);
  }

  /** null = no such member. */
  async detail(id: number): Promise<MemberDetail | null> {
    if (id <= 0) return null;
    const row = (await this.rows(detailQuery(id)))[0];
    return row ? toDetail(row) : null;
  }
}
