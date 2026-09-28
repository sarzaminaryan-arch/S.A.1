# ساختار کامل لینک‌های سایت سرزمین آریان — آماده‌ی کپی

> تولید: ۱۴۰۵/۰۷/۰۶ · منبع حقیقت: `inc/entities-config.php` و `inc/taxonomies.php` و `data/provinces.php` قالب فرزند + `counties.json` افزونه‌های واردکننده b01/b02/b03 · مدل داده: MASTER_DATA_MODEL (append-only)  
> دامنه: آدرس نصب وردپرس (`home_url`) — در جدول‌ها لینک‌ها **نسبی** آمده‌اند؛ کافی است دامنه را به ابتدای هر لینک بچسبانید (مثال: `https://sarzaminaryan.com` + `/province/tehran/`).

---

## ۱) الگوی کلی پیوندهای یکتا (Permalink)

همه موجودیت‌ها با `with_front = false` ثبت شده‌اند؛ یعنی هیچ پیشوندی (مثل `/blog/` یا تاریخ) روی آدرس‌ها نمی‌نشیند و ساختار نهایی چنین است:

| الگو | مثال واقعی |
|---|---|
| `/province/{نامک-استان}/` | `/province/tehran/` |
| `/city/{نامک-شهر}/` | `/city/tabriz/` |
| `/attraction/{نامک-جاذبه}/` | `/attraction/arg-karim-khan/` |
| `/route/{نامک-مسیر}/` | `/route/shiraz-kerman/` |
| `/food/{نامک-غذا}/` | `/food/ghalyeh-mahi/` |
| `/souvenir/{نامک-سوغات}/` | `/souvenir/gaz-isfahan/` |
| `/ostan/{نامک-استان}/` (آرشیو دسته‌بندی) | `/ostan/fars/` |

قاعده نامک (سطح ۴ مدل داده): فقط حروف کوچک لاتین، عدد و خط تیره؛ یونیک درون هر CPT؛ اگر نامک فارسی یا خالی باشد قالب به‌طور خودکار نویسه‌گردانی می‌کند (`sa_enforce_ascii_slug`) اما نامک‌های انگلیسیِ ازپیش‌تعریف‌شده هرگز نباید تغییر کنند (قاعده append-only).

---

## ۲) انواع محتوا (CPT) — نام‌ها و لینک پایه

| CPT (شناسه سیستمی) | نام تکی | نام جمع | نامک بازنویسی (URL Base) | آرشیو | صفحه تکی | وضعیت |
|---|---|---|---|---|---|---|
| `province` | استان | استان‌ها | `province` | `/province/` | `/province/{slug}/` | فعال |
| `city` | شهر | شهرها | `city` | `/city/` | `/city/{slug}/` | فعال |
| `attraction` | جاذبه | جاذبه‌ها | `attraction` | `/attraction/` | `/attraction/{slug}/` | فعال |
| `travel_route` | مسیر سفر | مسیرهای سفر | `route` ⚠️ | `/route/` | `/route/{slug}/` | فعال |
| `local_food` | غذای محلی | غذاهای محلی | `food` ⚠️ | `/food/` | `/food/{slug}/` | فعال |
| `souvenir` | سوغات | سوغات | `souvenir` | `/souvenir/` | `/souvenir/{slug}/` | فعال |
| `accommodation` | اقامتگاه | اقامتگاه‌ها | `accommodation` | `/accommodation/` | `/accommodation/{slug}/` | رزرو (ثبت نمی‌شود تا `SA_ENABLE_ACCOMMODATION` روشن شود) |

⚠️ نکته مهم: CPT «مسیر سفر» شناسه سیستمی `travel_route` است اما نامک URL آن `route` است؛ و CPT «غذای محلی» شناسه `local_food` با نامک URL `food`. در لینک‌سازی داخلی هرگز از شناسه سیستمی به‌عنوان آدرس استفاده نکنید.

---

## ۳) دسته‌بندی‌ها (تاکسونومی‌ها) و لینک همه‌ی ترم‌ها

| تاکسونومی (شناسه سیستمی) | نام فارسی | نامک URL | سلسله‌مراتب | اعمال‌شده روی | تعداد ترم |
|---|---|---|---|---|---|
| `province_tax` | استان‌ها | `ostan` | بله | همه موجودیت‌ها | ۳۱ (فهرست ثابت) |
| `attraction_type` | انواع جاذبه | `attraction-type` | بله | جاذبه | ۱۲ |
| `travel_season` | فصل‌های سفر | `season` | خیر | استان، شهر، جاذبه، مسیر | ۴ |
| `travel_budget` | بودجه‌های سفر | `budget` | خیر | مسیر (+اقامتگاه رزرو) | ۳ |
| `travel_duration` | مدت‌های سفر | `duration` | خیر | مسیر | ۴ |
| `accommodation_type` | انواع اقامتگاه | `accommodation-type` | بله | اقامتگاه (رزرو) | ۵ |

### ۳-۱) دسته‌بندی استان‌ها — `/ostan/{slug}/` (۳۱ ترم ثابت)

| # | ترم (نام فارسی) | نامک | لینک آماده |
|---|---|---|---|
| ۱ | آذربایجان شرقی | `east-azerbaijan` | `/ostan/east-azerbaijan/` |
| ۲ | آذربایجان غربی | `west-azerbaijan` | `/ostan/west-azerbaijan/` |
| ۳ | اردبیل | `ardabil` | `/ostan/ardabil/` |
| ۴ | اصفهان | `isfahan` | `/ostan/isfahan/` |
| ۵ | البرز | `alborz` | `/ostan/alborz/` |
| ۶ | ایلام | `ilam` | `/ostan/ilam/` |
| ۷ | بوشهر | `bushehr` | `/ostan/bushehr/` |
| ۸ | تهران | `tehran` | `/ostan/tehran/` |
| ۹ | چهارمحال و بختیاری | `chaharmahal-bakhtiari` | `/ostan/chaharmahal-bakhtiari/` |
| ۱۰ | خراسان جنوبی | `south-khorasan` | `/ostan/south-khorasan/` |
| ۱۱ | خراسان رضوی | `razavi-khorasan` | `/ostan/razavi-khorasan/` |
| ۱۲ | خراسان شمالی | `north-khorasan` | `/ostan/north-khorasan/` |
| ۱۳ | خوزستان | `khuzestan` | `/ostan/khuzestan/` |
| ۱۴ | زنجان | `zanjan` | `/ostan/zanjan/` |
| ۱۵ | سمنان | `semnan` | `/ostan/semnan/` |
| ۱۶ | سیستان و بلوچستان | `sistan-baluchestan` | `/ostan/sistan-baluchestan/` |
| ۱۷ | فارس | `fars` | `/ostan/fars/` |
| ۱۸ | قزوین | `qazvin` | `/ostan/qazvin/` |
| ۱۹ | قم | `qom` | `/ostan/qom/` |
| ۲۰ | کردستان | `kurdistan` | `/ostan/kurdistan/` |
| ۲۱ | کرمان | `kerman` | `/ostan/kerman/` |
| ۲۲ | کرمانشاه | `kermanshah` | `/ostan/kermanshah/` |
| ۲۳ | کهگیلویه و بویراحمد | `kohgiluyeh-boyer-ahmad` | `/ostan/kohgiluyeh-boyer-ahmad/` |
| ۲۴ | گلستان | `golestan` | `/ostan/golestan/` |
| ۲۵ | گیلان | `gilan` | `/ostan/gilan/` |
| ۲۶ | لرستان | `lorestan` | `/ostan/lorestan/` |
| ۲۷ | مازندران | `mazandaran` | `/ostan/mazandaran/` |
| ۲۸ | مرکزی | `markazi` | `/ostan/markazi/` |
| ۲۹ | هرمزگان | `hormozgan` | `/ostan/hormozgan/` |
| ۳۰ | همدان | `hamadan` | `/ostan/hamadan/` |
| ۳۱ | یزد | `yazd` | `/ostan/yazd/` |

