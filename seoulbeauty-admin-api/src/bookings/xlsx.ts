import { crc32, deflateRawSync } from 'node:zlib';

export interface Sheet { name: string; headers: string[]; rows: (string | number)[][] }

/** XML 1.0 forbids most control characters; they are dropped, the five markup characters escaped. */
const xml = (v: string): string =>
  v.replace(/[^\u0009\u000a\u000d -퟿-�\u{10000}-\u{10ffff}]/gu, '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[c] as string);

/** A, B, ... Z, AA ... (zero based). */
const col = (i: number): string => {
  let n = i + 1;
  let out = '';
  while (n > 0) { const r = (n - 1) % 26; out = String.fromCharCode(65 + r) + out; n = Math.floor((n - 1) / 26); }
  return out;
};

/** Every text cell is stored as plain (inline) text, so nothing a guest typed can ever be evaluated as a formula. */
function sheetXml(sh: Sheet): string {
  const cell = (v: string | number, r: number, c: number, style: number): string => {
    const ref = `${col(c)}${r}`;
    if (typeof v === 'number' && Number.isFinite(v)) return `<c r="${ref}"${style ? ` s="${style}"` : ''}><v>${v}</v></c>`;
    const t = String(v);
    return t === '' ? '' : `<c r="${ref}" t="inlineStr"${style ? ` s="${style}"` : ''}><is><t xml:space="preserve">${xml(t)}</t></is></c>`;
  };
  const head = `<row r="1">${sh.headers.map((h, c) => cell(h, 1, c, 1)).join('')}</row>`;
  const body = sh.rows.map((row, i) => `<row r="${i + 2}">${row.map((v, c) => cell(v, i + 2, c, 0)).join('')}</row>`).join('');
  const cols = sh.headers.map((_, c) => `<col min="${c + 1}" max="${c + 1}" width="${c === 0 ? 10 : 18}" customWidth="1"/>`).join('');
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>${cols}</cols><sheetData>${head}${body}</sheetData></worksheet>`;
}

interface Entry { name: string; data: Buffer }

/** Minimal ZIP (deflate) writer: enough for an .xlsx package. */
function zip(entries: Entry[]): Buffer {
  const local: Buffer[] = [];
  const central: Buffer[] = [];
  let offset = 0;
  for (const e of entries) {
    const name = Buffer.from(e.name, 'utf8');
    const packed = deflateRawSync(e.data);
    const crc = crc32(e.data) >>> 0;
    const h = Buffer.alloc(30);
    h.writeUInt32LE(0x04034b50, 0); h.writeUInt16LE(20, 4); h.writeUInt16LE(0x0800, 6); h.writeUInt16LE(8, 8);
    h.writeUInt16LE(0, 10); h.writeUInt16LE(0x21, 12); h.writeUInt32LE(crc, 14);
    h.writeUInt32LE(packed.length, 18); h.writeUInt32LE(e.data.length, 22); h.writeUInt16LE(name.length, 26); h.writeUInt16LE(0, 28);
    local.push(h, name, packed);
    const c = Buffer.alloc(46);
    c.writeUInt32LE(0x02014b50, 0); c.writeUInt16LE(20, 4); c.writeUInt16LE(20, 6); c.writeUInt16LE(0x0800, 8); c.writeUInt16LE(8, 10);
    c.writeUInt16LE(0, 12); c.writeUInt16LE(0x21, 14); c.writeUInt32LE(crc, 16);
    c.writeUInt32LE(packed.length, 20); c.writeUInt32LE(e.data.length, 24); c.writeUInt16LE(name.length, 28);
    c.writeUInt32LE(offset, 42);
    central.push(c, name);
    offset += h.length + name.length + packed.length;
  }
  const cdSize = central.reduce((n, b) => n + b.length, 0);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0); end.writeUInt16LE(entries.length, 8); end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(cdSize, 12); end.writeUInt32LE(offset, 16);
  return Buffer.concat([...local, ...central, end]);
}

export function buildXlsx(sheets: Sheet[]): Buffer {
  const files: Entry[] = [
    { name: '[Content_Types].xml', data: Buffer.from(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>${sheets.map((_, i) => `<Override PartName="/xl/worksheets/sheet${i + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('')}</Types>`) },
    { name: '_rels/.rels', data: Buffer.from('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>') },
    { name: 'xl/workbook.xml', data: Buffer.from(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>${sheets.map((s, i) => `<sheet name="${xml(s.name)}" sheetId="${i + 1}" r:id="rId${i + 1}"/>`).join('')}</sheets></workbook>`) },
    { name: 'xl/_rels/workbook.xml.rels', data: Buffer.from(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">${sheets.map((_, i) => `<Relationship Id="rId${i + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${i + 1}.xml"/>`).join('')}<Relationship Id="rId${sheets.length + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>`) },
    { name: 'xl/styles.xml', data: Buffer.from('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>') },
    ...sheets.map((s, i) => ({ name: `xl/worksheets/sheet${i + 1}.xml`, data: Buffer.from(sheetXml(s), 'utf8') })),
  ];
  return zip(files);
}
