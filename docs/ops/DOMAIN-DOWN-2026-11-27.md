# قطعی دامنهٔ `sarzaminaryan.ir` — تشخیص، ریشه و مسیر بازگرداندن

> ## 🔴 تصحیح تشخیص (۲۰۲۶-۱۱-۲۷، پس از نشست دوم)
>
> **این سند بر یک فرض غلط نوشته شده بود:** اینکه «دامنه به addon domain منتقل شده و یک دامنهٔ جدید اضافه شده است». مالک بعداً تصریح کرد: **دامنهٔ جدیدی وجود ندارد**؛ فقط `sarzaminaryan.ir` هست و آن یکی، **تایپو** است.
>
> **ریشهٔ واقعی:** در `wp_options`، مقدار `home` و `siteurl` به‌جای `sarzami**n**aryan.ir` نوشته شده `sarzami**ny**aran.ir`. وردپرس هر بازدید را به آن دامنهٔ ناموجود ریدایرکت می‌کرد.
>
> **سند درست و بستهٔ اصلاح:** [`DOMAIN-TYPO-FIX-2026-11-27.md`](DOMAIN-TYPO-FIX-2026-11-27.md) + SQL آماده در [`sql/domain-typo-fix.sql`](sql/domain-typo-fix.sql).
>
> بخش‌های زیر به‌عنوان **تاریخچهٔ سنجش** نگه داشته شده‌اند (اندازه‌گیری‌های DNS/TLS معتبرند)؛ ولی گام‌های cPanel با فرض «addon domain» نوشته شده‌اند و **اولویت ندارند**. اول SQL را اجرا کنید.

> **وضعیت در لحظهٔ نگارش (۲۰۲۶-۱۱-۲۷ / ۶ آذر ۱۴۰۵):** دامنه resolve می‌شود ولی **هیچ صفحه‌ای سرو نمی‌شود**؛ پورت ۸۰ صفحهٔ پیش‌فرض cPanel و پورت ۴۴۳ خطای TLS می‌دهد. سایت، فایل‌ها و پایگاه‌داده در جای خود هستند؛ مشکل در **لایهٔ هاست/دامنه** است، نه در وردپرس و نه در مخزن.
>
> بازسنجی همیشه با یک فرمان: `python3 tools/ops/site_health.py`

---

## ۱. سنجش واقعی (چه چیزی دیده شد)

| لایه | یافته | معنا |
|---|---|---|
| DNS · A | `sarzaminaryan.ir → 88.135.68.12` | دامنه به همان میزبان هاست (میزبان‌فا، `cip12.mizbanfadns.net`) اشاره می‌کند؛ DNS سالم است. |
| DNS · www | `www → CNAME sarzaminaryan.ir → 88.135.68.12` | پنجرهٔ www هم به همان میزبان می‌رود. |
| DNS · NS/MX | `dns1..4.mizbanfadns.net` · `mail.mizbanfalocal.com` | نیم‌سرورها و ایمیل هاست دست‌نخورده‌اند (ایمیل سایت از کار نیفتاده). |
| پورت ۸۰ | باز، ولی پاسخ `GET /` = **«SORRY! … Default Web Site Page»** (`/cgi-sys/defaultwebpage.cgi`) | Apache پاسخ می‌دهد اما **vhost این دامنه** روی سرور تعریف نشده؛ درخواست به «سایت پیش‌فرض» می‌افتد. همان پاسخ با دامنهٔ خود سرور (`cip12.mizbanfadns.net`) هم برمی‌گردد. |
| پورت ۴۴۳ | باز، ولی دست‌دادن TLS با SNI دامنه **EOF** می‌شود؛ گواهی‌ای ارائه نمی‌شود | برای دامنه هیچ vhost TLS/گواهی‌ای روی سرور نیست (AutoSSL ندارد چه چیزی را امضا کند). |
| `/wp-json/` | خطای TLS | وردپرس از بیرون در دسترس نیست. |
| گواهی‌های ثبت‌شده (crt.sh) | آخرین: Let's Encrypt، **۷ اکتبر ۲۰۲۶**، اعتبار تا ۵ ژانویه ۲۰۲۷ | تا پیش از این تغییر، SSL و vhost برقرار بوده؛ یعنی **یک تغییر در هاست/دامنه** این‌ها را از دسترس خارج کرده است. |
| فیلتر شبکهٔ محیط ما | دامنه‌های ایرانی (میزبان‌فا، دیجی‌کالا و…) از این سندباکس بسته‌اند؛ ولی درخواست HTTP از راه سرویس fetch بدون VPN به صفحهٔ پیش‌فرض cPanel رسید | این سنجش از بیرون هم تأیید شد؛ مشکل فقط از دید ما نیست. |

