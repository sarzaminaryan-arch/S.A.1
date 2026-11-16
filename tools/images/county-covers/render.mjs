import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';
import sharp from 'sharp';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../../..');
const THEME = path.join(ROOT, 'wp-content/themes/sarzaminaryan-child');
const OUTPUT = path.join(HERE, 'outputs');
const META = path.join(HERE, 'metadata.json');
const VAZIR = path.join(HERE, 'node_modules/@fontsource/vazirmatn/files/vazirmatn-arabic-900-normal.woff2');
const VAZIR_REGULAR = path.join(HERE, 'node_modules/@fontsource/vazirmatn/files/vazirmatn-arabic-400-normal.woff2');
const ORBITRON = path.join(HERE, 'node_modules/@fontsource/orbitron/files/orbitron-latin-600-normal.woff2');
const LOGO = path.join(THEME, 'assets/img/brand/sarzaminaryan-logo-original.webp');

const b64 = (file) => fs.readFileSync(file).toString('base64');
const fontFa = b64(VAZIR);
const fontFaRegular = b64(VAZIR_REGULAR);
const fontEn = b64(ORBITRON);
const logo = await sharp(LOGO).trim().resize({ width: 600, withoutEnlargement: true }).png().toBuffer();
const logoData = logo.toString('base64');

function htmlFor(item) {
  const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  return `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>
@font-face{font-family:Vazirmatn;src:url(data:font/woff2;base64,${fontFaRegular}) format('woff2');font-weight:400}
@font-face{font-family:Vazirmatn;src:url(data:font/woff2;base64,${fontFa}) format('woff2');font-weight:900}
@font-face{font-family:Orbitron;src:url(data:font/woff2;base64,${fontEn}) format('woff2');font-weight:600}
*{box-sizing:border-box}html,body{margin:0;width:600px;height:400px;overflow:hidden}
body{display:flex;align-items:center;justify-content:center;color:#fff;background-color:#05070d;background-image:radial-gradient(ellipse at center,rgba(0,240,255,.08),transparent 60%),linear-gradient(rgba(0,240,255,.10) 1px,transparent 1px),linear-gradient(90deg,rgba(0,240,255,.10) 1px,transparent 1px),linear-gradient(rgba(0,240,255,.22) 1px,transparent 1px),linear-gradient(90deg,rgba(0,240,255,.22) 1px,transparent 1px);background-size:auto,40px 40px,40px 40px,200px 200px,200px 200px;background-position:center}
.card{position:relative;width:500px;padding:35px 30px;border:1px solid #1a2942;border-radius:12px;background:linear-gradient(160deg,#0a0f1e,#0d1424 60%,#0a0f1e);box-shadow:0 10px 50px rgba(0,0,0,.8);text-align:center}
.card:before{content:"";position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#00f0ff,transparent);box-shadow:0 0 10px rgba(0,240,255,.7)}
.code-badge{position:absolute;top:20px;left:20px;direction:ltr;display:flex;align-items:center;gap:10px;padding:6px 9px;border:1px solid rgba(0,240,255,.4);border-radius:4px;background:rgba(0,240,255,.08);color:#00f0ff;font:600 14px Orbitron, sans-serif;letter-spacing:1px}
.code-badge .divider{height:14px;width:1px;background:rgba(0,240,255,.55)}
.title-group{display:flex;flex-direction:column;align-items:center;gap:9px;margin-top:24px}.fa{font-family:Vazirmatn,sans-serif;font-weight:900;line-height:1.35;white-space:nowrap;color:#fff;text-shadow:0 0 25px rgba(0,240,255,.5);max-width:100%}.en{direction:ltr;font:600 16px Orbitron,sans-serif;letter-spacing:6px;text-transform:uppercase;color:#5a7a99}
.rule{width:70%;height:1px;margin:21px auto 16px;background:linear-gradient(90deg,transparent,rgba(0,240,255,.75),transparent)}.slogan{direction:rtl;font:400 18px Vazirmatn,sans-serif;color:#7fa3c2;white-space:nowrap}.slogan b{direction:ltr;unicode-bidi:isolate;color:#00f0ff;font:600 14px Orbitron,sans-serif;margin-left:7px}
.site-logo{position:absolute;bottom:15px;right:15px;width:60px;height:auto;opacity:.8}
</style></head><body><main class="card"><div class="code-badge"><span>${escape(item.province_code)}</span><i class="divider"></i><span>${escape(item.county_code)}</span></div><div class="title-group"><div id="fa" class="fa">${escape(item.name_fa)}</div><div class="en">${escape(item.name_en)}</div></div><div class="rule"></div><div class="slogan"><b>//</b>${escape(item.slogan)}</div><img class="site-logo" src="data:image/png;base64,${logoData}" alt="سرزمین آریان"></main><script>document.fonts.ready.then(()=>{const e=document.getElementById('fa'),c=document.createElement('canvas').getContext('2d');let z=56;while(z>35){c.font='900 '+z+'px Vazirmatn';if(c.measureText(e.textContent).width<=436)break;z-=1}e.style.fontSize=z+'px'});</script></body></html>`;
}

const args = process.argv.slice(2);
const only = args.length ? new Set(args) : null;
const metadata = JSON.parse(fs.readFileSync(META, 'utf8'));
if (only) {
  const known = new Set(metadata.counties.map((item) => item.slug));
  const unknown = [...only].filter((slug) => !known.has(slug));
  if (unknown.length) throw new Error(`Unknown county slug(s): ${unknown.join(', ')}`);
}
fs.mkdirSync(OUTPUT, { recursive: true });
let browser;
let renderedCount = 0;
try {
  process.env.AWS_LAMBDA_JS_RUNTIME ||= 'nodejs22.x';
  const executablePath = process.env.CHROME_PATH || await chromium.executablePath();
  browser = await puppeteer.launch({ args: process.env.CHROME_PATH ? ['--no-sandbox', '--disable-setuid-sandbox'] : chromium.args, executablePath, headless: true, defaultViewport: { width: 600, height: 400, deviceScaleFactor: 2 } });
  const page = await browser.newPage();
  for (const item of metadata.counties) {
    if (only && !only.has(item.slug)) continue;
    await page.setContent(htmlFor(item), { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    const png = await page.screenshot({ type: 'png', omitBackground: false });
    const output = path.join(OUTPUT, `${item.slug}.webp`);
    const result = await sharp(png).webp({ quality: 82, effort: 6, smartSubsample: true }).toFile(output);
    if (result.width !== 1200 || result.height !== 800) throw new Error(`${item.slug} rendered ${result.width}x${result.height}; expected 1200x800.`);
    renderedCount += 1;
    if (only) console.log(`Rendered ${item.slug} (${item.name_fa})`);
  }
} finally {
  if (browser) await browser.close();
}
console.log(`Render complete: ${renderedCount} item(s); output=${OUTPUT}`);
