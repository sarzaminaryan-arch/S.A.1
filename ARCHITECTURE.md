# 🏛️ معماری فنی پروژه

> آخرین آپدیت: ۱۴۰۵/۰۷/۲۵ (۲۰۲۶-۱۰-۱۷) — بر پایهٔ اسکن کامل مخزن (مرحلهٔ ۱) و بازبینی دستی کد.
> منبع هر عدد در این سند، خودِ مخزن است؛ موارد نامشخص با **[نیاز به تأیید کاربر]** مشخص شده‌اند.
> 🚫 = فایل یا پوشه‌ای که به‌هیچ‌وجه نباید تغییر کند (هم‌راستا با بخش ۶ از `CLAUDE.md`).

---

## ساختار فایل‌ها (Tree)

```
S.A.1/                                            (۶۸۶ فایل · ۷۴ پوشه · ۳۷MB · .git ≈ ۱۹۷MB)
│
├── 🚫 wp-config.php , .htaccess                  ↩ در مخزن نیستند (فقط روی هاست)
│
├── CLAUDE.md                                     🧠 فایل هوش پروژه (۱۲ بخش، الزامی برای هر Agent)
├── PROJECT_STATUS.md                             📊 گزارش وضعیت (۱۵ مشکل + جدول ۲۰ فایل کلیدی)
├── ARCHITECTURE.md                               🏛️ این فایل
├── AGENT_RULES.md                                📋 قوانین اجباری Agent
├── AGENTS.md  ·  pyproject.toml  ·  .gitignore  ·  .env.example
├── 🚫 imagetool (23).zip                         ⚠️ ۱.۹MB — خلاف سیاست مخزن، باید حذف شود
│
├── wp-content/
│   ├── 🚫 themes/sarzaminaryan/                  ⛔ قالب والد ۱.۱.۰ — ممنوع مطلق (۱۶ فایل)
│   │   ├── 🚫 functions.php                      ۱۴ تابع `sarzaminaryan_*`، $content_width، ثبت منوها
│   │   ├── 🚫 header.php / footer.php            ساختار پایه (در فرزند override شده‌اند)
│   │   ├── 🚫 style.css                          فقط سربرگ؛ استایل‌ها در assets/css
│   │   ├── 🚫 inc/  (customizer, template-tags, sanitization, template-functions)
│   │   ├── 🚫 assets/css/{main,main-rtl,fonts,editor-style}.css · js/main.js
│   │   ├── 🚫 assets/fonts/fa/Vazirmatn-{Regular,Medium,Bold}.woff2
│   │   └── 🚫 languages/fa_IR.po|.mo · template-parts/ · screenshot.png · readme.txt
│   │
│   ├── themes/sarzaminaryan-child/               ✅ قالب فعال — ۲.۲۲.۱۸ (۱۳۶ PHP · ۵۱٬۸۶۷ خط · ۷۳۰ تابع)
│   │   ├── functions.php                         نقطهٔ ورود: SA_CHILD_VERSION 2.22.18، SA_MODEL_VERSION 1.3
│   │   │                                         و require_once ی ۳۹ ماژول از inc/
│   │   ├── style.css                             فقط سربرگ (Template: sarzaminaryan)
│   │   ├── header.php · footer.php               override کامل والد (نوار تیره v2 + فوتر سه‌ستونه)
│   │   ├── template-home.php (۳۱KB) · template-dashboard.php (۲۴KB)
│   │   ├── single-{attraction,city,province,local_food,souvenir,travel_route}.php · single.php
│   │   ├── archive-{…}.php ×۷ · archive.php · taxonomy-province_tax.php · taxonomy.php
│   │   ├── page.php · 404.php · search.php · searchform.php
│   │   ├── inc/   (۳۹ ماژول — هستهٔ منطق پروژه)
│   │   │   ├── 🚫 entities-config.php            ⚠️ تولیدی — سرآیند «model v1.2» در برابر مرجع ۱.۳
│   │   │   ├── post-types.php · taxonomies.php   ثبت ۶ CPT و ۵ طبقه‌بندی از روی config
│   │   │   ├── meta-fields.php · relations.php   جعبه‌های متا و روابط استان←شهرستان←دیدنی
│   │   │   ├── publish-gate.php                  دروازهٔ انتشار Level 7 (حالت advisory)
│   │   │   ├── seo.php · schema.php · citations.php · breadcrumbs.php · redirects.php
│   │   │   ├── internal-links.php · toc.php · entity-bars.php · region-map.php
│   │   │   ├── geo-counties.php · geo-import.php · county-profile.php · province-profile.php
│   │   │   ├── gallery.php (آلبوم + WebP) · featured-images.php · media-restore.php
│   │   │   ├── education.php · dashboard.php · ready-articles.php
│   │   │   ├── security.php · performance.php · system-diagnostics.php · jalali.php
│   │   │   ├── customizer.php · admin.php · content-health.php · content-repair.php
│   │   │   ├── site-cleanup.php                  ⚠️ شامل حذف برگشت‌ناپذیر — اجرا روی سایت ممنوع
│   │   │   ├── city-contrib.php + city-contrib/  مشارکت مردمی (۱۵ کلاس: OTP/نقش/امتیاز/نظارت/REST)
│   │   │   ├── contact-form.php · social-publish.php · telegram-bot.php
│   │   │   ├── github-updater.php · activation.php · setup.php · helpers.php · template-tags.php
│   │   ├── data/
│   │   │   ├── counties.php (۴۸۳) · provinces.php (۳۱) · attractions.php (۸۰ جاذبهٔ داشبورد)
│   │   │   ├── region-facts.php · borders.php · redirects.php · province-profiles.json
│   │   │   ├── ready-articles.php                ۳۸ مقالهٔ آماده (۰.۹۷MB)
│   │   │   └── 🚫 ready-guides.php               تولیدی — خروجی build_theme_data.py (۱.۵MB)
│   │   ├── template-parts/                       content-* ×۹ · card-entity · archive-entities
│   │   │   └── entity/                           hero · facts · faq · gallery · county-profile
│   │   │                                         province-identity · identity-attraction · related-*
│   │   ├── assets/css/{child,dashboard,gallery,city-contrib,ready-articles}.css
│   │   ├── assets/js/{child,dashboard,gallery,city-contrib,ready-articles,
│   │   │               iran-provinces-map,iran-counties-map}.js
│   │   ├── assets/img/  (logo.webp · articles/ ۱۴ پوشه · guides/ + ۲۰۴ کارت · captioned/ · svg/)
│   │   ├── tests/       (۱۱ فایل تست PHP — فقط در CI اجرا می‌شوند)
│   │   └── CHANGELOG.md (۲۵۹KB) · readme.txt · screenshot.png
│   │
│   └── 🚫 plugins/sa-ai-sentence-cleaner/        افزونهٔ پاک‌ساز ۱.۰.۱ (موتور + فروشگاه + صفحهٔ مدیریت)
│
├── data-model/
│   ├── MASTER_DATA_MODEL.md                      📕 مرجع canonical مدل (v1.3) — مبدأ تغییرات مدل
│   ├── schema/data-model.yaml                    منبع دستی مولد
│   ├── 🚫 schema/data-model.json                 تولیدی (build_json.py)
│   └── schema/build_json.py · build_child_config.py
│
├── tools/
│   ├── content-lint/       آنالیز ۶۷ مقالهٔ کمپینگ + build_theme_data.py → data/ready-guides.php
│   ├── images/             ساخت کارت‌های تصویری (آبرنگ، موضوعی، featured)
│   ├── county-sync/        نوشتن شناسنامهٔ شهرستان از راه REST + پشتیبان backup/{id}.json
│   ├── image-caption/      ·  build-ai-cleaner-plugin.py
│
├── ai_cleaner/            ابزار پایتونِ پاک‌ساز جمله‌ها (هم‌راستا با افزونهٔ PHP)
├── docs/                  ۱۷ سند عملیاتی + camping-education (۶۷ مقاله) + content-audit
│   └── project/           ۱۳ سند اولیه (مرحلهٔ ۲) + README ایندکس
├── skill-SA-agent/        SKILL.md + ۷ مرجع استاندارد (تم، امنیت، RTL، سئو، محتوا، گردش کار)
├── data/drafts/           stage-0-source-register.json
├── tests/                 test_ai_cleaner.py (پایتون)
└── .github/workflows/     release-child-theme · release-ai-cleaner-plugin
                           content-lint · fetch-drive-markdown
```