**نتیجه:** دامنه «پارک/آویزان» نیست و DNS هم غلط نیست؛ **دامنهٔ addon روی هاست به سند اصلی و vhost خودش وصل نشده**. یعنی دقیقاً همان چیزی که با انتقال به addon domain معمولاً رخ می‌دهد: مسیر سند دامنه عوض شده (یا `public_html` اصلی حالا مال دامنهٔ دیگر است) و Apache/SSL برای دامنهٔ تازه vhost نساخته است.

---

## ۲. ریشهٔ محتمل (به ترتیب احتمال)

1. **سند (document root) دامنهٔ addon اشتباه است.** پس از addon کردن، cPanel معمولاً سند را روی `public_html/<something>` می‌گذارد؛ در حالی که وردپرس در `public_html` (یا `public_html/sarzaminaryan`) است. نتیجه: vhost هست ولی پوشهٔ خالی/اشتباه، و اگر پوشه خالی باشد صفحهٔ پیش‌فرض می‌آید.
2. **دامنهٔ اصلی قبلی هنوز سند اصلی را در دست دارد** و حالا برای دامنهٔ addon vhost جدا ساخته نشده/غیرفعال است (در cPanel، دامنهٔ اصلی قابل حذف نیست و باید سندش دستی عوض شود).
3. **SSL برای دامنهٔ تازه صادر نشده** (AutoSSL بعد از تغییر vhost هنوز اجرا نشده یا شکست خورده) → پورت ۴۴۳ بی‌گواهی = EOF.
4. کم‌احتمال‌تر: حساب معلق/بسته، یا جابه‌جایی حساب بین سرورها (که در آن حالت DNS به IP قدیمی می‌ماند و همین نشانه‌ها را می‌دهد).

قرینهٔ مهم: **ایمیل سایت هنوز کار می‌کند** (MX و SPF دست‌نخورده) — یعنی حساب هاست حذف/معلق نشده و مشکل محدود به وب (vhost + SSL) است.

---

## ۳. کاری که باید در cPanel انجام شود (ترتیب دقیق)

> این بخش باید با دسترسی مالک/هاست انجام شود. اگر بخواهید، من هم می‌توانم با یک فایل کار (job) و از طریق پل ایجنت، فقط *خواندنی* بررسی کنم؛ اما **تا وقتی `https://sarzaminaryan.ir/wp-json/` بالا نیامده، پل هم نمی‌تواند وصل شود** (خودش از همین دامنه استفاده می‌کند).

### گام ۰ — قبل از هر تغییر: پشتیبان
- cPanel ← **JetBackup / Backup / Backup Wizard** ← پشتیبان کامل (Home Directory + Databases).
- اگر UpdraftPlus روی سایت نصب است، آخرین پشتیبان Dana/DB را از پنل وردپرس هم بگیرید — ولی الان پنل وردپرس هم از راه دامنه باز نمی‌شود؛ پس پشتیبان هاست کافی است.

### گام ۱ — پیدا کردن مسیر واقعی فایل‌های وردپرس
- cPanel ← **File Manager** ← `public_html` را باز کنید.
- ببینید `wp-config.php` و `wp-content/` کجا هستند:
  - حالت الف) `public_html/wp-config.php` موجود است ← **سند درست = `public_html`**
  - حالت ب) `public_html/sarzaminaryan/wp-config.php` یا `public_html/site/...` ← **سند درست = همان پوشه**
- مسیر را یادداشت کنید؛ در گام ۳ لازم است.

