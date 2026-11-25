import { ConsultationNotifyService } from './consultations.notify.service';
import { noticePayload, noticeEventOf, eventKindOf } from './consultations.logic';

type Row = Record<string, unknown>;

describe('consultation notices', () => {
  let consultation: Row | null;
  let memberRows: Row[];
  let fcmSetting: Row[];
  let tokens: Row[];
  let sendOk: boolean;
  let sendDead: boolean;
  let inAppFails: boolean;
  const roCalls: { sql: string; values: unknown[] }[] = [];
  const rwCalls: { sql: string; values: unknown[] }[] = [];
  const sendMessage = jest.fn();

  const ro = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      roCalls.push({ sql, values });
      if (sql.includes('FROM gangnamunni_consultations')) return [consultation ? [consultation] : []];
      if (sql.includes('FROM g5_member')) return [memberRows];
      if (sql.includes('gangnamunni_theme_settings')) return [fcmSetting];
      if (sql.includes('FROM g5_push_tokens')) return [tokens];
      return [[]];
    }),
  };
  const rwPool = {
    query: jest.fn(async (sql: string, values: unknown[] = []) => {
      rwCalls.push({ sql, values });
      if (inAppFails && sql.startsWith('INSERT')) throw new Error('insert denied');
      return [{ affectedRows: 1 }];
    }),
  };
  const fcm = { session: jest.fn(async () => ({ token: 't' })), sendMessage };
  const make = (rw: unknown = { get: () => rwPool }) => new ConsultationNotifyService(ro as any, rw as any, fcm as any);

  beforeEach(() => {
    consultation = { hospital_name: '청담병원', mb_id: 'member1', phone: '010-1234-5678' };
    memberRows = [{ mb_id: 'byphone' }];
    fcmSetting = [{ setting_value: '1' }];
    tokens = [{ pt_token: 'tok1', pt_platform: 'android', pt_device_info: '' }];
    sendOk = true; sendDead = false; inAppFails = false;
    roCalls.length = 0; rwCalls.length = 0;
    sendMessage.mockReset();
    sendMessage.mockImplementation(async () => (sendOk ? { ok: true } : { ok: false, dead: sendDead }));
  });

  it('the notice texts and pages are the ones of the PHP page; completed and rejected tell nobody', () => {
    expect(noticePayload('approved', '청담병원 ')).toEqual({ title: '상담신청이 확인되었습니다', body: '청담병원', link: 'my_appointments.php' });
    expect(noticePayload('contact_failed', '청담병원')).toEqual({ title: '병원에서 연락드렸지만 받지 않으셨어요', body: '청담병원 · 편한 시간을 골라 다시 요청해주세요.', link: 'my_consultations.php' });
    expect(['approved', 'contact_failed', 'completed', 'rejected', 'pending'].map(noticeEventOf)).toEqual(['approved', 'contact_failed', null, null, null]);
    expect(['completed', 'contact_failed', 'approved'].map(eventKindOf)).toEqual(['completed', 'no_answer', null]);
  });

  it('an in-app notice and a push for the member of the request (general type, absolute link)', async () => {
    const r = await make().notify(9, 'contact_failed');
    expect(r).toMatchObject({ inApp: true, pushSent: 1, pushFailed: 0 });
    const ins = rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_user_notifications'))!;
    expect(ins.values).toEqual(['member1', 'general', '병원에서 연락드렸지만 받지 않으셨어요', '청담병원 · 편한 시간을 골라 다시 요청해주세요.', 'https://seoulbeauty.app/my_consultations.php', 'bi-bell']);
    const msg = sendMessage.mock.calls[0][1].message;
    expect(msg.token).toBe('tok1');
    expect(msg.data).toMatchObject({ type: 'member_notification', url: 'https://seoulbeauty.app/my_consultations.php', webview_url: 'https://seoulbeauty.app/my_consultations.php' });
    expect(roCalls.every((c) => !c.sql.includes('g5_member'))).toBe(true);
  });

  it('a request made without signing in is matched to a member by phone number; no member, no notice', async () => {
    consultation = { hospital_name: '청담병원', mb_id: null, phone: '010-1234-5678' };
    const r = await make().notify(9, 'approved');
    expect(r.inApp).toBe(true);
    expect(roCalls.find((c) => c.sql.includes('FROM g5_member'))!.values).toEqual(['01012345678', '821012345678']);
    expect(rwCalls.find((c) => c.sql.startsWith('INSERT INTO gangnamunni_user_notifications'))!.values[0]).toBe('byphone');
    rwCalls.length = 0; memberRows = [];
    expect((await make().notify(9, 'approved')).reason).toBe('member_not_found');
    consultation = { hospital_name: 'x', mb_id: '', phone: 'abc' };
    expect((await make().notify(9, 'approved')).reason).toBe('member_not_found');
    expect(rwCalls).toHaveLength(0);
  });

  it('push switched off, no device, a push that fails, a token FCM reports dead: the in-app notice stays, nothing throws', async () => {
    fcmSetting = [{ setting_value: '0' }];
    expect(await make().notify(9, 'approved')).toMatchObject({ inApp: true, pushSent: 0, reason: 'fcm_disabled' });
    fcmSetting = [{ setting_value: '1' }]; tokens = [];
    expect(await make().notify(9, 'approved')).toMatchObject({ inApp: true, reason: 'no_device_tokens' });
    tokens = [{ pt_token: 'tok1', pt_platform: '', pt_device_info: 'iPhone' }]; sendOk = false; sendDead = true;
    rwCalls.length = 0;
    expect(await make().notify(9, 'approved')).toMatchObject({ pushSent: 0, pushFailed: 1 });
    expect(rwCalls.some((c) => c.sql.startsWith('UPDATE g5_push_tokens SET pt_use = 0') && c.values[0] === 'tok1')).toBe(true);
    expect(sendMessage.mock.calls[0][1].message.apns).toBeDefined();
  });

  it('a refused in-app insert or an unconfigured write user does not stop the push or throw', async () => {
    inAppFails = true;
    expect(await make().notify(9, 'approved')).toMatchObject({ inApp: false, pushSent: 1 });
    expect((await make({ get: () => null }).notify(9, 'approved')).reason).toBe('write_unavailable');
    consultation = null;
    expect((await make().notify(9, 'approved')).reason).toBe('consultation_not_found');
  });

  it('later() never throws, whatever goes wrong', async () => {
    const bad = new ConsultationNotifyService({ query: async () => { throw new Error('db down'); } } as any, { get: () => rwPool } as any, fcm as any);
    expect(() => bad.later(9, 'approved')).not.toThrow();
    await new Promise((r) => setImmediate(r));
  });
});
