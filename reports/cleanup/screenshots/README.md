# اسکرین‌شات‌های قبل/بعد — ۵ صفحهٔ نمونه (فاز ۹)

هر ردیف = PR table با `| قبل | بعد |`

## kuhsorkh
| قبل | بعد |
|-----|-----|
| ![kuhsorkh before](./kuhsorkh-before.png) | ![kuhsorkh after](./kuhsorkh-after.png) |

*قبل:* جملهٔ `برای بازبینی و ویرایش انسانی…`, ```` `, جمعیت `0` در شناسنامه با پس‌زمینه قرمز.
*بعد:* شناسنامه بدون ردیف جمعیت (خالی → مخفی), محتوا بدون بک‌تیک و جملهٔ قالبی, H1 واحد, FAQ رندرشده = FAQPage.

## kashmar
| قبل | بعد |
|-----|-----|
| ![kashmar before](./kashmar-before.png) | ![kashmar after](./kashmar-after.png) |

*قبل:* `این مقاله ... را معرفی می‌کند`, `google.com` در منابع با highlight.
*بعد:* منابع فقط `.ir` و `wikipedia`, جملهٔ متا حذف, `utm_source` پاک.

## ahvaz-city
| قبل | بعد |
|-----|-----|
| ![ahvaz before](./ahvaz-before.png) | ![ahvaz after](./ahvaz-city-after.png) |

*قبل:* ``` سرگردان + پاراگراف نجف‌آباد/نائین در انتها + جمعیت `0`.
*بعد:* کد فنس حذف, پاراگراف بی‌ربط flagged (پیشنهاد حذف کامل — فقط با تأیید مالک حذف شد), جمعیت خالی → ردیف شناسنامه مخفی, `noindex` تا تکمیل.

## mashhad
| قبل | بعد |
|-----|-----|
| ![mashhad before](./mashhad-before.png) | ![mashhad after](./mashhad-after.png) |

*قبل:* `لازم به ذکر است` (FLAG زرد) + رتبه با <5 رأی نمایش داده می‌شد.
*بعد:* پیونددهنده در پیشنهاد `suggestion` ماند (سطح زرد بدون تغییر), رتبه مخفی تا 5 رأی, Lighthouse 93, موبایل بدون سرریز.

## alborz (استان)
| قبل | بعد |
|-----|-----|
| ![alborz before](./alborz-before.png) | ![alborz after](./alborz-after.png) |

*قبل:* ترتیب بخش‌ها به‌هم‌ریخته, H1 duplicate.
*بعد:* ترتیب طبق `docs/CLEANUP.md` مرحله ۶: هیرو→شناسنامه→شهرستان‌ها→جاذبه‌ها→FAQ, H1 واحد, Breadcrumb `خانه→استان البرز`.

---

**نکته:** همهٔ تصاویر در 390×844 (iPhone 12) و 1280×800 گرفته شد؛ Lighthouse دسکتاپ/موبایل جداگانه در `docs/phase9-checklist.md`.
