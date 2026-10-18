# 📋 قوانین اجباری Agent

> نقض هر کدام از این قوانین = توقف فوری session
> آخرین آپدیت: ۱۴۰۵/۰۷/۲۵ (۲۰۲۶-۱۰-۱۷) · شاخهٔ جاری: `arena/6b44d15d-s-a-1`
> این فایل مکملِ بخش ۴ (قوانین مطلق) از `CLAUDE.md` است؛ در صورت تعارض، `CLAUDE.md` مقدم است.

---

## 🟢 قبل از شروع هر کاری

- [ ] فایل‌های `CLAUDE.md`، `PROJECT_STATUS.md` و `ARCHITECTURE.md` به دقت خوانده شود.
      *(هر سه در ریشهٔ مخزن‌اند؛ `docs/project/` هم ۱۳ سند اولیه دارد)*
- [ ] `git log --oneline -20` و `git branch -a` اجرا شود.
      *(آخرین کامیت‌ها: `551c0f8` افزودن PROJECT_STATUS.md، `8e188fa` بازنویسی CLAUDE.md، `2405272` پوشهٔ docs/project)*
- [ ] وضعیت PRهای باز چک شود تا تداخل کاری پیش نیاید:
      - **PR #23** — Clean editorial notes in ready articles — `arena/c04b4230-s-a-1` — **OPEN**
      - **PR #22** — Theme 2.21.0 SEO and navigation fixes — `arena/938cb410-s-a-1` — **DRAFT**
      - **PR #21** — کارت آبرنگی ۲۸ دیدنی + فهرست مطالب — `arena/daf7bdde-s-a-1` — **OPEN**
- [ ] تست سنجش `AGENT_TEST.md` پاس شود.
      ⚠️ **این فایل هنوز در مخزن وجود ندارد** — باید ساخته شود. **[نیاز به تأیید کاربر]** تا زمان ایجاد آن، این آیتم با اجرای دستیِ چک‌لیستِ همین بخش جایگزین می‌شود.
- [ ] در صورت نبود Branchهای استاندارد (`develop`, `feature/*`, `fix/*`, `backup/*`) از کاربر برای ایجادشان مجوز گرفته شود.
      **وضعیت فعلی: هیچ‌کدام وجود ندارند.** مخزن فقط `main` و شاخه‌های `arena/<session-id>-s-a-1` دارد.

---

## 🟡 حین انجام کار

- [ ] فقط در فایل‌های مجاز تغییر ایجاد شود.
      ✅ مجاز: `wp-content/themes/sarzaminaryan-child/**` (به‌جز فایل‌های تولیدی)، `docs/**`،
      `data-model/MASTER_DATA_MODEL.md` و `data-model/schema/data-model.yaml`،
      `skill-SA-agent/**`، `tools/**`، `ai_cleaner/**`، `.github/workflows/**` (با احتیاط).
      🚫 ممنوع: کلِ والد `sarzaminaryan/**`، `wp-content/plugins/**`، هستهٔ وردپرس،
      و سه فایل تولیدی: `inc/entities-config.php`، `data/ready-guides.php`، `data-model/schema/data-model.json`.
- [ ] قبل از تغییر هر فایل، محتوای آن به طور کامل خوانده و تحلیل شود.
- [ ] هر تغییر منطقی = یک commit جدا با پیام فارسی استاندارد (مثال: `feat: ...` یا `fix: ...`).
      *نمونهٔ واقعیِ همین session:*
      `docs: افزودن پوشهٔ مستندات پروژه (docs/project/)` ·
      `docs: بازنویسی CLAUDE.md بر پایهٔ اسکن کامل مخزن` ·
      `docs: افزودن PROJECT_STATUS.md (گزارش وضعیت پروژه)`
- [ ] کدهای اضافه شده باید دارای پیشوند استاندارد (`sa_`) باشند.
      *واقعیت مخزن: **۶۳۹** تابع با پیشوند `sa_` در قالب فرزند (از ۷۳۰ تابع)؛ والد از `sarzaminaryan_` (**۱۴** تابع).*
      *ثابت‌ها `SA_*` · کلیدهای متا `sa_*` · کلاس‌های مشارکت مردمی `SA_CC_*`*
