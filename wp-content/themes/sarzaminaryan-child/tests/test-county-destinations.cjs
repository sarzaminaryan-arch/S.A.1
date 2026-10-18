/* Render fixture first with: php tests/test-county-destinations.php --fixture > /PRIVATE/fixture.html
 * SA_DESTINATION_FIXTURE=/PRIVATE/fixture.html NODE_PATH=/path/to/node_modules
 * CHROMIUM_PATH=/path/to/chromium node tests/test-county-destinations.cjs
 * Screenshots (optional) go outside Git via SA_SCREENSHOT_DIR.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const theme = path.resolve(__dirname, '..');
assert(process.env.SA_DESTINATION_FIXTURE, 'provide the PHP-rendered synthetic fixture');
const fixture = fs.readFileSync(process.env.SA_DESTINATION_FIXTURE, 'utf8');
assert(fixture.startsWith('<!doctype html>'), 'no PHP errors/output precede fixture');
(async () => {
 const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
 try {
  const page = await browser.newPage();
  await page.route('**/*', route => route.fulfill({ contentType: 'text/html', body: '<p>synthetic destination</p>' }));
  await page.setContent(fixture);
  for (const file of ['../sarzaminaryan/assets/css/main.css', '../sarzaminaryan/assets/css/main-rtl.css', 'assets/css/child.css', 'assets/css/city-contrib.css']) {
   await page.addStyleTag({ content: fs.readFileSync(path.join(theme, file), 'utf8') });
  }
  const font = fs.readFileSync(path.join(theme, '../sarzaminaryan/assets/fonts/fa/Vazirmatn-Regular.woff2')).toString('base64');
  await page.addStyleTag({ content: `@font-face{font-family:sa_test;src:url(data:font/woff2;base64,${font})}body{font-family:sa_test,sans-serif}` });
  await page.evaluate(() => document.fonts.ready);
  // Stress wrapping without clamping or discarding any label.
  await page.locator('.sa-destination-card strong').first().evaluate(el => el.textContent += ' ' + 'نامبسیاربلند'.repeat(12));
  for (const width of [280, 320, 360, 390, 430, 600, 768, 1024, 1440]) {
   await page.setViewportSize({ width, height: 1000 });
   const result = await page.evaluate(() => {
    const card = document.querySelector('.sa-destination-card');
    const css = getComputedStyle(card), glow = getComputedStyle(card, '::after');
    const grids = [...document.querySelectorAll('.sa-destination-grid')];
    return {
     overflow: document.documentElement.scrollWidth > innerWidth,
     gridOverflow: grids.some(el => el.scrollWidth > el.clientWidth + 1),
     cardsOverflow: [...document.querySelectorAll('.sa-destination-card')].some(el => el.scrollWidth > el.clientWidth + 1),
     columns: getComputedStyle(grids[0]).gridTemplateColumns.split(' ').length,
     background: css.backgroundColor, color: css.color, glowColor: glow.backgroundColor,
     glowBottom: glow.bottom, glowShadow: glow.boxShadow,
     minTouch: [...document.querySelectorAll('.sa-destination-card')].every(el => el.getBoundingClientRect().height >= 44),
     firstX: card.getBoundingClientRect().x,
     secondX: document.querySelectorAll('.sa-destination-card')[1].getBoundingClientRect().x,
     deadAnchors: ['county-visit', 'county-attractions', 'county-siblings'].some(id => !document.getElementById(id)),
    };
   });
   assert(!result.overflow && !result.gridOverflow && !result.cardsOverflow, `no overflow at ${width}: ${JSON.stringify(result)}`);
   assert.equal(result.columns, width <= 300 ? 1 : width < 600 ? 2 : width < 1000 ? 3 : 4);
   assert.equal(result.background, 'rgb(16, 31, 61)');
   assert.equal(result.color, 'rgb(255, 255, 255)');
   assert.equal(result.glowColor, 'rgb(255, 229, 104)');
   assert.equal(result.glowBottom, '0px', 'glow flush against bottom');
   assert.notEqual(result.glowShadow, 'none');
   assert(result.minTouch && !result.deadAnchors);
   if (result.columns > 1) assert(result.firstX > result.secondX, 'RTL order');
   if (process.env.SA_SCREENSHOT_DIR && [320, 390, 1440].includes(width)) {
    fs.mkdirSync(process.env.SA_SCREENSHOT_DIR, { recursive: true });
    await page.screenshot({ path: path.join(process.env.SA_SCREENSHOT_DIR, `phase4-${width}.png`), fullPage: true });
   }
   console.log(`PASS destinations ${width}px: ${result.columns} columns, white/navy, flush yellow glow, no overflow`);
  }
  await page.keyboard.press('Tab');
  assert.equal(await page.locator(':focus').getAttribute('class'), 'sa-destination-card', 'keyboard-accessible link');
  assert.equal(await page.locator(':focus').evaluate(el => getComputedStyle(el).outlineStyle), 'solid');
  const expected = await page.locator('.sa-destination-card').first().getAttribute('href');
  await page.setViewportSize({ width: 390, height: 844 });
  await Promise.all([page.waitForURL(expected), page.locator('.sa-destination-card').first().click()]);
  assert.equal(page.url(), expected, 'whole card navigates to the existing relation URL (mock response only)');
  console.log('PASS keyboard focus and mobile card navigation (synthetic URL)');
 } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
