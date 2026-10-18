/* Offline phase 6: PHP fixture includes only synthetic source data.
 * php tests/test-article-sources.php --fixture > /PRIVATE/fixture.html
 * SA_SOURCES_FIXTURE=/PRIVATE/fixture.html NODE_PATH=/QA/node_modules
 * CHROMIUM_PATH=/path/to/chromium node tests/test-article-sources.cjs
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const theme = path.resolve(__dirname, '..');
assert(process.env.SA_SOURCES_FIXTURE, 'provide PHP-generated synthetic fixture');
const fixture = fs.readFileSync(process.env.SA_SOURCES_FIXTURE, 'utf8');
assert(fixture.startsWith('<!doctype html>'), 'fixture has no preceding PHP errors');
(async () => {
 const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
 try {
  const page = await browser.newPage();
  await page.route('**/*', route => route.abort());
  await page.setContent(fixture);
  for (const name of ['../sarzaminaryan/assets/css/main.css', '../sarzaminaryan/assets/css/main-rtl.css', 'assets/css/child.css']) {
   await page.addStyleTag({ content: fs.readFileSync(path.join(theme, name), 'utf8') });
  }
  const font = fs.readFileSync(path.join(theme, '../sarzaminaryan/assets/fonts/fa/Vazirmatn-Regular.woff2')).toString('base64');
  await page.addStyleTag({ content: `@font-face{font-family:sa_test;src:url(data:font/woff2;base64,${font})}body{font-family:sa_test,sans-serif}` });
  await page.evaluate(() => document.fonts.ready);
  for (const width of [280, 320, 360, 390, 430, 600, 768, 1024, 1440]) {
   await page.setViewportSize({ width, height: 1000 });
   const result = await page.evaluate(() => {
    const article = document.querySelector('article');
    const ids = [...article.querySelectorAll('[id]')].map(el => el.id);
    return {
     overflow: document.documentElement.scrollWidth > innerWidth,
     last: article.lastElementChild.id,
     sources: article.querySelectorAll('section#sources').length,
     headings: [...article.querySelectorAll('h2')].filter(el => el.textContent === 'منابع').length,
     unique: ids.length === new Set(ids).size,
     broken: [...article.querySelectorAll('a[href^="#"]')].filter(el => !document.getElementById(decodeURIComponent(el.hash.slice(1)))).map(el => el.hash),
    };
   });
   assert(!result.overflow && result.unique, JSON.stringify(result));
   assert.equal(result.last, 'sources');
   assert.equal(result.sources, 1);
   assert.equal(result.headings, 1);
   assert.deepEqual(result.broken, []);
   console.log(`PASS sources ${width}px: last section, one heading, unique IDs, no broken fragments/overflow`);
  }
 } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