> دسترسی مدیریت این ترم‌ها فقط برای مدیر کل است (`manage_options`) — فهرست ۳۱ تایی ثابت است و نباید ترمی کم/زیاد شود.

### ۳-۲) انواع جاذبه — `/attraction-type/{slug}/`

| # | ترم | نامک | لینک آماده |
|---|---|---|---|
| ۱ | تاریخی | `historical` | `/attraction-type/historical/` |
| ۲ | فرهنگی | `cultural` | `/attraction-type/cultural/` |
| ۳ | مذهبی | `religious` | `/attraction-type/religious/` |
| ۴ | طبیعی | `nature` | `/attraction-type/nature/` |
| ۵ | کوهستانی | `mountain` | `/attraction-type/mountain/` |
| ۶ | جنگلی | `forest` | `/attraction-type/forest/` |
| ۷ | کویری | `desert` | `/attraction-type/desert/` |
| ۸ | ساحلی | `beach` | `/attraction-type/beach/` |
| ۹ | جزیره‌ای | `island` | `/attraction-type/island/` |
| ۱۰ | روستایی | `village` | `/attraction-type/village/` |
| ۱۱ | بوم‌گردی | `ecotourism` | `/attraction-type/ecotourism/` |
| ۱۲ | ماجراجویی | `adventure` | `/attraction-type/adventure/` |

### ۳-۳) فصل سفر — `/season/{slug}/`

| # | ترم | نامک | لینک آماده |
|---|---|---|---|
| ۱ | بهار | `spring` | `/season/spring/` |
| ۲ | تابستان | `summer` | `/season/summer/` |
| ۳ | پاییز | `autumn` | `/season/autumn/` |
| ۴ | زمستان | `winter` | `/season/winter/` |

### ۳-۴) بودجه سفر — `/budget/{slug}/`

| # | ترم | نامک | لینک آماده |
|---|---|---|---|
| ۱ | اقتصادی | `economic` | `/budget/economic/` |
| ۲ | متوسط | `medium` | `/budget/medium/` |
| ۳ | لوکس | `luxury` | `/budget/luxury/` |

### ۳-۵) مدت سفر — `/duration/{slug}/`

| # | ترم | نامک | لینک آماده |
|---|---|---|---|
| ۱ | یک‌روزه | `one_day` | `/duration/one_day/` |
| ۲ | آخر هفته | `weekend` | `/duration/weekend/` |
| ۳ | ۳ تا ۵ روز | `3_to_5_days` | `/duration/3_to_5_days/` |
| ۴ | بیش از ۵ روز | `more_than_5_days` | `/duration/more_than_5_days/` |

### ۳-۶) انواع اقامتگاه — `/accommodation-type/{slug}/` (رزرو — فعال نیست)

| # | ترم | نامک | لینک آماده |
|---|---|---|---|
| ۱ | هتل | `hotel` | `/accommodation-type/hotel/` |
| ۲ | اقامتگاه بوم‌گردی | `eco_lodge` | `/accommodation-type/eco_lodge/` |
| ۳ | مهمان‌پذیر | `guest_house` | `/accommodation-type/guest_house/` |
| ۴ | خانه سنتی | `traditional_house` | `/accommodation-type/traditional_house/` |
| ۵ | کمپینگ | `camping` | `/accommodation-type/camping/` |

---

## ۴) لینک‌های منوی اصلی سایت (منوی پیش‌فرض قالب — `sa_fallback_menu`)

| ترتیب | آیتم منو | مقصد |
|---|---|---|
| ۱ | خانه | `/` |
| ۲ | استان‌ها | `/province/` |
| ۳ | شهرها | `/city/` |
| ۴ | جاذبه‌ها | `/attraction/` |
| ۵ | مسیرهای سفر | `/route/` |
| ۶ | غذاهای محلی | `/food/` |
| ۷ | سوغات | `/souvenir/` |
| — | برگه‌ی نوشته‌ها (در صورت تعیین «صفحه‌ی نوشته‌ها» در تنظیمات) | لینک برگه |

اگر منوی سفارشی ساخته شود، همین آدرس‌ها مبنای «منوی اصلی» و «دسترسی سریع» (secondary) هستند.

---

## ۵) ۳۱ صفحه‌ی استان — `/province/{slug}/` (آماده‌ی کپی)

