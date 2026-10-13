#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""اصلاح‌های نسخهٔ ۲٫۱۸٫۲ روی data/ready-articles.php (موج چهارم ممیزی).

یک‌بارمصرف است: روی درختِ اعمال‌شده دوباره اجرا نشود (assert اول بخش A می‌شکند).
برای بازتولید: ابتدا فایل داده را به نسخهٔ پیش از ۲٫۱۸٫۲ برگردانید (`git checkout <ref> -- data/ready-articles.php`)
و سپس این اسکریپت را اجرا کنید.

هر بخش با شمارش و assert کار می‌کند؛ هیچ جایگزینی بی‌تطبیق انجام نمی‌شود.
بخش‌ها:
  A) نامک فارسی «6789-باغ-گلشن-طبس» → «bagh-golshan-tabas» (کلید + slug + پیوندهای ورودی)
  B) متای دوگانهٔ rank_math_description → هم‌مقدار با sa_seo_description (۸ رکورد)
  C) کوتاه‌سازی دو توضیح متای بلندتر از ۱۶۰ نویسه
  D) اصلاح «جاذبه‌های مرتبط» بی‌ربط در سه مقاله (گلستان، قشم، انزلی)
  E) افزودن پیوند ورودی به مقاله‌های بی‌پیوند (دورود، کوهرنگ، تنگ صیاد، شهداد، باغ فین، ماسوله)
  F) دو پیوند ورودی تازه برای رکوردهای تودرتو (ریگ جن، قلعه دختر بشرویه)
