# آزمون و کیفیت — MR369L

| | |
|---|---|
| سند | `MR369L/docs/TEST_AND_QUALITY_FA.md` |
| نسخه | 1.0 |
| تاریخ | 2026-10-12 |
| قاعده | هر نتیجه با «فرمان + محیط + تاریخ + خروجی خام» ثبت می‌شود. هیچ نتیجهٔ حدسی وارد این سند نمی‌شود |

---

## ۱) محیط آزمون (واقعی، بررسی‌شده در 2026-10-12)

| مورد | مقدار | چطور تأیید شد |
|---|---|---|
| سیستم | Debian GNU/Linux 12 (bookworm)، کرنل 6.1.158+، ۲ هستهٔ CPU | `uname -a`, `/etc/os-release` |
| Node.js | v22.22.3 | `node --version` |
| npm | 10.9.8 | `npm --version` |
| git | 2.39.5 | `git --version` |
| gh CLI | 2.23.0 | `gh --version` |
| PHP بومی | **نصب نیست** | `command -v php` → یافت نشد؛ `apt-get install php-cli` → «Unable to locate package» |
| composer / wp-cli / mysql / sqlite3 CLI | **نصب نیستند** | `command -v …` |
| Python | 3.11.2 (+ venv با Pillow 12.3.0، بدون raqm) | `python3 -c "import PIL"` |
| PHP از طریق WASM | `@php-wasm/node` 3.1.58 → PHP **7.4.33 / 8.2.33** اجرا شد | خروجی `PHP=7.4.33`, `PHP=8.2.33` |
| WordPress واقعی | **7.1.3** (دانلود از `codeload.github.com/WordPress/WordPress/zip/refs/tags/7.1.3`) | `wp-includes/version.php` → `$wp_version = '7.1.3'` |
| سرور آزمون | Playground CLI 3.1.58 — PHP 8.3، SQLite، `0.0.0.0:9400` | `curl` → `HTTP 200` |
| مرورگر | **نصب نیست** (Chromium/Playwright موجود نیست) | `command -v chromium` → یافت نشد |
| WooCommerce | **قابل نصب نیست** (میزبان Release مسدود) | `curl` روی دارایی Release → خطای SSL |
| PHPCS/WPCS | قابل نصب نیست (composer و Packagist مسدود)؛ PHPCS 4.0.4 و WPCS 3.4.1 شناسایی شد | `api.github.com` |

## ۲) آزمون‌های انجام‌شده (شاهد دار)

