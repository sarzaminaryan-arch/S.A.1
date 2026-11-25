# ممیزی تمپلت مستر شهرستان و لینکسازی خوشهای — استان تهران

تاریخ: ۲۰۲۶-۱۱-۲۵ / ۱۴۰۵/۰۹/۰۴ · منبع قواعد: `docs/live-site/CLUSTER-SYSTEM-MASTER.md` §۳/§۴/§۵ و گارد کد `tools/wp-agent/replace_src/check_template.py`.

| مجموعه | فایلها | سالم | دارای ایراد | ایرادها | هشدارها |
|---|---:|---:|---:|---:|---:|
| وضعیت پیش از این نشست (baseline) | 27 | 3 | 24 | 38 | 13 |
| خروجی `outbox/20261125-tehran-cluster-v6/` | 27 | 27 | 0 | 0 | 21 |

## آنچه بررسی شد

- **شهرستانها (۸):** نشانگرهای بدنهٔ ششگانهٔ گارد کد، بودجهٔ واژه، متای کامل تمپلیت مستر (شناسنامه، تبصرهٔ سرشماری ۱۳۹۵، FAQ، منابع، SEO، دسترسی، همسایهها)، یک لینک به استان، لینک به تمام شاخههای جاذبهٔ همان خوشه و لینکهای همسایه، حداکثر یک لینک به هر مقصد و `target="_self"`.
- **شاخههای جاذبه (۱۸):** نشانگرهای بدنه، کارت اطلاعات سریع (`sa_city_id`، مختصات، بهترین زمان، مدت بازدید)، لینک به شهرستان مادر و استان، ۲ تا ۴ لینک هممسیر، بدون لینک تکراری.
- **هاب استان (۱):** لینک به تمام ۱۶ شهرستان و تمام ۱۸ جاذبهٔ خوشه، بدون تکرار.

تراکم ۲ تا ۳ لینک در هر بخش، مطابق §۵، **هشدار** است نه خطای مسدودکننده (فهرستهای راهنمای استان و بخش نقشهٔ شهرستانها از پیش پرلینکاند).

## تفاوت مستندات و کد (ثبتشده در `decisions.md`)

سند مستر، آبوهوا، جاذبهها و FAQ را جزو بدنهٔ مقاله میداند، ولی `single-city.php` همان بخشها را از متا رندر میکند (`#weather-section`، `#attractions-section`، FAQ). طبق قانون «کد بر سند قدیمی مقدم است»، این بخشها به بدنه اضافه نشدند و کامل بودن متا ممیزی شد. بودجهٔ واژهٔ سند ۱۲۰۰–۱۸۰۰ و گارد کد ۱۵۰۰–۲۴۰۰ است؛ خروجی در بازهٔ مشترک و با گارد کد سنجیده شد.

## ایرادهای وضعیت پیش از نشست

- **tehran-213** (city، 2200 واژه، 13 لینک داخلی)
  - sa_seo_description 173 chars (want 140–155)
  - missing cluster links (1): ['/city/pardis/']
- **eslamshahr-219** (city، 2075 واژه، 0 لینک داخلی)
  - missing master meta: ['sa_province_id']
  - sa_seo_description 137 chars (want 140–155)
  - missing cluster links (8): ['/province/tehran/', '/attraction/azadi-tower/', '/attraction/chitgar-lake/', '/attraction/joughin-mound-shahriar/', '/city/tehran/', '/city/rey/']
  - L1: no link back to /province/tehran/
- **shahriar-225** (city، 1019 واژه، 7 لینک داخلی)
  - words 1019 outside master/code budget (1200–2400)
  - sa_seo_title 62 chars (> 60)
  - sa_seo_description 108 chars (want 140–155)
- **pakdasht-221** (city، 1589 واژه، 7 لینک داخلی)
  - sa_seo_description 135 chars (want 140–155)
  - missing cluster links (1): ['/city/tehran/']
- **varamin-218** (city، 1114 واژه، 6 لینک داخلی)
  - words 1114 outside master/code budget (1200–2400)
  - sa_seo_title 70 chars (> 60)
  - sa_seo_description 115 chars (want 140–155)
- **damavand-216** (city، 1219 واژه، 7 لینک داخلی)
  - sa_seo_title 65 chars (> 60)
  - sa_seo_description 119 chars (want 140–155)
  - missing cluster links (1): ['/city/varamin/']