---

## جریان داده و معماری

### 🔌 پلاگین‌های کلیدی تأثیرگذار

| پلاگین | محل | نقش در معماری | قطعیت |
|---|---|---|---|
| `sa-ai-sentence-cleaner` ۱.۰.۱ | داخل مخزن | اسکن مقالات منتشرشده، شناسایی جمله‌های قالبی/هوش‌مصنوعی/تکراری، حذف با **پشتیبان و بازگردانی** (`class-sa-ai-store.php`)؛ هیچ تیتر، جدول، تصویر یا منبع معتبری حذف نمی‌شود | ✅ قطعی (در مخزن) |
| LiteSpeed Cache | سایت زنده | لایهٔ کشِ سمت هاست — روی رفتار تغییرات قالب (purge پس از انتشار) و نتایج سنجش کارایی اثر مستقیم دارد | **[نیاز به تأیید کاربر]** (از `active_plugins` دامپ ۲۰۲۶-۱۰-۰۸) |
| Rank Math Pro + Rank Math | سایت زنده | در صورت فعال‌بودن، `sa_seo_head()` در `inc/seo.php` خروجی خود را کنار می‌کشد و گره‌های سطح سایت (Organization/WebSite/Breadcrumb/Article) به افزونه واگذار می‌شود؛ اما جعبه‌های `sa_seo_*` همچنان ثبت و توسط publish-gate الزامی می‌شوند → **مالک یگانهٔ title/description/canonical/OG نامشخص است** (مورد TG-01 باز) | **[نیاز به تأیید کاربر]** |

