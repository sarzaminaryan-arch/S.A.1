# گزارش پوشش ایندکس سرچ‌کنسول — ۲۰۲۶-۱۱-۱۳ (۱۴۰۵-۰۸-۲۲)

منبع: شش خروجی Coverage از پوشهٔ گوگل‌درایو مالک (Valid / Discovered / Crawled / Not found / Alternate / Validation) + بررسی زندهٔ سایت و قالب. این گزارش مبنای ترمیم قالب 2.22.64 است.

## تصویر کلی

| وضعیت | تعداد | روند ۴ اکتبر تا ۹ نوامبر | معنی |
|---|---|---|---|
| ایندکس‌شده (Valid) | 470 | 205 ← 470 | سالم و در حال رشد |
| کشف‌شده، هنوز خزش‌نشده (Discovered) | 247 | 63 ← 247 | **مشکل اصلی**؛ گوگل پیداشان کرده ولی حتی یک بار نرفته سراغشان |
| خزش‌شده، ایندکس‌نشده (Crawled) | 21 | 2 ← 21 | گوگل دیده و فعلاً رد کرده (کیفیت/تازگی) |
| یافت‌نشد (404) | 17 | 3 ← 17 | صفحه‌های حذف‌شده و نشانی‌های آشغال قدیمی |
| صفحهٔ جایگزین با کنونیکال درست | 1 | `/page/4/` | عادی و بی‌نیاز از اقدام |
| اعتبارسنجی «صفحه با ریدایرکت» | 13 | ۴ در انتظار، ۹ ناموفق | ریدایرکت‌های عمدیِ نامک‌های تکراری؛ خطای واقعی نیست (پایین توضیح داده شده) |

## علت‌های عدم ایندکس (با شاهد)

۱. **بودجهٔ خزش روی سایت پخش می‌شود.** سایت‌مپ ۴۸۳ صفحهٔ شهرستان دارد که ۲۸۸تایشان در رجیستری وضعیت `empty` دارند (قالب خودکار بدون محتوای یونیک). گوگل با خزش محدود، اول سراغ صفحه‌هایی می‌رود که سیگنال قوی‌تری دارند؛ صف کشف (۲۴۷) تندتر از خزش رشد کرده است (۶۳ به ۲۴۷ در پنج هفته، در برابر رشد ایندکس ۲۰۵ به ۴۷۰). شاهد کیفیت: خوشهٔ شهرستان‌های فارس که ۱۵ تا ۱۷ اکتبر خزش شدند تقریباً همه رفتند سطل «خزش‌شده، ایندکس‌نشده» — یعنی گوگل نمونه‌برداری کرده و فعلاً کلاس `/city/` خالی را کم‌ارزش دیده است.

۲. **نشت لینک داخلی روی صفحهٔ استان‌ها (ترمیم شد).** صفحهٔ استان فقط وقتی به شهرستان لینک می‌داد که شهرستان هم رابطهٔ والدِ ثبت‌شده داشت و هم نامکش عین رجیستری بود. شهرستان‌هایی با نامک زندهٔ متفاوت (مثل `zanjan` در برابر `zanjan-city`) یا بدون رابطه، روی صفحهٔ استان اسمِ بی‌لینک می‌ماندند و قوی‌ترین سیگنال داخلی‌شان را نمی‌گرفتند. در قالب 2.22.64 با وضوح نامزد نامک + fallback به هر نوشتهٔ منتشرشده ترمیم شد و اگر نامک خودش مبدأ ۳۰۱ بود، لینک مستقیم به مقصد می‌رود.

۳. **تازگی هنوز دیده نشده است.** آخرین خزش بیشتر شهرهای ایندکس‌شده بین ۱ تا ۲۰ اکتبر است؛ داده‌های «از تجربهٔ مسافرها» در قالب‌های 2.22.62 و 2.22.63 (۱۳ نوامبر) بعد از آن خزش‌ها منتشر شدند. برای ۱۰ شهر داده‌دارِ داخل صف کشف، خزشِ بعدی لازم است و با Request Indexing و لینک داخلی می‌شود جلو انداخت.

۴. **چند صفحهٔ حذف‌شده بدون ریدایرکت مانده‌اند** (بخش ۴۰۴ پایین)؛ چهار مورد قابل‌رفع در 2.22.64 ریدایرکت گرفتند.