| # | استان | مرکز | نامک | لینک آماده |
|---|---|---|---|---|
| ۱ | آذربایجان شرقی | تبریز | `east-azerbaijan` | `/province/east-azerbaijan/` |
| ۲ | آذربایجان غربی | ارومیه | `west-azerbaijan` | `/province/west-azerbaijan/` |
| ۳ | اردبیل | اردبیل | `ardabil` | `/province/ardabil/` |
| ۴ | اصفهان | اصفهان | `isfahan` | `/province/isfahan/` |
| ۵ | البرز | کرج | `alborz` | `/province/alborz/` |
| ۶ | ایلام | ایلام | `ilam` | `/province/ilam/` |
| ۷ | بوشهر | بوشهر | `bushehr` | `/province/bushehr/` |
| ۸ | تهران | تهران | `tehran` | `/province/tehran/` |
| ۹ | چهارمحال و بختیاری | شهرکرد | `chaharmahal-bakhtiari` | `/province/chaharmahal-bakhtiari/` |
| ۱۰ | خراسان جنوبی | بیرجند | `south-khorasan` | `/province/south-khorasan/` |
| ۱۱ | خراسان رضوی | مشهد | `razavi-khorasan` | `/province/razavi-khorasan/` |
| ۱۲ | خراسان شمالی | بجنورد | `north-khorasan` | `/province/north-khorasan/` |
| ۱۳ | خوزستان | اهواز | `khuzestan` | `/province/khuzestan/` |
| ۱۴ | زنجان | زنجان | `zanjan` | `/province/zanjan/` |
| ۱۵ | سمنان | سمنان | `semnan` | `/province/semnan/` |
| ۱۶ | سیستان و بلوچستان | زاهدان | `sistan-baluchestan` | `/province/sistan-baluchestan/` |
| ۱۷ | فارس | شیراز | `fars` | `/province/fars/` |
| ۱۸ | قزوین | قزوین | `qazvin` | `/province/qazvin/` |
| ۱۹ | قم | قم | `qom` | `/province/qom/` |
| ۲۰ | کردستان | سنندج | `kurdistan` | `/province/kurdistan/` |
| ۲۱ | کرمان | کرمان | `kerman` | `/province/kerman/` |
| ۲۲ | کرمانشاه | کرمانشاه | `kermanshah` | `/province/kermanshah/` |
| ۲۳ | کهگیلویه و بویراحمد | یاسوج | `kohgiluyeh-boyer-ahmad` | `/province/kohgiluyeh-boyer-ahmad/` |
| ۲۴ | گلستان | گرگان | `golestan` | `/province/golestan/` |
| ۲۵ | گیلان | رشت | `gilan` | `/province/gilan/` |
| ۲۶ | لرستان | خرم‌آباد | `lorestan` | `/province/lorestan/` |
| ۲۷ | مازندران | ساری | `mazandaran` | `/province/mazandaran/` |
| ۲۸ | مرکزی | اراک | `markazi` | `/province/markazi/` |
| ۲۹ | هرمزگان | بندرعباس | `hormozgan` | `/province/hormozgan/` |
| ۳۰ | همدان | همدان | `hamadan` | `/province/hamadan/` |
| ۳۱ | یزد | یزد | `yazd` | `/province/yazd/` |

---

## ۶) صفحات شهرستان/شهر — `/city/{slug}/` (مجموع ۴۱۹ صفحه از فهرست شهرستان‌های ۳۱ استان)

این نامک‌ها از `counties.json` افزونه‌های واردکننده می‌آیند و با یک کلیک «وارد کردن شهرستان‌ها» در پیشخوان، داخل CPT «شهر» ساخته می‌شوند: b01 = ۱۵۳ شهر (۱۰ استان اول) · b02 = ۱۲۸ شهر (۱۰ استان دوم) · b03 = ۱۳۸ شهر (۹ استان بسته‌ی ۳) — مجموع **۴۱۹ شهر**.

> دو استان «همدان» و «یزد» هنوز مقاله کامل ندارند و فهرست شهرستان‌شان به ترتیب در نسخه‌های بعدی b03 اضافه می‌شود (بخش آن‌ها در این سند خالی است).

### ۱. آذربایجان شرقی — ۲۱ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | تبریز | `tabriz` | `/city/tabriz/` |
| ۲ | مراغه | `maragheh` | `/city/maragheh/` |
| ۳ | مرند | `marand` | `/city/marand/` |
| ۴ | اسکو | `osku` | `/city/osku/` |
| ۵ | کلیبر | `kaleybar` | `/city/kaleybar/` |
| ۶ | جلفا | `jolfa` | `/city/jolfa/` |
| ۷ | آذرشهر | `azarshahr` | `/city/azarshahr/` |
| ۸ | اهر | `ahar` | `/city/ahar/` |
| ۹ | بستان‌آباد | `bostanabad` | `/city/bostanabad/` |
| ۱۰ | بناب | `bonab` | `/city/bonab/` |
| ۱۱ | چاراویماق | `charoymaq` | `/city/charoymaq/` |
| ۱۲ | خداآفرین | `khoda-afarin` | `/city/khoda-afarin/` |
| ۱۳ | سراب | `sarab` | `/city/sarab/` |
| ۱۴ | شبستر | `shabestar` | `/city/shabestar/` |
| ۱۵ | عجب‌شیر | `ajabshir` | `/city/ajabshir/` |
| ۱۶ | ملکان | `malekan` | `/city/malekan/` |
| ۱۷ | میانه | `mianeh` | `/city/mianeh/` |
| ۱۸ | ورزقان | `varzaqan` | `/city/varzaqan/` |
| ۱۹ | هریس | `heris` | `/city/heris/` |
| ۲۰ | هشترود | `hashtrud` | `/city/hashtrud/` |
| ۲۱ | هوراند | `hurand` | `/city/hurand/` |

### ۲. آذربایجان غربی — ۲۰ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | ارومیه | `urmia` | `/city/urmia/` |
| ۲ | خوی | `khoy` | `/city/khoy/` |
| ۳ | مهاباد | `mahabad` | `/city/mahabad/` |
| ۴ | تکاب | `takab` | `/city/takab/` |
| ۵ | چالدران | `chaldoran` | `/city/chaldoran/` |
| ۶ | ماکو | `maku` | `/city/maku/` |
| ۷ | اشنویه | `oshnavieh` | `/city/oshnavieh/` |
| ۸ | باروق | `baruq` | `/city/baruq/` |
| ۹ | بوکان | `bukan` | `/city/bukan/` |
| ۱۰ | پلدشت | `poldasht` | `/city/poldasht/` |
| ۱۱ | پیرانشهر | `piranshahr` | `/city/piranshahr/` |
| ۱۲ | چایپاره | `chaypareh` | `/city/chaypareh/` |
| ۱۳ | چهاربرج | `chaharborj` | `/city/chaharborj/` |
| ۱۴ | سردشت | `sardasht` | `/city/sardasht/` |
| ۱۵ | سلماس | `salmas` | `/city/salmas/` |
| ۱۶ | شاهین‌دژ | `shahin-dezh` | `/city/shahin-dezh/` |
| ۱۷ | شوط | `showt` | `/city/showt/` |
| ۱۸ | میاندوآب | `miandoab` | `/city/miandoab/` |
| ۱۹ | نقده | `naqadeh` | `/city/naqadeh/` |
| ۲۰ | میرآباد | `mirabad` | `/city/mirabad/` |

### ۳. اردبیل — ۱۲ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | اردبیل | `ardabil-city` | `/city/ardabil-city/` |
| ۲ | سرعین | `sarein` | `/city/sarein/` |
| ۳ | مشگین‌شهر | `meshgin-shahr` | `/city/meshgin-shahr/` |
| ۴ | پارس‌آباد | `parsabad` | `/city/parsabad/` |
| ۵ | خلخال | `khalkhal` | `/city/khalkhal/` |
| ۶ | نمین | `namin` | `/city/namin/` |
| ۷ | گرمی | `germi` | `/city/germi/` |
| ۸ | بیله‌سوار | `bileh-savar` | `/city/bileh-savar/` |
| ۹ | اصلاندوز | `aslanduz` | `/city/aslanduz/` |
| ۱۰ | کوثر (گیوی) | `givi` | `/city/givi/` |
| ۱۱ | نیر | `nir` | `/city/nir/` |
| ۱۲ | انگوت | `angut` | `/city/angut/` |

