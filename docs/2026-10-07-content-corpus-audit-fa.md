# ممیزی corpus سه خروجی WXR — سرزمین آریان

این گزارش از خروجی `tools/audit_wxr_content.py` روی سه فایل WordPress WXR ساخته شده است. متن مقاله، عنوان/توضیح سئو و کلیدواژه در آن چاپ نمی‌شود؛ فقط شمارش‌ها، نشانی‌ها و نشانه‌های ساختاری.

## ۱. خلاصهٔ اجرایی

- موجودیت‌های منتشرشده در این سه فایل: **۵۴۰** (شهرستان/استان/نمای برتر).
- **۲۶۱** صفحهٔ منتشرشده `<h1>` داخل بدنه دارد؛ دروازهٔ انتشار جلوی نمونه‌های تازه را می‌گیرد، این‌ها ماندهٔ گذشته‌اند.
- **۳۸** صفحهٔ منتشرشده نشانهٔ یادداشت/جای‌نگهدار تحریریه در متن دارد.
- از ۳۶۲ صفحه‌ای که ۱۰ لینک بیرونی یا بیشتر دارند، **۳۴۰** صفحه بیش از ۳۵٪ ارجاع‌هایش به یک دامنه است.
- فهرست کامل و به‌تفکیک ریسک در بخش ۴ و در فایل CSV کنارِ همین سند آمده است.

## ۲. فایل‌های مبنا و اثر انگشت

| فایل | بایت | sha256 | زمان ثبت (Drive) |
| --- | --- | --- | --- |
| `namayebartar.xml` | 862927 | `45f9f23442fc418f…` | 2026-10-07T15:16:27.003Z |
| `s.aryan.ostanha.31.xml` | 3761021 | `44220b0ffca34e99…` | 2026-10-07T15:16:26.706Z |
| `s-aryan-shar.xml` | 17890059 | `5bca5b3145a104aa…` | 2026-10-07T15:16:26.915Z |

مجموع اقلام بررسی‌شده: **۱۱۰۴** · منتشرشده: **۵۴۰**

## ۳. تصویر کلی به تفکیک فایل/نوع/وضعیت

| فایل | نوع | وضعیت | تعداد | میانهٔ واژه | مجموع واژه | H1 بدنه | FAQ | منبع | لینک داخلی | لینک بیرونی | تصویر | alt ندارد | پیوند بی‌نام | یادداشت تحریریه | بی‌override سئو |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| namayebartar.xml | attachment | inherit | 3 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 3 |
| namayebartar.xml | attraction | draft | 1 | 1617 | 1617 | 0 | 12 | 6 | 9 | 0 | 4 | 0 | 0 | 0 | 0 |
| namayebartar.xml | attraction | publish | 22 | 1492 | 33938 | 0 | 254 | 183 | 258 | 64 | 51 | 0 | 0 | 1 | 0 |
| s-aryan-shar.xml | attachment | inherit | 521 | 0 | 1392 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 521 |
| s-aryan-shar.xml | city | draft | 4 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 4 |
| s-aryan-shar.xml | city | publish | 487 | 1130 | 509614 | 261 | 4260 | 2744 | 7369 | 14660 | 0 | 0 | 0 | 31 | 0 |
| s-aryan-shar.xml | city | trash | 2 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 2 |
| s.aryan.ostanha.31.xml | attachment | inherit | 33 | 34 | 1118 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 33 |
| s.aryan.ostanha.31.xml | province | publish | 31 | 5965 | 189842 | 0 | 382 | 1351 | 82 | 2694 | 0 | 0 | 0 | 6 | 0 |

## ۴. فهرست کار به ترتیب ریسک

### P0 — ساختار و اعتماد (منتشرشده) — ۲۹۹ مورد