| # | آزمون | فرمان | نتیجه | شاهد |
|---|---|---|---|---|
| T-001 | دسترسی به GitHub | `curl -sS -o /dev/null -w "%{http_code}" https://github.com` | ✅ `200` | خروجی دستور |
| T-002 | مسدود بودن wordpress.org | `curl -o /dev/null -w "%{http_code}" https://wordpress.org` | ⛔ `000` (SSL error) | خروجی دستور |
| T-003 | دانلود WordPress 7.1.3 | `curl -L -o wp-core.zip codeload.github.com/…/7.1.3` | ✅ `http=200 size=63882727` | خروجی دستور |
| T-004 | راه‌اندازی وردپرس واقعی | `wp-playground-cli server --auto-mount=/home/user/build/wp --port=9400 --php=8.3` | ✅ سرور بالا آمد، پورت `0.0.0.0:9400` | خروجی `start_process` |
| T-005 | پاسخ صفحهٔ اول | `curl -sSL http://127.0.0.1:9400/` | ✅ `http=200 time=1.059 size=69724` | خروجی curl |
| T-006 | هویت هستهٔ نصب‌شده | `grep -o '<title>[^<]*' /tmp/home2.html` + `version.php` | ✅ `<title>My WordPress Website`، `WordPress 7.1.3`، قالب فعال `twentytwentyfive` | خروجی دستور |
| T-007 | نبود خطای PHP در خروجی | `grep -oiE '(fatal error\|warning\|notice)[^<]{0,120}' /tmp/home2.html` | ✅ صفر مورد | خروجی دستور |
| T-008 | نصب PHP-WASM برای lint | `npm i @php-wasm/node@3.1.58` | ✅ نصب شد | خروجی دستور |
| T-009 | ~~lint روی PHP 7.4~~ (**باطل شد** — glob هیچ فایلی نمی‌خواند؛ به I-008 نگاه کنید) | `PHP_VERSION=7.4 node lint.mjs` | ⛔ شاهد نامعتبر | با T-014 جایگزین شد |
| T-010 | ~~lint روی PHP 8.2~~ (**باطل شد** — همان دلیل I-008) | `PHP_VERSION=8.2 node lint.mjs` | ⛔ شاهد نامعتبر | با T-014 جایگزین شد |
| T-011 | lint روی PHP 8.5 | `node tools/php-lint.mjs --php 8.5` | ⏱️ در این دور اجرا نشد (موتور JSPI کندتر است) | — (باقی‌مانده برای دور بعد) |
| T-014 | **lint واقعی روی ۷ نسخهٔ PHP** (اصلاح T-009/T-010) | `node MR369L/tools/php-lint.mjs --dir MR369L/theme --php 7.4,8.0,8.1,8.2,8.3,8.4` | ✅ همه سبز: `7.4.33`, `8.0.30`, `8.1.34`, `8.2.33`, `8.3.33`, `8.4.25` → هر کدام «۷ فایل، صفر خطای نحو» | خروجی ابزار (۷ فایل PHP بررسی‌شده) |
| T-015 | نصب و فعال‌سازی قالب روی WordPress واقعی | کپی `MR369L/theme` → `wp-content/themes/mr369l` + به‌روزرسانی `template`/`stylesheet` در SQLite | ✅ قالب فعال شد و صفحهٔ نخست بالا آمد | خروجی `node:sqlite` + curl |
| T-016 | آزمون دودی مسیرها | `curl -sSL http://127.0.0.1:9400{/ , /?s=test , /sample-page/ , /nonexistent/}` | ✅ `/` → 200 (۷۷٫۷۱۹ بایت، ۱٫۹۱s)؛ `/?s=test` → 200؛ `/sample-page/` → 200؛ مسیر ناموجود → **404** | خروجی curl |
| T-017 | RTL واقعی در خروجی | `grep '<html' ` | ✅ `<html dir="rtl" lang="fa-IR">` (با locale `fa_IR`) | خروجی curl |
| T-018 | کلاس سطح موشن روی body | `grep '<body class='` | ✅ `mr-motion-subtle` (پیش‌فرض) | خروجی curl |
| T-019 | نبود درخواست بیرونی در HTML | `grep -oE '(href\|src)="https?://[^"]+' \| grep -v 127.0.0.1` | ✅ خروجی خالی | خروجی دستور |
| T-020 | بازبینی ایستا (۱۳ قاعدهٔ AUD) | `node MR369L/tools/theme-audit.mjs --dir MR369L/theme` | ✅ `0 خطا، 0 هشدار` (۲ اطلاع: theme.json معتبر/نسخهٔ ۳، حجم CSS موشن ۵۰۵۱ بایت زیر بودجهٔ ۶KB) | خروجی ابزار |
| T-021 | سنجش کنتراست WCAG | `node MR369L/tools/contrast-check.mjs --theme MR369L/theme` | ✅ ۱۳ جفت، ۰ ناموفق (مثلاً contrast/base = ۱۷٫۵۴:۱؛ line-strong/base = ۳٫۳۲:۱) | خروجی ابزار |
| T-022 | بودجهٔ حجم CSS موشن | اندازهٔ فایل | ✅ ۵۰۵۱ بایت (بودجه ۶۱۴۴) | خروجی ابزار |
| T-012 | فهرست فایل‌های مخزن | `find . -path ./.git -prune -o -type f -print \| wc -l` | ✅ `243` فایل قبل از افزودن MR369L | خروجی دستور |
| T-013 | سلامت شاخهٔ کاری | `git status --porcelain=v1 -b` | ✅ شاخهٔ `arena/17d1690e-s-a-1` بدون تغییر کامیت‌نشدهٔ قبلی | خروجی دستور |

### آزمون‌هایی که **انجام نشدند** (صریح)
| آزمون | چرا | برنامه |
|---|---|---|
| اجرای WooCommerce (سبد/تسویه/محصول) | بستهٔ وو قابل دانلود نیست (I-004) | GitHub Actions (L5) + staging مالک |
| Lighthouse / axe / بازبینی بصری | مرورگر نصب نیست (I-006) | چک‌لیست دستی مالک (L6) |
| PHPCS/WPCS | composer/Packagist مسدود | GitHub Actions (L7) |
| آزمون MySQL/MariaDB | سرور DB نصب نیست | staging مالک |
| آزمون iOS/Safari | محیط iOS موجود نیست | مالک |

## ۳) لایه‌های آزمون و اسکریپت‌های پروژه (پیاده‌سازی در بستهٔ WP-3.12)

| لایه | اسکریپت/ابزار | فرمان (پیش‌بینی‌شده) | معیار قبولی |
|---|---|---|---|
| L1 نحو PHP چندنسخه‌ای | `MR369L/tools/php-lint.mjs` | `node tools/php-lint.mjs --php 7.4,8.0,8.1,8.2,8.3,8.4 <dir>` | صفر خطای نحو در همهٔ نسخه‌ها |
| L2 نحو JS/JSON | `node --check`, `node tools/json-check.mjs` | `node tools/json-check.mjs theme/` | صفر خطا |
| L3 بررسی ایستا | `MR369L/tools/theme-audit.mjs` | `node tools/theme-audit.mjs theme/` | صفر `error` |
| L4 آزمون دودی وردپرس | `MR369L/tools/smoke-test.sh` | bash + curl روی پورت 9400 | HTTP 200 + صفر خطای PHP |
| L5 یکپارچگی وو | `.github/workflows/mr369l-quality.yml` (job: woocommerce) | `gh workflow run` / push | نصب وو + رندر مسیرها بدون خطا |
| L6 بازبینی انسانی | `docs/ACCESSIBILITY_CHECKLIST_FA.md` (بستهٔ WP-3.11) | دستی توسط مالک | همهٔ بندها تیک |
| L7 استاندارد کدنویسی | CI: `phpcs --standard=WordPress` | در workflow | صفر `error` |

