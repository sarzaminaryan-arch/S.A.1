<?php
/**
 * نقشهٔ تغییر مسیرِ ۳۰۱ صفحه‌های تکراری.
 *
 * هر کلید یک مسیرِ قدیمیِ کامل است و مقدارش مسیرِ مقصد (هر دو با اسلشِ ابتدا و انتها).
 * این نقشه فقط برای صفحه‌هایی است که مالک تأیید کرده «هر دو یک موجودیت‌اند» و یکی
 * باید به دیگری تغییر مسیر بدهد. صفحهٔ قدیمی در محتوا دست‌نخورده می‌ماند؛ تغییرِ مسیر
 * در زمانِ درخواست اعمال می‌شود (بدونِ ویرایشِ محتوای منتشرشده).
 *
 * مهم: مقصد باید یک صفحهٔ منتشرشده و از نظرِ محتوایی کامل‌تر باشد؛ مبدأ هرگز نباید
 * خودش مقصدِ یک کلیدِ دیگر باشد (زنجیرهٔ تغییر مسیر ممنوع).
 *
 * @package SarzaminAryan
 */

return array(
	// ادغامِ تأییدشدهٔ مالک (۱۴۰۵-۰۷-۱۵): هر دو صفحه «شهرستان ایجرود» بودند و یکی است.
	// معیارِ انتخابِ مقصد: ردیفِ رجیستری (`ejrud`)، پیوندهای ورودیِ بیشتر و صفحهٔ قدیمی‌تر.
	'/city/ijrud/' => '/city/ejrud/',

	/*
	 * نامک‌های قدیمیِ جاذبه‌ها (۱۴۰۵-۰۷-۱۵).
	 * سه صفحهٔ منتشرشده و متنِ ذخیره‌شدهٔ آن‌ها، جاذبه‌هایی را با نامکِ فارسیِ قدیمی لینک
	 * کرده‌اند که صفحه‌شان با نامکِ تازهٔ انگلیسی در «مقالات آمادهٔ دیدنی‌ها» نوشته شده است.
	 * این نقشه، پیوندهای قدیمیِ موجود را به همان مقالهٔ تازه می‌رساند (بدونِ ساختِ صفحهٔ
	 * تکراری). تا وقتی صفحهٔ مقصد درج نشده باشد، تغییر مسیر اجرا نمی‌شود (۴۰۴ به ۴۰۴ وصل
	 * نمی‌شود)؛ به‌محضِ درجِ مقاله، مسیر قدیمی خودکار به آن می‌رود.
	 */
	'/attraction/دریاچه-ی-گهر/' => '/attraction/gahar-lake-dorud/',
	'/attraction/آبشار-بیشه/' => '/attraction/bisheh-waterfall-dorud/',
	'/attraction/آبشار-شوی-تله-زنگ/' => '/attraction/shevi-waterfall-tele-zang/',
	'/attraction/stars-valley-qeshm/' => '/attraction/qeshm-stars-valley-geopark/',

	/*
	 * نامکِ دیگرِ صفحه‌های موجود (۱۴۰۵-۰۷-۱۷).
	 * صفحه‌های منتشرشده یک دیدنی را با نامکِ دیگری لینک کرده‌اند، در حالی که همان دیدنی
	 * صفحهٔ منتشرشده دارد؛ به‌جای ساختنِ صفحهٔ تکراری، مسیرِ قدیمی به صفحهٔ موجود می‌رود.
	 * مقصدهای هر چهار مورد منتشرشده‌اند، پس این تغییر مسیرها همین حالا فعال‌اند.
	 */
	'/attraction/meidan-emam-isfahan/' => '/attraction/naqsh-e-jahan-square-isfahan/',
	'/attraction/shahdad-kalut/' => '/attraction/shahdad-kaluts-lut-desert/',
	'/attraction/22438-ابشار-نوژیان/' => '/attraction/nozhian-waterfall-khorramabad/',
	'/attraction/persepolis/' => '/attraction/persepolis-takht-jamshid/',

	// ادغام تأییدشدهٔ مالک (۱۴۰۵-۰۷-۱۸): «دره نیگاه (دره نگار)» دو صفحهٔ منتشرشدهٔ
	// تکراری داشت (nigah-valley-dorud و nigah-valley-dorud-2 با محتوای یکسان).
	// مقصد: مقالهٔ کامل‌ترِ «مقالات آمادهٔ دیدنی‌ها». پیشنهاد می‌شود خودِ صفحهٔ
	// «-2» از پیش‌خوان وردپرس به زباله‌دان منتقل شود؛ این نقشه تا آن زمان هم
	// بازدیدکننده و موتور جست‌وجو را به نسخهٔ درست می‌رساند.
	'/attraction/nigah-valley-dorud-2/' => '/attraction/nigah-valley-dorud/',

	/*
	 * پیش‌نویس‌های تکراریِ شهرستان (۱۴۰۵-۰۷-۱۵).
	 * سه پیش‌نویسِ خالیِ سایت (`galikesh`، `kabudarahang-city` و `shahdad`) نسخهٔ
	 * نامکِ قدیمیِ صفحه‌های منتشرشدهٔ `galikash`، `kabutarang→kabutarahang` و کرمان
	 * هستند. این نقشه، هر پیوند یا نشانیِ قدیمی را به صفحهٔ منتشرشدهٔ درست می‌رساند.
	 *
	 * بازنگری ۱۴۰۵-۰۸-۰۲ (تصمیم مالک: نشانیِ پایه بدونِ پسوند یکتاست):
	 * ردیفِ معکوسِ پیشین (`/city/isfahan/` ← `/city/isfahan-city/`) برداشته شد و جایش
	 * همهٔ اسلاگ‌های با پسوندِ `-city/-county` به اسلاگِ پایه می‌روند. تا زمانی که
	 * تغییرِ نامِ اسلاگ در دیتابیس (تسک B2) انجام نشده، نگهبانِ «وجودِ مقصد» این
	 * ردیف‌ها را غیرفعال نگه می‌دارد؛ پس هیچ ۳۰۱ِ شکسته‌ای ساخته نمی‌شود.
	 */
	'/city/galikesh/' => '/city/galikash/',
	'/city/kabudarahang-city/' => '/city/kabutarahang/',
	'/city/shahdad/' => '/city/kerman/',
	'/city/borazjan/' => '/city/dashtestan/',
	'/city/isfahan-city/' => '/city/isfahan/',
	'/city/ahvaz-city/' => '/city/ahvaz/',
	'/city/ardabil-city/' => '/city/ardabil/',
	'/city/ilam-city/' => '/city/ilam/',
	'/city/qazvin-city/' => '/city/qazvin/',
	'/city/qom-city/' => '/city/qom/',
	'/city/semnan-city/' => '/city/semnan/',
	'/city/tehran-city/' => '/city/tehran/',
	'/city/zanjan-city/' => '/city/zanjan/',
	'/city/dorud-city/' => '/city/dorud/',
	'/city/bushehr-city/' => '/city/bushehr/',
	'/city/bushehr-county/' => '/city/bushehr/',

	/*
	 * نام‌های مستعارِ شهرستان (۱۴۰۵-۰۸-۰۲، از exportهای GSC):
	 * هر دو نشانی در گزارشِ «Valid» بودند یعنی هر دو ایندکس شده‌اند؛ مقصد، ردیفِ
	 * رجیستریِ `data/counties.php` است.
	 */
	'/city/torbat-jam/' => '/city/torbatjam/',
	'/city/khorramdarreh/' => '/city/kharadere/',

	/*
	 * برگهٔ سیاست (۱۴۰۵-۰۸-۰۳): نامک canonical طبق template-parts/content-single-post.php
	 * برابر «editorial-policy» است؛ ۱۷ مقاله به /editorial-policy/ لینک داده‌اند (404 در GSC).
	 * این ۳۰۱ با نگهبانِ وجودِ مقصد فعال می‌شود — یعنی فقط پس از تغییر نام برگهٔ policy
	 * به‌وسیلهٔ افزونهٔ sa-seo-db-fixes. privacy مستقل می‌ماند (تصمیم مصوب).
	 */
	'/policy/' => '/editorial-policy/',

	/*
	 * «جاهای دیدنی طبس» (اصلاح ۱۴۰۵-۰۸-۰۲): مقصد نهایی بر پایهٔ فهرست مصوب T6 و
	 * نقشهٔ زندهٔ پیشین، خودِ هابِ دیدنی‌هاست (/attraction/tabas-attractions/).
	 * تصمیم قدیمیِ ادغام در صفحهٔ شهرستان (۱۴۰۵-۰۷-۲۶) هرگز روی سایت اجرا نشد و
	 * هاب اکنون مقاله‌ای منتشرشده با ۱۴٬۳۵۱ نویسه است؛ حذف/ادغام آن نیازمند تأیید
	 * جداگانهٔ مالک است. خط «tabas-attractions => /city/tabas/» نیز حذف شد: مقصدِ
	 * ۳۰۱ نباید خودش مبدأ ۳۰۱ باشد (خطر زنجیره و مرگ هاب).
	 */
	'/attraction/308149-attraction-tabas/' => '/attraction/tabas-attractions/',

	/*
	 * نامک‌های تازهٔ دو صفحهٔ شناسه‌دار (۱۴۰۵-۰۷-۲۱): شناسهٔ خروجیِ وردپرس از نشانی
	 * برداشته شد و مسیرهای قدیمی با ۳۰۱ به نامک‌های معنادار می‌روند.
	 */
	'/attraction/9235-tang-e-sayad-national-park/' => '/attraction/tang-e-sayad-national-park/',
	'/attraction/23328-sar-agha-seyed-village/' => '/attraction/sar-agha-seyed-village/',

	/*
	 * نامکِ تازهٔ «باغ گلشن طبس» (۱۴۰۵-۰۷-۲۱): نامکِ فارسیِ قدیمی در پیوندهای موجود بود و
	 * نامکِ زندهٔ سایت هم شناسهٔ خروجی داشت؛ هر دو با ۳۰۱ به نامکِ معنادار می‌روند.
	 */
	'/attraction/6789-bagh-glshn-tbs/' => '/attraction/bagh-golshan-tabas/',
	'/attraction/6789-باغ-گلشن-طبس/' => '/attraction/bagh-golshan-tabas/',

	/*
	 * یکسان‌سازی نشانی «شهرستان دورود» روی مسیرِ استانداردِ شهرستان (`/city/dorud/`).
	 */
	'/attraction/dorud/' => '/city/dorud/',
	'/attraction/dorud-city/' => '/city/dorud/',

	/*
	 * نامک‌های تازهٔ سه رکورد شناسه‌دارِ زنده (۱۴۰۵-۰۷-۲۱): نامکِ زندهٔ سایت شناسهٔ خروجی داشت و
	 * نامکِ رکورد هم فارسی بود؛ هر دو مسیر با ۳۰۱ به نامکِ معنادار می‌روند.
	 */
	'/attraction/6976-aramgah-ghasm-anvar/' => '/attraction/qasem-anvar-shrine/',
	'/attraction/6976-ارامگاه-قاسم-انوار/' => '/attraction/qasem-anvar-shrine/',
	'/attraction/26950-kvir-rig-jn/' => '/attraction/rig-e-jenn-desert/',
	'/attraction/26950-کویر-ریگ-جن/' => '/attraction/rig-e-jenn-desert/',
	'/attraction/6619-ghlh-dkhtr-bshrvih/' => '/attraction/ghaleh-dokhtar-boshruyeh/',
	'/attraction/6619-قلعه-دختر-بشرویه/' => '/attraction/ghaleh-dokhtar-boshruyeh/',
);