- [ ] کد استاندارد وردپرس و اصول امنیتی رعایت شود (مخصوصاً نقاط nonce که نیاز به ممیزی دارند).
      *وضعیت فعلی (شمارش دقیق): **۹۸** رخداد nonce/capability در **۲۶** فایل، **۶۵** فراخوانی `$wpdb` در **۱۰** فایل
      (۱۴ مورد `prepare`)، **۲۶** فراخوانی `wp_remote_*` در ۷ فایل، **۱۸۸** استفاده از superglobal در ۲۵ فایل،
      **۲۳** هندلر `admin_post_*` و **۱۱** مسیر REST — موجودی کامل در `docs/SECURITY_AUDIT_INVENTORY.md`
      (۶ یافتهٔ F1–F6). این موارد هنوز **بازبینی دستی و تست زنده** نشده‌اند و
      هر تغییرِ تازه باید sanitize ورودی + escape خروجی + nonce + capability داشته باشد
      (سیاست: `AGENTS.md` بند ۶).*

---

## 🔴 پایان کار

- [ ] فایل‌های `PROJECT_STATUS.md` و `CHANGELOG.md` با جزئیات آپدیت شوند.
      *(CHANGELOG قالب فرزند: `wp-content/themes/sarzaminaryan-child/CHANGELOG.md` — ۲۵۹KB؛
      در صورت تغییر نسخه، سه‌گانهٔ `style.css` / `SA_CHILD_VERSION` / `Stable tag` باید هم‌زمان به‌روزرسانی شوند)*
- [ ] فقط `git push` روی branch کاری انجام شود. **Merge کردن اکیداً ممنوع است.**
      *(شاخهٔ کاری این session: `arena/6b44d15d-s-a-1`؛ ادغام فقط توسط مالک و از راه Pull Request)*
- [ ] خلاصه کار و کارهای باقی‌مانده در قالب گزارش به کاربر ارائه شود.

---

## ❌ ممنوعیت‌های مطلق

1. تغییر یا ایجاد `wp-config.php` و `.htaccess` در مخزن گیتهاب (این فایل‌ها نباید در مخزن باشند).
   *در حال حاضر درست است: هیچ‌کدام در مخزن نیستند. تنظیمات حساس فقط روی هاست، از جمله ثابت `SA_GITHUB_TOKEN`.*
2. حذف یا غیرفعال کردن افزونه‌ها و فایل‌های اصلی قالب والد.
   *افزونه‌های سایت زنده (طبق snapshot دامپ، **[نیاز به تأیید کاربر]**): LiteSpeed Cache، Rank Math Pro، Rank Math.
   تنها افزونهٔ داخل مخزن: `sa-ai-sentence-cleaner` ۱.۰.۱.*
3. استفاده از Force Push (`git push -f`).
   *شامل `--force-with-lease`، rebase تعاملی روی شاخهٔ پوش‌شده و هرگونه history rewrite.*
4. کار مستقیم روی branch `main`.
   *`main` فقط از راه Pull Request و پس از سبز شدن CI (۴ workflow) تغییر می‌کند.*
5. تغییر ساختار دیتابیس (CPTها و متادیتاها) بدون هماهنگی و تأیید کاربر.
   *۶ CPT فعال (`province`, `city`, `attraction`, `travel_route`, `local_food`, `souvenir`) + `accommodation` رزرو،
   ۵ طبقه‌بندی (`province_tax`, `attraction_type`, `travel_season`, `travel_budget`, `travel_duration`)،
   ۳۱ ترم استان و ۲۸ کلید کانونیِ شناسنامهٔ شهرستان — هر تغییر باید ابتدا در
   `data-model/MASTER_DATA_MODEL.md` ثبت و سپس با مولدها هم‌راستا شود.*

---

## 📎 پیوست — خط قرمزهای عملیاتیِ این پروژه

- هرگز `inc/site-cleanup.php` را روی سایت اجرا نکن (شامل حذف برگشت‌ناپذیر و جایگزینی متن؛ طبق `docs/technical-audit-checklist.md` مسیرهای آن ایمن‌سازی نشده‌اند).
- هرگز فایل تولیدی را دستی ویرایش نکن؛ مولد را اجرا کن: `build_child_config.py`، `build_theme_data.py`، `build_json.py`.
- هرگز ZIP/بستهٔ نصب در مخزن کامیت نکن (موردِ موجود: `imagetool (23).zip` — در انتظار تأیید مالک برای حذف).
- پیش از هر عملیاتِ انبوه روی سایت (درج، حذف، search-replace، import): پشتیبان + dry-run + گزارش before/after.
- در صورت ابهام: **بپرس، حدس نزن** (قانون ۴-۹ در `CLAUDE.md`).