۵. **فنیِ پایه سالم است:** robots.txt مانع نیست و سایت‌مپ (`wp-sitemap.xml`) اعلام شده؛ صفحهٔ نمونهٔ اراک روی سایت کامل رندر می‌شود و لینک استان و هم‌استانی‌ها را دارد. مشکل «خرابی صفحه» نیست؛ اولویت خزش و کیفیت ادراک‌شده است.

## الف) کشف‌شده و خزش‌نشده — ۲۴۷ صفحه

### اولویت ۱: شهرهای دارای دادهٔ «از تجربهٔ مسافرها» (۱۰ شهر) — برای Request Indexing دستی

| شهرستان | نامک | استان | نشانی |
|---|---|---|---|
| کرمانشاه | `kermanshah` | kermanshah | https://sarzaminaryan.ir/city/kermanshah/ |
| رشت | `rasht` | gilan | https://sarzaminaryan.ir/city/rasht/ |
| همدان | `hamedan` | hamadan | https://sarzaminaryan.ir/city/hamedan/ |
| کرمان | `kerman` | kerman | https://sarzaminaryan.ir/city/kerman/ |
| یزد | `yazd` | yazd | https://sarzaminaryan.ir/city/yazd/ |
| بندرعباس | `bandar-e-abbas` | hormozgan | https://sarzaminaryan.ir/city/bandar-e-abbas/ |
| اراک | `arak` | markazi | https://sarzaminaryan.ir/city/arak/ |
| سنندج | `sanandaj` | kurdistan | https://sarzaminaryan.ir/city/sanandaj/ |
| خرم‌آباد | `khorramabad` | lorestan | https://sarzaminaryan.ir/city/khorramabad/ |
| ساری | `sari` | mazandaran | https://sarzaminaryan.ir/city/sari/ |

### اولویت ۲: صفحه‌های استان (۶ صفحه)

- fars — `fars` — https://sarzaminaryan.ir/province/fars/
- kohgiluyeh-boyer-ahmad — `kohgiluyeh-boyer-ahmad` — https://sarzaminaryan.ir/province/kohgiluyeh-boyer-ahmad/
- kurdistan — `kurdistan` — https://sarzaminaryan.ir/province/kurdistan/
- markazi — `markazi` — https://sarzaminaryan.ir/province/markazi/
- razavi-khorasan — `razavi-khorasan` — https://sarzaminaryan.ir/province/razavi-khorasan/
- tehran — `tehran` — https://sarzaminaryan.ir/province/tehran/

### اولویت ۳: راهنماهای دیدنی (۱۶ صفحه)

- https://sarzaminaryan.ir/attraction/ali-sadr-cave-hamedan-guide/
- https://sarzaminaryan.ir/attraction/anzali-international-wetland-guide/
- https://sarzaminaryan.ir/attraction/azgan-wetland-dorud/
- https://sarzaminaryan.ir/attraction/babahur-forest-park-dorud/
- https://sarzaminaryan.ir/attraction/bisheh-waterfall-dorud/
- https://sarzaminaryan.ir/attraction/falak-ol-aflak-castle-khorramabad/
- https://sarzaminaryan.ir/attraction/golestan-national-park-guide/
- https://sarzaminaryan.ir/attraction/kuhrang-fritillaria-plain-guide/
- https://sarzaminaryan.ir/attraction/mount-damavand-guide/
- https://sarzaminaryan.ir/attraction/naqsh-e-jahan-square-isfahan/
- https://sarzaminaryan.ir/attraction/nozhian-waterfall-khorramabad/
- https://sarzaminaryan.ir/attraction/qeshm-stars-valley-geopark/
- https://sarzaminaryan.ir/attraction/shahdad-kaluts-lut-desert/
- https://sarzaminaryan.ir/attraction/shevi-waterfall-tele-zang/
- https://sarzaminaryan.ir/attraction/shushtar-historical-hydraulic-system/
- https://sarzaminaryan.ir/attraction/tangeh-lili-gorge-doroud/

### اولویت ۴: مقاله‌ها و تماس (۴ صفحه)

- https://sarzaminaryan.ir/burn-injury-first-aid/
- https://sarzaminaryan.ir/contact/
- https://sarzaminaryan.ir/diy-emergency-torch/
- https://sarzaminaryan.ir/drowning-rescue-guide/