> نکتهٔ معماریِ مهم: پروژه روی اصلِ **«بدون افزونه»** بنا شده — گالری، فرم تماس، مشارکت مردمی، انتشار به تلگرام/اینستاگرام، ربات، مقالات آماده و به‌روزرسان گیت‌هاب همگی داخل قالب فرزند پیاده شده‌اند. افزونهٔ پاک‌ساز تنها استثناست چون ابزار عملیاتی است، نه بخشی از سایت.

### ⚙️ توابع و منطق کلیدی

| لایه | پیشوند | تعداد (استخراج‌شده از کد) | نمونه‌ها |
|---|---|---|---|
| قالب فرزند | `sa_` | **۶۳۹ تابع یکتا** (از مجموع **۷۳۰** تابع تعریف‌شده در فرزند) | `sa_entity_types()`, `sa_archive_url()`, `sa_breadcrumbs()`, `sa_gate_split()`, `sa_jalali_year()`, `sa_number()`, `sa_social_links()`, `sa_nav_entity_types()`, `sa_county_profile_fields()` |
| قالب والد | `sarzaminaryan_` | **۱۴ تابع** | `sarzaminaryan_setup()`, `sarzaminaryan_widgets_init()`, `sarzaminaryan_assets()`, `sarzaminaryan_preload_font()` |
| ثابت‌ها | `SA_` | — | `SA_CHILD_VERSION`, `SA_CHILD_DIR/URI`, `SA_MODEL_VERSION`, `SA_ENABLE_ACCOMMODATION`, `SA_GITHUB_TOKEN` |
| کلیدهای متا | `sa_` | — | `sa_province_id`, `sa_city_id`, `sa_seo_title`, `sa_seo_description`, `sa_show_attraction_image`, ۲۸ کلیدِ `county-profile` |
| کلاس‌های مشارکت مردمی | `SA_CC_` | ۱۵ کلاس در `inc/city-contrib/` | `class-cc-otp.php`, `class-cc-rating.php`, `class-cc-moderation.php`, `class-cc-rest.php` |

