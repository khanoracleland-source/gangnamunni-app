import { reportDetailText } from './report-detail';

describe('reportDetailText', () => {
  it('app JSON becomes the labels, then the typed detail', () => {
    expect(reportDetailText('{"version":1,"reasons":["sexual"],"labels":["음란성/선정성"],"detail":""}')).toBe('음란성/선정성');
    expect(reportDetailText('{"version":1,"reasons":["sexual","other"],"labels":["음란성/선정성","기타"],"detail":" 광고 같아요 "}')).toBe('음란성/선정성, 기타 — 광고 같아요');
  });
  it('older plain text, empty and broken input pass through', () => {
    expect(reportDetailText('rude')).toBe('rude');
    expect(reportDetailText(null)).toBe('');
    expect(reportDetailText('{not json')).toBe('{not json');
    expect(reportDetailText('{"a":1}')).toBe('{"a":1}');
  });
  it('the result is cut to the limit', () => {
    expect(reportDetailText('x'.repeat(400))).toHaveLength(300);
    expect(reportDetailText('{"labels":["' + 'y'.repeat(400) + '"],"detail":""}')).toHaveLength(300);
  });
});
