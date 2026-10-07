# ابزارهای توسعه و تست (بخشی از قالب نیست)

این پوشه فقط برای توسعه و کنترلِ کیفیت است و در بستهٔ نصبیِ قالب (زیپِ منتشرشده) قرار نمی‌گیرد.

## ۰. ممیزی کم‌ریسک WXR

برای خروجی WordPress ابتدا گزارش امن و صرفاً ساختاری بسازید؛ متن مقاله، عنوان/توضیح سئو و کلیدواژه در CSV/JSON گزارش چاپ نمی‌شود:

```bash
python3 tools/audit_wxr_content.py /مسیر/export.xml \
  --site-url https://sarzaminaryan.ir \
  --json /tmp/wxr-audit.json --csv /tmp/wxr-audit.csv
python3 -m unittest discover -s tools/tests -v
```

گزارش شامل نوع/وضعیت/URL، شمار H1 و لینک و FAQ/منبع، alt، الگوهای احتمالی placeholder، تکرار title/description و **هیستوگرام دامنه‌های بیرونی** (برای دیدن تمرکز روی یک منبع) است.

برای تبدیل خروجی‌های JSON به گزارشِ فارسیِ منتشرشدنی و فهرستِ کارِ CSV (بدون متن مقاله/فرادادهٔ SEO؛ فقط slug/URL و شمارش‌ها):

```bash
python3 tools/wxr_audit_rollup.py /tmp/ostanha.json /tmp/shar.json /tmp/namayebartar.json \
  --provenance reports/provenance.json \
  --out docs/YYYY-MM-DD-content-corpus-audit-fa.md \
  --issues docs/YYYY-MM-DD-content-issues-fa.csv
```

`--provenance` اختیاری است (نام فایل/بایت/sha256/تاریخ Drive). سطح پوشش: پیوست/رسانه و ردیف‌های سطل‌زباله از فهرست کار کنار گذاشته می‌شوند. نمونهٔ اجراشده: `docs/2026-10-07-content-corpus-audit-fa.md`. این ابزار 404 مقصدها، اعتبار منبع، صحت واقعیت، مفیدبودن محتوا یا رتبهٔ Google را تعیین نمی‌کند؛ نبود override سئو هم لزوماً خطا نیست چون قالب/افزونه ممکن است fallback بسازد. CSV/JSON را فقط در فضای امن نگه دارید؛ از واردکردن SQL یا اطلاعات شخصی در این ابزار بپرهیزید.

## ۱. کنترل ساختاری رجیستری جغرافیا

این آزمون ساختار رجیستری‌های بستهٔ قالب را بررسی می‌کند: شمار ۳۱ استان/۴۸۳ شهرستان، یکتایی slug، فیلدهای لازم و ارجاع معتبر هر شهرستان به استان. **درستی رسمی نام‌ها، مرزها، مراکز و تازگی status را تأیید نمی‌کند.** گزارش اجرا: `docs/2026-10-07-geography-registry-audit-fa.md`.

```bash
node tools/phpwasm/exec.js tools/sa-tests/run-geo-registry-audit.php
```

### ۱.۱ مقایسهٔ رجیستری با خروجی واقعی WXR

`tools/registry_live_diff.py` رجیستری `data/counties.php` را با خروجی شهرستان‌ها/استان‌ها مقایسه می‌کند و اختلاف‌ها را به‌شکل جدول‌های منتشرشدنی (اختلاف slug، تکراری‌های منتشرشده، پیش‌نویس/سطل‌زبالهٔ هم‌نام، اختلاف نام با slug یکسان) و شمارش لینک‌های `/city/` در صفحه‌های استان چاپ می‌کند. خروجی فقط slug/عنوان/وضعیت است؛ متن مقاله چاپ نمی‌شود.

```bash
python3 tools/registry_live_diff.py \
  --registry wp-content/themes/sarzaminaryan-child/data/counties.php \
  --wxr /مسیر/s-aryan-shar.xml \
  --wxr-provinces /مسیر/s.aryan.ostanha.31.xml \
  --json reports/registry-live-diff.json --markdown /tmp/registry-live-diff.md
python3 -m unittest tools/tests/test_registry_live_diff.py -v
```