**زنجیرهٔ اجرا (Bootstrap):** `functions.php` → ۳۹ ماژول `inc/` (ترتیب معنادار: `entities-config` ← `helpers` ← `system-diagnostics` ← `setup` ← `post-types` ← `taxonomies` ← `meta-fields` ← … ← `github-updater` ← `activation`).

**زنجیرهٔ دادهٔ مدل (مهم‌ترین جریان معماری):**
```
data-model/schema/data-model.yaml  (منبع دستی، v1.3)
        │  build_child_config.py
        ▼
inc/entities-config.php            (تولیدی — ⚠️ اکنون «v1.2»؛ drift دارد)
        ▼
inc/post-types.php · taxonomies.php · meta-fields.php · relations.php · publish-gate.php
        ▼
ساختار وردپرس (CPTها، طبقه‌بندی‌ها، متاباکس‌ها، دروازهٔ انتشار)
        ▼
template-parts/entity/*  ←  inc/schema.php (JSON-LD)  ←  inc/seo.php
```

**جریان محتوا:**
```
docs/camping-education/articles/*.md (۶۷ مقاله)
        │  tools/content-lint/build_theme_data.py --check
        ▼
data/ready-guides.php  ──►  inc/ready-articles.php  ──►  پیش‌نویس در وردپرس
data/ready-articles.php (۳۸ مقالهٔ دیدنی) ─────────►  پیش‌نویس (انتشار فقط با تأیید مالک)
```

**جریان انتشار:** پیش‌نویس → `publish-gate.php` (Level 7 در حالت **advisory**: فقط یادآوری، هرگز مسدود نمی‌کند) → انتشار با تأیید مالک → `schema.php`/`seo.php` → `internal-links.php` و `toc.php` روی خروجی → `social-publish.php`/`telegram-bot.php` (در صورت فعال‌بودن).

**جریان به‌روزرسانی:** GitHub Release (ZIP + SHA-256) → `inc/github-updater.php` → «پیشخوان ← به‌روزرسانی‌ها».

### 🗂️ موجودیت‌ها (Entities) و ساختار داده

