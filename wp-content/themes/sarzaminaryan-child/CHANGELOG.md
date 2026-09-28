# Changelog — sarzaminaryan-child

## 2.0.1 — 2026-09-28

- **ریسپانسیو کامل همه‌ی صفحات**: زیر ۱۲۰۰ پیکسل، ستون رزروشده‌ی خالی (به اندازه‌ی سایدبار) حذف می‌شود — `.sa-entity__layout` و `.site-content` تک‌ستونه می‌شوند و سقف متن `--sa-max-text` به ۱۰۰٪ می‌رسد؛ متن مقاله‌ها تا لبه‌ی صفحه پر می‌شود (رفع فضای سفید سمت چپ در تبلت/لپ‌تاپ).
- **صفحه‌ی اول v2**: خطوط کج سفیدِ روی هیرو (`.sa-hero::after` از استایل قالب) در صفحه‌ی اصلی حذف شد؛ لایه‌ی اسامی استان/شهرهای چشمک‌زن (فارسی و انگلیسی) به‌طور کامل برداشته شد و به‌جایش کف هیرو با نقاط ریز طوسی (مانند بافت نقشه) پوشیده شد + ۴۲ نقطه‌ی کاندید که هر ۳ ثانیه، ۷ نقطه‌ی تصادفی از آن‌ها روشن و گروه قبلی خاموش می‌شود.
- **لوگوی هیرو**: بزرگ‌تر (۱۳۶ پیکسل، موبایل ۹۶) بدون زمینه‌ی تیره با سایه‌ی عمیق دولایه.
- **شعار جدید (فردوسی)**: «چو ایران نباشد، تن من مباد» — پیش‌فرض جدید شعار در هیرو، هدر، فوتر و سفارشی‌سازی (اگر قبلاً شعار را در سفارشی‌سازی ذخیره کرده‌اید، همان را دستی عوض کنید).

## 2.0.0 — 2026-09-28

- **صفحه‌ی اصلی جدید (طرح v2)**: طراحی `sarzamin-home-v2.html` به قالب برگه‌ی «صفحه اصلی سرزمین آریان (طرح v2)» (`template-home.php`) تبدیل شد — هیروی انیمیشنی با لوگو و شعار، جعبه‌ی جست‌وجو، چیپ‌های آرشیو، نقشه‌ی ایران با ۳۱ نقطه‌ی درخشان (لینک به هر استان)، شمارنده‌ی آمار، نوار هر ۳۱ استان (سرور-رندر برای سئو)، «آخرین مقالات» و «پست‌های معروف» (سرور-رندر با WP_Query به‌جای REST).
- **انتقال صفحه‌ی اول به برگه**: `front-page.php` و `template-parts/home/*` حذف شدند؛ صفحه‌ی نخست اکنون برگه‌ی «خانه» (`/home/`) در برگه‌هاست با قالب v2. در ارتقای نسخه (`sa_home_v2_setup()`) برگه‌ی خانه ساخته/قالب‌گذاری و `show_on_front=page` + `page_on_front` تنظیم می‌شود؛ برگه‌ی وبلاگ هم `page_for_posts` می‌شود.
- **سفارشی‌سازی دستی در پیشخوان**: بخش جدید «صفحه اصلی (طرح v2)» در سفارشی‌سازی — شعار، متن معرفی، جای‌نگاشت جست‌وجو، اعداد و برچسب‌های آمار، عناوین سه بخش و شناسه‌های دستی «پست‌های معروف» (خالی = Sticky).
- **شعار حماسی پیش‌فرض**: «از البرز تا خلیج فارس؛ هر گوشه‌ی این خاک، یک آسمان است.» (قابل ویرایش از سفارشی‌سازی).
- **هدر و فوتر جدید با رنگ طرح v2**: نوار سرمه‌ای چسبان (`#0b1424`) با بلور، لینک‌های سفید/آبی `#2f6bff`، لوگوی گرد‌‌‌‌گوشه، نام سایت + شعار، منوی باز‌شونده تیره؛ فوتر سرمه‌ای با چهار ستون (برند+شعار+درباره+شبکه‌های اجتماعی، کاوش در ایران، برگه‌های سایت، ارتباط با ما) و نوار کپی‌رایت.
- **لوگوی اصلی**: لوگوی رسمی (`assets/img/logo.webp` از `S.A.logo.webp` مخزن) به‌عنوان پیش‌فرض هدر/فوتر/هیرو؛ اگر «لوگوی سفارشی» در سفارشی‌سازی انتخاب شود همان استفاده می‌شود.
- **لینک برگه‌ها در فوتر**: صفحه اصلی، درباره ما (`/about/`)، تماس با ما (`/contact/`)، حریم خصوصی (`/privacy/` — برگه‌ی جدید در seeding) و سیاست تحریریه (`/policy/`)؛ لینک‌ها از `sa_default_pages` و در نبودِ برگه، آدرس تمیز پیشنهادی.
- نسخه‌ی افزونه‌ی قالب به 2.0.0 و هدر `style.css` به‌روز شد (رفرش کش CSS).

## 1.0.4 — 2026-09-27

