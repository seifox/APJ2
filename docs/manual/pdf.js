// Convierte manual.html a manual.pdf con Microsoft Edge (sin ventana) vía playwright-core
const { chromium } = require('playwright-core');
const path = require('path'), url = require('url');
(async () => {
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const p = await b.newPage();
  await p.goto(url.pathToFileURL(path.join(__dirname, 'manual.html')).href, { waitUntil: 'load' });
  await p.pdf({ path: path.join(__dirname, 'manual.pdf'), format: 'A4', printBackground: true, displayHeaderFooter: true,
    headerTemplate: '<span></span>',
    footerTemplate: '<div style="width:100%;font-size:8pt;color:#777;padding:0 2cm;display:flex;justify-content:space-between;font-family:Calibri,Arial"><span>APJ 2.1 · Manual de referencia</span><span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span></div>',
    margin: { top: '2cm', bottom: '2.2cm', left: '2cm', right: '2cm' } });
  await b.close();
})();