### ۴. اصفهان — ۲۹ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | اصفهان | `isfahan-city` | `/city/isfahan-city/` |
| ۲ | کاشان | `kashan` | `/city/kashan/` |
| ۳ | نطنز | `natanz` | `/city/natanz/` |
| ۴ | سمیرم | `semirom` | `/city/semirom/` |
| ۵ | نایین | `nain` | `/city/nain/` |
| ۶ | خور و بیابانک | `khur-biabanak` | `/city/khur-biabanak/` |
| ۷ | خمینی‌شهر | `khomeyni-shahr` | `/city/khomeyni-shahr/` |
| ۸ | نجف‌آباد | `najafabad` | `/city/najafabad/` |
| ۹ | لنجان (زرین‌شهر) | `lenjan` | `/city/lenjan/` |
| ۱۰ | فلاورجان | `falavarjan` | `/city/falavarjan/` |
| ۱۱ | شاهین‌شهر و میمه | `shahin-shahr-meymeh` | `/city/shahin-shahr-meymeh/` |
| ۱۲ | شهرضا | `shahreza` | `/city/shahreza/` |
| ۱۳ | مبارکه | `mobarakeh` | `/city/mobarakeh/` |
| ۱۴ | برخوار | `borkhar` | `/city/borkhar/` |
| ۱۵ | آران و بیدگل | `aran-bidgol` | `/city/aran-bidgol/` |
| ۱۶ | گلپایگان | `golpayegan` | `/city/golpayegan/` |
| ۱۷ | تیران و کرون | `tiran-karvan` | `/city/tiran-karvan/` |
| ۱۸ | فریدن | `fereydan` | `/city/fereydan/` |
| ۱۹ | اردستان | `ardestan` | `/city/ardestan/` |
| ۲۰ | جرقویه | `jarqavieh` | `/city/jarqavieh/` |
| ۲۱ | فریدون‌شهر | `fereydunshahr` | `/city/fereydunshahr/` |
| ۲۲ | دهاقان | `dehaqan` | `/city/dehaqan/` |
| ۲۳ | خوانسار | `khansar` | `/city/khansar/` |
| ۲۴ | چادگان | `chadegan` | `/city/chadegan/` |
| ۲۵ | ورزنه | `varzaneh` | `/city/varzaneh/` |
| ۲۶ | بوئین میاندشت | `buin-miandasht` | `/city/buin-miandasht/` |
| ۲۷ | کوهپایه | `kuhpayeh` | `/city/kuhpayeh/` |
| ۲۸ | هرند | `harand` | `/city/harand/` |
| ۲۹ | میمه و وزوان (پس از ابلاغ) | `meymeh-vazvan` | `/city/meymeh-vazvan/` |

### ۵. البرز — ۷ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | کرج | `karaj` | `/city/karaj/` |
| ۲ | فردیس | `ferdows` | `/city/ferdows/` |
| ۳ | ساوجبلاغ | `sojablogh` | `/city/sojablogh/` |
| ۴ | نظرآباد | `nazarabad` | `/city/nazarabad/` |
| ۵ | چهارباغ | `chaharbagh` | `/city/chaharbagh/` |
| ۶ | اشتهارد | `eshtehard` | `/city/eshtehard/` |
| ۷ | طالقان | `talqan` | `/city/talqan/` |

### ۶. ایلام — ۱۲ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | ایلام | `ilam-city` | `/city/ilam-city/` |
| ۲ | مهران | `mehran` | `/city/mehran/` |
| ۳ | دهلران | `dehloran` | `/city/dehloran/` |
| ۴ | ایوان | `eyvan` | `/city/eyvan/` |
| ۵ | آبدانان | `abdanan` | `/city/abdanan/` |
| ۶ | دره‌شهر | `darreh-shahr` | `/city/darreh-shahr/` |
| ۷ | چرداول | `chardavol` | `/city/chardavol/` |
| ۸ | بدره | `badreh` | `/city/badreh/` |
| ۹ | ملکشاهی | `malekshahi` | `/city/malekshahi/` |
| ۱۰ | چوار | `chavar` | `/city/chavar/` |
| ۱۱ | هلیلان | `halilan` | `/city/halilan/` |
| ۱۲ | سیروان | `sirvan` | `/city/sirvan/` |

### ۷. بوشهر — ۱۲ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | بوشهر (مرکز استان) | `bushehr-city` | `/city/bushehr-city/` |
| ۲ | برازجان | `borazjan` | `/city/borazjan/` |
| ۳ | بندر گناوه | `ganaveh` | `/city/ganaveh/` |
| ۴ | خورموج | `khormoj` | `/city/khormoj/` |
| ۵ | بندر کنگان | `kangan` | `/city/kangan/` |
| ۶ | عسلویه | `asaluyeh` | `/city/asaluyeh/` |
| ۷ | جم | `jam` | `/city/jam/` |
| ۸ | اهرم | `ahram` | `/city/ahram/` |
| ۹ | بندر دیر | `deyr` | `/city/deyr/` |
| ۱۰ | بندر دیلم | `deylam` | `/city/deylam/` |
| ۱۱ | شهرستان بوشهر | `bushehr-county` | `/city/bushehr-county/` |
| ۱۲ | شهرستان دشتستان | `dashtestan` | `/city/dashtestan/` |

### ۸. تهران — ۱۶ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | تهران | `tehran-city` | `/city/tehran-city/` |
| ۲ | ری | `rey` | `/city/rey/` |
| ۳ | شمیرانات | `shemiranat` | `/city/shemiranat/` |
| ۴ | دماوند | `damavand` | `/city/damavand/` |
| ۵ | فیروزکوه | `firuzkuh` | `/city/firuzkuh/` |
| ۶ | ورامین | `varamin` | `/city/varamin/` |
| ۷ | اسلامشهر | `eslamshahr` | `/city/eslamshahr/` |
| ۸ | بهارستان | `baharestan` | `/city/baharestan/` |
| ۹ | پاکدشت | `pakdasht` | `/city/pakdasht/` |
| ۱۰ | پردیس | `pardis` | `/city/pardis/` |
| ۱۱ | پیشوا | `pishva` | `/city/pishva/` |
| ۱۲ | رباط‌کریم | `robat-karim` | `/city/robat-karim/` |
| ۱۳ | شهریار | `shahriar` | `/city/shahriar/` |
| ۱۴ | قدس | `qods` | `/city/qods/` |
| ۱۵ | قرچک | `qarchak` | `/city/qarchak/` |
| ۱۶ | ملارد | `malard` | `/city/malard/` |