- **firuzkuh-217** (city، 1153 واژه، 4 لینک داخلی)
  - words 1153 outside master/code budget (1200–2400)
  - sa_seo_title 66 chars (> 60)
  - sa_seo_description 110 chars (want 140–155)
- **shemiranat-215** (city، 1250 واژه، 8 لینک داخلی)
  - sa_seo_title 73 chars (> 60)
  - sa_seo_description 122 chars (want 140–155)
- **milad-tower** (attraction، 1349 واژه، 6 لینک داخلی)
  - missing cluster links (1): ['/attraction/saadabad-complex/']
- **golestan-palace** (attraction، 1227 واژه، 5 لینک داخلی)
  - missing cluster links (2): ['/attraction/milad-tower/', '/attraction/tabiat-bridge/']
- **saadabad-complex** (attraction، 1136 واژه، 5 لینک داخلی)
  - missing cluster links (3): ['/attraction/milad-tower/', '/attraction/golestan-palace/', '/attraction/tabiat-bridge/']
- **darband-darakeh** (attraction، 1079 واژه، 5 لینک داخلی)
  - missing cluster links (2): ['/attraction/milad-tower/', '/attraction/golestan-palace/']
- **tehran-grand-bazaar** (attraction، 1149 واژه، 6 لینک داخلی)
  - missing cluster links (2): ['/attraction/milad-tower/', '/attraction/saadabad-complex/']
- **azadi-tower** (attraction، 1099 واژه، 8 لینک داخلی)
  - missing cluster links (1): ['/attraction/saadabad-complex/']
- **chitgar-lake** (attraction، 1068 واژه، 5 لینک داخلی)
  - missing cluster links (2): ['/attraction/golestan-palace/', '/attraction/saadabad-complex/']
- **tochal-ski-resort** (attraction، 1023 واژه، 7 لینک داخلی)
  - missing cluster links (1): ['/attraction/afjeh-carrot-plain/']
- **afjeh-carrot-plain** (attraction، 1017 واژه، 5 لینک داخلی)
  - missing cluster links (2): ['/attraction/tochal-ski-resort/', '/attraction/dizin-ski-resort/']
- **tar-havir-lakes** (attraction، 1014 واژه، 5 لینک داخلی)
  - missing cluster links (1): ['/attraction/veresk-bridge/']
- **abali-ski-resort** (attraction، 886 واژه، 8 لینک داخلی)
  - missing cluster links (1): ['/attraction/veresk-bridge/']
- **tangeh-vashi** (attraction، 1106 واژه، 5 لینک داخلی)
  - missing cluster links (1): ['/attraction/abali-ski-resort/']
- **veresk-bridge** (attraction، 1136 واژه، 5 لینک داخلی)
  - missing cluster links (1): ['/attraction/tar-havir-lakes/']
- **varamin-jameh-mosque** (attraction، 1002 واژه، 7 لینک داخلی)
  - missing cluster links (1): ['/attraction/joughin-mound-shahriar/']
- **joughin-mound-shahriar** (attraction، 1040 واژه، 6 لینک داخلی)
  - missing cluster links (3): ['/attraction/varamin-jameh-mosque/', '/attraction/golestan-palace/', '/attraction/tehran-grand-bazaar/']
- **tehran-43** (province_hub، 1805 واژه، 25 لینک داخلی)
  - L1/L2: hub misses 12 links: ['/attraction/saadabad-complex/', '/attraction/tabiat-bridge/', '/attraction/darband-darakeh/', '/attraction/tehran-grand-bazaar/', '/attraction/chitgar-lake/', '/attraction/dizin-ski-resort/', '/attraction/shemshak-ski-resort/', '/attraction/afjeh-carrot-plain/']

## وضعیت خروجی v6

همهٔ 27 فایل بدون ایراد مسدودکننده ممیزی شدند.

## محدودهٔ کار

این خروجی **پیشنویس محلی** است؛ هیچ نوشتن زندهای انجام نشد (قفل fail-closed در `.agent/RULES.md`). متنهای محتوایی تأییدشده حفظ شدند و فقط لینکسازی، همترازسازی متا و نگارش خوشهٔ بدون شاخهٔ پاکدشت از شواهد ازپیشتأییدشدهٔ مخزن ساخته شد؛ پژوهش وب تازهای انجام نشد.