### گام ۲ — بررسی وضعیت دامنه در cPanel
- cPanel ← **Domains** (در نسخه‌های جدید) یا **Addon Domains**:
  - ردیف `sarzaminaryan.ir` باید **Document Root** برابر مسیر گام ۱ داشته باشد.
  - اگر Document Root اشتباه است: آیکن **Manage / ✎** ← اصلاح مسیر ← ذخیره.
- اگر دامنه به‌عنوان **Alias/Domain** اضافه شده و سند اصلی حساب روی دامنهٔ دیگر است، همان‌جا **Document Root** را به مسیر وردپرس تغییر دهید.

### گام ۳ — اصلاح سند (اگر با UI نشد، از راه `.htaccess` هم می‌شود)
اگر پنل اجازهٔ تغییر سند نداد، در `public_html/.htaccess` **بالای** همهٔ قواعد وردپرس این بلوک را بگذارید تا درخواست‌های همین دامنه به پوشهٔ درست برود (مسیر را با مسیر واقعی عوض کنید):

```apache
# هدایت درخواست‌های دامنهٔ اصلی به پوشهٔ وردپرس (در صورت اشتباه بودن Document Root)
RewriteEngine On
RewriteCond %{HTTP_HOST} ^(www\.)?sarzaminaryan\.ir$ [NC]
RewriteCond %{REQUEST_URI} !^/sarzaroot/
RewriteRule ^(.*)$ /sarzaroot/$1 [L]
```

> ⚠️ این راه فقط «چسب زخم» است. راه درست، اصلاح **Document Root** در بخش Domains است؛ `.htaccess` را وقتی بگذارید که به پنل دسترسی ندارید.

### گام ۴ — ساخت SSL برای دامنه
- cPanel ← **SSL/TLS Status** ← `sarzaminaryan.ir` و `www.sarzaminaryan.ir` را تیک بزنید ← **Run AutoSSL**.
- منتظر بمانید پیام `Certificate installed` بیاید (چند دقیقه).
- اگر شکست خورد، پیام خطا را برایم بفرستید (اغلب: «DCV failed» چون دامنه به این سرور resolve نمی‌شود یا vhost هنوز ساخته نشده).

### گام ۵ — بازسازی vhost (اگر گام ۲ و ۴ اثر نکرد)
- cPanel ← **Domains** ← حذف و **دوباره افزودن** دامنه با سند درست. (حذف دامنه فایل‌ها و پایگاه‌داده را پاک نمی‌کند، ولی پیش از آن پشتیبان گام ۰ را داشته باشید.)
- بعد دوباره گام ۴ (AutoSSL) را اجرا کنید.
- اگر به WHM دسترسی دارید: `WHM → Rebuild the Apache Configuration` و `WHM → SSL/TLS → Manage AutoSSL → Run for sarzaminaryan.ir`.

### گام ۶ — اگر سایت بالا آمد ولی به دامنهٔ قدیمی/دیگری ریدایرکت شد
این یعنی **آدرس وردپرس** هنوز روی دامنهٔ قبلی است. دو راه:

**الف) از پنل وردپرس (ساده‌تر):** `https://sarzaminaryan.ir/wp-admin/` ← Settings → General ← `WordPress Address (URL)` و `Site Address (URL)` را روی `https://sarzaminaryan.ir` بگذارید.

**ب) اگر پنل باز نشد، از پایگاه‌داده (phpMyAdmin ← پایگاه‌دادهٔ وردپرس ← SQL):**

```sql
-- پیش از اجرا: نام جدول‌ها را با پیشوند واقعی خود عوض کنید (مثلاً wp_ یا xyz_)
SELECT option_name, option_value FROM wp_options
WHERE option_name IN ('siteurl','home');

UPDATE wp_options SET option_value = 'https://sarzaminaryan.ir'
WHERE option_name = 'home';
UPDATE wp_options SET option_value = 'https://sarzaminaryan.ir'
WHERE option_name = 'siteurl';
```

اگر محتوای قدیمی لینک‌های دامنهٔ قبلی را داخل متن دارند (مهاجرت کامل دامنه)، این جست‌وجو/جایگزینی را هم اجرا کنید — **اول پشتیبان، بعد**:

```sql
-- تعداد لینک‌های باقی‌مانده با دامنهٔ قدیمی را بشمارید (به‌جای OLD-DOMAIN دامنهٔ قبلی را بگذارید)
SELECT COUNT(*) FROM wp_posts WHERE post_content LIKE '%OLD-DOMAIN%';
-- سپس در صورت نیاز (ترجیحاً با ابزار Better Search Replace، نه دستی):
UPDATE wp_posts SET post_content = REPLACE(post_content, 'https://OLD-DOMAIN', 'https://sarzaminaryan.ir');
UPDATE wp_posts SET guid = REPLACE(guid, 'https://OLD-DOMAIN', 'https://sarzaminaryan.ir');
```

در `wp-config.php` هم اگر ثابت‌های زیر وجود دارند، باید با دامنهٔ فعلی هم‌خوان باشند (وگرنه تغییر بالا در پنل بی‌اثر می‌ماند):

```php
define('WP_HOME',    'https://sarzaminaryan.ir');
define('WP_SITEURL', 'https://sarzaminaryan.ir');
```

### گام ۷ — پاک‌سازی کش
- cPanel ← **LiteSpeed Web Cache Manager** (اگر هست) ← Purge All.
- وردپرس ← افزونهٔ کش (LiteSpeed Cache) ← Purge All.

### گام ۸ — تأیید
- `python3 tools/ops/site_health.py` باید `تشخیص: ok` بدهد.
- بعد در مرورگر: `https://sarzaminaryan.ir/` ، `/wp-login.php` ، `/city/tehran/` ، `/wp-json/`.
- سپس shِ سئو: در Google Search Console (اگر دسترسی دارید) `Change of Address` لازم **نیست** چون دامنه عوض نشده؛ فقط **Sitemap** را دوباره submit کنید (`/wp-sitemap.xml`).

---

## ۴. وقتی سایت برگشت — کارهای این مخزن

1. `python3 tools/ops/site_health.py` را در `docs/` ثبت کنید (سنجش قطعی/برگشت).
2. اجرای فقط‌خواندنی `ping` با پل ایجنت (workflow `wp-agent`، فقط از `main`) تا معلوم شود `SA_SITE_URL` در GitHub Secrets با دامنهٔ فعلی هم‌خوان است:

```bash
gh workflow run wp-agent.yml -f job=tools/wp-agent/jobs/<job-ping>.json
```

> اگر سایت روی دامنهٔ دیگری (مثلاً `www` یا دامنهٔ addon) بالا آمد، **مقدار `SA_SITE_URL` در GitHub Secrets** هم باید همان باشد؛ در غیر این صورت پل ایجنت و اجرای جابه‌جایی‌ها با خطای شبکه متوقف می‌شود (نمونهٔ ثبت‌شده: شکست `network error on POST /post/105` در `results/20261119-24-experiences-batch9.json`).

3. اگر لازم شد سایت **موقتاً** روی دامنهٔ دوم بالا بماند، تا اصلاح کامل DNS آن را در `SEO` به‌عنوان canonical اعلام نکنید؛ بعد از برگشت `canonical` باید `https://sarzaminaryan.ir` باشد (تنظیم در `inc/seo.php` قالب؛ تغییرش نیازمند نسخهٔ تازه و Release است).

---

## ۵. چه چیزی از این حادثه یاد گرفتیم (برای حافظهٔ پروژه)

- **تست پیش از تغییر دامنه:** قبل از هر تغییر در addon/دامنه، دو سنجه بگیرید: `site_health.py` و یک `curl -I` از بیرون؛ بعد از تغییر، همان دو را تکرار کنید. تفاوت «صفحهٔ پیش‌فرض cPanel» با «سایت واقعی» دقیقاً همین ابزار است.
- **آدرس‌های hard-coded:** `SA_SITE_URL`, `.env.example` و مستندات همگی `https://sarzaminaryan.ir` را دارند. تغییر دامنه بدون به‌روزرسانی این‌ها یعنی قطع پل، سئو و sitemap.
- **هیچ‌گاه `home`/`siteurl` را بدون پشتیبان عوض نکنید**؛ و در مهاجرت دامنه، `guid` را هم در نظر بگیرید (برای فیدها و تصاویر).

---

*سنجه‌ها و متن این سند فقط‌خواندنی‌اند و هیچ تغییری روی سایت زنده یا پایگاه‌داده انجام نشده است.*