### اولویت ۵: بقیهٔ شهرستان‌ها (۲۲۱ صفحه) — خزششان به محتوای یونیک (صف پژوهش) و زمان بستگی دارد؛ اقدام فوری لازم ندارند

وضعیت رجیستری این ۲۲۱ شهر: empty: 186، published: 18، missing: 13، draft: 4

| شهرستان | نامک | استان | وضعیت رجیستری |
|---|---|---|---|
| اصلاندوز | `aslanduz` | ardabil | published |
| نیر | `nir` | ardabil | published |
| پارس‌آباد | `parsabad` | ardabil | published |
| سرعین | `sarein` | ardabil | published |
| گناوه | `ganaveh` | bushehr | published |
| اردل | `ardal` | chaharmahal-bakhtiari | empty |
| بن | `ben` | chaharmahal-bakhtiari | empty |
| بروجن | `borujen` | chaharmahal-bakhtiari | empty |
| فلارد | `falard` | chaharmahal-bakhtiari | empty |
| فرخ‌شهر | `farrokhshahr` | chaharmahal-bakhtiari | empty |
| فارسان | `farsan` | chaharmahal-bakhtiari | empty |
| خانمیرزا | `khanmirza` | chaharmahal-bakhtiari | empty |
| کیار | `kiar` | chaharmahal-bakhtiari | empty |
| کوهرنگ | `kuhrang` | chaharmahal-bakhtiari | empty |
| لردگان | `lordegan` | chaharmahal-bakhtiari | empty |
| سامان | `saman` | chaharmahal-bakhtiari | empty |
| شهرکرد | `sharekurd` | chaharmahal-bakhtiari | empty |
| ترکمانچای | `torkamanchay` | east-azerbaijan | missing |
| آباده | `abadeh` | fars | empty |
| بختگان | `bakhtegan` | fars | missing |
| بوانات | `bavanat` | fars | empty |
| چنارشاهیجان | `chenar-shahijan` | fars | missing |
| داراب | `darab` | fars | empty |
| استهبان | `estahban` | fars | empty |
| فراشبند | `farashband` | fars | empty |
| فسا | `fasa` | fars | empty |
| فیروزآباد | `firuzabad` | fars | empty |
| گراش | `gerash` | fars | empty |
| جهرم | `jahrom` | fars | empty |
| جویم | `jooyom` | fars | missing |
| کازرون | `kazerun` | fars | empty |
| خفر | `khafr` | fars | missing |
| خرامه | `kharameh` | fars | empty |
| خرم‌بید | `khorrambid` | fars | empty |
| لارستان | `larestan` | fars | empty |
| ممسنی | `mamasani` | fars | empty |
| مرودشت | `marvdasht` | fars | empty |
| مهر | `mehr` | fars | empty |
| نی‌ریز | `neyriz` | fars | empty |
| سرچهان | `sarchahan` | fars | missing |
| زرقان | `zarghan` | fars | missing |
| املش | `amlash` | gilan | empty |
| آستانه اشرفیه | `astaneh-ye-ashrafiyeh` | gilan | empty |
| آستارا | `astara` | gilan | empty |
| فومن | `fuman` | gilan | empty |
| خمام | `khomam` | gilan | empty |
| لاهیجان | `lahijan` | gilan | empty |
| لنگرود | `langarud` | gilan | empty |
| رشت | `rasht` | gilan | empty |
| رودبار | `rudbar` | gilan | empty |
| رودسر | `rudsar` | gilan | empty |
| سیاهکل | `siahkal` | gilan | empty |
| صومعه‌سرا | `sowme-eh-sara` | gilan | empty |
| تالش | `talesh` | gilan | empty |
| علی‌آباد کتول | `aliabad` | golestan | published |
| آق‌قلا | `aqqala` | golestan | empty |
| آزادشهر | `azadshahr` | golestan | empty |
| گز | `bandar-e-gaz` | golestan | empty |
| گالیکش | `galikash` | golestan | empty |
| گمیشان | `gomishan` | golestan | empty |
| گنبد کاووس | `gonbad-e-kavus` | golestan | empty |
| کلاله | `kalaleh` | golestan | empty |
| کردکوی | `kordkuy` | golestan | empty |
| مراوه‌تپه | `maraveh-tappeh` | golestan | empty |
| مینودشت | `minudasht` | golestan | empty |
| رامیان | `ramian` | golestan | empty |
| ترکمن | `torkaman` | golestan | empty |
| بهار | `bahar` | hamadan | empty |
| درگزین | `dargazin` | hamadan | missing |
| فامنین | `famenin` | hamadan | empty |
| همدان | `hamedan` | hamadan | empty |
| کبودرآهنگ | `kabutarahang` | hamadan | empty |
| نهاوند | `nahavand` | hamadan | empty |
| تویسرکان | `tuyserkan` | hamadan | empty |
| ابوموسی | `abumusa` | hormozgan | empty |
| بندرعباس | `bandar-e-abbas` | hormozgan | empty |
| لنگه | `bandar-lengeh` | hormozgan | empty |
| بشاگرد | `bashagard` | hormozgan | empty |
| بستک | `bastak` | hormozgan | empty |
| حاجی‌آباد | `hajjiabad` | hormozgan | empty |
| جاسک | `jask` | hormozgan | empty |
| خمیر | `khamir` | hormozgan | empty |
| میناب | `minab` | hormozgan | empty |
| پارسیان | `parsian` | hormozgan | empty |
| رودان | `rudan` | hormozgan | empty |
| سیریک | `sirik` | hormozgan | empty |
| چرداول | `chardavol` | ilam | published |
| آران و بیدگل | `aran-bidgol` | isfahan | published |
| اردستان | `ardestan` | isfahan | published |
| نجف‌آباد | `najafabad` | isfahan | published |
| انار | `anar` | kerman | empty |
| عنبرآباد | `anbarabad` | kerman | empty |
| ارزوئیه | `arzuiyeh` | kerman | empty |
| بافت | `baft` | kerman | empty |
| بم | `bam` | kerman | empty |
| فهرج | `fahraj` | kerman | empty |
| فاریاب | `faryab` | kerman | empty |
| گنبکی | `gonbaki` | kerman | empty |
| جازموریان | `jazmurian` | kerman | empty |
| جیرفت | `jiroft` | kerman | empty |
| کهنوج | `kahnuj` | kerman | empty |
| کرمان | `kerman` | kerman | empty |
| کوهبنان | `kuhbanan` | kerman | empty |
| منوجان | `manujan` | kerman | empty |
| قلعه‌گنج | `qaleh-ganj` | kerman | empty |
| رفسنجان | `rafsanjan` | kerman | empty |
| ریگان | `rigan` | kerman | empty |
| رودبار جنوب | `rudbar-e-jonubi` | kerman | empty |
| شهربابک | `shahr-e-babak` | kerman | empty |
| سیرجان | `sirjan` | kerman | empty |
| زرند | `zarand` | kerman | empty |
| اسلام‌آباد غرب | `eslamabad-e-gharb` | kermanshah | empty |
| گیلانغرب | `gilan-e-gharb` | kermanshah | empty |
| جوانرود | `javanrud` | kermanshah | empty |
| کنگاور | `kangavar` | kermanshah | empty |
| کرمانشاه | `kermanshah` | kermanshah | empty |
| پاوه | `paveh` | kermanshah | empty |
| قصر شیرین | `qasr-e-shirin` | kermanshah | empty |
| روانسر | `ravansar` | kermanshah | empty |
| صحنه | `sahneh` | kermanshah | empty |
| ثلاث باباجانی | `salas-e-babajani` | kermanshah | empty |
| سرپل ذهاب | `sarpol-e-zahab` | kermanshah | empty |
| کرخه | `karkheh` | khuzestan | empty |
| بهمئی | `bahmai` | kohgiluyeh-boyer-ahmad | draft |
| باشت | `basht` | kohgiluyeh-boyer-ahmad | draft |
| گچساران | `gachsaran` | kohgiluyeh-boyer-ahmad | draft |
| لنده | `landeh` | kohgiluyeh-boyer-ahmad | draft |
| بانه | `baneh` | kurdistan | empty |
| دیواندره | `divandarreh` | kurdistan | empty |
| قروه | `ghorveh` | kurdistan | empty |
| کامیاران | `kamyaran` | kurdistan | empty |
| سنندج | `sanandaj` | kurdistan | empty |
| سقز | `saqqez` | kurdistan | empty |
| سروآباد | `sarvabad` | kurdistan | empty |
| الیگودرز | `aligudarz` | lorestan | empty |
| ازنا | `azna` | lorestan | empty |
| بروجرد | `borujerd` | lorestan | empty |
| چگنی | `chegeni` | lorestan | empty |
| دلفان | `delfan` | lorestan | empty |
| خرم‌آباد | `khorramabad` | lorestan | empty |
| کوهدشت | `kuhdasht` | lorestan | empty |
| معمولان | `mamulan` | lorestan | empty |
| پلدختر | `pol-e-dokhtar` | lorestan | empty |
| رومشکان | `rumeshkan` | lorestan | empty |
| سلسله | `selseleh` | lorestan | empty |
| اراک | `arak` | markazi | empty |
| آشتیان | `ashtian` | markazi | empty |
| دلیجان | `delijan` | markazi | empty |
| فراهان | `farahan` | markazi | empty |
| خمین | `khomeyn` | markazi | empty |
| کمیجان | `komijan` | markazi | empty |
| محلات | `mahallat` | markazi | empty |
| ساوه | `saveh` | markazi | empty |
| تفرش | `tafresh` | markazi | empty |
| زرندیه | `zarandieh` | markazi | empty |
| عباس‌آباد | `abbasabad` | mazandaran | empty |
| آمل | `amol` | mazandaran | empty |
| بابل | `babol` | mazandaran | empty |
| بابلسر | `babolsar` | mazandaran | empty |
| بهشهر | `behshahr` | mazandaran | empty |
| چالوس | `chalus` | mazandaran | empty |
| فریدونکنار | `freydunkenar` | mazandaran | empty |
| گلوگاه | `gologah` | mazandaran | empty |
| جویبار | `joybar` | mazandaran | empty |
| کلاردشت | `kelardasht` | mazandaran | empty |
| میاندورود | `miandorud` | mazandaran | empty |
| نکا | `neka` | mazandaran | empty |
| نوشهر | `nowshahr` | mazandaran | empty |
| نور | `nur` | mazandaran | empty |
| قائم‌شهر | `qaemshahr` | mazandaran | empty |
| رامسر | `ramsar` | mazandaran | empty |
| ساری | `sari` | mazandaran | empty |
| سوادکوه | `savadkuh` | mazandaran | empty |
| سوادکوه شمالی | `savadkuh-shomali` | mazandaran | empty |
| سیمرغ | `simorgh` | mazandaran | empty |
| تنکابن | `tonekabon` | mazandaran | empty |
| بام و صفی‌آباد | `bam-and-safiabad` | north-khorasan | empty |
| مانه | `maneh` | north-khorasan | empty |
| سملقان | `samalqan` | north-khorasan | published |
| آبیک | `abyek` | qazvin | empty |
| البرز | `alborz-qazvin` | qazvin | empty |
| آوج | `avaj` | qazvin | empty |
| بوئین‌زهرا | `buin-zahra` | qazvin | empty |
| تاکستان | `takestan` | qazvin | empty |
| جعفرآباد | `jafarabady` | qom | empty |
| کهک | `kahak` | qom | empty |
| سرخس | `sarakhs` | razavi-khorasan | missing |
| تایباد | `taybad` | razavi-khorasan | published |
| دامغان | `damghan` | semnan | empty |
| مهدی‌شهر | `mehdishahr` | semnan | empty |
| سرخه | `sorkheh` | semnan | empty |
| بمپور | `bampur` | sistan-baluchestan | missing |
| چابهار | `chabahar` | sistan-baluchestan | empty |
| دلگان | `dalgan` | sistan-baluchestan | empty |
| گلشن | `golshan` | sistan-baluchestan | missing |
| لاشار | `lashar` | sistan-baluchestan | missing |
| مهرستان | `mehrestan` | sistan-baluchestan | empty |
| میرجاوه | `mirjaveh` | sistan-baluchestan | empty |
| نیک‌شهر | `nik-shahr` | sistan-baluchestan | empty |
| راسک | `rask` | sistan-baluchestan | missing |
| سراوان | `saravan` | sistan-baluchestan | empty |
| سیب و سوران | `sib-va-suran` | sistan-baluchestan | empty |
| بیرجند | `birjand` | south-khorasan | published |
| بشرویه | `boshruyeh` | south-khorasan | published |
| خوسف | `khusf` | south-khorasan | published |
| زیرکوه | `zirkuh` | south-khorasan | published |
| فیروزکوه | `firuzkuh` | tehran | published |
| پاکدشت | `pakdasht` | tehran | published |
| ابرکوه | `abarkuh` | yazd | empty |
| اردکان | `ardakan` | yazd | empty |
| اشکذر | `ashkezar` | yazd | empty |
| خاتم | `khatam` | yazd | empty |
| مروست | `marvast` | yazd | empty |
| مهریز | `mehriz` | yazd | empty |
| میبد | `meybod` | yazd | empty |
| تفت | `taft` | yazd | empty |
| یزد | `yazd` | yazd | empty |
| زارچ | `zarach` | yazd | empty |
| ابهر | `abhar` | zanjan | empty |
| خدابنده | `khodabandeh` | zanjan | empty |
| طارم | `tarom` | zanjan | empty |