### ۹. چهارمحال و بختیاری — ۱۲ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | شهرکرد | `shahrekord` | `/city/shahrekord/` |
| ۲ | کوهرنگ (چلگرد) | `kuhrang` | `/city/kuhrang/` |
| ۳ | سامان | `saman` | `/city/saman/` |
| ۴ | بروجن | `borujen` | `/city/borujen/` |
| ۵ | لردگان | `lordegan` | `/city/lordegan/` |
| ۶ | فارسان | `farsan` | `/city/farsan/` |
| ۷ | اردل | `ardal` | `/city/ardal/` |
| ۸ | کیار (شلمزار) | `kiar` | `/city/kiar/` |
| ۹ | خانمیرزا (آلونی) | `khanmirza` | `/city/khanmirza/` |
| ۱۰ | بن | `ben` | `/city/ben/` |
| ۱۱ | فرخ‌شهر | `farrokhshahr` | `/city/farrokhshahr/` |
| ۱۲ | فلارد (مال‌خلیفه) | `falard` | `/city/falard/` |

### ۱۰. خراسان جنوبی — ۱۲ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | بیرجند | `birjand` | `/city/birjand/` |
| ۲ | فردوس | `ferdows` | `/city/ferdows/` |
| ۳ | طبس | `tabas` | `/city/tabas/` |
| ۴ | قائن | `ghaen` | `/city/ghaen/` |
| ۵ | نهبندان | `nehbandan` | `/city/nehbandan/` |
| ۶ | سربیشه | `sarbisheh` | `/city/sarbisheh/` |
| ۷ | اسدیه | `asadieh` | `/city/asadieh/` |
| ۸ | سرایان | `sarayan` | `/city/sarayan/` |
| ۹ | بشرویه | `beshrooyeh` | `/city/beshrooyeh/` |
| ۱۰ | حاجی‌آباد | `hajjiabad` | `/city/hajjiabad/` |
| ۱۱ | خوسف | `khosf` | `/city/khosf/` |
| ۱۲ | عشق‌آباد | `eshqabad` | `/city/eshqabad/` |

### ۱۱. خراسان رضوی — ۸ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | مشهد | `mashhad` | `/city/mashhad/` |
| ۲ | نیشابور | `neyshabur` | `/city/neyshabur/` |
| ۳ | سبزوار | `sabzevar` | `/city/sabzevar/` |
| ۴ | تربت حیدریه | `torbat-heydarieh` | `/city/torbat-heydarieh/` |
| ۵ | تربت جام | `torbat-jam` | `/city/torbat-jam/` |
| ۶ | قوچان | `quchan` | `/city/quchan/` |
| ۷ | کاشمر | `kashmar` | `/city/kashmar/` |
| ۸ | گناباد | `gonabad` | `/city/gonabad/` |

### ۱۲. خراسان شمالی — ۱۰ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | بجنورد | `bojnord` | `/city/bojnord/` |
| ۲ | اسفراین | `esfarayen` | `/city/esfarayen/` |
| ۳ | شیروان | `shirvan` | `/city/shirvan/` |
| ۴ | مانه (آشخانه) | `maneh` | `/city/maneh/` |
| ۵ | جاجرم | `jajarm` | `/city/jajarm/` |
| ۶ | سملقان | `samalqan` | `/city/samalqan/` |
| ۷ | راز و جرگلان | `raz-jargalan` | `/city/raz-jargalan/` |
| ۸ | فاروج | `faruj` | `/city/faruj/` |
| ۹ | گرمه | `garmeh` | `/city/garmeh/` |
| ۱۰ | بام و صفی‌آباد | `bam-safiabad` | `/city/bam-safiabad/` |

### ۱۳. خوزستان — ۲۷ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | آبادان | `abadan` | `/city/abadan/` |
| ۲ | آغاجاری | `aghajari` | `/city/aghajari/` |
| ۳ | اهواز | `ahvaz-city` | `/city/ahvaz-city/` |
| ۴ | امیدیه | `omidiyeh` | `/city/omidiyeh/` |
| ۵ | اندیکا | `andika` | `/city/andika/` |
| ۶ | اندیمشک | `andimeshk` | `/city/andimeshk/` |
| ۷ | ایذه | `izeh` | `/city/izeh/` |
| ۸ | باغ‌ملک | `bagh-e-malek` | `/city/bagh-e-malek/` |
| ۹ | باوی | `bavi` | `/city/bavi/` |
| ۱۰ | بندر ماهشهر | `mahshahr` | `/city/mahshahr/` |
| ۱۱ | بهبهان | `behbahan` | `/city/behbahan/` |
| ۱۲ | حمیدیه | `hamidiyeh` | `/city/hamidiyeh/` |
| ۱۳ | خرمشهر | `khorramshahr` | `/city/khorramshahr/` |
| ۱۴ | دزفول | `dezful` | `/city/dezful/` |
| ۱۵ | دشت آزادگان | `dasht-azadegan` | `/city/dasht-azadegan/` |
| ۱۶ | رامشیر | `ramshir` | `/city/ramshir/` |
| ۱۷ | رامهرمز | `ramhormoz` | `/city/ramhormoz/` |
| ۱۸ | شادگان | `shadegan` | `/city/shadegan/` |
| ۱۹ | شوش | `shush` | `/city/shush/` |
| ۲۰ | شوشتر | `shushtar` | `/city/shushtar/` |
| ۲۱ | کارون | `karun` | `/city/karun/` |
| ۲۲ | گتوند | `gotvand` | `/city/gotvand/` |
| ۲۳ | لالی | `lali` | `/city/lali/` |
| ۲۴ | مسجدسلیمان | `masjed-soleyman` | `/city/masjed-soleyman/` |
| ۲۵ | هفتکل | `haftkel` | `/city/haftkel/` |
| ۲۶ | هندیجان | `hendijan` | `/city/hendijan/` |
| ۲۷ | هویزه | `hoveyzeh` | `/city/hoveyzeh/` |

### ۱۴. زنجان — ۸ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | زنجان | `zanjan-city` | `/city/zanjan-city/` |
| ۲ | خدابنده | `khodabandeh` | `/city/khodabandeh/` |
| ۳ | ابهر | `abhar` | `/city/abhar/` |
| ۴ | خرمدره | `kharadere` | `/city/kharadere/` |
| ۵ | طارم | `tarom` | `/city/tarom/` |
| ۶ | ماهنشان | `mahneshan` | `/city/mahneshan/` |
| ۷ | ایجرود | `ejrud` | `/city/ejrud/` |
| ۸ | سلطانیه | `soltaniyeh` | `/city/soltaniyeh/` |