گزارش اجراشده روی خروجی ۲۰۲۶-۱۰-۰۷: `docs/2026-10-07-registry-vs-live-divergence-fa.md` (۳۹ اختلاف slug، ۲ جفت منتشرشدهٔ تکراری، ۳ پیش‌نویس هم‌نام و ۴ لینک `/city/` در کل ۳۱ استان). این ابزار ۴۰۴، وضعیت ایندکس، درستی رسمی نام‌ها و شمار قطعی تقسیمات کشوری را تعیین نمی‌کند.

## ۱.۲ ممیزیِ رندرِ لینک‌سازیِ داخلیِ خودکار

لینک‌های داخلیِ قالب در زمان نمایش ساخته می‌شوند (`inc/internal-links.php`)، پس شمارشِ لینک در فایل WXR رفتار سایت را نشان نمی‌دهد. این جفت ابزار همان موتورِ قالب را روی محتوای واقعی اجرا می‌کند:

```bash
python3 tools/audit_autolinks.py \
  --wxr-shar /مسیر/s-aryan-shar.xml \
  --wxr-provinces /مسیر/s.aryan.ostanha.31.xml \
  --subjects both --batch 6 \
  --json reports/autolink-render-audit.json \
  --markdown docs/YYYY-MM-DD-autolink-render-audit-fa.md \
  --self-links-csv docs/YYYY-MM-DD-self-links-fa.csv
python3 -m unittest tools/tests/test_audit_autolinks.py -v
```

- `--subjects province|both|ambiguous` — فقط استان‌ها، همهٔ صفحه‌های استان/شهرستان، یا فقط صفحه‌هایی که نامشان با موجودیتِ دیگری هم‌نام است (کاندیدِ لینکِ غلط).
- `tools/sa-tests/run-autolink-audit.php` موتورِ PHP است؛ `exec.js` آن را اجرا می‌کند. خروجیِ php-wasm حدود ۶۴KB بریده می‌شود، پس `--batch` (پیش‌فرض ۵) صفحه‌ها را دسته‌بندی می‌کند.
- گزارش شامل: شمار لینکِ تولیدشده به تفکیک نوع، لینکِ هم‌نامِ بین‌استانی (خطای واقعی)، لینکِ خودیِ موتور (نباید رخ دهد) و پیوندهای خودارجاعِ متنِ ذخیره‌شده (کارِ تحریریه) است.
- سنجهٔ قاعده «متنِ لینک = نامِ کامل»: ستونِ «لینکِ تولیدشده با متنِ بدونِ پیشوند» باید صفر بماند؛ اگر بزرگ‌تر از صفر شد، یعنی موتور لینکِ برهنه ساخته است.
- نمونهٔ اجراشده روی خروجی ۲۰۲۶-۱۰-۰۷: `docs/2026-10-07-autolink-render-audit-fa.md` و `docs/2026-10-07-self-links-fa.csv` (۵۱۸ صفحه، ۶٬۶۲۱ لینکِ شهرستانی، ۰ لینکِ غلطِ خودی، ۰ متنِ بدونِ پیشوند، ۱۷۶ پیوندِ خودارجاعِ تحریریه). شرحِ قواعد و پیش/پس: `docs/2026-10-07-autolink-rules-fix-fa.md`.
- محدودیت: شبیه‌سازی است، نه صفحهٔ زنده؛ ترم‌های تاکسونومی/صفحه‌بندی/افزونه‌ها/کش مدل نمی‌شوند. متن مقاله در گزارش چاپ نمی‌شود.

## ۲. اجرای PHP بدون نیاز به نصبِ PHP روی سیستم