## ب) خزش‌شده ولی ایندکس‌نشده — ۲۱ صفحه

گوگل این‌ها را دیده و فعلاً نگه نداشته است. مهم‌ترینشان **شیراز** است: خزش ۱۷ اکتبر، یعنی پیش از آن‌که دادهٔ «از تجربهٔ مسافرها» رویش بنشیند؛ با نصب نسخه‌های تازه و Request Indexing باید دوباره دیده شود. `/feed/` در این فهرست عادی است و اقدامی نمی‌خواهد.

| نشانی | آخرین خزش |
|---|---|
| https://sarzaminaryan.ir/city/qeshm/ | 2026-11-11 |
| https://sarzaminaryan.ir/city/mahmudabad/ | 2026-11-11 |
| https://sarzaminaryan.ir/city/bandar-e-anzali/ | 2026-11-10 |
| https://sarzaminaryan.ir/maintain-fire-in-rain/ | 2026-11-10 |
| https://sarzaminaryan.ir/attraction/gahar-lake-dorud/ | 2026-11-10 |
| https://sarzaminaryan.ir/city/masal/ | 2026-11-10 |
| https://sarzaminaryan.ir/city/razan/ | 2026-11-09 |
| https://sarzaminaryan.ir/city/darmiyan/ | 2026-10-17 |
| https://sarzaminaryan.ir/city/shiraz/ | 2026-10-17 |
| https://sarzaminaryan.ir/city/eqlid/ | 2026-10-17 |
| https://sarzaminaryan.ir/city/kavar/ | 2026-10-17 |
| https://sarzaminaryan.ir/city/hamun/ | 2026-10-17 |
| https://sarzaminaryan.ir/city/sarvestan/ | 2026-10-16 |
| https://sarzaminaryan.ir/city/beyza/ | 2026-10-16 |
| https://sarzaminaryan.ir/city/zarrin-dasht/ | 2026-10-16 |
| https://sarzaminaryan.ir/city/rostam/ | 2026-10-16 |
| https://sarzaminaryan.ir/city/qir-va-karzin/ | 2026-10-16 |
| https://sarzaminaryan.ir/city/pasargad/ | 2026-10-15 |
| https://sarzaminaryan.ir/city/evaz/ | 2026-10-15 |
| https://sarzaminaryan.ir/city/jovein/ | 2026-10-03 |
| https://sarzaminaryan.ir/feed/ | 2026-10-01 |

