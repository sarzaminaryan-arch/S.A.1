# گزارش دروازه‌های صفحهٔ شهرستان خرم‌آباد (ID=514)

**تاریخ: ۱۴۰۵/۰۹/۰۲ | وضعیت نهایی: همهٔ دروازه‌های محلی سبز**

## فرمان‌ها و نتایج

```
python3 tools/wp-agent/replace_src/county_page.py khorramabad
→ OK   khorramabad → tools/wp-agent/outbox/20261117-replace-khorramabad/khorramabad-514.html (1566 words)

python3 tools/wp-agent/replace_src/check_template.py tools/wp-agent/outbox/20261117-replace-khorramabad
→ OK   outbox/20261117-replace-khorramabad/khorramabad-514.html
→ 1/1 pages conform to the county template
→ Stage Wikipedia sources: 0 (target 0; maximum 1)
```

- fa_lint: ۰ مسئله (پیکر + متا)؛ «ة» عربی از دو نام (دایره‌المعارف، نزهت‌القلوب) زدوده شد.
- خلاصهٔ بیلد (`summary.json`): `ok: true`, `words_after: 1566`, بخش‌ها = intro/map/history/people/economy/stay/modern (با نشانگرهای `<!-- sa:sec:* -->`).
- بازرس کامل (`check_template.py` — الگوی صفحه، کادر عددی، منابع، FAQ، ریدینگ‌های ممنوع، هم‌پوشانی با seed، ساختار): یافته‌ای نداشت؛ ۰ منبع ویکی.
- هم‌پوشانی ۳گرمی بدن ↔ seed تجربهٔ محلی (khorramabad در `county-experiences.php`) زیر آستانهٔ ۰٫۳۵؛ عبارت‌های seed («دیدنی‌های شهری نزدیک هم‌اند»، «قلعه از همه‌جا پیداست»، «مهمان‌نوازی‌شان مشهور است» و…) در متن تازه نیامده‌اند.
- ممنوعه‌ها: ۰ مورد «باید»/«نباید» (substring)، بدون تاریخ میلادی، بدون جدول/لوله، بدون لینک‌های تکراری.
- `sa_cty_distance_center` = '' (پایتخت استان، طبق قاعدهٔ ahvaz) — «۰» نوشته نشد.

## اثرانگشت

| پرونده | sha256 (۱۶ رقم اول) |
|---|---|
| `outbox/20261117-replace-khorramabad/khorramabad-514.html` | `e8bf89e0868f27b6` |
| `replace_src/data/khorramabad.json` | `d63044f58c1e924a` |

## یادداشت‌های اجرا

- قالب داده: `replace_src/data/khorramabad.json` (منبع‌ها dict با accessed_on = «۱۴۰۵/۰۹/۰۲»).
- کلیدهای `lat`/`lng` تمیز (33.4667 / 48.3667) در meta برای نیاز بخش نقشه؛ `sa_city_latitude/longitude` به شکل متنی.
- جمعیت کادر = ۵۰۶٬۰۰۰ (نزدیک‌ترین هزارِ ۵۰۶٬۴۷۱؛ سرشماری ۱۳۹۵)؛ سال سرشماری ۱۳۹۵.
- تا دستور بعدی هیچ انتشاری انجام نمی‌شود؛ این خروجی فقط در outbox محلی است.