### ۱۵. سمنان — ۸ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | سمنان | `semnan-city` | `/city/semnan-city/` |
| ۲ | شاهرود | `shahrud` | `/city/shahrud/` |
| ۳ | دامغان | `damghan` | `/city/damghan/` |
| ۴ | گرمسار | `garmsar` | `/city/garmsar/` |
| ۵ | مهدی‌شهر | `mehdishahr` | `/city/mehdishahr/` |
| ۶ | سرخه | `sorkheh` | `/city/sorkheh/` |
| ۷ | میامی | `meyami` | `/city/meyami/` |
| ۸ | آرادان | `aradan` | `/city/aradan/` |

### ۱۶. سیستان و بلوچستان — ۱۹ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | زاهدان | `zahedan` | `/city/zahedan/` |
| ۲ | چابهار | `chabahar` | `/city/chabahar/` |
| ۳ | ایرانشهر | `iranshahr` | `/city/iranshahr/` |
| ۴ | سراوان | `saravan` | `/city/saravan/` |
| ۵ | سرباز | `sarbaz` | `/city/sarbaz/` |
| ۶ | خاش | `khash` | `/city/khash/` |
| ۷ | زابل | `zabol` | `/city/zabol/` |
| ۸ | نیک‌شهر | `nik-shahr` | `/city/nik-shahr/` |
| ۹ | کنارک | `konarak` | `/city/konarak/` |
| ۱۰ | سیب و سوران | `sib-va-suran` | `/city/sib-va-suran/` |
| ۱۱ | زهک | `zehak` | `/city/zehak/` |
| ۱۲ | مهرستان | `mehrestan` | `/city/mehrestan/` |
| ۱۳ | دلگان | `dalgan` | `/city/dalgan/` |
| ۱۴ | هیرمند | `hirmand` | `/city/hirmand/` |
| ۱۵ | قصرقند | `qasr-e-qand` | `/city/qasr-e-qand/` |
| ۱۶ | فنوج | `fanuj` | `/city/fanuj/` |
| ۱۷ | نیمروز | `nimruz` | `/city/nimruz/` |
| ۱۸ | میرجاوه | `mirjaveh` | `/city/mirjaveh/` |
| ۱۹ | هامون | `hamun` | `/city/hamun/` |

### ۱۷. فارس — ۲۹ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | شیراز | `shiraz` | `/city/shiraz/` |
| ۲ | مرودشت | `marvdasht` | `/city/marvdasht/` |
| ۳ | کازرون | `kazerun` | `/city/kazerun/` |
| ۴ | جهرم | `jahrom` | `/city/jahrom/` |
| ۵ | لارستان | `larestan` | `/city/larestan/` |
| ۶ | فسا | `fasa` | `/city/fasa/` |
| ۷ | داراب | `darab` | `/city/darab/` |
| ۸ | فیروزآباد | `firuzabad` | `/city/firuzabad/` |
| ۹ | ممسنی | `mamasani` | `/city/mamasani/` |
| ۱۰ | نی‌ریز | `neyriz` | `/city/neyriz/` |
| ۱۱ | آباده | `abadeh` | `/city/abadeh/` |
| ۱۲ | اقلید | `eqlid` | `/city/eqlid/` |
| ۱۳ | لامرد | `lamerd` | `/city/lamerd/` |
| ۱۴ | سپیدان | `sepidan` | `/city/sepidan/` |
| ۱۵ | کوار | `kavar` | `/city/kavar/` |
| ۱۶ | زرین‌دشت | `zarrin-dasht` | `/city/zarrin-dasht/` |
| ۱۷ | قیر و کارزین | `qir-va-karzin` | `/city/qir-va-karzin/` |
| ۱۸ | استهبان | `estahban` | `/city/estahban/` |
| ۱۹ | مهر | `mehr` | `/city/mehr/` |
| ۲۰ | خرامه | `kharameh` | `/city/kharameh/` |
| ۲۱ | گراش | `gerash` | `/city/gerash/` |
| ۲۲ | خرم‌بید | `khorrambid` | `/city/khorrambid/` |
| ۲۳ | بوانات | `bavanat` | `/city/bavanat/` |
| ۲۴ | فراشبند | `farashband` | `/city/farashband/` |
| ۲۵ | رستم | `rostam` | `/city/rostam/` |
| ۲۶ | ارسنجان | `arsanjan` | `/city/arsanjan/` |
| ۲۷ | خنج | `khonj` | `/city/khonj/` |
| ۲۸ | سروستان | `sarvestan` | `/city/sarvestan/` |
| ۲۹ | پاسارگاد | `pasargad` | `/city/pasargad/` |

### ۱۸. قزوین — ۶ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | قزوین | `qazvin-city` | `/city/qazvin-city/` |
| ۲ | تاکستان | `takestan` | `/city/takestan/` |
| ۳ | البرز | `alborz-qazvin` | `/city/alborz-qazvin/` |
| ۴ | بوئین‌زهرا | `buin-zahra` | `/city/buin-zahra/` |
| ۵ | آبیک | `abyek` | `/city/abyek/` |
| ۶ | آوج | `avaj` | `/city/avaj/` |

### ۱۹. قم — ۳ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | قم | `qom-city` | `/city/qom-city/` |
| ۲ | جعفرآباد | `jafarabady` | `/city/jafarabady/` |
| ۳ | کهک | `kahak` | `/city/kahak/` |

### ۲۰. کردستان — ۱۰ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | سنندج | `sanandaj` | `/city/sanandaj/` |
| ۲ | بانه | `baneh` | `/city/baneh/` |
| ۳ | بیجار | `bijar` | `/city/bijar/` |
| ۴ | دهگلان | `dehgolan` | `/city/dehgolan/` |
| ۵ | دیواندره | `divandarreh` | `/city/divandarreh/` |
| ۶ | سروآباد | `sarvabad` | `/city/sarvabad/` |
| ۷ | سقز | `saqqez` | `/city/saqqez/` |
| ۸ | قروه | `qorveh` | `/city/qorveh/` |
| ۹ | کامیاران | `kamyaran` | `/city/kamyaran/` |
| ۱۰ | مریوان | `marivan` | `/city/marivan/` |