## ج) ۴۰۴ — ۱۷ نشانی و اقدام

| نشانی | آخرین خزش | اقدام |
|---|---|---|
| https://sarzaminaryan.ir/city/savojbolagh/ | 2026-10-30 | ریدایرکت ۳۰۱ به `/province/alborz/` در 2.22.64 اضافه شد |
| https://sarzaminaryan.ir/attraction/vank-cathedral/ | 2026-10-26 | ریدایرکت ۳۰۱ به `/province/isfahan/` در 2.22.64 اضافه شد |
| https://sarzaminaryan.ir/attraction/shah-cheragh/ | 2026-10-24 | ریدایرکت ۳۰۱ به `/city/shiraz/` در 2.22.64 اضافه شد |
| https://sarzaminaryan.ir/wp-content/* | 2026-10-22 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/city/harand/W_HAR | 2026-10-22 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/city/chadegan/W_CHA | 2026-10-19 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/* | 2026-10-19 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/city/fereydan/W_FER | 2026-10-19 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/attraction/dorud-city/ | 2026-10-19 | از قبل ریدایرکت به `/city/dorud/` داشت؛ خزشِ گزارش قدیمی‌تر از ترمیم است و خودش پاک می‌شود |
| https://sarzaminaryan.ir/wp-content/uploads/* | 2026-10-18 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/city/kuhpayeh/W_KUH | 2026-10-18 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/wp-content/themes/sarzaminaryan-child/* | 2026-10-18 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/wp-*.php | 2026-10-18 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/editorial-policy/ | 2026-10-18 | ریدایرکت ۳۰۱ به `/policy/` در 2.22.64 اضافه شد |
| https://sarzaminaryan.ir/?page_id=9 | 2026-09-29 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/?page_id=7 | 2026-09-29 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |
| https://sarzaminaryan.ir/?page_id=8 | 2026-09-29 | نشانی الگویی/قدیمی؛ صفحهٔ واقعی نیست، اقدامی لازم ندارد |

