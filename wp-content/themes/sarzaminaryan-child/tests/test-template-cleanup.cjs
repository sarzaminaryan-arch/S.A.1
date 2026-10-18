/* Phase 1 CSS regression test. No WordPress/database writes or live requests.
 * npm install --prefix /path/to/qa playwright
 * NODE_PATH=/path/to/qa/node_modules CHROMIUM_PATH=/path/to/chromium node this-file
 * Optional: SA_SCREENSHOT_DIR=/path/outside/repository
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const theme = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(theme, p), 'utf8');
const child = read('assets/css/child.css');
const marker = child.lastIndexOf('/* ==========================================================================', child.indexOf('Template cleanup — phase 1:'));
assert(marker > 0, 'phase 1 CSS block exists');
const baseline = child.slice(0, marker);
const parent = fs.readFileSync(path.join(theme, '../sarzaminaryan/assets/css/main.css'), 'utf8');
const rtl = fs.readFileSync(path.join(theme, '../sarzaminaryan/assets/css/main-rtl.css'), 'utf8');
const font = fs.readFileSync(path.join(theme, '../sarzaminaryan/assets/fonts/fa/Vazirmatn-Regular.woff2')).toString('base64');
const stars = Array.from({ length: 7 }, (_, i) => `<button type="button" data-cc-star="${i + 1}" aria-label="امتیاز ${i + 1} از ۷"><span class="cc-rating__num">${i + 1}</span><span class="cc-rating__glyph" aria-hidden="true">★</span></button>`).join('');
// Same component structure/classes as CC_UI::rating_block() and sa_toc_markup().
const fixture = `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body class="profile-page"><main class="sa-main--entity"><div class="container sa-entity__layout">
<section class="sa-cyber-id sa-cyber-id--county profile-box" id="county-identity"><header class="sa-cyber-id__header profile-box"><h1 class="sa-cyber-id__title">شناسنامه شهرستان</h1></header><div class="sa-cyber-grid sa-cyber-grid--county"><div class="sa-cyber-grid__cell profile-box"><span class="sa-cyber-grid__label">شهرستان</span><span class="sa-cyber-grid__value">کاشمر</span></div></div></section>
<section class="cc-block cc-rating" data-cc-rating data-city="1"><h2 class="cc-block__title">امتیاز کاربران به شهرستان بوئین و میاندشت</h2><div class="cc-rating__row"><p class="cc-rating__avg"><span class="cc-stars">★★★★★★☆</span> <b>۶</b> از ۷ <span>· میانگین خام ۶ از ۱۲۳۴۵ رأی</span></p><p class="cc-rating__rank">🏆 رتبهٔ وزنی ۱ از ۴۸۰ شهرستان</p></div><div class="cc-rating__picker"><p class="cc-rating__picker-label">ستارهٔ خود را انتخاب کنید (۱ تا ۷) و سپس «ثبت امتیاز» را بزنید:</p><div class="cc-rating__stars" role="group" aria-label="انتخاب امتیاز از یک تا هفت">${stars}</div><button type="button" class="cc-btn cc-btn--primary cc-rating__submit" data-cc-submit disabled>ثبت امتیاز</button></div><p class="cc-rating__status" data-cc-status role="status">بدون ثبت‌نام امتیاز بدهید.</p></section>
<div class="sa-entity__content entry-content"><nav class="sa-toc" id="sa-toc" aria-labelledby="sa-toc-title"><div class="sa-toc__head"><h2 class="sa-toc__title" id="sa-toc-title">فهرست مطالب</h2><span class="sa-toc__count">۱۲ سرفصل</span></div><ol class="sa-toc__list">${Array.from({ length: 12 }, (_, i) => `<li class="sa-toc__item sa-toc__item--l${i % 3 + 1}"><a class="sa-toc__link" href="#section-${i}"><span class="sa-toc__num" aria-hidden="true">${i + 1}</span><span class="sa-toc__label">${i === 11 ? 'ستارهٔ خود را انتخاب کنید (۱ تا ۷) و سپس «ثبت امتیاز» را بزنید:' : 'اطلاعات کلیدی'}</span></a></li>`).join('')}</ol></nav><h2 id="section-0">اطلاعات کلیدی</h2></div></div></main></body></html>`;
(async () => {
 const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
 try {
  const page = await browser.newPage();
  await page.route('https://sa.test/**', route => route.fulfill({ contentType: 'text/html', body: fixture }));
  await page.route('https://sa.test/wp-json/**', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify(route.request().method() === 'POST' ? { success: true, user_rating: 7, message: 'رأی شما ثبت شد؛ سپاس!', rank: 1, total: 480 } : { average: 6, user_rating: 0 }) }));
  await page.goto('https://sa.test/');
  await page.addStyleTag({ content: `${parent}\n${rtl}\n@font-face{font-family:sa_test;src:url(data:font/woff2;base64,${font})}body,button{font-family:sa_test,sans-serif}` });
  const sheet = await page.addStyleTag({ content: baseline });
  // Deliberately load the contribution stylesheet last (real enqueue order).
  await page.addStyleTag({ content: read('assets/css/city-contrib.css') });
  await page.addStyleTag({ content: '*,*::before,*::after{transition:none!important;animation:none!important}' });
  await page.evaluate(() => document.fonts.ready);
  const measure = () => page.evaluate(() => {
   const box = s => { const e = document.querySelector(s), r = e.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, scroll: e.scrollWidth, client: e.clientWidth }; };
   const style = s => { const c = getComputedStyle(document.querySelector(s)); return [c.fontSize, c.padding, c.margin, c.background, c.boxShadow, c.border, c.lineHeight]; };
   const buttons = [...document.querySelectorAll('[data-cc-star]')].map(e => { const r = e.getBoundingClientRect(); return { y: r.y, width: r.width, height: r.height, x: r.x }; });
   return { buttons, picker: box('.cc-rating__picker'), group: box('.cc-rating__stars'), status: box('[data-cc-status]'), list: box('.sa-toc__list'), item: box('.sa-toc__item'), identity: style('#county-identity'), cell: style('.sa-cyber-grid__cell'), title: box('.cc-block__title'), titleLine: parseFloat(getComputedStyle(document.querySelector('.cc-block__title')).lineHeight), font: style('[data-cc-status]')[0] };
  });
  const results = [];
  for (const width of [320, 360, 390, 430, 640, 760, 768, 1024, 1440]) {
   await page.setViewportSize({ width, height: 1100 });
   await sheet.evaluate((el, css) => el.textContent = css, baseline);
   const before = await measure();
   await sheet.evaluate((el, css) => el.textContent = css, child);
   const after = await measure();
   assert.deepEqual(after.identity, before.identity, `identity unchanged at ${width}`);
   assert.deepEqual(after.cell, before.cell, `identity cell unchanged at ${width}`);
   assert(after.item.height <= before.item.height * .76, `TOC at least 24% denser at ${width}`);
   assert(after.group.scroll <= after.group.client + 1, `stars do not overflow at ${width}`);
   assert(after.list.scroll <= after.list.client + 1, `TOC does not overflow at ${width}`);
   if (width <= 760) {
    assert.equal(new Set(after.buttons.map(b => b.y)).size, 1, `seven stars on one line at ${width}`);
    assert(after.buttons.every(b => b.width >= 24 && b.height >= 44), `touch targets at ${width}`);
    assert(after.title.height <= after.titleLine + 1, `title on one line at ${width}`);
   } else {
    assert.deepEqual(after.buttons, before.buttons, `desktop rating unchanged at ${width}`);
   }
   await page.locator('[data-cc-status]').evaluate(el => { el.textContent = 'رأی شما ثبت شد؛ سپاس!'; el.classList.add('cc-status--ok'); });
   const short = await measure();
   assert(short.status.width < after.picker.width, `short notice shrink-wraps at ${width}`);
   await page.locator('[data-cc-status]').evaluate(el => el.textContent = '✅ رأی شما: ۷ ستاره ثبت شده است. تا ۲۴ ساعت آینده نمی‌توانید آن را تغییر دهید.');
   const success = await measure();
   assert(success.status.width <= 512 && success.status.scroll <= success.status.client + 1, `long notice fits at ${width}`);
   if (process.env.SA_SCREENSHOT_DIR && [320, 390, 1440].includes(width)) {
    fs.mkdirSync(process.env.SA_SCREENSHOT_DIR, { recursive: true });
    await page.screenshot({ path: path.join(process.env.SA_SCREENSHOT_DIR, `phase1-${width}.png`), fullPage: true });
   }
   results.push({ width, tocBefore: before.item.height, tocAfter: after.item.height, starWidth: after.buttons[0].width, successWidth: success.status.width, successFont: success.font });
  }
  // Exercise the unmodified production script with a mocked API, including hidden submit.
  await page.evaluate(() => window.CC_CONFIG = { api: 'https://sa.test/wp-json/', nonce: 'fixture' });
  await page.addScriptTag({ content: read('assets/js/city-contrib.js') });
  await page.locator('[data-cc-star="7"]').click();
  await page.locator('[data-cc-submit]').click();
  await page.waitForFunction(() => document.querySelector('[data-cc-status]').textContent.includes('رأی شما ثبت شد'));
  assert(await page.locator('[data-cc-submit]').isHidden(), 'submit remains hidden after success');
  assert.equal(await page.locator('[data-cc-star]:disabled').count(), 7, 'all stars locked after success');
  await page.locator('.sa-toc__link').first().focus();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  assert(await page.locator('.sa-toc__link').first().evaluate(el => el.matches(':focus-visible')), 'TOC keyboard focus remains visible');
  console.table(results);
  console.log('PASS: 9 RTL viewports; compact TOC; one-row mobile stars/title; short/long success; identity unchanged; mocked voting; keyboard focus.');
 } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