### ۲۱. کرمان — ۲۵ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | کرمان | `kerman` | `/city/kerman/` |
| ۲ | سیرجان | `sirjan` | `/city/sirjan/` |
| ۳ | رفسنجان | `rafsanjan` | `/city/rafsanjan/` |
| ۴ | جیرفت | `jiroft` | `/city/jiroft/` |
| ۵ | بم | `bam` | `/city/bam/` |
| ۶ | زرند | `zarand` | `/city/zarand/` |
| ۷ | شهربابک | `shahr-e-babak` | `/city/shahr-e-babak/` |
| ۸ | کهنوج | `kahnoj` | `/city/kahnoj/` |
| ۹ | بافت | `baft` | `/city/baft/` |
| ۱۰ | عنبرآباد | `anbarabad` | `/city/anbarabad/` |
| ۱۱ | بردسیر | `bardsir` | `/city/bardsir/` |
| ۱۲ | قلعه‌گنج | `qaleh-ganj` | `/city/qaleh-ganj/` |
| ۱۳ | فهرج | `fahraj` | `/city/fahraj/` |
| ۱۴ | منوجان | `manujan` | `/city/manujan/` |
| ۱۵ | ریگان | `rigan` | `/city/rigan/` |
| ۱۶ | رودبار جنوب | `rudbar-e-jonub` | `/city/rudbar-e-jonub/` |
| ۱۷ | نرماشیر | `narmashir` | `/city/narmashir/` |
| ۱۸ | راور | `ravar` | `/city/ravar/` |
| ۱۹ | ارزوئیه | `arzuiyeh` | `/city/arzuiyeh/` |
| ۲۰ | انار | `anar` | `/city/anar/` |
| ۲۱ | رابر | `rabor` | `/city/rabor/` |
| ۲۲ | فاریاب | `faryab` | `/city/faryab/` |
| ۲۳ | کوهبنان | `kuhbanan` | `/city/kuhbanan/` |
| ۲۴ | جازموریان | `jazmourian` | `/city/jazmourian/` |
| ۲۵ | گنبکی | `gonbaki` | `/city/gonbaki/` |

### ۲۲. کرمانشاه — ۱۴ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | کرمانشاه | `kermanshah-city` | `/city/kermanshah-city/` |
| ۲ | اسلام‌آباد غرب | `eslamabad-e-gharb` | `/city/eslamabad-e-gharb/` |
| ۳ | پاوه | `paveh` | `/city/paveh/` |
| ۴ | ثلاث باباجانی | `salas-e-babajani` | `/city/salas-e-babajani/` |
| ۵ | جوانرود | `javanrud` | `/city/javanrud/` |
| ۶ | دالاهو | `dalahu` | `/city/dalahu/` |
| ۷ | روانسر | `ravansar` | `/city/ravansar/` |
| ۸ | سرپل ذهاب | `sarpol-e-zahab` | `/city/sarpol-e-zahab/` |
| ۹ | سنقر | `sonqor` | `/city/sonqor/` |
| ۱۰ | صحنه | `sahneh` | `/city/sahneh/` |
| ۱۱ | قصر شیرین | `qasr-e-shirin` | `/city/qasr-e-shirin/` |
| ۱۲ | کنگاور | `kangavar` | `/city/kangavar/` |
| ۱۳ | گیلانغرب | `gilan-e-gharb` | `/city/gilan-e-gharb/` |
| ۱۴ | هرسین | `harsin` | `/city/harsin/` |

### ۲۳. کهگیلویه و بویراحمد — ۹ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | بویراحمد | `boyer-ahmad` | `/city/boyer-ahmad/` |
| ۲ | کهگیلویه | `kohgiluyeh` | `/city/kohgiluyeh/` |
| ۳ | گچساران | `gachsaran` | `/city/gachsaran/` |
| ۴ | دنا | `dena` | `/city/dena/` |
| ۵ | بهمئی | `bahmai` | `/city/bahmai/` |
| ۶ | چرام | `choram` | `/city/choram/` |
| ۷ | باشت | `basht` | `/city/basht/` |
| ۸ | لنده | `landeh` | `/city/landeh/` |
| ۹ | مارگون | `margoun` | `/city/margoun/` |

### ۲۴. گلستان — ۱۴ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | گرگان | `gorgan` | `/city/gorgan/` |
| ۲ | گنبد کاووس | `gonbad-e-qabus` | `/city/gonbad-e-qabus/` |
| ۳ | علی‌آباد کتول | `aliabad-e-katul` | `/city/aliabad-e-katul/` |
| ۴ | آق‌قلا | `aqqala` | `/city/aqqala/` |
| ۵ | کلاله | `kalaleh` | `/city/kalaleh/` |
| ۶ | آزادشهر | `azadshahr` | `/city/azadshahr/` |
| ۷ | رامیان | `ramian` | `/city/ramian/` |
| ۸ | بندر ترکمن | `bandar-e-torkaman` | `/city/bandar-e-torkaman/` |
| ۹ | مینودشت | `minudasht` | `/city/minudasht/` |
| ۱۰ | کردکوی | `kordkuy` | `/city/kordkuy/` |
| ۱۱ | گمیشان | `gomishan` | `/city/gomishan/` |
| ۱۲ | گالیکش | `galikesh` | `/city/galikesh/` |
| ۱۳ | مراوه‌تپه | `maraveh-tapeh` | `/city/maraveh-tapeh/` |
| ۱۴ | بندر گز | `bandar-e-gaz` | `/city/bandar-e-gaz/` |

### ۲۵. گیلان — ۱۷ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | رشت | `rasht` | `/city/rasht/` |
| ۲ | تالش | `talesh` | `/city/talesh/` |
| ۳ | لاهیجان | `lahijan` | `/city/lahijan/` |
| ۴ | رودسر | `rudsar` | `/city/rudsar/` |
| ۵ | لنگرود | `langarud` | `/city/langarud/` |
| ۶ | بندر انزلی | `bandar-e-anzali` | `/city/bandar-e-anzali/` |
| ۷ | صومعه‌سرا | `someh-sara` | `/city/someh-sara/` |
| ۸ | آستانه اشرفیه | `astaneh-ye-ashrafiyeh` | `/city/astaneh-ye-ashrafiyeh/` |
| ۹ | رودبار | `rudbar` | `/city/rudbar/` |
| ۱۰ | فومن | `fuman` | `/city/fuman/` |
| ۱۱ | آستارا | `astara` | `/city/astara/` |
| ۱۲ | رضوانشهر | `rezvanshahr` | `/city/rezvanshahr/` |
| ۱۳ | خمام | `khomam` | `/city/khomam/` |
| ۱۴ | ماسال | `masal` | `/city/masal/` |
| ۱۵ | شفت | `shaft` | `/city/shaft/` |
| ۱۶ | سیاهکل | `siahkal` | `/city/siahkal/` |
| ۱۷ | املش | `amlash` | `/city/amlash/` |

### ۲۶. لرستان — ۱۲ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | خرم‌آباد | `khorramabad` | `/city/khorramabad/` |
| ۲ | بروجرد | `borujerd` | `/city/borujerd/` |
| ۳ | دورود | `dorud` | `/city/dorud/` |
| ۴ | کوهدشت | `kuhdasht` | `/city/kuhdasht/` |
| ۵ | دلفان | `delfan` | `/city/delfan/` |
| ۶ | الیگودرز | `aligudarz` | `/city/aligudarz/` |
| ۷ | سلسله | `selseleh` | `/city/selseleh/` |
| ۸ | ازنا | `azna` | `/city/azna/` |
| ۹ | پلدختر | `pol-e-dokhtar` | `/city/pol-e-dokhtar/` |
| ۱۰ | چگنی | `chegeni` | `/city/chegeni/` |
| ۱۱ | رومشکان | `rumeshkan` | `/city/rumeshkan/` |
| ۱۲ | معمولان | `mamulan` | `/city/mamulan/` |

