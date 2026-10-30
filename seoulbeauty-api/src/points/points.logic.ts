/** my_points.php: the ledger types, their labels and the way a row is shown. */
const CREDIT = ['earn', 'refund', 'admin_add', 'admin'];
export const isCredit = (type: string): boolean => CREDIT.includes(type);

const TYPE_LABEL: Record<string, string> = {
  earn: '적립', admin_add: '지급', admin: '지급', refund: '환불', use: '사용', admin_sub: '차감', expire: '소멸', earn_cancel: '회수',
};

export interface PointLog { id: number; kind: 'earn' | 'use'; type: string; label: string; reason: string; meta: string; amount: number; balance: number | null }

const str = (v: unknown): string => (v == null ? '' : String(v));

/** One ledger row: credit/debit, the tag, the reason (falls back to the tag), "mm.dd HH:ii[ · 사용 기한 YYYY.mm.dd]". */
export function logItem(row: Record<string, unknown>): PointLog {
  const type = str(row.type);
  const credit = isCredit(type);
  const label = TYPE_LABEL[type] ?? (credit ? '적립' : '사용');
  const at = str(row.created_at);
  let meta = at.length >= 16 ? `${at.slice(5, 7)}.${at.slice(8, 10)} ${at.slice(11, 16)}` : '';
  const exp = str(row.expires_at).slice(0, 10);
  if (credit && /^\d{4}-\d{2}-\d{2}$/.test(exp)) meta += ` · 사용 기한 ${exp.replace(/-/g, '.')}`;
  return {
    id: Number(row.id) || 0,
    kind: credit ? 'earn' : 'use',
    type, label,
    reason: str(row.reason).trim() || label,
    meta,
    amount: Math.abs(Number(row.amount) || 0),
    balance: row.balance == null ? null : Number(row.balance) || 0,
  };
}

/** FIFO estimate when no lots exist: credits expiring up to the date minus every debit, capped by the balance. */
export const fifoExpiring = (balance: number, credits: number, debits: number): number => Math.min(balance, Math.max(0, credits - debits));