- **H1 داخل بدنه (دو سربرگ اصلی در صفحه)** — ۲۳۴ مورد
  - نمونه‌ها: `abadeh`، `abbasabad`، `abdanan`، `abhar`، `abyek`، `ahar`، `ahram`، `ajabshir`، `alborz-qazvin`، `aligudarz`، `amol`، `aradan`، `aran-bidgol`، `arsanjan`، `asaluyeh`، `avaj`، `azarshahr`، `azna`، `babol`، `babolsar`، `badreh`، `baharestan`، `bakhtegan`، `bampur`، `baruq`، `basht`، `bavanat`، `behshahr`، `beyza`، `bileh-savar`، `bonab`، `borazjan`، `borkhar`، `borujerd`، `bostanabad`، `boyer-ahmad`، `buin-zahra`، `bukan`، `bushehr-city`، `bushehr-county` … (+۱۹۴ مورد دیگر)
- **نشانهٔ یادداشت/جای‌نگهدار تحریریه در متن** — ۳۱ مورد
  - نمونه‌ها: `abadan`، `aghajari`، `ahvaz-city`، `andika`، `andimeshk`، `bagh-e-malek`، `bavi`، `behbahan`، `binalud`، `bojnord`، `chenaran`، `dezful`، `dezpart`، `esfarayen`، `faruj`، `garmeh`، `golbahar`، `hamidiyeh`، `izeh`، `jajarm`، `kalat`، `khalilabad`، `khorramshahr`، `kuhsorkh`، `mahshahr`، `mahvelat`، `mian-jolgeh`، `omidiyeh`، `raz-jargalan`، `shirvan`، `zebarkhan`
- **H1 داخل بدنه (دو سربرگ اصلی در صفحه)، کوتاه‌تر از ۸۰۰ واژه (شهرستان)** — ۲۴ مورد
  - نمونه‌ها: `angut`، `ardabil-city`، `ardestan`، `aslanduz`، `bahmai`، `buin-miandasht`، `chadegan`، `eyvan`، `fereydan`، `fereydunshahr`، `givi`، `golpayegan`، `harand`، `jarqavieh`، `khomeyni-shahr`، `kohgiluyeh`، `kuhpayeh`، `meshgin-shahr`، `mobarakeh`، `parsabad`، `sarein`، `shahreza`، `tiran-karvan`، `varzaneh`
- **نشانهٔ یادداشت/جای‌نگهدار تحریریه در متن، کمتر از ۵ پیوند داخلی در متنِ ذخیره‌شده (لینک خودکار قالب شمرده نمی‌شود)** — ۷ مورد
  - نمونه‌ها: `bisheh-waterfall-dorud`، `alborz`، `bushehr`، `kermanshah`، `razavi-khorasan`، `west-azerbaijan`، `zanjan`
- **H1 داخل بدنه (دو سربرگ اصلی در صفحه)، کمتر از ۵ پیوند داخلی در متنِ ذخیره‌شده (لینک خودکار قالب شمرده نمی‌شود)** — ۳ مورد
  - نمونه‌ها: `jafarabady`، `kahak`، `qom-city`

### P1 — یکدستی و تکراری (منتشرشده) — ۳ مورد

- **عنوان/توضیح متای تکراری در گروهی از نوشته‌ها** — ۳ مورد
  - نمونه‌ها: `aznadar-waterfall-doroud`، `falak-ol-aflak-castle-khorramabad`، `nigah-valley-dorud-2`

### P2 — پاکیزگی و بهینه‌سازی — ۱۷۷ مورد

- **کوتاه‌تر از ۸۰۰ واژه (شهرستان)** — ۱۳۹ مورد
  - نمونه‌ها: `abarkuh`، `abumusa`، `aliabad`، `amlash`، `anar`، `anbarabad`، `aqqala`، `arak`، `ardakan`، `ardal`، `arzuiyeh`، `asadabad`، `ashkezar`، `ashtian`، `astaneh-ye-ashrafiyeh`، `astara`، `azadshahr`، `bafq`، `baft`، `bahar`، `bam`، `bandar-e-abbas`، `bandar-e-anzali`، `bandar-e-gaz`، `bandar-lengeh`، `baneh`، `bardsir`، `bashagard`، `bastak`، `behabad`، `ben`، `bijar`، `borujen`، `dalahu`، `dargazin`، `dehgolan`، `delijan`، `divandarreh`، `eslamabad-e-gharb`، `fahraj` … (+۹۹ مورد دیگر)