## د) اعتبارسنجی «صفحه با ریدایرکت» — خطای واقعی نیست

این ۱۳ نشانی نسخه‌های نامکِ تکراری‌اند که عمداً ۳۰۱ می‌شوند (مثل `/city/isfahan-city/` که به نسخهٔ زنده می‌رود). «ناموفق» شدن اعتبارسنجی برایشان طبیعی است، چون بنا نیست ایندکس شوند؛ نسخهٔ کنونیکالشان ایندکس است. کاری لازم ندارند و نباید حذف شوند:

- https://sarzaminaryan.ir/ostan/hamadan/ — Pending
- https://sarzaminaryan.ir/ostan/semnan/ — Pending
- https://sarzaminaryan.ir/provinces/ardabil/ — Pending
- https://sarzaminaryan.ir/provinces/mazandaran/ — Pending
- https://sarzaminaryan.ir/city/dorud-city/ — Failed
- https://sarzaminaryan.ir/city/ilam-city/ — Failed
- https://sarzaminaryan.ir/city/isfahan-city/ — Failed
- https://sarzaminaryan.ir/city/borazjan/ — Failed
- https://sarzaminaryan.ir/city/bushehr-city/ — Failed
- https://sarzaminaryan.ir/city/torbat-jam/ — Failed
- https://sarzaminaryan.ir/attraction/308149-attraction-tabas/ — Failed
- https://sarzaminaryan.ir/attraction/6789-bagh-glshn-tbs/ — Failed
- https://sarzaminaryan.ir/attraction/26950-kvir-rig-jn/ — Failed
- https://sarzaminaryan.ir/city/ahvaz-city/ — Failed

