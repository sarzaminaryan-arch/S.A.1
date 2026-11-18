# کدباکس نشست جدید — اجرای تمپلیت مستر روی شهرستان‌های ایران (دستهٔ ۹)

> این فایل برای کپی‌کردن در پیام اول نشست بعدی است. کل بلوک زیر را عیناً بفرستید؛ خودش مرجع، قوانین، دستورها و نوبت شهرها را دارد.
> تاریخ ساخت: ۲۷ آبان ۱۴۰۵ — پس از بسته‌شدن دستهٔ ۸ (PR #66، مرج روی `main` با کامیت `ff26abe`).

---

```text
تو ایجنت کدنویسی روی مخزن sarzaminaryan-arch/S.A.1 هستی. مأموریت: ادامهٔ برنامهٔ ترمیم سایت
sarzaminaryan.ir بعد از Core Update نوامبر ۲۰۲۶ — یعنی بردن صفحه‌های شهرستان ایران یکی‌یکی
روی «تمپلیت مستر» و پر کردنشان با اطلاعات واقعی، تازه و میدانی. کار دسته‌ای است: هر دسته
ده شهرستان. دسته‌های ۱ تا ۸ بسته شده‌اند؛ تو از دستهٔ ۹ شروع می‌کنی.

۱) اول این‌ها را بخوان (به همین ترتیب)
   docs/live-site/HANDOFF-next-session.md
   AGENTS.md
   docs/live-site/county-template-worklist.md
   docs/live-site/batch8-template-diff.md      (نمونهٔ کاملِ یک دستهٔ بسته‌شده)
   tools/wp-agent/README.md

۲) محیط کار — این چهار نکته را از قبل بدان
   • سندباکس به sarzaminaryan.ir و upload.wikimedia.org دسترسی ندارد. نوشتن روی سایت فقط از
     راه فایل کار (tools/wp-agent/jobs/*.json) انجام می‌شود: push روی شاخهٔ arena/** باعث
     اجرای .github/workflows/wp-agent.yml می‌شود، رانر گیت‌هاب کار را اجرا می‌کند و نتیجه در
     tools/wp-agent/results/<همان نام>.json کامیت می‌شود. هر کار فقط یک بار اجرا می‌شود
     (وجود فایل نتیجه = اجرا شده).
   • تصاویر هم از همین راه: گام photos-import با فایل tools/photos/<batch>.json.
   • هر نوشتن، پشتیبان قبلی می‌گیرد؛ شمارهٔ پشتیبان‌ها را در دفتر و CHANGELOG ثبت کن.
   • شاخهٔ کاری همین شاخهٔ arena است؛ PR فقط از همین شاخه به main.

۳) تمپلیت مستر — شکلی که هر صفحهٔ تازه دارد
   • شش بخش اجباری با نشانگر: intro، map، history، people، economy، stay
       <!-- sa:sec:intro --> … <!-- /sa:sec:intro -->  (و همین شکل برای بقیه)
     بخش notables اختیاری است (اگر نام‌آورها راستی‌آزمایی نشدند، تیتر خالی نگذار).
   • سه چیز بازنشسته است و هرگز برنمی‌گردد: بخش «چند نکتهٔ کوچک» (tips)، بخش اقلیم (climate)
     و بخش دیدنی‌ها (sights) در بدنه — اقلیم و دیدنی‌ها را بلوک «از تجربهٔ مسافرها»/قالب
     نشان می‌دهد. جدول «شناسنامهٔ کوتاه» و بلوک «درخواست از مردم» هم بازنشسته‌اند.
   • کف حجم: ۹۰۰ واژهٔ بدنه (کشش به ۹۱۰–۹۵۰ بهتر است). پرسش متداول: ۱۰ پرسش با پاسخ محکم؛
     پرسشی که پاسخ منبع‌دار ندارد کامل حذف می‌شود.
   • منابع: بلوک منابع کار قالب است؛ sa_sources حداقل دو دامنهٔ مستقل و حداکثر یک خط ویکی‌پدیا.
   • مختصات تمیز داخل مرزهای ایران (نقشه را قالب می‌کشد؛ نقشه در بدنه نگذار).
   • فهرست کلیدهای اجباری متا: sa_city_slug, lat, lng, sa_cty_center, sa_cty_population,
     sa_cty_census_year, sa_cty_neighbors, sa_access_road, sa_access_rail, sa_access_air,
     sa_seo_title, sa_seo_description, sa_focus_keyword, sa_og_title, sa_og_description,
     sa_faq, sa_map_zoom, sa_schema_contained_in, sa_schema_sameas — به‌علاوهٔ
     sa_cty_area_km2, sa_cty_divisions, sa_cty_climate, sa_cty_language, sa_cty_livelihood,
     sa_cty_poi_*, sa_cty_poi_keys, season_* .

۴) قوانین سخت (تخلف = برگشت کار، نه هشدار)
   منابع
   • مجاز (فهرست ثابت مالک): irib، fars، yjc، tabnak، amar.org.ir (سرشماری ۱۳۹۵)، inja، iau،
     به‌علاوهٔ سایت فرمانداری/شهرداری همان شهرستان. خبرگزاری دیگر فقط با اجازهٔ صریح مالک.
   • ممنوع به‌عنوان منبع چاپ‌شدنی: هر چیزی روی wikipedia.org / wikidata.org / wikimedia.org
     (فقط یک خط تعریفی در sa_sources، و آن هم وقتی منبع رسمی نیست)، و پورتال‌های سفر:
     kojaro, koohbist, irantripadviser, karnaval, alibaba, lastsecond, flytoday, lahzeakhar,
     eligasht, snapptrip, jabama, utravs, mrbilit, safarmarket, tripyar, gardeshgari724,
     seeiran, anyja, otaghak, shab, mihmansho, pinorest, hamsafar, offdecor, bartarinha,
     namnak, beytoote.
   • در بدنه هیچ http/https چاپ نمی‌شود و هیچ ارجاع عددی به منبع ممنوع نمی‌خورد.

   ارقام و متا
   • جمعیت = جمعیت خودِ شهر/شهرستانِ همان صفحه در سرشماری ۱۳۹۵ (نه استان، نه شهر دیگر).
   • مساحت به کیلومتر مربع، تعداد بخش/شهر/دهستان، مرکز شهرستان، همسایه‌ها.
   • sa_cty_neighbors فقط شهرستان است. دریا، استان و کشور در این فهرست نمی‌آید
     (محمودآباد: «دریای خزر» برداشته شد).
   • schema: sa_schema_contained_in باید نشانی استان باشد، sa_schema_sameas باید نشانی واقعی
     ویکی‌مدیا (نه «…/wiki/___») — این دو جای تاریخی خطا بوده‌اند.

   تصاویر
   • سه تصویر برای هر شهر. فقط میزبانی ویکی‌مدیا با نشانی اصلی upload.wikimedia.org
     (نسخهٔ thumb روی thumb.wikimedia.org خطای ۵۰۲ می‌دهد؛ استفاده نکن).
   • مجوز فقط cc0، pd، cc-by-2/3/4، cc-by-sa-2/3/4؛ مجوز FAL یا « fair use» رد است.
   • نام عکاس و نشانی صفحهٔ فایل و آدرس مجوز اجباری است. هیچ عکس خبرگزاری یا گوگل‌مپ.
   • مجوز و نام عکاس را از extmetadata همان فایل در کامانز بخوان؛ پارامتر titles= فایل
     ناموجود را بی‌صدا حذف می‌کند، پس وجود و موضوع تصویر را جدا تأیید کن
     (نمونهٔ واقعی: File:Ahar Cenotaphs.jpg در واقع اودایپور هند است).

   لایهٔ «از تجربهٔ مسافرها» (متای همان نوشته، نه بدنه)
   • ۴ واقعیت سریع: کیفیت جاده، اینترنت موبایل، سختی دسترسی، پارکینگ.
   • ۴ فصل؛ ۵ تا ۸ نکتهٔ بومی در سه دستهٔ هشدار/فرهنگ/لجستیک؛ ۶ جای بیشتر؛ منش مردم (مثبت)؛
     کد پلاک؛ field_update_note؛ updated_at.
   • نقل‌قول مردم فقط عیناً، با نام نویسنده و تاریخ دیدن (مثال درست: «۲۶ آبان ۱۴۰۵»). بازنویسی
     یا paraphrase ممنوع؛ اگر عین متن نیست، نکته را به‌عنوان برداشت تحریریه بنویس و در
     field_update_note صریح بگو بازدید میدانی تازه انجام نشده.

   زبان و املا
   • واژه‌های ممنوع: «باید»، «راستش»، «به‌زودی»، «در حال ساخت»، «در حال احداث»،
     «در حال تکمیل»، «در حال نهایی»، «در حال اجرا»، «کلنگ»، «آمادهٔ افتتاح»، «هنوز کامل نیست»،
     «منبع معتبر»، «فاقد منبع»، «بدون منبع»، «تأیید نمی‌شود»، «بررسی بیشتر»، «اسرائیل»،
     و هر اشاره به هوش مصنوعی/بازبینی انسانی/متادیتا.
     (فهرست رسمی: tools/county-insights/seed.py → BANNED، و tools/wp-agent/replace_src/fa_lint.py)
   • نام ماه میلادی در متن فارسی نمی‌آید؛ تاریخ‌ها خورشیدی. نیم‌فاصله درست، حروف عربی
     (ي/ك) نه، نویسهٔ نامرئی نه، فاصله پیش از نشانهٔ نگارش نه.
   • واژهٔ لاتین فقط در فهرست مجاز (km، 4G، GPS، UNESCO…) و داخل
     <span class="sa-photo-credit-latin"> برای نام عکاس.

۵) نوبت شهرها — دستهٔ ۹ (ده شهرستان، همین ترتیب)
   ۱ nur            534  مازندران
   ۲ talesh         454  گیلان
   ۳ masal          466  گیلان
   ۴ sarein         142  اردبیل
   ۵ jolfa          105  آذربایجان شرقی
   ۶ marvdasht      324  فارس
   ۷ kazerun        325  فارس
   ۸ jahrom         326  فارس
   ۹ larestan       327  فارس
   ۱۰ ganaveh       203  بوشهر
   قاعدهٔ نوبت: ترتیب صف از tools/wp-agent/template_worklist.json می‌آید (ستون rank)، منهای
   شهرستان‌هایی که در دسته‌های ۱–۸ بسته شده‌اند. دستهٔ ۸ ردیف‌های ۷۱ تا ۸۰ و ۸۱ (محمودآباد)
   را برد؛ پس دستهٔ ۹ از ردیف ۸۲ شروع می‌شود. پیش‌نمایش دستهٔ ۱۰ (ردیف ۹۲ تا ۱۰۱):
   asaluyeh, mahshahr, abadan, khorramshahr, torbat-heydarieh, quchan, shushtar, behbahan,
   izeh, sirjan. اگر مالک گفت «شهرهای بزرگ اول»، آن مسیر جداست: ۳۷ صفحهٔ «فقط بلوک» بدون
   بازنویسی (تهران، مشهد، تبریز، اصفهان، شیراز، کرج، قم، کرمانشاه، ارومیه، زاهدان…) و برایشان
   یک دستهٔ ده‌تایی و یک نسخهٔ قالب لازم است — بدون دستور مالک شروع نکن.

۶) خط تولید هر دسته (همان مسیر دستهٔ ۸، بدون اختراع مسیر تازه)
   گام ۰ — خواندن زندهٔ ده صفحه:
     فایل کار با ده گام {"cmd":"get","id":<id>} بساز → push → نتیجه در results/
   گام ۱ — مشخصات نویسنده برای هر شهر: tools/wp-agent/replace_src/batch9/<slug>.json
     (شعار، پاراگراف‌های شش بخش، فهرست‌ها، منابع، پرسش‌ها، meta_overrides)
   گام ۲ — ساخت داده و صفحه:
     python3 tools/wp-agent/replace_src/build_batch7.py --content batch9 --read results/<read>.json <slug>
     python3 tools/wp-agent/replace_src/county_page.py <slug>
     (خروجی سه فایل در tools/wp-agent/outbox/20261117-replace-<slug>/)
   گام ۳ — گیت‌ها قبل از هر push:
     python3 tools/wp-agent/replace_src/check_template.py tools/wp-agent/outbox/20261117-replace-<slug>
     python3 tools/wp-agent/replace_src/fa_lint.py tools/wp-agent/outbox/20261117-replace-<slug>
     python3 - <<'PY'  # فهرست واژه‌های ممنوع seed.py روی همان پوشه
     ...
     PY
     هدف: 10/10 «pages conform to the county template» و ۰ ایراد.
   گام ۴ — کار نوشتن (پنج شهر اول، بعد پنج شهر دوم) با content_file + meta_file +
     expected_modified_gmt از نتیجهٔ گام ۰ + owner_approved_live_edit: true، سپس
     cache-purge و probe. تست ایمنی قبل از push: python3 tools/wp-agent/test_wp_agent_jobs.py
   گام ۵ — تصاویر: tools/photos/<batch>.json با سه رکورد برای هر شهر (city_id, place, kind,
     url, author, license, license_url, source_page, alt, note, points, highlight) →
     کار با گام photos-import + cache-purge + probe. تست: test_import_photos.py
   گام ۶ — لایهٔ تجربه: یک فایل متا برای هر شهر در
     tools/wp-agent/outbox/20261117-experiences-batch9/<slug>.meta.json و کار با گام
     update فقط با meta_file (بدنه دست نمی‌خورد) + cache-purge + probe.
   گام ۷ — بستن دسته: ده ردیف در tools/wp-agent/county_ledger.json با
     python3 tools/wp-agent/county_ledger.py set <slug> --authority "…" --note "…"
     بعد python3 tools/wp-agent/county_ledger.py build و check؛ یک ردیف در
     docs/live-site/CHANGELOG.md؛ بخش «پایان دستهٔ ۹» در HANDOFF-next-session.md؛
     سند docs/live-site/batch9-template-diff.md؛ سپس PR از همین شاخه.

۷) تعریف «انجام‌شده» برای هر شهر
   بدنهٔ شش‌بخشی ≥۹۰۰ واژه با نشانگر · ۱۰ پرسش · ≥۲ دامنهٔ منبع و ≤۱ خط ویکی‌پدیا ·
   متای کامل و جمعیت/مساحت/همسایهٔ درست · ۳ عکس مجوزدار با نام عکاس · بلوک تجربه با
   facts 4 / seasons 4 / places 6 / tips 5–8 · probe وضعیت ۲۰۰ با experience_block 1 و
   photo_spotlight 1 · شمارهٔ پشتیبان‌ها ثبت‌شده.

۸) تله‌های ثبت‌شده — تکرارشان نکن
   • seed.py write امن نیست: رکورد اهواز و اردبیل مستقیم در data/county-experiences.php
     ویرایش شده‌اند و در data/raw عقب‌ترند؛ نوشتن دوبارهٔ فایل آن‌ها را عقب می‌برد. تا
     هم‌ترازشدن، لایهٔ تجربه را فقط با متای صفحه منتشر کن.
   • برای اضافه‌کردن متن تجربه، بدنه را دوباره نساز.
   • probe فقط اولین photo_credit_texts را چاپ می‌کند؛ از آن نتیجه نگیر که هر سه عکس درست است.
   • صفحهٔ جست‌وجوی گوگل‌مپ مرکز نقشهٔ بی‌ربط برمی‌گرداند؛ فقط مختصات کارت مکان معتبر است.
   • ادغام شاخه: git merge origin/arena/<...> (بدون پیشوند arena کار نمی‌کند).
   • کف واژه: بالا بردن ۸۴۱–۸۷۵ واژه با یک پاراگراف جواب نمی‌دهد؛ دو پاراگراف در هر پاس.

۹) بدهی باز که به تو مربوط است
   • هم‌ترازکردن data/raw اهواز و اردبیل با متن زنده، بعد ساخت دوبارهٔ
     data/county-experiences.php در یک ارتقای نسخهٔ قالب.
   • تأیید کامل سه اعتبار عکس هر صفحه (probe فقط اولی را نشان می‌دهد).
```

---

## راهنمای کوتاه برای خودت (بیرون کدباکس)

| مورد | مقدار |
|---|---|
| مخزن | `sarzaminaryan-arch/S.A.1` — شاخهٔ کاری `arena/4b76b6ae-s-a-1` |
| آخرین وضعیت | دستهٔ ۸ بسته و مرج‌شده (PR #66، کامیت `ff26abe`) |
| دستهٔ ۹ | `nur 534` · `talesh 454` · `masal 466` · `sarein 142` · `jolfa 105` · `marvdasht 324` · `kazerun 325` · `jahrom 326` · `larestan 327` · `ganaveh 203` |
| دستهٔ ۱۰ (پیش‌نمایش) | `asaluyeh 206` · `mahshahr 278` · `abadan 269` · `khorramshahr 281` · `torbat-heydarieh 66` · `quchan 68` · `shushtar 288` · `behbahan 279` · `izeh 275` · `sirjan 490` |
| باقی‌ماندهٔ صف | ۴۰۲ صفحه پس از پایان دستهٔ ۸ |
