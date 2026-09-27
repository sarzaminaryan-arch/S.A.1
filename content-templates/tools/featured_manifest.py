#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Builds assets/featured/provinces/manifest.json + README.md from the alt/caption table below
and the actual WEBP files on disk (dimensions/bytes are read with ImageMagick `identify`).

Usage:  python3 content-templates/tools/featured_manifest.py
"""
import json, os, subprocess, sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
IMG_DIR = os.path.join(ROOT, 'assets', 'featured', 'provinces')
PROVINCES_PHP = os.path.join(ROOT, 'wp-content', 'themes', 'sarzaminaryan-child', 'data', 'provinces.php')

GENERATED_JALALI = '۱۴۰۵/۰۷/۰۴'
GENERATED_ISO = '2026-09-26'
SOURCE_PACKAGE = 'imagetool (14).zip — commit a8a4fa1 روی main (۳۱ فایل JPG، ۲۴ تصویر یکتا) + imagetool (15).zip — commit e48e84b روی main (۷ فایل JPG، ۷ استان باقی‌مانده)'
CREDIT = 'تصویرسازی: سرزمین آریان'

# slug -> (source file id, alt, symbols, watermark_fixed)
IMAGES = {
    'tehran': ('1000099786',
        'تصویرسازی استان تهران با برج میلاد، برج آزادی، خط آسمان شهر، رشته‌کوه برفی البرز و انار در پیش‌زمینه',
        ['برج میلاد', 'برج آزادی', 'رشته‌کوه البرز', 'انار'], False),
    'hormozgan': ('1000099787',
        'تصویرسازی استان هرمزگان با ستاره‌ی دریایی، رقص محلی بندری، دلفین‌های در حال پرش، لنج و نخل‌های خلیج فارس',
        ['ستاره‌ی دریایی', 'رقص بندری', 'دلفین', 'لنج', 'نخل'], True),
    'markazi': ('1000099789',
        'تصویرسازی استان مرکزی با خانه‌ی تاریخی آجری و کاشی‌کاری، قالی دستباف و باغ‌های میوه',
        ['خانه‌ی تاریخی آجری', 'قالی دستباف', 'باغ میوه'], False),
    'mazandaran': ('1000099790',
        'تصویرسازی استان مازندران با جنگل‌های سرسبز هیرکانی، قله‌ی برفی دماوند، ساحل دریای خزر و مرکبات شمال',
        ['جنگل هیرکانی', 'دماوند', 'دریای خزر', 'مرکبات'], False),
    'golestan': ('1000099792',
        'تصویرسازی استان گلستان با جنگل و رودخانه در کنار تپه‌های ماسه‌ای ترکمن‌صحرا و دو اسب ترکمن',
        ['جنگل', 'رودخانه', 'تپه‌های ماسه‌ای', 'اسب ترکمن'], True),
    'kohgiluyeh-boyer-ahmad': ('1000099793',
        'تصویرسازی استان کهگیلویه و بویراحمد با آبشار، جنگل بلوط زاگرس، سیاه‌چادر عشایری و گلیم دستباف',
        ['آبشار', 'بلوط زاگرس', 'سیاه‌چادر', 'گلیم'], True),
    'kermanshah': ('1000099794',
        'تصویرسازی استان کرمانشاه با طاق‌بستان، کتیبه‌ی میخی بیستون و یک جفت گیوه‌ی دست‌باف',
        ['طاق‌بستان', 'کتیبه‌ی بیستون', 'گیوه'], True),
    'kerman': ('1000099795',
        'تصویرسازی استان کرمان با کلوت‌های بیابان لوت، مسجدی با کاشی‌کاری فیروزه‌ای و پسته‌های تازه',
        ['کلوت‌های لوت', 'مسجد کاشی‌کاری', 'پسته'], True),
    'qazvin': ('1000099797',
        'تصویرسازی استان قزوین با بنایی گنبددار و کاشی‌کاری آبی، دروازه‌ی آجری تاریخی و انارهای رسیده',
        ['بنای گنبددار', 'دروازه‌ی آجری', 'انار'], False),
    'ardabil': ('1000099798',
        'تصویرسازی استان اردبیل با قله‌ی برفی سبلان، گنبد و مناره‌های بقعه‌ی شیخ صفی‌الدین و شیشه‌ی عسل سبلان',
        ['سبلان', 'بقعه‌ی شیخ صفی‌الدین', 'عسل سبلان'], False),
    'bushehr': ('1000099799',
        'تصویرسازی استان بوشهر با ساحل خلیج فارس، بادگیرهای بافت قدیم، لنج‌های رنگارنگ و نخل‌های خرما',
        ['خلیج فارس', 'بادگیر', 'لنج', 'نخل خرما'], True),
    'fars': ('1000099800',
        'تصویرسازی استان فارس با ستون‌های تخت جمشید، آرامگاه حافظ، آرامگاه کوروش، درختان نارنج و ظرف میوه روی قالی',
        ['تخت جمشید', 'آرامگاه حافظ', 'آرامگاه کوروش', 'نارنج', 'قالی'], False),
    'gilan': ('1000099802',
        'تصویرسازی استان گیلان با شالیزارهای سبز، تالاب نیلوفرهای آبی، ساحل دریای خزر و خانه‌های چوبی روستایی',
        ['شالیزار', 'تالاب انزلی', 'دریای خزر', 'خانه‌ی چوبی'], True),
    'lorestan': ('1000099803',
        'تصویرسازی استان لرستان با آبشار، قلعه‌ی فلک‌الافلاک، سیاه‌چادر عشایری، تاکستان و گلیم‌های لری در زاگرس',
        ['آبشار', 'قلعه‌ی فلک‌الافلاک', 'سیاه‌چادر', 'تاکستان', 'گلیم لری'], False),
    'kurdistan': ('1000099805',
        'تصویرسازی استان کردستان با روستای پلکانی اورامان، کوه‌های برفی زاگرس، پارچه‌های رنگی کردی، شان عسل و گردو',
        ['روستای پلکانی', 'زاگرس', 'پارچه‌ی کردی', 'عسل', 'گردو'], True),
    'yazd': ('1000099806',
        'تصویرسازی استان یزد با بادگیرها و بافت تاریخی خشتی، مناره‌های مسجد جامع، تپه‌های شنی و شیرینی‌های سنتی یزدی',
        ['بادگیر', 'بافت خشتی', 'مسجد جامع یزد', 'تپه‌های شنی', 'شیرینی یزدی'], True),
    'hamadan': ('1000099807',
        'تصویرسازی استان همدان با آرامگاه بوعلی سینا، غار علیصدر، کتیبه‌ی گنج‌نامه، قله‌ی الوند، سفال لالجین و عسل',
        ['آرامگاه بوعلی سینا', 'غار علیصدر', 'گنج‌نامه', 'الوند', 'سفال لالجین', 'عسل'], True),
    'chaharmahal-bakhtiari': ('1000099809',
        'تصویرسازی استان چهارمحال و بختیاری با کوه‌های زاگرس، آبشار، درختان بلوط، چادرهای عشایری و دشت‌های پرگل',
        ['زاگرس', 'آبشار', 'بلوط', 'چادر عشایری', 'دشت گل'], True),
    'north-khorasan': ('1000099811',
        'تصویرسازی استان خراسان شمالی با دو اسب در حال تاخت، فرش‌های دستباف، زین و دشت‌های خشک',
        ['اسب', 'فرش دستباف', 'زین', 'دشت'], False),
    'khuzestan': ('1000099812',
        'تصویرسازی استان خوزستان با زیگورات چغازنبیل، رود کارون، نخلستان‌ها و دو زن با لباس محلی',
        ['چغازنبیل', 'کارون', 'نخلستان', 'لباس محلی'], True),
    'zanjan': ('1000099813',
        'تصویرسازی استان زنجان با گنبد سلطانیه، غار کتله‌خور و چاقوهای دست‌ساز زنجانی',
        ['گنبد سلطانیه', 'غار کتله‌خور', 'چاقوی زنجان'], True),
    'semnan': ('1000099814',
        'تصویرسازی استان سمنان با تپه‌های شنی کویر، بنای آجری گنبددار با کاشی‌کاری فیروزه‌ای و پسته‌ی دامغان',
        ['کویر', 'بنای آجری گنبددار', 'پسته‌ی دامغان'], True),
    'sistan-baluchestan': ('1000099815',
        'تصویرسازی استان سیستان و بلوچستان با قلعه‌ی خشتی کهن، ساحل دریای عمان و سوزن‌دوزی رنگارنگ بلوچی',
        ['قلعه‌ی خشتی', 'دریای عمان', 'سوزن‌دوزی بلوچی'], False),
    'qom': ('1000099817',
        'تصویرسازی استان قم با گنبد طلایی حرم حضرت معصومه، فرش ابریشمی قم و حاشیه‌ی کویر',
        ['حرم حضرت معصومه', 'فرش قم', 'کویر'], False),
    # ---- بسته‌ی دوم: imagetool (15).zip — هفت استان باقی‌مانده (بدون نشان دامنه؛ نشان به سبک بقیه افزوده شد) ----
    'east-azerbaijan': ('1000099838',
        'تصویرسازی استان آذربایجان شرقی با قله‌ی برفی سهند، کوشک میان دریاچه‌ی ائل‌گلی، جنگل و گنبد کاشی‌کاری مسجد کبود',
        ['سهند', 'ائل‌گلی', 'مسجد کبود'], 'added'),
    'west-azerbaijan': ('1000099831',
        'تصویرسازی استان آذربایجان غربی با دریاچه‌ی صورتی ارومیه و فلامینگوها، قلعه‌ی سنگی کهن و خانه‌ی روستایی خشتی',
        ['دریاچه‌ی ارومیه', 'فلامینگو', 'قلعه‌ی کهن', 'خانه‌ی خشتی'], 'added'),
    'isfahan': ('1000099832',
        'تصویرسازی استان اصفهان با گنبد فیروزه‌ای میدان نقش جهان، باغ چهارباغ با جوی‌های آب و سروها و پل تاریخی زاینده‌رود',
        ['نقش جهان', 'چهارباغ', 'پل زاینده‌رود', 'سرو'], 'added'),
    'alborz': ('1000099833',
        'تصویرسازی استان البرز با دریاچه‌ی سد امیرکبیر کرج، قله‌های برفی رشته‌کوه البرز و شکوفه‌های بهاری',
        ['سد امیرکبیر', 'رشته‌کوه البرز', 'شکوفه‌ی بهاری'], 'added'),
    'ilam': ('1000099834',
        'تصویرسازی استان ایلام با کوه‌های زاگرس، قلعه‌ی سنگی کهن، چادرهای عشایری و دشت گل‌های وحشی',
        ['زاگرس', 'قلعه‌ی کهن', 'چادر عشایری', 'گل‌های وحشی'], 'added'),
    'razavi-khorasan': ('1000099836',
        'تصویرسازی استان خراسان رضوی با گنبد طلایی و مناره‌های فیروزه‌ای حرم، رواق کاشی‌کاری و تپه‌های شنی کویر',
        ['گنبد طلایی حرم', 'کاشی‌کاری', 'کویر'], 'added'),
    'south-khorasan': ('1000099839',
        'تصویرسازی استان خراسان جنوبی با مزرعه‌ی زعفران، قلعه‌ی خشتی کهن، نخل‌های خرما و کوه‌های سرخ کویری',
        ['زعفران', 'قلعه‌ی خشتی', 'نخل خرما', 'کوه‌های کویری'], 'added'),
}

# exact byte-duplicates inside the zip (md5): duplicate file -> kept file
DUPLICATES = {
    '1000099791': '1000099790', '1000099821': '1000099790',
    '1000099822': '1000099789', '1000099804': '1000099802',
    '1000099819': '1000099793', '1000099823': '1000099787',
    '1000099820': '1000099792',
}

NOTES = {
    'east-azerbaijan': 'بسته‌ی دوم؛ نشان دامنه نداشت و به سبک بقیه (پیل آبی sarzaminaryan.ir) افزوده شد. کوه برفی مخروطی = سهند، کوشک دریاچه = ائل‌گلی، گنبد کاشی = مسجد کبود.',
    'west-azerbaijan': 'بسته‌ی دوم؛ نشان دامنه افزوده شد. دریاچه‌ی صورتی ارومیه با فلامینگو — با متن مقاله (احیای دریاچه) هم‌خوان است.',
    'isfahan': 'بسته‌ی دوم؛ نشان دامنه افزوده شد.',
    'alborz': 'بسته‌ی دوم؛ نشان دامنه افزوده شد. دریاچه‌ی سد امیرکبیر (کرج) و شکوفه‌های بهاری.',
    'ilam': 'بسته‌ی دوم؛ نشان دامنه افزوده شد.',
    'razavi-khorasan': 'بسته‌ی دوم؛ نشان دامنه افزوده شد. گنبد طلایی/مناره‌های فیروزه‌ای اشاره به حرم رضوی است (تصویرسازی آزاد، نه عکس واقعی).',
    'south-khorasan': 'بسته‌ی دوم؛ نشان دامنه افزوده شد. قاب کاغذ کهنه بخشی از طرح است؛ در برش هیرو ۱۶۰۰×۷۰۰ حاشیه‌ی کاغذ بالا/پایین می‌ماند.',
    'sistan-baluchestan': 'روی تصویر «SISTAN AND BALUCESTAN» نوشته شده (املای درست: BALUCHESTAN) — در بازتولید اصلاح شود.',
    'kohgiluyeh-boyer-ahmad': 'روی تصویر فقط «KOHGILUYEH» نوشته شده (بدون Boyer-Ahmad).',
    'chaharmahal-bakhtiari': 'روی تصویر فقط «CHAHARMAHAL» نوشته شده (بدون Bakhtiari).',
    'hamadan': 'روی تصویر «HAMEDAN» نوشته شده؛ نامک سایت hamadan است (اشکالی ندارد).',
    'golestan': 'ترکیب تپه‌های ماسه‌ای و اسب‌های ترکمن؛ برای گلستان قابل قبول است (ترکمن‌صحرا) اما جنگل ابر/هیرکانی برجسته‌تر بود.',
    'qazvin': 'انار نماد رایج قزوین نیست (پسته و باقلوا رایج‌ترند) — در بازتولید احتمالی توجه شود.',
}


def load_provinces():
    """Ordered list of (slug, name, en, center) parsed from data/provinces.php."""
    import re
    out = []
    txt = open(PROVINCES_PHP, encoding='utf-8').read()
    for m in re.finditer(r"'slug'\s*=>\s*'([^']+)'.*?'name'\s*=>\s*'([^']+)'.*?'en'\s*=>\s*'([^']+)'.*?'center'\s*=>\s*'([^']+)'", txt):
        out.append(m.groups())
    return out


def identify(path):
    out = subprocess.run(['identify', '-format', '%w %h %B', path], capture_output=True, text=True, check=True).stdout.split()
    return int(out[0]), int(out[1]), int(out[2])


def main():
    provinces = load_provinces()
    names = {s: (n, en, c) for s, n, en, c in provinces}
    images = {}
    for slug, (src, alt, symbols, wm) in IMAGES.items():
        path = os.path.join(IMG_DIR, slug + '.webp')
        if not os.path.exists(path):
            print('MISSING FILE', path, file=sys.stderr)
            continue
        w, h, b = identify(path)
        name_fa = 'استان ' + names[slug][0]
        assert len(alt) <= 125, (slug, len(alt))
        images[slug] = {
            'file': f'assets/featured/provinces/{slug}.webp',
            'filename': f'{slug}.webp',
            'width': w, 'height': h, 'bytes': b, 'kb': round(b / 1024),
            'mime': 'image/webp',
            'source_file': src + '.jpg',
            'source_duplicates': sorted([d + '.jpg' for d, k in DUPLICATES.items() if k == src]),
            'watermark_fixed': wm is True,
            'watermark': 'fixed' if wm is True else ('added' if wm == 'added' else 'original'),
            'source_package': 'imagetool (15).zip' if int(src) >= 1000099831 else 'imagetool (14).zip',
            'alt': alt,
            'title': f'{name_fa} — تصویر شاخص راهنمای سفر سرزمین آریان',
            'caption': f'نمادهای {name_fa} در یک نگاه: {"، ".join(symbols)} — {CREDIT}',
            'description': f'تصویر شاخص مقاله‌ی «{name_fa}» در سرزمین آریان؛ تصویرسازی گرافیکی با {"، ".join(symbols)}. '
                           f'نسبت ۱۶:۹، {w}×{h} پیکسل، WEBP. {CREDIT}؛ ویرایش و بهینه‌سازی: {GENERATED_JALALI}.',
            'symbols': symbols,
            'notes': NOTES.get(slug, ''),
        }
    missing = [s for s, _, _, _ in provinces if s not in images]
    manifest = {
        'version': '1.0',
        'generated': {'jalali': GENERATED_JALALI, 'iso': GENERATED_ISO},
        'source_package': SOURCE_PACKAGE,
        'spec': {'aspect': '16:9', 'width': 1600, 'height': 900, 'format': 'webp', 'max_kb': 300,
                 'note': 'تصاویر متن انگلیسی نام استان و نشان sarzaminaryan.ir را در خود دارند؛ برش هیرو (۱۶۰۰×۷۰۰) قالب از بالا لنگر می‌شود تا عنوان بریده نشود (قالب فرزند ۱.۰.۳).'},
        'count': {'unique': len(images), 'missing': len(missing), 'duplicates_in_package': len(DUPLICATES)},
        'missing': missing,
        'duplicates_in_package': {d + '.jpg': k + '.jpg' for d, k in DUPLICATES.items()},
        'images': {s: images[s] for s, _, _, _ in provinces if s in images},
    }
    with open(os.path.join(IMG_DIR, 'manifest.json'), 'w', encoding='utf-8') as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)
        f.write('\n')

    # README table
    fa = lambda s: str(s).translate(str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹'))
    lines = []
    lines.append('# assets/featured/provinces — تصاویر شاخص استان‌ها\n')
    lines.append(f'منبع: `{SOURCE_PACKAGE}` · تولید manifest: {GENERATED_JALALI} · اسکریپت: `content-templates/tools/featured_manifest.py`\n')
    lines.append('استاندارد خروجی: ۱۶:۹ · ۱۶۰۰×۹۰۰ · WEBP ≤ ۳۰۰ KB (کیفیت ۸۲، method 6) · بدون متادیتا (strip) · نام فایل = نامک وردپرس · ALT فارسی ≤ ۱۲۵ کاراکتر شامل نام استان (راهنمای تصویر گوگل). هفت تصویر بسته‌ی دوم نشان دامنه نداشتند؛ نشان sarzaminaryan.ir به همان سبک (پیل آبی، پایین‌وسط) افزوده شد.\n')
    lines.append(f'**وضعیت: {fa(len(images))} استان دارای تصویر · {fa(len(missing))} استان بدون تصویر · {fa(len(DUPLICATES))} فایل تکراری در بسته (حذف شد).**\n')
    lines.append('| # | استان | فایل | ابعاد | حجم | فایل مبدأ | نشان دامنه | ALT |')
    lines.append('|---|---|---|---|---|---|---|---|')
    i = 0
    for slug, name, en, center in provinces:
        i += 1
        if slug in images:
            im = images[slug]
            lines.append(f'| {fa(i)} | {name} | `{im["filename"]}` | {fa(im["width"])}×{fa(im["height"])} | {fa(im["kb"])} KB | `{im["source_file"]}` | { {"fixed": "✅ املا اصلاح شد", "added": "➕ افزوده شد", "original": "— اصلی"}[im["watermark"]] } | {im["alt"]} |')
        else:
            lines.append(f'| {fa(i)} | {name} | — | — | — | — | — | **بدون تصویر — باید ساخته شود** |')
    lines.append('')
    lines.append('## استان‌های بدون تصویر (برای ساخت)\n')
    for s in missing:
        n, en, c = names[s]
        lines.append(f'- {n} (`{s}`, {en}) — مرکز: {c}')
    if not missing:
        lines.append('- هیچ — هر ۳۱ استان تصویر شاخص دارند (بسته‌ی دوم هفت استان باقی‌مانده را کامل کرد).')
    lines.append('')
    lines.append('## فایل‌های تکراری داخل بسته (بایت‌به‌بایت یکسان، md5)\n')
    for d, k in sorted(DUPLICATES.items()):
        slug = next(s for s, v in IMAGES.items() if v[0] == k)
        lines.append(f'- `{d}.jpg` = `{k}.jpg` ({names[slug][0]})')
    lines.append('')
    lines.append('## اصلاح نشان دامنه\n')
    lines.append('در ۱۴ تصویر، نشان پایین تصویر با املای غلط تولید شده بود (`sarzamiaryan.ir`، `sarzaminryan.ir`، `sarzamiiaryan.ir`). '
                 'متن غلط با رنگ خود نوار پوشانده و «sarzaminaryan.ir» با فونت Vazirmatn Bold در همان جایگاه بازنویسی شد (بدون تغییر بقیه‌ی تصویر). '
                 'تصاویر اردبیل، تهران، سیستان و بلوچستان، فارس، قزوین، قم، خراسان شمالی، لرستان، مازندران و مرکزی از ابتدا درست بودند.\n')
    lines.append('## یادداشت‌های محتوایی\n')
    for s, note in NOTES.items():
        lines.append(f'- **{names[s][0]}:** {note}')
    lines.append('')
    lines.append('## نکته‌ی نمایش در قالب\n')
    lines.append('نام انگلیسی استان در بالای تصویر و نشان دامنه در پایین آن قرار دارد. اندازه‌ی `sa-hero` قالب (۱۶۰۰×۷۰۰) از نسخه‌ی ۱.۰.۳ قالب فرزند از **بالا** برش می‌خورد و CSS هیرو `object-position: center top` دارد تا عنوان حفظ شود؛ در کارت‌ها (۶۰۰×۳۳۸) و شبکه‌های اجتماعی تصویر کامل نمایش داده می‌شود. '
                 'مشخصات قالب (`content-templates/province.md` بلوک ۲: بدون متن، سه‌رنگ) برای تصاویر آینده همچنان توصیه می‌شود.\n')
    with open(os.path.join(IMG_DIR, 'README.md'), 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))
    print('images', len(images), 'missing', missing)
    for s, im in images.items():
        print(f'{s:24s} {im["kb"]:4d} KB alt={len(im["alt"])}')


if __name__ == '__main__':
    main()