- **موبایل/ریسپانسیو صفحه‌ی تک استان**: `--sa-max-text` اکنون `min(72ch, 100%)` است؛ روی نمایشگرهای باریک دیگر ستون خالی در چپ متن نمی‌ماند و بدنه‌ی مقاله با مقدمه هم‌عرض می‌شود (۶ محل استفاده — مقدمه، بدنه، FAQ، itineraries و …).
- **کادر ۳بعدی برای سربرگ‌های H2**: سربرگ‌های سطح ۲ داخل بدنه‌ی مقاله (و FAQ/منابع) در کادر قاب‌دار با گرادیان سبز کمرنگ، لبه‌ی داخلی سبز و سایه‌ی نرمِ چندلایه‌ی عمیق (`--sa-shadow-deep`) قرار می‌گیرند.
- **جدول‌های سبز**: هدر سبز قوی (`--sa-green-700`)، ردیف‌های متناوب سبز خیلی‌کمرنگ/کمرنگ (`--sa-green-50`/`--sa-green-100`)، گوشه‌های گرد، سایه، و اسکرول افقی در موبایل (`overflow-x: auto` + `min-width` جدول).
- **FAQ مدرن**: کارت‌های گرد با سایه‌ی نرم، چیپ دایره‌ای `+/−` که در حالت باز می‌چرخد و سبز می‌شود، پاسخ روی پس‌زمینه‌ی سبز خیلی‌کمرنگ با لبه‌ی نقطه‌چین، افکت hover.
- **سایه برای جعبه‌ها**: «مشخصات کلی» (`.sa-facts`)، جعبه‌ی «منابع» (`.sa-sources` — حالا کادر کامل با سایه و هدر کوچک قاب‌دار) و مقدمه (`.sa-entity__lead` — کادر سفید با لبه‌ی درونی آبی) سایه‌ی یکدست می‌گیرند.
- متغیرهای جدید رنگ سبز (`--sa-green-*`) و `--sa-shadow-deep` در `:root`.

## 1.0.3 — 2026-09-26

- `sa-hero` (1600×700) is now cropped with `array( 'center', 'top' )` and `.sa-entity__hero-media img` gets `object-position: center top`: the province featured posters (`assets/featured/provinces/*.webp`, 1600×900) carry their title in the upper band, so a centred crop cut it off. Run a thumbnail regeneration for images uploaded before this version.
- Companion plugin `wp-content/plugins/sa-province-importer-b01` (batch 01 of the province drafts) writes the same `sa_*` meta keys, `sa_faq` JSON and `sa_sources` text this theme reads; no theme code path changed for it.

## 1.0.2 — 2026-09-26

- **SEO-plugin coexistence** (`inc/schema.php`): with Rank Math / Yoast / AIOSEO / SEOPress / TSF active the theme no longer drops its whole JSON-LD graph. Site-level nodes (Organization, WebSite, BreadcrumbList, BlogPosting/WebPage, CollectionPage) are left to the plugin; the entity node (AdministrativeArea/TouristDestination/… from the meta fields) and FAQPage (from the FAQ box) are still printed, using the same `home_url( '/#organization' )` @id convention so references resolve. `add_filter( 'sa_schema_with_plugin', '__return_false' )` restores the old all-off behaviour. `inc/seo.php` keeps yielding titles/meta/OG to the plugin as before.
- **External links in content** (`sa_content_external_links()`, `the_content` @13): same rel policy as the sources box — `sa_source_rel()` (official domains followed, others `nofollow noopener external`) plus `target="_blank"`; existing `rel`/`target` attributes are respected, same-site links untouched. Needed for the inline citations `<sup>[n](URL)</sup>` the production prompts now place after sourced facts (n = row in the sources box, which renders as an `<ol>`).
- CSS for inline citations (`.entry-content sup a`).

## 1.0.1 — 2026-09-26

- Data model **v1.1**: `attraction.official_website` (→ JSON-LD `sameAs`) and `attraction.last_verified_date`; new `date` field type (ISO input, Jalali hint, stale warning after `sa_stale_after_days()` = 365).
- Publish gate reads per-entity minimums from the model (`sa_content_minimums()`): FAQ ≥ n, sources ≥ n (lines with a URL before the `---` separator), internal links ≥ n (same-site `<a href>` in content), coordinates required. New non-blocking warnings: uncertainty markers count, missing/stale verification date. Admin badge «بازبینی».
- Visible **منابع** section on entity singles (`sa_sources_section()`), official domains followed, others `nofollow`; text after a `---` line stays private (editor FACT CHECK notes).
- `[نیازمند بررسی]` / `[منبع لازم]` rendered as `<mark class="sa-flag">` badges in content and FAQ answers (Helpful Content: transparency over guessing).
- `sa_transliterate_fa()` for Persian slugs on publish; number formatting drops `.0`; RTL stylesheet appended instead of replaced (parent).

## 1.0.0 — 2026-09-26

First release. Everything the project needs lives in this child theme (no plugins).

- Data model v1.0 wired in: `inc/entities-config.php` is generated from `data-model/schema/data-model.yaml` by `data-model/schema/build_child_config.py` — never edit by hand.
- 6 CPTs + 5 taxonomies, 31 provinces seeded from `data/provinces.php` on activation.
- Meta boxes (fields / relations / SEO / FAQ / sources), R1 denormalisation (city → province), R4 delete guard, province_tax mirroring, reverse lookups.
- Publish gate (Level 7) with hard/soft mode, list-table badge, dashboard widget.
- SEO head, JSON-LD `@graph`, breadcrumbs, sitemap tuning, security hardening, performance tweaks, Jalali dates + Persian digits.
- Front page (hero, stats, provinces, entity sections, latest posts), all singles/archives/taxonomies, 404, search.
- Live-tested on WordPress 7.1.2 / PHP 8.4 with the SQLite drop-in: 30+ front-end and admin views render with zero PHP notices; gate blocked an incomplete attraction; Persian slugs transliterated on publish.