| موجودیت (CPT) | وضعیت | توضیح |
|---|---|---|
| `province` (استان) | فعال | ۳۱ استان منتشر |
| `city` (شهرستان) | فعال | ۴۸۳ رکورد در `data/counties.php` (در برابر ۴۸۴ رسمی — مشکل #۷) |
| `attraction` (دیدنی/نمای برتر) | فعال | ۲۶ منتشر در آخرین WXR؛ ۳۸ مقالهٔ آماده؛ ۸۰ جاذبه در داشبورد |
| `travel_route` (مسیر سفر) | ثبت‌شده اما پنهان | از منو/پیشخوان کنار رفته (ساختار ۴ ستون) |
| `local_food` (غذای محلی) | ثبت‌شده اما پنهان | همان وضعیت |
| `souvenir` (سوغات) | ثبت‌شده اما پنهان | همان وضعیت |
| `accommodation` (اقامتگاه) | **رزرو شده** | فقط با ثابت `SA_ENABLE_ACCOMMODATION` فعال می‌شود |

**طبقه‌بندی‌ها (۵ مورد + ۱ رزرو):** `province_tax` (۳۱ استان، seeding باید‌هم‌گردان/idempotent)، `attraction_type`، `travel_season`، `travel_budget`، `travel_duration` (+ `accommodation_type` رزرو).

**روابط:** `attraction.sa_city_id → city` و `city.sa_province_id → province` (زنجیرهٔ استان ← شهرستان ← دیدنی)؛ مدیریت در `inc/relations.php` و لینک‌سازی داخلی یکتا در `inc/internal-links.php`.

**زیرسامانه‌های داخلی مرتبط با داده:**
- **شناسنامهٔ شهرستان** (`inc/county-profile.php` + `template-parts/entity/county-profile.php`): ۲۸ کلید کانونی با fallback به `sa_cty_*`/`sa_city_*`؛ ثبت با `register_post_meta(..., 'show_in_rest' => true)`؛ قوانین خروجی (خانهٔ خالی حذف می‌شود، جمعیت بدون سال چاپ نمی‌شود، مختصات اعتبارسنجی می‌شود).
- **گالری آلبومی** (`inc/gallery.php`): هر استان/شهرستان یک آلبوم؛ تبدیل خودکار به WebP، حذف metadata، واترمارک؛ تصاویر مردمی ابتدا `pending`.
- **فرم تماس** (`inc/contact-form.php`): داخلیِ قالب، بدون افزونه.
- **مشارکت مردمی «شهر من»** (`inc/city-contrib/`): ورود با کد پیامکی (OTP)، امتیازدهی ۷ ستاره، پنل نظارت، REST روی namespace `cc/v1`.
- **داشبورد سایبری** (`inc/dashboard.php` + `template-dashboard.php` + ۲ نقشهٔ JS): ۳۱ استان → ۴۷۸ شهرستان، شناسنامهٔ استان/شهرستان، شمارندهٔ پوشش مقالات (۳۸ از ۸۰).

### 🎨 وضعیت قالب

| مورد | توضیح |
|---|---|
| الگو | **Child/Parent** واقعی — `Template: sarzaminaryan` در سربرگ فرزند؛ والد برای فعال‌سازی الزامی است |
| تقسیم نقش | والد: پایهٔ استاندارد (supports، منوها، اندازه‌های تصویر، enqueue، فونت محلی). فرزند: **تمام** امکانات پروژه |
| Overrideها | فرزند `header.php`، `footer.php`، `single*`، `archive*`، `taxonomy*`، `page.php`، `404.php`، `search*` را کاملاً جایگزین کرده است |
| دارایی‌ها | والد: `fonts.css` → `main.css` (+`main-rtl.css` خودکار) → `style.css`. فرزند: `child.css` با وابستگی به `sarzaminaryan-style` و اولویت ۲۰؛ `child.js` با `defer` در فوتر |
| یکپارچگی نسخه | `Version` در `style.css` = `SA_CHILD_VERSION` در `functions.php` = `Stable tag` در `readme.txt` = **2.22.18** (workflow این برابری را اجبار می‌کند) |
| سلامت فعلی | هیچ نشانی از خرابی یا ویرایش مخرب در والد نیست؛ ۲۰ ایراد نسخهٔ قبلی (Quantum Pedia) در ۱.۱.۰ رفع شده است |

---

## نقاط ضعف و مشکلات معماری فعلی

**۱. 🔴 انحرافِ مولد از مدل مرجع (Generator Drift)**
`inc/entities-config.php` یک «مادهٔ مصنوعی» (generated artifact) است که سرآیند آن هنوز **model v1.2** را نشان می‌دهد، در حالی که مرجع **۱.۳** است. طبق `docs/technical-audit-checklist.md` این فایل با مولد round-trip نمی‌شود (اجرای `build_child_config.py` فیلدهای اختصاصی را حذف و حداقل منابع دیدنی را از ۰ به ۵ تغییر می‌دهد).
**پیامد معماری:** مدل داده در عمل «منبع حقیقت» نیست و مخزنِ اصلیِ ثبات، یک فایل تولیدیِ ویرایش‌شدهٔ دستی است — هر بازتولید ناخواسته، ساختار را عوض می‌کند.

**۲. 🔴 معماریِ انتشار و نسخه‌گذاری شکننده است**
- شمارهٔ نسخه در **سه جا** تکرار شده (`style.css`، `functions.php`، `readme.txt`) و workflow فقط برابریِ آن‌ها را چک می‌کند، نه یکتاییِ تگ را.
- تگ‌های گیت و ریلیزها از هم جدا افتاده‌اند (`v2.22.1` دو رکورد ریلیز بدون تگ؛ `v2.21.0` دو رکورد؛ `v2.22.0` Draft). در نتیجه گاردِ «نسخهٔ ازپیش‌منتشرشده» در `release-child-theme.yml` عملاً دور زده می‌شود.
- هر push روی مسیرهای حساس (حتی یک CHANGELOG) کل خط لوله را اجرا می‌کند → دو اجرای ناموفق در ۱۳ ساعت گذشته.

**۳. 🔴 انشعابِ خطوط توسعه (Branch Divergence)**
سه PR بازِ موازی روی خطوط قدیمی (#21 روی ۲.۱۸.x، #22 روی ۲.۲۱.۰، #23 روی مقالات آماده) در حالی که هیچ شاخهٔ `develop` یا قرارداد `feature/*` و `fix/*` وجود ندارد. سابقهٔ پروژه نشان می‌دهد این الگو قبلاً به تضاد نسخه (۲.۱۸.۹) و ادغام‌های دستیِ پرهزینه انجامیده است.

**۴. 🔴 دادهٔ حجیم درون لایهٔ کد**
`data/ready-guides.php` (۱.۵MB) و `data/ready-articles.php` (۰.۹۷MB) درون پوشهٔ قالب‌اند و باید بررسی شود در هر بارگذاری عمومی هم پارس می‌شوند یا نه. همین الگو در دارایی‌ها تکرار شده: ۲۸۲ فایل WebP در درخت مخزن و `.git` ≈ ۱۹۷MB. این‌ها هم هزینهٔ CI/بسته‌بندی را بالا می‌برند و هم با سیاست «بسته‌های تصویری فقط در Release» در تضادند.

**۵. 🔴 خط لولهٔ تأییدِ کیفیت ناقص و وابسته به CI**
PHP در محیط توسعه در دسترس نیست، بنابراین `php -l` و ۱۱ فایل تست PHP فقط در گیت‌هاب اجرا می‌شوند؛ حلقهٔ بازخورد کند است و خطاها با push‌های اصلاحی («push → fail → push») حل می‌شوند. در مقابل، بررسی‌هایی که واقعاً به اجرا نیاز دارند (staging با `WP_DEBUG`، REST/فرم‌ها/rewrite/cron، مرورگر و WCAG/CWV، crawl و Search Console) تاکنون **انجام نشده** و در `docs/technical-audit-checklist.md` باز مانده‌اند.

**ضعف‌های معماریِ درجه‌دو (🟠/🟡):**
- **مالکیت دوگانهٔ سئو:** خروجی `inc/seo.php` در برابر Rank Math؛ فیلدهای قالب الزامی می‌شوند اما معلوم نیست در خروجی افزونه مصرف شوند (TG-01).
- **تضاد REST:** CPTها `show_in_rest = false` دارند، اما متاهای شهرستان با `show_in_rest = true` ثبت شده‌اند و ابزار `tools/county-sync` قصد نوشتن از راه REST روی `city` را دارد → این مسیر باید پیش از اجرا روی staging تست شود.
- **ابزارهای پرخطر بدون گارد کافی:** `inc/site-cleanup.php` شامل `wp_delete_post(..., true)` و جایگزینی متن است و طبق مستنداتِ ممیزی، queryهایش نوشته‌های منتشرشده را هم می‌گیرد.
- **دوگانگی ابزار پاک‌ساز:** افزونهٔ PHP و بستهٔ پایتون `ai_cleaner/` دو مجموعه الگوی جدا دارند (`includes/patterns.php` در برابر `patterns.yaml`).
- **یکپارچگیِ دادهٔ جغرافیایی:** سه عدد متفاوت برای شهرستان‌ها (۴۸۳ / ۴۸۴ / ۴۷۸) و اختلاف مختصات و مساحت با منابع رسمی.
- **استانداردسازیِ جزئی:** پوشهٔ `languages/` در فرزند وجود ندارد اما `load_child_theme_textdomain()` فراخوانی می‌شود؛ نسخهٔ PHP اعلامی (۷.۴) با ماتریس آزمون (۸.۱+) هم‌خوان نیست.
