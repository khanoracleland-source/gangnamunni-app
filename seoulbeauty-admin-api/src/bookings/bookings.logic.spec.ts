import { inflateRawSync } from 'node:zlib';
import { buildXlsx } from './xlsx';
import { actionsFor, parseExportQuery, parseListQuery, sheetName, transitionOf, isFail } from './bookings.logic';

describe('booking flow', () => {
  it('follows pending -> checking -> confirmed -> completed, with a cancellation only while checking', () => {
    expect(transitionOf('pending', 'checking')).toBe('hospital_checking');
    expect(transitionOf('hospital_checking', 'confirm')).toBe('confirmed');
    expect(transitionOf('hospital_checking', 'cancel')).toBe('cancelled');
    expect(transitionOf('confirmed', 'complete')).toBe('completed');
    expect(transitionOf('confirmed', 'cancel')).toBeNull();
    expect(transitionOf('completed', 'confirm')).toBeNull();
    expect(actionsFor('completed')).toEqual([]);
  });
});

describe('booking queries', () => {
  it('the list takes only known filters', () => {
    expect(isFail(parseListQuery({ status: 'bogus' }))).toBe(true);
    expect(isFail(parseListQuery({ from: '2026-02-30' }))).toBe(true);
    expect(isFail(parseListQuery({ status: 'confirmed', hospital_id: '12', page: '2' }))).toBe(false);
  });
  it('the export needs a date range', () => {
    expect(isFail(parseExportQuery({}))).toBe(true);
    expect(isFail(parseExportQuery({ from: '2026-11-01', to: '2026-11-30' }))).toBe(false);
  });
  it('sheet names are short, valid and unique', () => {
    const used = new Set<string>();
    const a = sheetName('A/B:C*?[x]'.repeat(5), 12, used);
    const b = sheetName('A/B:C*?[x]'.repeat(5), 12, used);
    expect(a.length).toBeLessThanOrEqual(31);
    expect(a).not.toMatch(/[\\/:*?[\]]/);
    expect(b).not.toBe(a);
  });
});

describe('xlsx writer', () => {
  const zipEntries = (buf: Buffer): Record<string, string> => {
    const out: Record<string, string> = {};
    let o = 0;
    while (buf.readUInt32LE(o) === 0x04034b50) {
      const method = buf.readUInt16LE(o + 8);
      const csize = buf.readUInt32LE(o + 18);
      const nlen = buf.readUInt16LE(o + 26);
      const elen = buf.readUInt16LE(o + 28);
      const name = buf.subarray(o + 30, o + 30 + nlen).toString();
      const data = buf.subarray(o + 30 + nlen + elen, o + 30 + nlen + elen + csize);
      out[name] = (method === 8 ? inflateRawSync(data) : data).toString();
      o += 30 + nlen + elen + csize;
    }
    return out;
  };
  it('writes a zip with the workbook parts and keeps cell text as text, never as a formula', () => {
    const buf = buildXlsx([{ name: 'H1', headers: ['이름', '메모'], rows: [['김', '=HYPERLINK("http://x","y")'], ['<b>&', '+1']] }]);
    expect(buf.readUInt32LE(0)).toBe(0x04034b50);
    const files = zipEntries(buf);
    expect(Object.keys(files)).toEqual(expect.arrayContaining(['[Content_Types].xml', 'xl/workbook.xml']));
    const sheet = Object.entries(files).find(([k]) => k.startsWith('xl/worksheets/'))![1];
    expect(sheet).not.toContain('<f>');
    expect(sheet).toContain('inlineStr');
    expect(sheet).toContain('&lt;b&gt;&amp;');
  });
});
