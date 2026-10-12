# سند انتقال — MR369L

| | |
|---|---|
| آخرین به‌روزرسانی | 2026-10-12 |
| شاخهٔ کاری | `arena/17d1690e-s-a-1` (مخزن `sarzaminaryan-arch/S.A.1`) |
| فضای کار Drive | پوشهٔ `MR369L` در Google Drive (شناسه: `1QdjDsA_WeK_ElLp07342IW2Vv1imagj7`) |
| وضعیت کلی | فاز ۰، ۱ و بیشتر ۲ تمام؛ **فاز ۳ آغاز شد و اسکلت قالب روی WordPress واقعی اجرا می‌شود**؛ ۱ مسدودیت: پاسخ مالک به Q-01..Q-04 |

---

## ۱) این پروژه دقیقاً چیست؟

ساخت قالب وردپرس **MR369L**: قالب فروشگاهی مینیمال، فارسی و راست‌چین با پشتیبانی WooCommerce، معماری **بلوکی (FSE)**، با موشن تایپوگرافی CSS-only و صفحهٔ تنظیمات مالک. همهٔ سندها در `MR369L/docs/` و همهٔ کد قالب در `MR369L/theme/` (و پس از نصب، در `wp-content/themes/mr369l/`).

**نکتهٔ مهم برای عامل بعدی:** این پروژه **جدا** از پروژهٔ «سرزمین آریان» است (قالب `sarzaminaryan-child`). آن پروژه دست‌نخورده می‌ماند. هیچ فایلی از آن تغییر نمی‌کند مگر با اجازهٔ صریح مالک.

## ۲) چطور در ۶۰ ثانیه ادامه بدهم (Resume Procedure)

```bash
# ۱) وضعیت مخزن و شاخه
cd /home/user/S.A.1 && git status --short && git log --oneline -3

# ۲) خواندن نقشهٔ کار و وضعیت بسته‌ها
sed -n '1,60p' MR369L/docs/WORK_PACKAGES_FA.md
grep -n "وضعیت:" MR369L/docs/WORK_PACKAGES_FA.md | head -30

# ۳) ادامه از بستهٔ جاری (طبق جدول وضعیت تجمعی)
#    بستهٔ بعدی معمولاً WP-3.1 (اسکلت قالب) است.

# ۴) پس از هر تغییر: lint PHP (بدون نیاز به PHP سیستمی)
cd /home/user/.tools/phptest && node /home/user/S.A.1/MR369L/tools/php-lint.mjs 7.4,8.2 --dir /home/user/S.A.1/MR369L/theme

# ۵) آزمون دودی روی وردپرس واقعی (سرور روی پورت 9400)
curl -sSL -o /tmp/smoke.html -w "http=%{http_code}\n" http://127.0.0.1:9400/
grep -ciE '(fatal error|warning:|notice:)' /tmp/smoke.html

# ۶) به‌روزرسانی اسناد: WORK_PACKAGES (وضعیت)، ISSUES (تله‌ها)، DECISIONS (تصمیم)، TEST_AND_QUALITY (شاهد)، MASTER_PLAN (چک‌لیست)
```

## ۳) وضعیت دقیق اجزا

| جزء | وضعیت | یادداشت |
|---|---|---|
| مستندات ۸گانه | ✅ ۶ سند نوشته شد (`MASTER_PLAN`, `ARCHITECTURE`, `FEATURE_CATALOG`, `RESEARCH_AND_SKILLS`, `DECISIONS`, `ISSUES_AND_SOLUTIONS`, `TEST_AND_QUALITY`, `HANDOFF`) + `WORK_PACKAGES` + `BRAND_AND_MOTION` | همه فارسی |
| اسکلت قالب | ✅ ساخته و روی WordPress 7.1.3 فعال | ۸ قالب بلوکی، ۳ الگو، ۲ بخش، `theme.json` نسخهٔ ۳، فونت محلی |
| اسکریپت‌های کیفیت | ✅ سه ابزار ساخته و اجرا شد | `php-lint.mjs` (۷ نسخهٔ PHP)، `theme-audit.mjs` (۱۳ قاعده)، `contrast-check.mjs` (۱۳ جفت) |
| صفحهٔ تنظیمات مالک | 🟡 یک گزینهٔ آزمون‌شده | «ظاهر ← تنظیمات MR369L» → سطح موشن (خاموش/ملایم/نمایشی) |
| محیط آزمون | ✅ WordPress 7.1.3 / PHP 8.3 / SQLite روی پورت 9400 | فرمان راه‌اندازی پایین |
| GitHub | ⏳ پوشهٔ `MR369L` در شاخهٔ کاری این دور؛ PR به `main` بستهٔ WP-3.1 به بعد | قاعدهٔ مخزن: ZIP در Release نه در tree |
| Google Drive | ✅ پوشهٔ `MR369L` با ۵ زیرپوشه ساخته شد | اسناد/بسته‌ها آینه می‌شوند |

## ۴) فرمان‌های دقیق محیط

