# قانون تصویر شاخص مقالات شهرستان (v1.0 — ۱۴۰۵/۰۷/۰۷)

> وضعیت: **قانون لازم‌الاجرا** · دامنه: همهٔ مقالات موجودیت «شهرستان» (`content/cities/{slug}.md` → `/city/{slug}/`)
> مرجع بالادستی: `content-templates/city-county-structure.md` (BLOCK 2) و `data-model/MASTER_DATA_MODEL.md`

## ۱) قاعده

هر مقالهٔ شهرستان باید **یک تصویر شاخص پانوراما ۱۶:۹** داشته باشد که با پرامپت استاندارد بند ۳ ساخته شود.

1. مسیر فایل: `assets/featured/counties/{province-slug}/{county-slug}.webp`
2. قالب و اندازه: WEBP، نسبت ۱۶:۹، عرض مرجع ۱٬۶۷۲ پیکسل (۱۶۷۲×۹۴۱)، حجم هدف < ۳۰۰ کیلوبایت.
3. تیتر فارسی نام شهرستان در بالای کادر + زیرتیتر کوتاه؛ واترمارک کوچک «SARZAMIN ARYAN» در پایین.
4. alt و زیرنویس فارسی اجباری‌اند و در `manifest.json` همان پوشه ثبت می‌شوند.
5. **ممنوع:** جعل جاذبهٔ مشهورِ متعلق به شهرستان دیگر، معماری فانتزی، آدم در پیش‌زمینه، خودرو، بیلبورد و تبلیغات مدرن.
6. چهار «عنصر شاخص» تصویر باید از متن خودِ مقاله (بلوک‌های مستندشده) استخراج شوند، نه از حافظهٔ مدل.
7. زبان بصری، ترکیب‌بندی و اتمسفر همهٔ شهرستان‌های کشور باید یکسان بماند (نور طلایی غروب، سبک مستند سفر).
8. تصویر شاخص «تصویرسازی» است، نه عکس مستند؛ هرگز جای عکس واقعیِ مکان داخل متن را نمی‌گیرد (قاعدهٔ بند ۱۱ `city.md`).
9. بدون تصویر شاخص، مقاله گیت انتشار را رد می‌کند و `DRAFT ONLY` می‌ماند.

## ۲) جریان کار

1. استخراج چهار عنصر شاخص + زیرتیتر از مقاله.
2. تولید تصویر با پرامپت بند ۳ (جایگزینی متغیرها).
3. تبدیل به WEBP با اندازهٔ مرجع و قرار دادن در پوشهٔ استان.
4. افزودن ردیف به `assets/featured/counties/{province-slug}/manifest.json` (slug، alt، caption، symbols، ابعاد، بایت).
5. اجرای `python3 content-templates/tools/build_city_import_package.py --province {province-slug}` تا تصویر درون بستهٔ افزونهٔ درون‌ریز کپی شود.

## ۳) پرامپت استاندارد (بدون تغییر ساختار استفاده شود)

```
Create a cinematic, highly realistic and visually rich panoramic hero image representing the Iranian county of [نام شهرستان].

The scene must authentically combine the most recognizable geographical, historical, architectural, cultural and natural elements of [نام شهرستان], especially:
- [عنصر شاخص ۱]
- [عنصر شاخص ۲]
- [عنصر شاخص ۳]
- [عنصر شاخص ۴]

Compose these elements naturally into ONE cohesive panoramic landscape, as if photographed from an elevated viewpoint. Show the characteristic landscape, vegetation, architecture, agricultural fields, mountains, rivers, historical monuments and local atmosphere of the region where appropriate.

Visual style:
cinematic Iranian landscape photography, photorealistic, ultra-detailed, warm golden-hour sunset, soft atmospheric haze, dramatic sky with scattered clouds, natural sunlight, realistic shadows, rich architectural details, authentic Persian/Iranian atmosphere, sophisticated travel-documentary aesthetic, wide panoramic composition, deep depth, realistic colors, premium tourism poster, 16:9.

The foreground should contain a recognizable historical or architectural landmark from the county, the middle ground should show the town/city, gardens, agricultural lands and local landscape, and the background should feature the characteristic mountains or horizon of the region.

At the upper center of the image, add the Persian county name in large, bold, elegant Persian typography:
«[نام شهرستان]»
The Persian title must be clearly readable, correctly spelled, visually dominant, and integrated naturally into the composition. Use a refined traditional Persian calligraphic/typographic style, dark charcoal/black color, with no Latin letters.

Directly below the main title, add a smaller Persian subtitle:
«[توصیف کوتاه یا ویژگی شاخص شهرستان]»
The subtitle should be elegant, readable and significantly smaller than the main title.

At the very bottom, optionally add a small, subtle English watermark: "SARZAMIN ARYAN"

Important:
- Do not invent famous landmarks that do not belong to [نام شهرستان].
- Keep all architecture and landscape geographically plausible and culturally authentic to the region.
- Avoid fantasy architecture and generic Middle Eastern scenery.
- The image should look like a professional high-end tourism poster.
- No people in the foreground.
- No cars, modern advertisements, billboards or distracting urban elements.
- Correct Persian typography and spelling are extremely important.
- Maintain the same overall visual language, composition and atmosphere across all Iranian counties.
```

## ۴) تاریخچهٔ نسخه

| نسخه | تاریخ | تغییر |
|---|---|---|
| v1.0 | ۱۴۰۵/۰۷/۰۷ | ثبت قانون و پرامپت استاندارد تصویر شاخص شهرستان (ابلاغ کارفرما) |