### ۲۷. مازندران — ۲۲ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | ساری | `sari` | `/city/sari/` |
| ۲ | بابل | `babol` | `/city/babol/` |
| ۳ | آمل | `amol` | `/city/amol/` |
| ۴ | قائم‌شهر | `qaemshahr` | `/city/qaemshahr/` |
| ۵ | بهشهر | `behshahr` | `/city/behshahr/` |
| ۶ | تنکابن | `tonekabon` | `/city/tonekabon/` |
| ۷ | نوشهر | `nowshahr` | `/city/nowshahr/` |
| ۸ | بابلسر | `babolsar` | `/city/babolsar/` |
| ۹ | نور | `nur` | `/city/nur/` |
| ۱۰ | نکا | `neka` | `/city/neka/` |
| ۱۱ | چالوس | `chalus` | `/city/chalus/` |
| ۱۲ | محمودآباد | `mahmudabad` | `/city/mahmudabad/` |
| ۱۳ | جویبار | `joybar` | `/city/joybar/` |
| ۱۴ | رامسر | `ramsar` | `/city/ramsar/` |
| ۱۵ | فریدون‌کنار | `freydunkenar` | `/city/freydunkenar/` |
| ۱۶ | میان‌دورود | `miandorud` | `/city/miandorud/` |
| ۱۷ | عباس‌آباد | `abbasabad` | `/city/abbasabad/` |
| ۱۸ | سوادکوه | `savadkuh` | `/city/savadkuh/` |
| ۱۹ | گلوگاه | `gologah` | `/city/gologah/` |
| ۲۰ | سوادکوه شمالی | `savadkuh-shomali` | `/city/savadkuh-shomali/` |
| ۲۱ | کلاردشت | `kelardasht` | `/city/kelardasht/` |
| ۲۲ | سیمرغ | `simorgh` | `/city/simorgh/` |

### ۲۸. مرکزی — ۱۲ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | اراک | `arak` | `/city/arak/` |
| ۲ | ساوه | `saveh` | `/city/saveh/` |
| ۳ | شازند | `shazand` | `/city/shazand/` |
| ۴ | خمین | `khomeyn` | `/city/khomeyn/` |
| ۵ | زرندیه | `zarandieh` | `/city/zarandieh/` |
| ۶ | محلات | `mahallat` | `/city/mahallat/` |
| ۷ | خنداب | `khondab` | `/city/khondab/` |
| ۸ | دلیجان | `delijan` | `/city/delijan/` |
| ۹ | کمیجان | `komijan` | `/city/komijan/` |
| ۱۰ | فراهان | `farahan` | `/city/farahan/` |
| ۱۱ | تفرش | `tafresh` | `/city/tafresh/` |
| ۱۲ | آشتیان | `ashtian` | `/city/ashtian/` |

### ۲۹. هرمزگان — ۱۳ شهر

| # | شهر مرکز شهرستان | نامک | لینک آماده |
|---|---|---|---|
| ۱ | بندرعباس | `bandar-abbas` | `/city/bandar-abbas/` |
| ۲ | بندر لنگه | `bandar-lengeh` | `/city/bandar-lengeh/` |
| ۳ | میناب | `minab` | `/city/minab/` |
| ۴ | قشم | `qeshm` | `/city/qeshm/` |
| ۵ | ابوموسی | `abumusa` | `/city/abumusa/` |
| ۶ | جاسک | `jask` | `/city/jask/` |
| ۷ | رودان | `rudan` | `/city/rudan/` |
| ۸ | حاجی‌آباد | `hajiabad` | `/city/hajiabad/` |
| ۹ | بستک | `bastak` | `/city/bastak/` |
| ۱۰ | پارسیان | `parsian` | `/city/parsian/` |
| ۱۱ | خمیر | `khamir` | `/city/khamir/` |
| ۱۲ | سیریک | `sirik` | `/city/sirik/` |
| ۱۳ | بشاگرد | `bashagard` | `/city/bashagard/` |

### ۳۰. همدان — هنوز وارد نشده

**در انتظار تکمیل مقاله همدان؛ پس از آن فهرست کامل شهرستان‌ها (همدان، ملایر، نهاوند، تویسرکان، اسدآباد، بهار، کبودرآهنگ، رزن، فامنین، درگزین) به `counties.json` بسته‌ی b03 اضافه می‌شود.**

### ۳۱. یزد — هنوز وارد نشده

**در انتظار فیلتر ویراستار پیش‌نویس یزد؛ پس از آن فهرست کامل شهرستان‌ها (یزد، اردکان، میبد، مهریز، تفت، صدوق، بافق، ابرکوه، خاتم، بهاباد) به `counties.json` بسته‌ی b03 اضافه می‌شود.**

---

## ۷) الگوی صفحات آینده (هنوز محتوا تولید نشده)

| موجودیت | الگو | نمونه از نقشه‌ی لینک مقالات استان |
|---|---|---|
| جاذبه | `/attraction/{slug}/` | `/attraction/falak-ol-aflok/` · `/attraction/chal-nakhjir-cave/` · `/attraction/stars-valley-qeshm/` |
| غذا | `/food/{slug}/` | `/food/abgoosht-e-bozbash/` · `/food/ghalyeh-mahi/` |
| سوغات | `/souvenir/{slug}/` | `/souvenir/saveh-pomegranate/` · `/souvenir/sarough-carpet/` |
| مسیر سفر | `/route/{slug}/` | `/route/tehran-sari/` |

سایر آدرس‌های سیستمی: جست‌وجو `/?s={عبارت}` · خوراک هر موجودیت `/province/feed/` · صفحه ۴۰۴ · آرشیو نویسنده.

---

## ۸) قواعد ثابت (از AGENTS و مدل داده — تغییرناپذیر)

1. نامک‌ها فقط انگلیسی/ASCII، حروف کوچک و خط تیره؛ تغییر نامک موجود ممنوع (append-only).
2. اسلاگ استان‌ها عیناً از `data/provinces.php` می‌آید؛ حتی اگر سئو پلاگین اعتراض کند تغییر نمی‌کنند.
3. ترم دسته‌بندی استان (province_tax) با همان نامک صفحه استان ساخته می‌شود: صفحه استان `/province/fars/` ↔ آرشیو دسته `/ostan/fars/`.
4. هر مقاله استان به همه شهرستان‌های همان استان لینک می‌دهد (جدول بالا مبنای لینک‌سازی داخلی است).
5. افزونه‌های واردکننده: b01 (۱۰ استان اول)، b02 (۱۰ استان دوم)، b03 (۱۱ استان سوم) — فهرست شهرستان هر استان از همین بسته‌ها وارد می‌شود؛ بسته‌ی قدیمی `sa-province-importer-5provinces` منسوخ است.
