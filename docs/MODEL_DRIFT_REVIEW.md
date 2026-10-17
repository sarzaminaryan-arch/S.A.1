# بررسی غیرمخرب drift مدل — ۲۰۲۶-۱۰-۱۷

شاخه: `arena/a32fb27a-s-a-1`؛ آزمون ایجنت با نمرهٔ ۷/۷ توسط مالک تأیید شد.

## روش و نتیجه
مولد موجود با importlib بارگذاری و فقط متغیر OUT آن در حافظه به
`/tmp/sa-entities-config-v1.3.php` تغییر داده شد؛ main اجرا و diff کامل در
`/tmp/sa-model-drift.diff` ذخیره شد. هیچ فایل مدل، مولد یا خروجی اجرایی بازنویسی نشد.
PyYAML 6.0.3 فقط در venv موقت `/tmp/sa-hygiene-venv` نصب شد. PHP در PATH موجود نیست؛
این بررسی متنی است، نه تست اجرایی PHP/وردپرس. فایل‌های /tmp خروجی موقت‌اند و تحویل پایدار نیستند.

## اختلاف‌های مشاهده‌شده در خروجی پیشنهادی
- سرآیند از ۱٫۲ به ۱٫۳ می‌رود؛ نام‌های نمایشی شهرستان/دیدنی به شهر/جاذبه برمی‌گردند.
- هفت فیلد دیدنی حذف می‌شوند: `english_name`, `attraction_age`, `attraction_area`,
  `elevation`, `access_level`, `trail_note`, `safety_note`.
- توابع `sa_seo_optional_overrides`, `sa_seo_deprecated_fields`,
  `sa_content_advisory_checks`, `sa_content_hygiene_checks` حذف می‌شوند.
- OG، breadcrumb، canonical و `faq_schema` دوباره در فهرست SEO الزامی قرار می‌گیرند.
- `missing_faq`، `missing_coordinates` و `missing_internal_links` به فهرست blockers
  برمی‌گردند و `missing_content_hygiene` حذف می‌شود. این نام‌گذاری به‌تنهایی اثبات
  مسدودشدن انتشار نیست؛ قرارداد اصلی همچنان advisory و تأیید مالک است.
- حداقل FAQ استان/شهرستان/دیدنی از ۳ به ۱۰ و منابع دیدنی از ۰ به ۵ می‌رود.
  لینک داخلی استان ۵→۲۰، شهرستان ۵→۱۰، دیدنی ۳→۱۰، مسیر ۳→۵ و
  غذا/سوغات/اقامتگاه ۲→۳ می‌شود؛ fallback نوع ناشناخته FAQ از ۰ به ۱ می‌رود.

## تصمیم این فاز
drift واقعی و باز است؛ اصلاح صرف سرآیند یا جایگزینی خروجی مجاز و کم‌ریسک نیست.
هم‌راستاسازی مدل و مولد باید با تصمیم مالک دربارهٔ نسخهٔ بعدی مدل، حفظ کلیدهای موجود،
ثبت تاریخچه و تست‌های عدم پسرفت انجام شود. نسخهٔ مدل در این فاز تغییر نکرد.

## SHA-256 ورودی‌ها و نمونهٔ مقایسه‌ای
- `data-model/schema/data-model.yaml`: `435147fb98fea09b0e7a9e9040a94da82520b10945b9daa310106436e63b33d7`
- `data-model/schema/build_child_config.py`: `b7b78b5a50aad44f27db84160b1708d9465f400b5a84233a21f30c15a9b118e2`
- `wp-content/themes/sarzaminaryan-child/inc/entities-config.php`: `f0e74f0de8e7e8729efe8772de07403ceb235ee406061cd2b5c1deaaa3742721`
- `/tmp/sa-entities-config-v1.3.php`: `669ffec58b4e86fa1795bcd0a951c38c1c2f9ea913d489c51b1a892b90b4968c`