## ۴) چیزی که در L3 بررسی می‌شود (فهرست اولیهٔ قاعده‌ها)

| کد | قاعده | شدت |
|---|---|---|
| AUD-01 | `<?php` ابتدای هر فایل PHP و نبود کد بعد از فایل | error |
| AUD-02 | حضور `ABSPATH` guard در فایل‌های `inc/` | error |
| AUD-03 | نبود `eval(`, `base64_decode(`, `unserialize(` | error |
| AUD-04 | نبود آدرس بیرونی (`http://`/`https://` غیر از دامنهٔ مجاز; فقط `wordpress.org`/`gnu.org` در مجوز) | error |
| AUD-05 | نبود `margin-left/right`, `padding-left/right`, `left:, right:` در CSS (ویژگی منطقی الزامی) | warning |
| AUD-06 | نبود `flex-direction: row-reverse` (تلهٔ I-101) | warning |
| AUD-07 | نبود سلکتور روی `.wc-block-*`/`.woocommerce-*` در CSS قالب (تلهٔ I-103) | error |
| AUD-08 | هر `register_setting` باید `sanitize_callback` داشته باشد | error |
| AUD-09 | Presence of `prefers-reduced-motion` در هر فایل CSS که `@keyframes` دارد | error |
| AUD-10 | هر `@keyframes` باید در فایل موشن باشد و حجم فایل ≤ 6KB | warning |
| AUD-11 | نبود `!important` بیش از حد آستانه (نشانهٔ جنگ CSS) | warning |
| AUD-12 | `theme.json` معتبر + `version: 3` | error |

## ۵) کارنامهٔ کیفیت (وضعیت فعلی — پیش از فاز ۳)

| محور | سطح فعلی (۰–۵) | مبنا | آنچه سنجیده نشده |
|---|---|---|---|
| امنیت | ۳ / ۵ | بازبینی ایستا سبز (AUD-01..17)، صفر درخواست بیرونی (T-019)، غیرفعال‌بودن `eval/unserialize` و escape در نقاط نوشتن؛ Settings API با sanitize و capability | PHPCS/WPCS در CI، بازبینی سومی، آزمون نفوذ |
| کارایی | ۲ / ۵ | صفر JS اختصاصی، ۳ درخواست فونت محلی با `swap`، preload فقط وزن Regular، HTML ~۷۷KB؛ زمان سرور محلی ~۰٫۸–۱٫۹s (شامل سربار WASM) | اندازه‌گیری واقعی مرورگر/CWV، اثر کش، تصاویر |
| دسترس‌پذیری | ۳ / ۵ | کنتراست ۱۳ جفت سنجیده (T-021)، skip-link و focus-visible، احترام کامل به `prefers-reduced-motion` (AUD-09) | آزمون کیبورد/مرورگر (L6) و ابزار axe |
| سازگاری | ۳ / ۵ | اجرای واقعی روی WordPress 7.1.3 + PHP 8.3 و lint روی PHP 7.4–8.4 | WooCommerce (L5)، MySQL/MariaDB، مرورگرها |
| پوشش ویژگی | ۳ / ۵ | اسکلت قالب، سیستم طراحی، موشن تایپوگرافی، صفحهٔ تنظیمات، ۳ الگو، ۸ قالب بلوکی | بقیهٔ فهرست (سبد/تسویه/حساب/کارایی/…) |
| آمادگی انتشار | ۲ / ۵ | اسکلت نصب‌شدنی و فعال‌شده روی سایت آزمون، changelog و readme | ZIP نهایی، `screenshot.png`، CI، آزمون وو، آزمون مالک |

> این کارنامه **برآورد مبتنی بر شاهد** است، نه گواهی. اعداد در پایان هر فاز بازبینی می‌شوند و فقط با شاهد جدید بالا می‌روند.

## ۶) روش‌شناسی درجه‌بندی (۰–۵)

| سطح | معنا |
|---|---|
| ۰ | انجام نشده و برنامه‌ریزی هم نشده |
| ۱ | طراحی/مستند شده، ولی هیچ اجرا/شاهدی نیست |
| ۲ | بخشی پیاده شده و در محیط محدود آزمون شده |
| ۳ | پیاده و در محیط واقعی آزمون شده، با شکاف‌های شناخته‌شده |
| ۴ | پیاده، آزمون‌شده در محیط واقعی و CI، شکاف‌ها مستند |
| ۵ | پیاده، آزمون‌شده، بازبینی‌شده توسط سومی و بدون شکاف باز بحرانی |

**قاعدهٔ اعلام:** سطح ۴ در هیچ محوری بدون اجرای موفق CI + آزمون روی WordPress واقعی داده نمی‌شود؛ «آمادهٔ تولید» تنها با GATE-4 و بدون مسدودکنندهٔ باز اعلام می‌شود.