- **کمتر از ۵ پیوند داخلی در متنِ ذخیره‌شده (لینک خودکار قالب شمرده نمی‌شود)** — ۲۸ مورد
  - نمونه‌ها: `dargaz`، `davarzan`، `firuzeh`، `joghatai`، `jovein`، `khoshab`، `mashhad`، `neyshabur`، `quchan`، `sabzevar`، `sheshtamad`، `ardabil`، `chaharmahal-bakhtiari`، `east-azerbaijan`، `fars`، `gilan`، `ilam`، `isfahan`، `kerman`، `kurdistan`، `lorestan`، `north-khorasan`، `qazvin`، `qom`، `sistan-baluchestan`، `south-khorasan`، `tehran`، `yazd`
- **کوتاه‌تر از ۸۰۰ واژه (شهرستان)، کمتر از ۵ پیوند داخلی در متنِ ذخیره‌شده (لینک خودکار قالب شمرده نمی‌شود)** — ۵ مورد
  - نمونه‌ها: `bam-and-safiabad`، `ferdows`، `maneh`، `samalqan`، `torkamanchay`
- **پیش‌نویس خالی (بدون متن)، بدون پرسش متداول، بدون منبع ثبت‌شده، کوتاه‌تر از ۸۰۰ واژه (شهرستان)، کمتر از ۵ پیوند داخلی در متنِ ذخیره‌شده (لینک خودکار قالب شمرده نمی‌شود)، بدون override عنوان/توضیح متا (هشدار، نه خطا)** — ۴ مورد
  - نمونه‌ها: `galikesh`، `isfahan`، `kabudarahang-city`، `shahdad`
- **عنوان/توضیح متای تکراری در گروهی از نوشته‌ها** — ۱ مورد
  - نمونه‌ها: `nigah-valley-dorud`

## ۵. تمرکز دامنه‌های بیرونی (سرنخ تنوع منبع)

نوشته‌هایی که بیش از ۳۵٪ ارجاع‌شان به یک دامنه است (≥۱۰ لینک): **۳۴۰**

| دامنه | تعداد ارجاع |
| --- | --- |
| `fa.wikipedia.org` | 12041 |
| `maps.google.com` | 1276 |
| `google.com` | 273 |
| `whc.unesco.org` | 232 |
| `iranicaonline.org` | 171 |
| `kojaro.com` | 147 |
| `mehrnews.com` | 144 |
| `citypopulation.de` | 141 |
| `visitiran.ir` | 128 |
| `irna.ir` | 116 |
| `amar.org.ir` | 101 |
| `en.wikipedia.org` | 91 |
| `britannica.com` | 87 |
| `wikidata.org` | 59 |
| `alibaba.ir` | 56 |

## ۶. محدودیت‌ها و روش

- این ابزار ساختار را می‌شمارد؛ درستی واقعیت، اعتبار منبع، سودمندی برای مسافر یا رتبهٔ Google را داوری نمی‌کند.
- جدول‌ها را عمداً با رقم لاتین نگه داشتیم تا با اسکریپت/اکسل و ویرایش‌های بعدی ساده بماند؛ متن، رقم فارسی دارد.
- نبود override سئو لزوماً خطا نیست؛ قالب یا افزونهٔ سئو می‌تواند عنوان/توضیح را خودکار بسازد.
- متن کامل سایت، تصویرها، منوها، سفارشی‌ساز و پایگاه‌داده در این سه خروجی نیستند؛ این ممیزی فقط همین سه فایل را توصیف می‌کند.
- داده‌های زمان‌محور (آمار، جمعیت، قیمت) بدون منبع و تاریخ اعتبار قابل انتشار نیستند؛ این ابزار چنین چیزی را هم بررسی نمی‌کند.