"""
import io, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
ART = os.path.join(ROOT, 'wp-content', 'themes', 'sarzaminaryan-child', 'data', 'ready-articles.php')
TESTS = os.path.join(ROOT, 'wp-content', 'themes', 'sarzaminaryan-child', 'tests', 'test-internal-links.php')

DRY = '--dry' in sys.argv
log = []


def read(p):
    return io.open(p, encoding='utf-8').read()


def write(p, s):
    if not DRY:
        io.open(p, 'w', encoding='utf-8').write(s)


def sub(text, old, new, n, label):
    hits = text.count(old)
    assert hits == n, '%s: %d تطبیق (انتظار %d) — %r' % (label, hits, n, old[:70])
    log.append('%-46s ×%d' % (label, hits))
    return text.replace(old, new)


def record(text, key):
    m = re.search(r"\n\t'%s'\s*=>\s*array\(" % re.escape(key), text)
    assert m, 'رکورد پیدا نشد: ' + key
    end = text.find("\n\t'", m.end() + 10)
    return m.start(), (end if end > 0 else len(text))


def sub_in(text, key, old, new, n, label):
    """جایگزینی محدود به یک رکورد (برای متن‌های تکرارشده در چند مقاله)."""
    i, j = record(text, key)
    seg = text[i:j]
    hits = seg.count(old)
    assert hits == n, '%s [%s]: %d تطبیق (انتظار %d)' % (label, key, hits, n)
    log.append('%-46s ×%d' % (label, hits))
    return text[:i] + seg.replace(old, new) + text[j:]

A = read(ART)

# ── A) نامک 6789 ────────────────────────────────────────────────
OLDK, NEWK = '6789-باغ-گلشن-طبس', 'bagh-golshan-tabas'
i, j = record(A, OLDK)
seg = A[i:j]
assert seg.count("'slug'") == 1 and "'slug'" in seg
seg2 = re.sub(r"'slug'(\s*)=>(\s*)'%s'" % re.escape(OLDK), lambda m: "'slug'%s=>%s'%s'" % (m.group(1), m.group(2), NEWK), seg)
assert seg2.count(OLDK) == 1 and seg2.count("'%s'" % NEWK) == 1, 'A: نامک slug جابه‌جا نشد'
A = A[:i] + seg2 + A[j:]
A = sub(A, "\n\t'%s' => array(" % OLDK, "\n\t'%s' => array(" % NEWK, 1, 'A1 کلید رکورد')
A = sub(A, 'href="/attraction/%s/"' % OLDK, 'href="/attraction/%s/"' % NEWK, 2, 'A2 پیوندهای ورودی نامک')

# ── A3) یکسان‌سازی slug «شهرستان دورود» با کلید (key=slug) ───────
i, j = record(A, 'dorud-city')
seg = A[i:j]
assert seg.count("'slug'         => 'dorud',") == 1, 'A3: slug دورود پیدا نشد'
seg = seg.replace("'slug'         => 'dorud',", "'slug'         => 'dorud-city',")
A = A[:i] + seg + A[j:]
log.append('%-46s ×1' % 'A3 یکسان‌سازی slug دورود')

# ── A4) سه رکورد زندهٔ شناسه‌دار → نامک‌های معنادار ───────────────
RENAMES = {
    '6976-ارامگاه-قاسم-انوار': 'qasem-anvar-shrine',
    '26950-کویر-ریگ-جن': 'rig-e-jenn-desert',
    '6619-قلعه-دختر-بشرویه': 'ghaleh-dokhtar-boshruyeh',
}
for old_key, new_key in RENAMES.items():
    i, j = record(A, old_key)
    seg = A[i:j]
    assert seg.count("'slug'         => '%s'," % old_key) == 1, 'A4: slug پیدا نشد ' + old_key
    seg = seg.replace("'slug'         => '%s'," % old_key, "'slug'         => '%s'," % new_key)
    A = A[:i] + seg + A[j:]
    A = sub(A, "\n\t'%s' => array(" % old_key, "\n\t'%s' => array(" % new_key, 1, 'A4 نامک تازه %s' % new_key)

# ── B) متای دوگانه ──────────────────────────────────────────────
DUAL = ['sar-agha-seyed-village', 'tang-e-sayad-national-park', 'tabas-attractions',
        'persepolis-takht-jamshid', 'naqsh-e-jahan-square-isfahan', 'shahdad-kaluts-lut-desert',
        'fin-garden', 'masouleh',
        'qasem-anvar-shrine', 'rig-e-jenn-desert', 'ghaleh-dokhtar-boshruyeh',
        'bagh-golshan-tabas']
for k in DUAL:
    i, j = record(A, k)
    seg = A[i:j]
    sa = re.search(r"'sa_seo_description'\s*=>\s*(\"[^\"]*\"|'[^']*')", seg)
    rm = re.search(r"'rank_math_description'\s*=>\s*(\"[^\"]*\"|'[^']*')", seg)
    assert sa and rm, 'B: متای دوگانه پیدا نشد: ' + k
    if sa.group(1) == rm.group(1):
        continue
    seg2 = seg[:rm.start(1)] + sa.group(1) + seg[rm.end(1):]
    A = A[:i] + seg2 + A[j:]
    log.append('%-46s %s' % ('B هم‌مقدارسازی متا', k))

# ── C) کوتاه‌سازی متای بلندتر از ۱۶۰ ─────────────────────────────
LONG = {
    'seo_desc_alisadr': (
        'غار علیصدر در همدان با مسیر گردشگریِ قایق‌رانی و تالارهای آهکی شناخته می‌شود؛ مجتمع گردشگری آن را بزرگ‌ترین غار آبی قابل قایق‌رانی جهان معرفی می‌کند. راهنمای مسیر و نکات ایمنی را بخوانید.',
        'غار علیصدر در همدان با مسیر قایق‌رانی و تالارهای آهکی شناخته می‌شود؛ مجتمع آن را بزرگ‌ترین غار آبی قابل قایق‌رانی جهان معرفی می‌کند. راهنمای مسیر و نکات ایمنی.',
    ),
    'seo_desc_aznadar': (
        'آبشار فصلی ازنادر در دره اسپر دورود؛ مقصدی بهاری با جنگل‌های بلوط، چشمه‌های جوشان و مسیر پیاده‌روی یک‌ساعته. راهنمای مسیر دسترسی، بهترین زمان بازدید و نکات ایمنی.',
        'آبشار فصلی ازنادر در دره اسپر دورود؛ مقصدی بهاری با جنگل‌های بلوط، چشمه‌های جوشان و مسیر پیاده‌روی یک‌ساعته. راهنمای مسیر دسترسی و نکات ایمنی.',
    ),
}
for var, (old, new) in LONG.items():
    assert len(old) > 160 and len(new) <= 160, 'C: طول نادرست %s (%d→%d)' % (var, len(old), len(new))
    A = sub(A, "'%s'" % old, "'%s'" % new, 1, 'C کوتاه‌سازی %s (%d→%d)' % (var, len(old), len(new)))

# ── D) «جاذبه‌های مرتبط» بی‌ربط ───────────────────────────────────
D = [
    # گلستان: دریاچه گهر (لرستان) → درّهٔ ستارگان قشم (میراث طبیعی، ژئوپارک جهانی)
    ('golestan-national-park-guide',
     '<a href="/attraction/gahar-lake-dorud/">دریاچه گهر</a>',
     '<a href="/attraction/qeshm-stars-valley-geopark/">درّهٔ ستارگان قشم</a>'),
    ('golestan-national-park-guide',
     '؛ نمونه‌ای دیگر از طبیعت دست‌نخورده ایران',
     '؛ ژئوپارک جهانی و نمونه‌ای دیگر از میراث طبیعی ایران'),
    # قشم: لالجین → کلوت‌های شهداد (زمین‌چهر فرسایشی)، فلک‌الافلاک → باغ فین (میراث ثبت‌شده)
    ('qeshm-stars-valley-geopark',
     '<a href="/attraction/lalejin-pottery/">لالجین</a>',
     '<a href="/attraction/shahdad-kaluts-lut-desert/">کلوت‌های شهداد</a>'),
    ('qeshm-stars-valley-geopark',
     '؛ سفال و صنایع دستی، از تهران تا همدان مسیر هویت ایرانی',
     '؛ زمین‌چهرهای فرسایشی کویر لوت برای مقایسه با کال‌های قشم'),
    ('qeshm-stars-valley-geopark',
     '<a href="/attraction/falak-ol-aflak-castle-khorramabad/">قلعه فلک‌الافلاک</a>',
     '<a href="/attraction/fin-garden/">باغ فین کاشان</a>'),
    ('qeshm-stars-valley-geopark',
     '؛ نگاهی به معماری و تاریخ ایران در سفر جاده‌ای',
     '؛ نمونه‌ای دیگر از میراث ثبت‌شدهٔ ایران در فهرست یونسکو'),
    # انزلی: دریاچه گهر → تالاب ازگن (تالاب کوهستانی برای مقایسه)، حذف دماوندِ بی‌ربط،
    # حفظ علی‌صدر (نمونهٔ طبیعی) و افزودن ماسوله (همان استان گیلان)
    ('anzali-international-wetland-guide',
     '<a href="/attraction/gahar-lake-dorud/">دریاچه گهر</a>',
     '<a href="/attraction/azgan-wetland-dorud/">تالاب ازگن (قارون)</a>'),
    ('anzali-international-wetland-guide',
     '؛ مقصدی دیگر برای دوست‌داران آب و کوهستان',
     '؛ تالاب کوهستانی برای مقایسه با تالاب ساحلی انزلی'),
    ('anzali-international-wetland-guide',
     '\n<li><a href="/attraction/mount-damavand-guide/">قله دماوند</a>؛ بلندترین اثر طبیعی ملی ایران</li>',
     ''),
    ('anzali-international-wetland-guide',
     '<li><a href="/attraction/ali-sadr-cave-hamedan-guide/">غار علیصدر</a>؛ غار آهکی با بخشی از مسیر گردشگریِ قایق‌رانی</li>',
     '<li><a href="/attraction/ali-sadr-cave-hamedan-guide/">غار علیصدر</a>؛ غار آهکی با بخشی از مسیر گردشگریِ قایق‌رانی</li>\n<li><a href="/attraction/masouleh/">ماسوله</a>؛ روستای پلکانی گیلان در همان استان، برای ادامهٔ سفر شمال</li>'),
]
for key, old, new in D:
    A = sub_in(A, key, old, new, 1, 'D مرتبط‌سازی')

# ── E) پیوند ورودی مقاله‌های بی‌پیوند ────────────────────────────
# ۱) گلستان: پارک ملی تنگ صیاد
A = sub(
    A,
    '<li><a href="/attraction/anzali-international-wetland-guide/">تالاب بین‌المللی انزلی</a>؛ دیدنی‌ها دیگر شمال ایران</li>',
    '<li><a href="/attraction/anzali-international-wetland-guide/">تالاب بین‌المللی انزلی</a>؛ دیدنی‌ها دیگر شمال ایران</li>\n<li><a href="/attraction/tang-e-sayad-national-park/">پارک ملی تنگ صیاد</a>؛ نمونه‌ای دیگر از پارک‌های ملی ایران</li>',
    1, 'E1 ورودی تنگ صیاد (گلستان)')
# ۲) نی‌گاه: شهرستان دورود
A = sub(
    A,
    '<li><a href="/attraction/shevi-waterfall-tele-zang/">آبشار شوی (تله‌زنگ)</a>؛ آبشاری در خوزستان با مسیر ریلیِ شناخته‌شده از ایستگاه تله‌زنگ.</li>',
    '<li><a href="/attraction/shevi-waterfall-tele-zang/">آبشار شوی (تله‌زنگ)</a>؛ آبشاری در خوزستان با مسیر ریلیِ شناخته‌شده از ایستگاه تله‌زنگ.</li>\n<li><a href="/attraction/dorud-city/">شهرستان دورود</a>؛ راهنمای خودِ شهر و مقصدهای نزدیکِ همین خوشهٔ سفر.</li>',
    1, 'E2 ورودی دورود (نی‌گاه)')
# ۳) سرآقا سید: دشت لاله‌های واژگون کوهرنگ
A = sub(
    A,
    'راهنمای <a href="/province/chaharmahal-bakhtiari/">استان چهارمحال‌وبختیاری</a> را ببینید. فاصله‌ها در این ناحیه کوهستانی‌اند',
    'راهنمای <a href="/province/chaharmahal-bakhtiari/">استان چهارمحال‌وبختیاری</a> و <a href="/attraction/kuhrang-fritillaria-plain-guide/">دشت لاله‌های واژگون کوهرنگ</a> را ببینید. فاصله‌ها در این ناحیه کوهستانی‌اند',
    1, 'E3 ورودی دشت لاله‌ها (سرآقا سید)')
# ۴) شهداد: کویر ریگ جن
A = sub(
    A,
    'طبس در استان خراسان جنوبی است و سفر به آن باید با برآورد جداگانهٔ مسافت و وضعیت جاده برنامه‌ریزی شود.</p>',
    'طبس در استان خراسان جنوبی است و سفر به آن باید با برآورد جداگانهٔ مسافت و وضعیت جاده برنامه‌ریزی شود. اگر پهنه‌های ماسه‌ای دیگر را هم می‌پسندید، <a href="/attraction/rig-e-jenn-desert/">کویر ریگ جن</a> در مرز سمنان و اصفهان مقصد بیابانی دیگری است که برنامه و مجوز جداگانه می‌خواهد.</p>',
    1, 'E4 ورودی ریگ جن (شهداد)')
# ۵) طبس: قلعه دختر بشرویه
A = sub(
    A,
    'محیط‌زیست شهرستان استعلام کنید.</li>\n</ul>',
    'محیط‌زیست شهرستان استعلام کنید.</li>\n</ul>\n<h2>ادامهٔ مسیر در خراسان جنوبی</h2>\n<p>اگر برنامه‌ات چندروزه است و به سمت شمال شهرستان می‌روی، <a href="/attraction/ghaleh-dokhtar-boshruyeh/">قلعه دختر بشرویه</a> بنای خشتی دیگری در همین استان است که صعود به آن آمادگی و کفش کوهستان می‌خواهد.</p>\n',
    1, 'E5 ورودی قلعه دختر (طبس)')

assert A.count("\n\t'bagh-golshan-tabas' => array(") == 1
write(ART, A)

# ── G2) نگهبان رگرسیون پیوندهای مقالات (فقط اگر نبود) ──────────
GUARD_MARK = 'v2.18.2 — نگهبانِ پیوندهای مقالات دیدنی‌ها'
T = read(TESTS)
if GUARD_MARK not in T:
    PASS_OLD = 'fwrite( STDOUT, "PASS: internal geographic auto-link and rewritten-article link tests\\n" );'
    GUARD = r"""/**
 * v2.18.2 — نگهبانِ پیوندهای مقالات دیدنی‌ها:
 *  ۱) کلیدِ هر رکورد باید همان نامک باشد (قاعدهٔ آزمون راهنماها)؛
 *  ۲) هیچ پیوند داخلیِ دیدنی در بدنه نباید به مقصدی برود که رکورد آمادهٔ آن وجود ندارد
 *     (پیوندهای ۴۰۴ پیشین در ۲٫۱۸٫۲ به متن ساده تبدیل شدند).
 */