`tools/phpwasm` یک اجراکنندهٔ سبک بر پایهٔ [php-wasm](https://www.npmjs.com/package/@php-wasm/node) است که PHP 8.1 و 7.4 را داخل Node اجرا می‌کند.

```bash
cd tools/phpwasm
sh setup.sh                 # فقط بار اول: نصب node_modules (در مخزن نگه‌داری نمی‌شود)
```

بررسیِ نحو (lint) همهٔ فایل‌های PHP قالب روی هر دو نسخه:

```bash
cd /ریشهٔ-مخزن
node tools/phpwasm/lint.js $(find wp-content/themes/sarzaminaryan-child -name '*.php' | sort)
PHP_VER=7.4 node tools/phpwasm/lint.js $(find wp-content/themes/sarzaminaryan-child -name '*.php' | sort)
```

اجرای یک اسکریپتِ PHP دلخواه با شبیه‌سازِ وردپرس:

```bash
node tools/phpwasm/exec.js tools/sa-tests/run-bot.php
```

`exec.js` هنگام اجرا:

- پوشه‌های `data/` و `inc/` قالب را به شکل فقط‌خواندنی در فایل‌سیستمِ مجازیِ PHP کپی می‌کند،
- اسکریپت و همهٔ فایل‌های کناری‌اش (مثل `wp-stubs.php` و `assert.php`) را هم کپی می‌کند،
- متغیر محیطی `SA_CHILD_DIR` را برای اسکریپت تنظیم می‌کند.

متغیرهای محیطیِ مفید: `PHP_VER` (پیش‌فرض `8.1`) و `SA_THEME_DIR` (مسیر قالب؛ پیش‌فرض نسبت به ریشهٔ مخزن).

## ۳. تست‌ها

| فایل | چه چیزی را تست می‌کند |
| --- | --- |
| `tools/sa-tests/run-publish-gate.php` | v1.2: اجباری/اختیاری بودن SEO، غیرفعال‌بودن FAQPage gate، hygiene blockers، regex/anchor edge cases و ادغام پروفایل شهرستان روی `#place` یکتا |
| `tools/sa-tests/run-geo-registry-audit.php` | ساختار رجیستری استان/شهرستان، slugهای یکتا، ارجاع استان و شمار وضعیت snapshot؛ نه صحت رسمی یا وضعیت زنده |
| `tools/tests/test_wxr_audit.py` | ممیزی WXR: شمارش ساختاری، hygiene/FAQ/source، duplicateها و عدم افشای متن/فرادادهٔ SEO در گزارش |
| `tools/sa-tests/run-bot.php` | ربات راهنمای تلگرام: تنظیمات، ارتباط با Bot API، خلاصه‌ها، صفحه‌بندی، مسیرِ استان←شهر←دیدنی، جستجو، وب‌هوک (۴۰۳/۵۰۳/۲۰۰)، تنظیم وب‌هوک، پاک‌سازیِ ورودی و رندرِ صفحهٔ مدیریت |
| `tools/sa-tests/run-social.php` | انتشار خودکار: استخراج تصویر، ساخت متن، ارسال تلگرام/اینستاگرام، شرط‌ها و زمان‌بندی، **ارسالِ انبوه** و رندرِ صفحهٔ مدیریت |
| `tools/sa-tests/run-content-repair.php` | تعمیر مکانیکی `H1→H2`: تابع خالص (چند سربرگ/ویژگی‌ها/برچسب ناقص)، پیش‌نمایش و اعمالِ دسته‌ای، سقف دسته، capability/nonce و رندرِ بخش پیشخوان |
| `tools/sa-tests/run-autolink-audit.php` | ممیزیِ لینک‌سازی خودکار: ساختِ واژه‌نامه از دادهٔ واقعی و اجرای موتورِ `inc/internal-links.php` روی بدنهٔ صفحه‌ها (شبیه‌سازی رندر، بدون تغییر محتوا) |
| `tools/sa-tests/run-autolink-rules.php` | ۲۶ ادعا برای قواعدِ ضدخطایِ موتورِ لینک‌سازی: متنِ کاملِ نام، واژه‌های نامبهم («بافت شهری»)، پدیده/تقسیمِ چسبیده («رود شاهرود»، «بخش نطنز»، «کلان‌شهر کرمان»)، نامِ خودِ صفحه، یک‌بار‌بودنِ مقصد و سقفِ ۳۰ لینک |
| `tools/sa-tests/run-health-scan.php` | اسکنِ صفحه‌بندی‌شدهٔ سلامت محتوا: کامل‌بودن اسکن (۵۴۰ سند در ۳ صفحه)، سقفِ ایمنی فیلتردار، هشدارِ بریدگی و رندرِ صفحه با/بدون سقف |
| `tools/tests/test_audit_autolinks.py` | ممیزیِ رندرِ لینک‌سازی: یکسان‌سازی نام، شناساییِ نام‌های هم‌نام (تداخلِ واژه‌نامه)، تفکیکِ لینکِ تولیدشده از پیوندِ موجود، تشخیصِ لینکِ غلطِ بین‌استانی و پیوندِ خودارجاع، و دسته‌بندیِ اجرای php-wasm |
| `tools/tests/test_registry_live_diff.py` | مقایسهٔ رجیستری↔WXR: یکسان‌سازی نام (ی/ک عربی، نیم‌فاصله، پیشوند «شهرستان»)، تشخیص اختلاف slug فقط برای صفحهٔ منتشرشده، تکراری‌های منتشرشده، برخورد پیش‌نویس/سطل‌زباله، آمار لینک `/city/` استان و نبودِ متن مقاله در گزارش |
| `tools/tests/test_wxr_rollup.py` | rollup: دسته‌بندی ریسک، تشخیص H1/یادداشت/تکراری/نازک، حذفِ پیوست‌ها، ساختِ گزارش و CSV و نبودِ فرادادهٔ SEO |
| `tools/sa-tests/wp-stubs.php` | شبیه‌سازِ وردپرس (گزینه‌ها، متا، نوشته‌ها، ترنزینت‌ها، رویدادهای زمان‌بندی‌شده، درخواست‌های HTTP، REST، قلاب‌ها، توابعِ پیشخوان) |
| `tools/sa-tests/assert.php` | توابعِ سادهٔ ادعا و چاپِ خلاصه |

اجرا:

```bash
node     tools/phpwasm/exec.js tools/sa-tests/run-publish-gate.php
PHP_VER=7.4 node tools/phpwasm/exec.js tools/sa-tests/run-publish-gate.php
node     tools/phpwasm/exec.js tools/sa-tests/run-geo-registry-audit.php
PHP_VER=7.4 node tools/phpwasm/exec.js tools/sa-tests/run-geo-registry-audit.php
node     tools/phpwasm/exec.js tools/sa-tests/run-bot.php
PHP_VER=7.4 node tools/phpwasm/exec.js tools/sa-tests/run-bot.php
node     tools/phpwasm/exec.js tools/sa-tests/run-social.php
PHP_VER=7.4 node tools/phpwasm/exec.js tools/sa-tests/run-social.php
node     tools/phpwasm/exec.js tools/sa-tests/run-content-repair.php
PHP_VER=7.4 node tools/phpwasm/exec.js tools/sa-tests/run-content-repair.php
node     tools/phpwasm/exec.js tools/sa-tests/run-autolink-rules.php
node     tools/phpwasm/exec.js tools/sa-tests/run-health-scan.php
PHP_VER=7.4 node tools/phpwasm/exec.js tools/sa-tests/run-health-scan.php
SA_AUTOLINK_JOB=/ws/.tmp-autolink/job.json node tools/phpwasm/exec.js tools/sa-tests/run-autolink-audit.php
```

خروجیِ پایانی به‌شکل `PASS: 113   FAIL: 0` است و در صورت شکست، کدِ خروج `۱` برمی‌گردد.

نکته: در تست‌ها **هر اخطار یا نوتیسِ PHP به خطا تبدیل می‌شود** (`set_error_handler` در ابتدای هر فایل تست)، بنابراین «بدون خطا اجرا شدن» واقعاً بررسی می‌شود.