```bash
# راه‌اندازی مجدد وردپرس آزمون (اگر سرور خوابید)
cd /home/user/.tools/phptest && \
npx --no-install wp-playground-cli server \
  --auto-mount=/home/user/build/wp \
  --wordpress-install-mode=install-from-existing-files-if-needed \
  --port=9400 --php=8.3
# نسخهٔ هستهٔ دانلودشده: /home/user/build/wp (WordPress 7.1.3)

# ابزار lint (PHP-WASM)
# نصب یک‌بارهٔ وابستگی (در صورت پاک‌شدن محیط):
cd /home/user/.tools/phptest && npm i @php-wasm/node@3.1.58

# نصب قالب در سایت آزمون (پس از ساخته‌شدن سورس)
# همگام‌سازی قالب با سایت آزمون (کپی واقعی، نه symlink؛ Playground لینک‌ها را دنبال نمی‌کند)
cp -a /home/user/S.A.1/MR369L/theme/. /home/user/build/wp/wp-content/themes/mr369l/

# فعال‌سازی قالب در سایت آزمون (بدون WP-CLI): به‌روزرسانی جدول wp_options در SQLite
node --input-type=module -e "
import { DatabaseSync } from 'node:sqlite';
const db = new DatabaseSync('/home/user/build/wp/wp-content/database/.ht.e382490df95e4cf72d08e29310169408/.ht.sqlite');
db.prepare('UPDATE wp_options SET option_value=? WHERE option_name=?').run('mr369l','template');
db.prepare('UPDATE wp_options SET option_value=? WHERE option_name=?').run('mr369l','stylesheet');
db.close();"
# ⚠️ پیش از به‌روزرسانی SQLite، سرور Playground را متوقف کنید (قفل فایل).

# فایل زبان آزمون (fixture): fa_IR.mo ساخته‌شده در همین محیط، تا dir="rtl" واقعی شود
# مسیر: /home/user/build/wp/wp-content/languages/fa_IR.mo

# آزمون‌های کیفیت قالب
node /home/user/S.A.1/MR369L/tools/theme-audit.mjs --dir /home/user/S.A.1/MR369L/theme
node /home/user/S.A.1/MR369L/tools/contrast-check.mjs --theme /home/user/S.A.1/MR369L/theme
cd /home/user/.tools/phptest && node /home/user/S.A.1/MR369L/tools/php-lint.mjs \
  --dir /home/user/S.A.1/MR369L/theme --php 7.4,8.0,8.1,8.2,8.3,8.4
```

## ۵) محدودیت‌های شناخته‌شده (صریح)

1. **WooCommerce قابل نصب نیست** در این محیط (I-004) → آزمون وو فقط در CI/staging.
2. **مرورگر نیست** → هیچ ادعای بصری/Lighthouse در مستندات مجاز نیست (D-014).
3. **MySQL نیست** → آزمون با SQLite؛ تفاوت‌ها به‌عنوان ریسک باقی است.
4. **PHP بومی نیست** → lint از طریق WASM روی نسخه‌های 7.4 و 8.2 آزموده شده؛ نسخه‌های 8.0/8.1/8.3/8.4/8.5 باید در دور بعد اجرا شوند (۸٫۵ کند است → با `start_process` یا در CI).
5. **PHPCS/WPCS** فقط در CI.
6. `screenshot.png` راست‌چین با کیفیت، نیازمند رندر SVG یا اسکرین‌شات واقعی است (I-007).

## ۶) ورودی‌های مورد نیاز از مالک

| ID | پرسش | پیشنهاد تیم | اگر پاسخ ندهید |
|---|---|---|---|
| Q-01 | نام نمایشی نهایی (فارسی) و اینکه `MR369L` عنوان نمایشی بماند یا شناسهٔ فنی | `MR369L` نمایشی بماند | با `MR369L` ادامه می‌دهیم (بی‌خطر) |
| Q-02 | معماری بلوکی تأیید می‌شود؟ | بله (شواهد در `ARCHITECTURE_FA.md`) | با فرض بلوکی ادامه می‌دهیم؛ تغییر بعدی پرهزینه است |
| Q-03 | مخاطب: فروشگاه خودتان یا انتشار عمومی | قابل انتشار، تمرکز بر نیاز واقعی | «قابل انتشار» فرض می‌شود |
| Q-04 | دسترسی staging برای آزمون ووکامرس | بله/خیر | آزمون وو در CI می‌ماند و در «مسدودکننده‌ها» ثبت می‌شود |
| B-02 | روایت رسمی نام (`369` و `L` به چه معناست؟) | هیچ روایتی ساخته نمی‌شود | نام به‌عنوان شناسه می‌ماند |
| B-03 | سطح پیش‌فرض موشن | «ملایم» | «ملایم» اجرا می‌شود (D-009) |

## ۷) اقدام بعدی (Next Action — دقیق و تک‌مرحله‌ای)

> **بستهٔ WP-3.7 — یکپارچگی نمایشی ووکامرس:** افزودن `inc/woocommerce.php`، قالب‌های بلوکی `archive-product.html`/`single-product.html`/`product-search-results.html`/`page-cart.html`/`page-checkout.html`/`order-confirmation.html` و `parts/mini-cart.html` با رعایت ساختار رسمی `woocommerce/page-content-wrapper` + `core/post-content`، سپس **آزمون واقعی ووکامرس در GitHub Actions** (چون در سندباکس بستهٔ وو نصب‌شدنی نیست) و ثبت نتایج.

**بستهٔ موازی و کوتاه (توصیه‌شده پیش از WP-3.7):** WP-3.12 الف — تولید `screenshot.png` و ZIP قابل نصب + workflow کیفیت، تا خروجی قابل تحویل مالک زودتر آماده شود.

پس از آن: WP-3.4 (تکمیل هدر/منو و آزمون کیبورد) → WP-3.8 (لایهٔ فارسی نمایشی) → WP-3.10 (کارایی) → WP-3.9 (تکمیل تنظیمات) → فاز ۴.

## ۸) قاعدهٔ به‌روزرسانی این سند

در **پایان هر دور کاری** سه بخش زیر حتماً تازه می‌شوند: بند ۳ (وضعیت اجزا)، بند ۶ (ورودی‌های مالک) و بند ۷ (اقدام بعدی). اگر دور کاری نیمه‌کاره ماند، وضعیت بسته در `WORK_PACKAGES_FA.md` روی `in-progress` می‌ماند و دلیلش اینجا نوشته می‌شود.