$ready_article_keys = array_keys( $ready_articles );
foreach ( $ready_articles as $article_key => $article_record ) {
	sa_test_expect( $article_key === $article_record['slug'], 'article key must equal its slug: ' . $article_key );
	sa_test_expect( ! empty( $article_record['content'] ), 'article record must ship content: ' . $article_key );
	preg_match_all( '~href="(/attraction/[^"#?]+)/"~', (string) $article_record['content'], $attraction_hrefs );
	foreach ( array_unique( $attraction_hrefs[1] ) as $attraction_href ) {
		$target_key = substr( trim( $attraction_href, '/' ), strlen( 'attraction/' ) );
		sa_test_expect(
			in_array( $target_key, $ready_article_keys, true ),
			'article ' . $article_key . ' links to an attraction without a prepared record: ' . $attraction_href
		);
	}
}

fwrite( STDOUT, "PASS: internal geographic auto-link, rewritten-article link and prepared-record link tests\n" );"""
    assert T.count(PASS_OLD) == 1, 'G2: خط PASS پیدا نشد'
    T = T.replace(PASS_OLD, GUARD)
    write(TESTS, T)
    log.append('%-46s ×1' % 'G2 نگهبان رگرسیون')

# ── G) انتظار آزمون ────────────────────────────────────────────
T = read(TESTS)
T = sub(T, "'/attraction/%s/'" % OLDK, "'/attraction/%s/'" % NEWK, 1, 'G انتظار آزمون پیوند')
write(TESTS, T)

# ── H) تبدیل پیوندهای نامعلوم به متن ساده (ابزار همراه) ─────────
import subprocess
tool = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'unlink-unknown-links.py')
if not DRY:
    out = subprocess.run([sys.executable, tool, '--apply'], capture_output=True, text=True)
    print(out.stdout.strip())
    chk = subprocess.run([sys.executable, tool], capture_output=True, text=True).stdout.splitlines()[0]
    assert '0 نشانی یکتا' in chk, 'H: پیوند نامعلوم باقی مانده — ' + chk
    log.append('%-46s ✓ بازبینی صفر' % 'H تبدیل پیوند نامعلوم')

print('== ۲٫۱۸٫۲ ==')
for line in log:
    print('  ✓', line)
print('حالت:', 'خشک (بدون نوشتن)' if DRY else 'اعمال شد')