## ترمیم انجام‌شده در قالب 2.22.64

۱. **لینک‌سازی داخلی استان ← شهرستان:** وضوح نامزد نامک (با/بدون `-city`) و fallback به هر نوشتهٔ منتشرشدهٔ شهرستان، حتی بدون رابطهٔ والد؛ لینک به مبدأهای ۳۰۱ مستقیم به مقصد می‌رود. تست `test-province-county-links.php` این رفتار را قفل می‌کند.
۲. **ریدایرکت‌های ۴۰۴:** چهار نشانی بخش (ج) به نزدیک‌ترین صفحهٔ زنده وصل شدند؛ موتور تغییرمسیر فقط وقتی مقصد منتشر شده باشد ۳۰۱ می‌فرستد.

## کارهای دستی باقی‌مانده (مالک)

۱. نصب قالب 2.22.64 از Releaseها.
۲. در سرچ‌کنسول برای اولویت‌های ۱ تا ۳ (۱۰ شهر + ۶ استان + چند دیدنی شاخص مثل علی‌صدر و دماوند و فلک‌الافلاک) Request Indexing؛ شیراز هم از بخش (ب) یک بار درخواست شود.
۳. صبر برای خزش بعدی بقیه؛ رشد ایندکس (۲۰۵ به ۴۷۰ در پنج هفته) نشان می‌دهد صف در حال حرکت است. شهرستان‌های `empty` با پژوهش دانه‌دانهٔ صف صاحب محتوای یونیک می‌شوند و نوبتشان می‌رسد.
