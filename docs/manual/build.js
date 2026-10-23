// Genera el manual en .docx (docx-js) y .html (para PDF) desde content*.js
// Uso: npm install && npm run build   (ver README.md)
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, ImageRun, Table, TableRow, TableCell,
  HeadingLevel, AlignmentType, WidthType, BorderStyle, ShadingType, LevelFormat,
  TableOfContents, Footer, PageNumber, PageBreak, PositionalTab,
  PositionalTabAlignment, PositionalTabRelativeTo, PositionalTabLeader, TableLayoutType,
} = require('docx');

const DIR = __dirname;
const BLOCKS = [
  ...require('./content1'), ...require('./content2'),
  ...require('./content3'), ...require('./content4'),
];
const VERSION = '2.1';
const REVISION = 'Revisión octubre 2026';
const C = { dark: '26465A', teal: '2F5E6B', mid: '3A7585', light: 'E8F1F3', code: 'F3F6F8', codeBorder: '86BFD1', note: 'EAF4F7', warn: 'FFF4E0', warnBorder: 'E0A030', grid: 'C9D6DB' };
const FONT = 'Calibri';
const MONO = 'Consolas';

// ─── Marcado en línea: `codigo` y **negrita** ──────────────────────────────
function tokens(s, bold = false) {
  const out = []; const re = /(\*\*.+?\*\*|`[^`]+`)/g; let last = 0, m;
  while ((m = re.exec(s))) {
    if (m.index > last) out.push({ k: 't', v: s.slice(last, m.index), b: bold });
    const v = m[0];
    if (v.startsWith('**')) out.push(...tokens(v.slice(2, -2), true));
    else out.push({ k: 'c', v: v.slice(1, -1), b: bold });
    last = m.index + v.length;
  }
  if (last < s.length) out.push({ k: 't', v: s.slice(last), b: bold });
  return out;
}
function runs(s, base = {}) {
  return tokens(s).map(t => t.k === 'c'
    ? new TextRun({ text: t.v, font: MONO, size: 19, color: C.teal, ...base, bold: t.b || base.bold })
    : new TextRun({ text: t.v, ...base, bold: t.b || base.bold }));
}

// ─── DOCX ────────────────────────────────────────────────────────────────────
const imgSize = f => { // ancho/alto en px del PNG
  const b = fs.readFileSync(path.join(DIR, 'shots', f));
  return { w: b.readUInt32BE(16), h: b.readUInt32BE(20), b };
};
const thin = { style: BorderStyle.SINGLE, size: 4, color: C.grid };
const cellBorders = { top: thin, bottom: thin, left: thin, right: thin };

function codePara(x) {
  const lines = x.split('\n');
  const children = [];
  lines.forEach((l, i) => children.push(new TextRun({ text: l.length ? l : ' ', font: MONO, size: 17, color: '1F2937', break: i ? 1 : 0 })));
  return new Paragraph({
    children, spacing: { before: 80, after: 160, line: 252 }, keepLines: true,
    shading: { type: ShadingType.CLEAR, fill: C.code, color: 'auto' },
    border: { left: { style: BorderStyle.SINGLE, size: 18, color: C.codeBorder, space: 8 } },
    indent: { left: 120, right: 120 },
  });
}
function callout(x, warn) {
  return new Paragraph({
    children: [new TextRun({ text: warn ? 'Importante: ' : 'Nota: ', bold: true, color: warn ? '8A5A00' : C.teal }), ...runs(x)],
    spacing: { before: 120, after: 160 }, keepLines: true,
    shading: { type: ShadingType.CLEAR, fill: warn ? C.warn : C.note, color: 'auto' },
    border: { left: { style: BorderStyle.SINGLE, size: 18, color: warn ? C.warnBorder : C.mid, space: 8 } },
    indent: { left: 120, right: 120 },
  });
}
function table(b) {
  const total = b.widths.reduce((a, c) => a + c, 0);
  const hdr = new TableRow({ tableHeader: true, cantSplit: true, children: b.head.map((h, i) => new TableCell({
    width: { size: b.widths[i], type: WidthType.DXA }, borders: cellBorders,
    shading: { type: ShadingType.CLEAR, fill: C.teal, color: 'auto' },
    margins: { top: 60, bottom: 60, left: 100, right: 100 },
    children: [new Paragraph({ children: [new TextRun({ text: h, bold: true, color: 'FFFFFF' })], spacing: { after: 0 } })],
  })) });
  const rows = b.rows.map((r, ri) => new TableRow({ cantSplit: true, children: r.map((c, i) => new TableCell({
    width: { size: b.widths[i], type: WidthType.DXA }, borders: cellBorders,
    shading: ri % 2 ? { type: ShadingType.CLEAR, fill: 'F7FAFB', color: 'auto' } : undefined,
    margins: { top: 50, bottom: 50, left: 100, right: 100 },
    children: [new Paragraph({ children: runs(c), spacing: { after: 0, line: 252 } })],
  })) }));
  return [new Table({ width: { size: total, type: WidthType.DXA }, columnWidths: b.widths, layout: TableLayoutType.FIXED, rows: [hdr, ...rows] }),
    new Paragraph({ children: [], spacing: { after: 120 } })];
}
let olInstance = 0;
function docxBlocks() {
  const out = [];
  for (const b of BLOCKS) {
    switch (b.t) {
      case 'h1': out.push(new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun(b.x)] })); break;
      case 'h2': out.push(new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun(b.x)] })); break;
      case 'h3': out.push(new Paragraph({ heading: HeadingLevel.HEADING_3, children: [new TextRun(b.x)] })); break;
      case 'p': out.push(new Paragraph({ children: runs(b.x) })); break;
      case 'code': out.push(codePara(b.x)); break;
      case 'note': out.push(callout(b.x, false)); break;
      case 'warn': out.push(callout(b.x, true)); break;
      case 'ul': b.items.forEach(i => out.push(new Paragraph({ numbering: { reference: 'bul', level: 0 }, children: runs(i), spacing: { after: 60 } }))); break;
      case 'ol': olInstance++; b.items.forEach(i => out.push(new Paragraph({ numbering: { reference: 'num', level: 0, instance: olInstance }, children: runs(i), spacing: { after: 60 } }))); break;
      case 'table': out.push(...table(b)); break;
      case 'img': {
        const s = imgSize(b.f); const w = b.w; const h = Math.round(w * s.h / s.w);
        out.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 80, after: 200 }, keepNext: false,
          children: [new ImageRun({ type: 'png', data: s.b, transformation: { width: w, height: h } })] }));
        break;
      }
      case 'api':
        out.push(new Paragraph({ children: [new TextRun({ text: b.sig, font: MONO, size: 19, bold: true, color: C.dark })],
          spacing: { before: 200, after: 60 }, keepNext: true,
          shading: { type: ShadingType.CLEAR, fill: C.light, color: 'auto' }, indent: { left: 0 } }));
        out.push(new Paragraph({ children: runs(b.d), indent: { left: 240 } }));
        if (b.ex) out.push(codePara(b.ex));
        break;
      default: throw new Error('bloque desconocido ' + b.t);
    }
  }
  return out;
}

function cover() {
  const logo = fs.readFileSync(path.join(DIR, 'img', 'logo.jpeg'));
  return [
    new Paragraph({ spacing: { before: 1200 }, children: [] }),
    new Paragraph({ alignment: AlignmentType.CENTER, children: [new ImageRun({ type: 'jpg', data: logo, transformation: { width: 250, height: 264 } })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 600, after: 120 }, children: [new TextRun({ text: 'Framework de PHP Asíncrono y jQuery', size: 40, bold: true, color: C.teal })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 600 }, children: [new TextRun({ text: 'Manual de referencia', size: 32, color: C.mid })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 80 }, children: [new TextRun({ text: 'Versión ' + VERSION + '  (compatible con 2.0)', size: 26, bold: true, color: C.dark })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 1400 }, children: [new TextRun({ text: REVISION, size: 22, color: '555555' })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 }, children: [new TextRun({ text: 'Ricardo Seiffert', size: 22, bold: true })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'seifox@hotmail.es  ·  github.com/seifox/APJ2', size: 20, color: '555555' })] }),
    new Paragraph({ children: [new PageBreak()] }),
    new Paragraph({ children: [new TextRun({ text: 'Contenido', size: 36, bold: true, color: C.teal })], spacing: { after: 240 } }),
    new TableOfContents('Contenido', { hyperlink: true, headingStyleRange: '1-2' }),
  ];
}

async function buildDocx(out) {
  const doc = new Document({
    creator: 'Ricardo Seiffert', title: 'APJ ' + VERSION + ' - Manual de referencia',
    features: { updateFields: true },
    styles: {
      default: { document: { run: { font: FONT, size: 21 }, paragraph: { spacing: { after: 120, line: 276 } } } },
      paragraphStyles: [
        { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true,
          run: { size: 36, bold: true, color: C.teal, font: FONT },
          paragraph: { spacing: { before: 0, after: 240 }, outlineLevel: 0, keepNext: true,
            border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: C.codeBorder, space: 4 } } } },
        { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true,
          run: { size: 28, bold: true, color: C.mid, font: FONT },
          paragraph: { spacing: { before: 360, after: 120 }, outlineLevel: 1, keepNext: true } },
        { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true,
          run: { size: 24, bold: true, color: C.dark, font: FONT },
          paragraph: { spacing: { before: 240, after: 80 }, outlineLevel: 2, keepNext: true } },
      ],
    },
    numbering: { config: [
      { reference: 'bul', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 270 } } } }] },
      { reference: 'num', levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 300 } } } }] },
    ] },
    sections: [{
      properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1134, bottom: 1134, left: 1134, right: 1134, footer: 567 } }, titlePage: true },
      footers: {
        default: new Footer({ children: [new Paragraph({ children: [
          new TextRun({ text: 'APJ ' + VERSION + ' · Manual de referencia', size: 16, color: '777777' }),
          new TextRun({ children: [new PositionalTab({ alignment: PositionalTabAlignment.RIGHT, relativeTo: PositionalTabRelativeTo.MARGIN, leader: PositionalTabLeader.NONE }), 'Página ', PageNumber.CURRENT], size: 16, color: '777777' }),
        ] })] }),
        first: new Footer({ children: [new Paragraph({ children: [] })] }),
      },
      children: [...cover(), ...docxBlocks()],
    }],
  });
  fs.writeFileSync(out, await Packer.toBuffer(doc));
}

// ─── HTML (para PDF) ─────────────────────────────────────────────────────────
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const inl = s => tokens(s).map(t => { const h = t.k === 'c' ? `<code>${esc(t.v)}</code>` : esc(t.v); return t.b ? `<b>${h}</b>` : h; }).join('');
function buildHtml(out) {
  let n1 = 0, n2 = 0; const toc = []; const body = [];
  for (const b of BLOCKS) {
    switch (b.t) {
      case 'h1': n1++; n2 = 0; toc.push(`<li class="t1"><a href="#s${n1}">${esc(b.x)}</a></li>`); body.push(`<h1 id="s${n1}">${esc(b.x)}</h1>`); break;
      case 'h2': n2++; toc.push(`<li class="t2"><a href="#s${n1}_${n2}">${esc(b.x)}</a></li>`); body.push(`<h2 id="s${n1}_${n2}">${esc(b.x)}</h2>`); break;
      case 'h3': body.push(`<h3>${esc(b.x)}</h3>`); break;
      case 'p': body.push(`<p>${inl(b.x)}</p>`); break;
      case 'code': body.push(`<pre>${esc(b.x)}</pre>`); break;
      case 'note': body.push(`<div class="note"><b>Nota:</b> ${inl(b.x)}</div>`); break;
      case 'warn': body.push(`<div class="warn"><b>Importante:</b> ${inl(b.x)}</div>`); break;
      case 'ul': body.push('<ul>' + b.items.map(i => `<li>${inl(i)}</li>`).join('') + '</ul>'); break;
      case 'ol': body.push('<ol>' + b.items.map(i => `<li>${inl(i)}</li>`).join('') + '</ol>'); break;
      case 'table': {
        const tot = b.widths.reduce((a, c) => a + c, 0);
        body.push('<table><colgroup>' + b.widths.map(w => `<col style="width:${(100 * w / tot).toFixed(1)}%">`).join('') + '</colgroup><thead><tr>' +
          b.head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr></thead><tbody>' +
          b.rows.map(r => '<tr>' + r.map(c => `<td>${inl(c)}</td>`).join('') + '</tr>').join('') + '</tbody></table>');
        break;
      }
      case 'img': body.push(`<p class="img"><img src="shots/${b.f}" style="width:${b.w * 0.75}pt"></p>`); break;
      case 'api': body.push(`<div class="api"><div class="sig">${esc(b.sig)}</div><p>${inl(b.d)}</p>${b.ex ? `<pre>${esc(b.ex)}</pre>` : ''}</div>`); break;
    }
  }
  const html = `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>APJ ${VERSION} - Manual de referencia</title><style>
@page { size: A4; margin: 2cm 2cm 2.2cm 2cm; }
body { font-family: Calibri, "Segoe UI", Arial, sans-serif; font-size: 10.5pt; line-height: 1.38; color: #1f2937; }
h1 { font-size: 18pt; color: #${C.teal}; border-bottom: 1.5px solid #${C.codeBorder}; padding-bottom: 4pt; margin: 0 0 12pt; break-before: page; }
h2 { font-size: 14pt; color: #${C.mid}; margin: 18pt 0 6pt; break-after: avoid; }
h3 { font-size: 12pt; color: #${C.dark}; margin: 12pt 0 4pt; break-after: avoid; }
p { margin: 0 0 6pt; } ul, ol { margin: 0 0 8pt; padding-left: 22pt; } li { margin-bottom: 3pt; }
code { font-family: Consolas, monospace; font-size: 9.5pt; color: #${C.teal}; }
pre { font-family: Consolas, monospace; font-size: 8.5pt; line-height: 1.32; background: #${C.code}; border-left: 3px solid #${C.codeBorder}; padding: 6pt 8pt; margin: 4pt 0 9pt; white-space: pre-wrap; break-inside: avoid; color: #1f2937; }
.note, .warn { padding: 6pt 8pt; margin: 6pt 0 9pt; border-left: 3px solid #${C.mid}; background: #${C.note}; break-inside: avoid; }
.note b { color: #${C.teal}; } .warn { background: #${C.warn}; border-left-color: #${C.warnBorder}; } .warn > b:first-child { color: #8a5a00; }
table { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt; table-layout: fixed; }
th { background: #${C.teal}; color: #fff; text-align: left; padding: 3pt 5pt; font-size: 10pt; }
td { border: 0.5pt solid #${C.grid}; padding: 3pt 5pt; vertical-align: top; font-size: 10pt; overflow-wrap: anywhere; }
tr { break-inside: avoid; } tbody tr:nth-child(even) td { background: #f7fafb; }
.api { margin: 10pt 0 6pt; } .api .sig { font-family: Consolas, monospace; font-size: 9.5pt; font-weight: bold; color: #${C.dark}; background: #${C.light}; padding: 2pt 5pt; break-after: avoid; } .api > p { margin-left: 12pt; }
.img { text-align: center; margin: 4pt 0 12pt; } .img img { max-width: 100%; }
.cover { text-align: center; height: 24cm; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.cover img { width: 190pt; } .cover .t { font-size: 20pt; font-weight: bold; color: #${C.teal}; margin-top: 30pt; }
.cover .s { font-size: 16pt; color: #${C.mid}; margin: 6pt 0 30pt; } .cover .v { font-size: 13pt; font-weight: bold; color: #${C.dark}; }
.cover .r { color: #555; margin-bottom: 70pt; } .cover .a { font-weight: bold; } .cover .m { color: #555; font-size: 10pt; }
.toc { break-before: page; } .toc h1 { break-before: auto; } .toc ul { list-style: none; padding: 0; }
.toc .t1 { font-weight: bold; margin-top: 6pt; } .toc .t2 { margin-left: 16pt; } .toc a { color: #1f2937; text-decoration: none; }
a { color: #${C.mid}; }
</style></head><body>
<div class="cover"><img src="img/logo.jpeg"><div class="t">Framework de PHP Asíncrono y jQuery</div><div class="s">Manual de referencia</div>
<div class="v">Versión ${VERSION}  (compatible con 2.0)</div><div class="r">${REVISION}</div><div class="a">Ricardo Seiffert</div><div class="m">seifox@hotmail.es · github.com/seifox/APJ2</div></div>
<div class="toc"><h1>Contenido</h1><ul>${toc.join('')}</ul></div>
${body.join('\n')}
</body></html>`;
  fs.writeFileSync(out, html);
}

(async () => {
  await buildDocx(path.join(DIR, 'manual.docx'));
  buildHtml(path.join(DIR, 'manual.html'));
  console.log('ok', BLOCKS.length, 'bloques');
})();
