# tools/county-sync — نوشتن شناسنامهٔ شهرستان روی وردپرس

ابزار فاز ۲ و ۳ نقشهٔ راه شناسنامهٔ شهرستان (`docs/county-identity-roadmap.md`):
کلیدهای کانونی متا را از یک فایل JSON منبع‌دار می‌خواند و از راه WordPress REST API
روی نوشتهٔ `city` همان شهرستان می‌نویسد؛ پیش از هر نوشتن، پشتیبان کامل پست را
در `backup/{id}.json` ذخیره می‌کند و بعد از نوشتن، نتیجه را راستی‌آزمایی و گزارش می‌کند.

## فایل‌ها

| فایل | نقش |
|---|---|
| `sync_county_meta.py` | اسکریپت اجرا (فقط کتابخانهٔ استاندارد پایتون ۳.۹+) |
| `payload.template.json` | قالب فایل دادهٔ یک دسته از شهرستان‌ها |

فایل‌های دادهٔ هر دسته، پشتیبان‌ها و `progress.json` در مخزن کامیت **نمی‌شوند**
(`.gitignore`: `progress.json`، `reports/`، `tools/importers/`)؛ محل پیشنهادی دادهٔ دسته‌ها
`tools/importers/county-identity/<batch>.json` است.

## .env

در ریشهٔ مخزن (کپی از `.env.example`)؛ هرگز در چت یا مخزن چاپ/کامیت نمی‌شود:

```
SA_SITE_URL=https://sarzaminaryan.ir
SA_USER=<نام کاربری>
SA_APP_PASSWORD=<Application Password>
```

> **مانع شناخته‌شده:** `inc/security.php` فعلاً
> `add_filter( 'wp_is_application_passwords_available', '__return_false' )` را دارد.
> تا برداشتن موقت این فیلتر (یا استفاده از WP-CLI روی سرور)، نوشتن با Application
> Password با ۴۰۱ شکست می‌خورد. اسکریپت در این حالت فقط پیام خطا را می‌دهد و چیزی نمی‌نویسد.

## اجرا

```bash
# فقط گزارش، بدون هیچ نوشتنی
python3 tools/county-sync/sync_county_meta.py tools/importers/county-identity/samples.json --dry-run

# نوشتن واقعی (با پشتیبان‌گیری خودکار در backup/)
python3 tools/county-sync/sync_county_meta.py tools/importers/county-identity/samples.json

# فقط چند کلید مشخص
python3 tools/county-sync/sync_county_meta.py payload.json --only county_en,center_fa,main_city

# ثبت پیشرفت دسته (فاز ۳) و تعیین date_modified واقعی همان دسته
python3 tools/county-sync/sync_county_meta.py batch-01.json --progress progress.json --modified-date 2026-10-18T09:30:00
```

## قواعدی که اسکریپت اجرا می‌کند

1. **منبع اجباری:** هر کلید داخل `meta` باید در `sources` همان رکورد منبع معتبر داشته باشد؛
   در غیر این صورت آن کلید نوشته نمی‌شود و اگر هیچ کلیدی نماند، اجرا با کد ۲ متوقف می‌شود.
2. **کلید مجاز:** فهرست کلیدها مستقیم از `sa_county_profile_fields()` در
   `inc/county-profile.php` خوانده می‌شود؛ کلید ناشناخته خطاست (واگرایی مخزن و سایت ممکن نیست).
3. **جمعیت با سال:** `population` بدون `population_year` نوشته نمی‌شود.
4. **مختصات معتبر:** `lat`/`lng` فقط با هم و داخل بازهٔ ±۹۰/±۱۸۰.
5. **نامک و تاریخ انتشار دست‌نخورده:** اسکریپت هیچ‌وقت `slug` یا `date` را نمی‌فرستد؛
   `date_modified` فقط با پرچم صریح `--modified-date`.
6. **پشتیبان پیش از نوشتن:** `backup/{id}.json` شامل محتوا، متا، تصویر شاخص، تاریخ‌ها و وضعیت؛
   با `--no-backup` قابل رد کردن است (توصیه نمی‌شود).
7. **راستی‌آزمایی پس از نوشتن:** پست دوباره خوانده می‌شود و هر کلیدی که مقدارش ننشسته باشد
   با ⚠ گزارش می‌شود.
8. **گزارش پایانی:** برای هر شهرستان — شناسهٔ پست، لینک صفحه، کلیدهای نوشته‌شده با منبعشان،
   و فهرست کلیدهایی که خالی ماندند؛ به‌همراه شهرستان‌های ردشده و دلیل رد شدن (خروجی JSON).

## منابع مجاز داده

مرکز آمار ایران، استانداری/فرمانداری، ادارهٔ کل میراث فرهنگی استان، سازمان هواشناسی،
و کتاب یا پژوهش منتشرشده. ویکی‌پدیا و متن فعلی صفحه فقط سرنخ‌اند؛ دادهٔ بی‌منبع
نوشته نمی‌شود و خانهٔ آن کلید در کارت شناسنامه خالی می‌ماند (یعنی نمایش داده نمی‌شود).
