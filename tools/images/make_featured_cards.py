#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت «کارت تصویر شاخص» دو‌نیمه برای مقالات دیدنی‌ها (۱۲۰۰×۶۰۰).

هر کارت: زمینهٔ سفید و لایت؛ نیمهٔ چپ نقاشی آبرنگ برگرفته از محتوای همان مقاله
(آبشار، دریاچه، روستا، قلعه، …) و نیمهٔ راست متنِ نزدیک به وسط: نام فارسی با خط
درشت و رنگ آبی ناوی، نام انگلیسی کوچک‌تر زیر آن با قلم «سایبری» Orbitron.
خروجی WebP سبک برای `assets/img/attraction-covers/`.

کاربرد:
    python3 tools/images/make_featured_cards.py                 # همهٔ مقاله‌ها
    python3 tools/images/make_featured_cards.py --only fin-garden --png
    python3 tools/images/make_featured_cards.py --preview .preview/featured-cards.html

نیازمندی‌ها: Pillow، arabic-reshaper، python-bidi و قلم‌ها (TTF).
قلم Vazirmatn از بستهٔ npm وازیرمتن (registry.npmjs.org) و قلم نمایشی Orbitron
از مخزن google/fonts در `~/.cache/sa-fonts/` گذاشته می‌شود؛ هیچ فایل قلمی داخل
مخزن نگه داشته نمی‌شود. اگر Orbitron در دسترس نباشد، نام انگلیسی با Vazirmatn
بولد نوشته می‌شود و ساخت متوقف نمی‌شود.

@package Sarzaminaryan_Child
"""

import argparse
import io
import json
import os
import re
import sys
import base64
import tarfile
import urllib.request
import zlib

from PIL import Image, ImageDraw, ImageFont

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from featured_card_art import PAL, Canvas, mix  # noqa: E402
from featured_card_watercolor import paint as paint_watercolor  # noqa: E402

try:  # فارسی‌سازی و ترتیب نمایش
    import arabic_reshaper
    from bidi.algorithm import get_display
except ImportError:  # pragma: no cover
    sys.stderr.write('Pillow / arabic-reshaper / python-bidi لازم است: '
                     'pip install --break-system-packages pillow arabic-reshaper python-bidi\n')
    raise

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.abspath(os.path.join(HERE, '..', '..'))
CHILD = os.path.join(REPO, 'wp-content', 'themes', 'sarzaminaryan-child')
DATA_ARTICLES = os.path.join(CHILD, 'data', 'ready-articles.php')
DATA_PROVINCES = os.path.join(CHILD, 'data', 'provinces.php')
SPEC_PATH = os.path.join(HERE, 'featured-cards.json')
OUT_DIR = os.path.join(CHILD, 'assets', 'img', 'attraction-covers')

CARD_W, CARD_H = 1200, 600
CARD_BOX = (30.0, 30.0, 1170.0, 570.0)    # کادر سفید کارت
ART_BOX = (58.0, 58.0, 588.0, 542.0)      # نیمهٔ نقاشی آبرنگ (چپ)
TEXT_CX = 872.0                            # مرکز ستون نوشته (نیمهٔ راست، نزدیک وسط)
NAVY = '#0d3a63'      # آبی ناوی نام فارسی
CYBER = '#3d7ea6'     # آبی روشن‌تر نام انگلیسی و برچسب نوع

VAZIR_VERSION = '33.0.3'
VAZIR_TARBALL = 'https://registry.npmjs.org/vazirmatn/-/vazirmatn-%s.tgz' % VAZIR_VERSION
WEIGHTS = {'regular': 400, 'medium': 500, 'bold': 700, 'extrabold': 800}
FONT_FILES = {
    400: 'Vazirmatn-Regular.ttf',
    500: 'Vazirmatn-Medium.ttf',
    700: 'Vazirmatn-Bold.ttf',
    800: 'Vazirmatn-ExtraBold.ttf',
}

ORBITRON_FILE = 'Orbitron-Variable.ttf'
ORBITRON_URLS = (
    'https://raw.githubusercontent.com/google/fonts/main/ofl/orbitron/Orbitron%5Bwght%5D.ttf',
    'https://api.github.com/repos/google/fonts/contents/ofl/orbitron/Orbitron%5Bwght%5D.ttf',
)


# ------------------------------------------------------------------ fonts ---

def font_dirs():
    candidates = [
        os.environ.get('SA_FONT_DIR'),
        os.path.join(os.path.expanduser('~'), '.cache', 'sa-fonts'),
        os.path.join(HERE, 'fonts'),
    ]
    return [d for d in candidates if d]


def ensure_fonts():
    """اطمینان از وجود قلم‌های TTF وازیرمتن (در صورت نیاز از npm)."""
    for d in font_dirs():
        if all(os.path.isfile(os.path.join(d, f)) for f in FONT_FILES.values()):
            return d
    target = os.path.join(os.path.expanduser('~'), '.cache', 'sa-fonts')
    os.makedirs(target, exist_ok=True)
    sys.stderr.write('قلم وازیرمتن یافت نشد؛ دریافت از %s\n' % VAZIR_TARBALL)
    with urllib.request.urlopen(VAZIR_TARBALL, timeout=90) as response:
        blob = response.read()
    with tarfile.open(fileobj=io.BytesIO(blob)) as tar:
        for member in tar.getmembers():
            name = os.path.basename(member.name)
            if member.name.endswith('/fonts/ttf/' + name) and name in FONT_FILES.values():
                with tar.extractfile(member) as src, open(os.path.join(target, name), 'wb') as dst:
                    dst.write(src.read())
    return target


class FontBook:
    def __init__(self, directory):
        self.dir = directory
        self.cache = {}

    def get(self, weight, size):
        key = (weight, size)
        if key not in self.cache:
            path = os.path.join(self.dir, FONT_FILES[weight])
            self.cache[key] = ImageFont.truetype(path, int(round(size * 3)))
        return self.cache[key]

    def latin(self, size, variation='Bold'):
        """قلم نمایشی لاتین (Orbitron) با وزن دلخواه؛ در نبودش وزیرمتن بولد."""
        path = os.path.join(self.dir, ORBITRON_FILE)
        if not os.path.isfile(path):
            return self.get(700, size)
        key = ('latin', size, variation)
        if key not in self.cache:
            font = ImageFont.truetype(path, int(round(size * 3)))
            try:
                font.set_variation_by_name(variation)
            except Exception:  # قلم ثابت یا قدیمی‌تر از پشتیبانی متغیر
                pass
            self.cache[key] = font
        return self.cache[key]


def ensure_orbitron(directory):
    """دریافت قلم نمایشی Orbitron (OFL) در پوشهٔ قلم‌ها؛ بی‌خطا در نبود شبکه."""
    target = os.path.join(directory, ORBITRON_FILE)
    if os.path.isfile(target):
        return True
    for url in ORBITRON_URLS:
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'sarzaminaryan-build'})
            with urllib.request.urlopen(req, timeout=60) as response:
                blob = response.read()
            if url.endswith('/contents/ofl/orbitron/Orbitron%5Bwght%5D.ttf'):
                blob = base64.b64decode(json.loads(blob.decode('utf-8'))['content'])
            if blob[:4] not in (b'\x00\x01\x00\x00', b'true', b'OTTO'):
                continue
            with open(target, 'wb') as dst:
                dst.write(blob)
            sys.stderr.write('قلم Orbitron دریافت شد: %s\n' % target)
            return True
        except Exception as exc:  # noqa: BLE001 — نبود شبکه نباید ساخت را متوقف کند
            sys.stderr.write('دریافت Orbitron از %s ناموفق بود: %s\n' % (url, exc))
    return False


# ------------------------------------------------------------------- text ---

def fa(text):
    """شکل‌دهی و ترتیب نمایش متن فارسی برای رسم روی تصویر."""
    return get_display(arabic_reshaper.reshape(str(text)))


def spaced(text, gap=' '):
    """فاصله‌گذاری حروف برای برچسب‌های لاتین (caps)."""
    return gap.join(list(str(text)))


def fit_single(c, txt, font, max_w, sizes):
    for size in sizes:
        w, _ = c.text_size(txt, font.get(700, size))
        if w <= max_w:
            return size
    return sizes[-1]


def split_two_lines(txt):
    """بهترین نقطهٔ شکست برای دو خط (فاصله یا نیم‌فاصله)."""
    for sep in (' ', '\u200c'):
        parts = txt.split(sep)
        if len(parts) > 1:
            best, best_diff = None, None
            for i in range(1, len(parts)):
                a, b = sep.join(parts[:i]), sep.join(parts[i:])
                diff = abs(len(a) - len(b))
                if best_diff is None or diff < best_diff:
                    best, best_diff = (a, b), diff
            return best
    return None


def draw_block(c, txt, cx, y, font, color, max_w, sizes, align='center', line_gap=1.08):
    """رسم متن یک‌خطی؛ در صورت نیاز شکست به دو خط."""
    size = fit_single(c, txt, font, max_w, sizes)
    best = split_two_lines(txt)
    if best and size <= sizes[0] * 0.62 and best[0] and best[1]:
        size = max(size, int(sizes[0] * 0.55))
        lines = [best[0], best[1]]
    else:
        lines = [txt]
    step = size * line_gap
    y0 = y - (len(lines) - 1) * step / 2
    for i, line in enumerate(lines):
        c.text((cx, y0 + i * step), line, font.get(700, size), color, anchor='mm')
    return step


# ------------------------------------------------------------------- card ---

def soft_shadow(c, box, radius, spread=18, color='#b6c8d8', strength=0.30, offset_y=9):
    x0, y0, x1, y1 = box
    for i in range(spread, 0, -1):
        t = (spread - i) / spread
        col = mix('#ffffff', color, strength * (t ** 1.6))
        c.rrect([x0 - i, y0 - i + offset_y, x1 + i, y1 + i + offset_y], radius + i * 0.8, fill=col)


def card_seed(key):
    """بذر پایدار هر مقاله تا نقاشی کارت تکرارپذیر بماند."""
    return zlib.crc32(str(key).encode('utf-8')) & 0xffffffff


def draw_fa_name(c, raw, cx, y, fonts, color, max_w, sizes, line_gap=1.06):
    """نام فارسی: درشت و بولد؛ در صورت لزوم شکست به دو خط (پیش از شکل‌دهی)."""
    shown = fa(raw)
    size = sizes[-1]
    for s in sizes:
        w, _ = c.text_size(shown, fonts.get(800, s))
        if w <= max_w:
            size = s
            break
    best = split_two_lines(raw) if size < sizes[0] else None
    if best and best[0] and best[1] and size <= sizes[0] * 0.72:
        step = size * line_gap
        for i, part in enumerate(best):
            c.text((cx, y - step / 2 + i * step), fa(part), fonts.get(800, size), color,
                   anchor='mm')
        return size
    c.text((cx, y), shown, fonts.get(800, size), color, anchor='mm')
    return size


def wrap_two_lines(txt):
    """شکست متن لاتین به دو خط از نزدیک‌ترین فاصله به میانه."""
    words = str(txt).split()
    if len(words) < 2:
        return None
    best, best_diff = None, None
    for i in range(1, len(words)):
        left, right = ' '.join(words[:i]), ' '.join(words[i:])
        diff = abs(len(left) - len(right))
        if best_diff is None or diff < best_diff:
            best, best_diff = (left, right), diff
    return best


def draw_latin_name(c, txt, cx, y, fonts, color, max_w, sizes, line_gap=1.14):
    """نام انگلیسی با قلم نمایشی (Orbitron)؛ در صورت نیاز دو خط و کوچک‌تر."""
    txt = str(txt).upper()

    def fit(lines):
        for s in sizes:
            if all(c.text_size(ln, fonts.latin(s))[0] <= max_w for ln in lines):
                return s
        return None

    size = fit([txt])
    if size:
        c.text((cx, y), txt, fonts.latin(size), color, anchor='mm')
        return size
    pair = wrap_two_lines(txt)
    if not pair:
        size = sizes[-1]
        c.text((cx, y), txt, fonts.latin(size), color, anchor='mm')
        return size
    smalls = [max(17, int(s * 0.66)) for s in sizes]
    size = fit(list(pair)) or smalls[-1]
    step = size * line_gap
    for i, line in enumerate(pair):
        c.text((cx, y - step / 2 + i * step), line, fonts.latin(size), color, anchor='mm')
    return size


def paste_painting(c, image, xy, radius):
    """چسباندن نقاشی آبرنگ با گوشه‌های گرد روی بوم ابرنمونه‌برداری‌شده."""
    ss = c.ss
    big = image.resize((image.width * ss, image.height * ss), Image.LANCZOS)
    mask = Image.new('L', big.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, big.size[0] - 1, big.size[1] - 1],
                                           radius=int(radius * ss), fill=255)
    c.img.paste(big, (int(c.s(xy[0])), int(c.s(xy[1]))), mask)


def draw_card(record, spec, fonts, png=False):
    """کارت دو‌نیمه: نقاشی آبرنگ در چپ، نام فارسی/انگلیسی نزدیک وسط در راست."""
    c = Canvas(CARD_W, CARD_H, ss=3, bg='#ffffff')
    soft_shadow(c, CARD_BOX, 38)
    c.rrect(CARD_BOX, radius=38, fill='#ffffff', outline='#e7eef4', width=1.4)

    scene = spec.get('scene') or 'lake'
    flags = spec.get('flags') or []
    art_w, art_h = int(ART_BOX[2] - ART_BOX[0]), int(ART_BOX[3] - ART_BOX[1])

    # ------------------------------------------- نیمهٔ نقاشی آبرنگ (چپ) ---
    painting = paint_watercolor(scene, flags, size=(art_w, art_h),
                                seed=card_seed(record['key']))
    paste_painting(c, painting, (ART_BOX[0], ART_BOX[1]), 26)
    c.rrect(ART_BOX, radius=26, outline='#e3ecf3', width=1.5)

    # ----------------------------------- نیمهٔ نوشته (راست، نزدیک وسط) ---
    chip(c, TEXT_CX, 152, fa(spec.get('kind_fa', '')), fonts.get(500, 18),
         fill='#eef4fb', color=CYBER)
    draw_fa_name(c, record['title'], TEXT_CX, 258, fonts, NAVY, 470,
                 [92, 86, 80, 74, 68, 62, 56, 50, 44])
    draw_latin_name(c, record['english'], TEXT_CX, 344, fonts, CYBER, 452,
                    [33, 30, 28, 26, 24, 22, 20])
    c.text((TEXT_CX, 416), fa('%s، %s' % (record['city_name'], record['province_name'])),
           fonts.get(500, 22), '#4d6b7d', anchor='mm')
    c.text((TEXT_CX, 506), 'sarzaminaryan.ir', fonts.get(500, 15), '#a9b8c6', anchor='mm')
    return c


def chip(c, cx, cy, txt, font, fill, color, pad_x=16, pad_y=7, radius=14):
    w, h = c.text_size(txt, font)
    c.rrect([cx - w / 2 - pad_x, cy - h / 2 - pad_y - 3,
             cx + w / 2 + pad_x, cy + h / 2 + pad_y - 1], radius=radius, fill=fill)
    c.text((cx, cy), txt, font, color, anchor='mm')


def divider(c, cx, y, color, half=36, thickness=4):
    c.rrect([cx - half, y, cx + half, y + thickness], radius=thickness / 2, fill=color)


def motif_row_at(c, names, cx, cy, r, gap):
    names = [n for n in (names or []) if n]
    if not names:
        return
    total = (len(names) - 1) * (r * 2 + gap)
    x = cx - total / 2
    for name in names:
        draw_motif(c, name, x, cy, r)
        x += r * 2 + gap


# -------------------------------------------------------------- data read ---

def php_scalar(block, name):
    m = re.search(r"'%s'\s*=>\s*'((?:[^'\\]|\\.)*)'" % re.escape(name), block)
    if not m:
        return ''
    return m.group(1).replace("\\'", "'").replace('\\\\', '\\')


def load_articles(path):
    src = open(path, encoding='utf-8').read()
    body = src[src.index('\nreturn array('):]
    starts = [(m.start(), m.group(1), m.end())
              for m in re.finditer(r"\n\t'([^']+)'\s*=>\s*array\(", body)]
    records = []
    for i, (start, key, end) in enumerate(starts):
        stop = starts[i + 1][0] if i + 1 < len(starts) else len(body)
        block = body[end:stop]
        records.append({
            'key': key,
            'title': php_scalar(block, 'title'),
            'english': php_scalar(block, 'english'),
            'slug': php_scalar(block, 'slug'),
            'type': php_scalar(block, 'type'),
            'kind': php_scalar(block, 'kind'),
            'province': php_scalar(block, 'province'),
            'province_name': php_scalar(block, 'province_name'),
            'city_name': php_scalar(block, 'city_name'),
        })
    return records


def load_provinces(path):
    src = open(path, encoding='utf-8').read()
    out = {}
    for m in re.finditer(r"'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']*)'\s*,\s*'en'\s*=>\s*'([^']*)'",
                         src):
        out[m.group(1)] = {'name': m.group(2), 'en': m.group(3)}
    return out


# ------------------------------------------------------------------- main ---

def cover_alt(record, spec):
    return '%s: نقاشی آبرنگ %s با نام فارسی و انگلیسی %s — %s، %s' % (
        'کارت تصویر شاخص ' + record['title'], spec.get('kind_fa', ''),
        record['title'], record['city_name'], record['province_name'])


def cover_title(record):
    english = re.sub(r'\s*\(.*?\)\s*', ' ', record['english']).strip()
    return 'کارت تصویر شاخص %s (%s)' % (record['title'], english or record['english'])


def apply_to_data(path, covers, dry=False):
    """درج/به‌روزرسانی کلید `cover` در هر رکورد data/ready-articles.php (idempotent)."""
    src = open(path, encoding='utf-8').read()
    for key, cover in covers.items():
        block = re.compile(r"(\n\t'%s'\s*=>\s*array\(\n)(.*?)(?=\n\t'[^']+'\s*=>\s*array\(|\n\);)"
                           % re.escape(key), re.S)
        new_block = (
            "\t\t'short_fa'     => '%s',\n"
            "\t\t'short_en'     => '%s',\n"
            "\t\t'city_en'      => '%s',\n"
            "\t\t'province_en'  => '%s',\n"
            "\t\t'cover'        => array(\n"
            "\t\t\t'file'  => '%s',\n"
            "\t\t\t'alt'   => '%s',\n"
            "\t\t\t'title' => '%s',\n"
            "\t\t),\n" % (cover['short_fa'].replace("'", "\\'"),
                            cover['short_en'].replace("'", "\\'"),
                            cover['city_en'].replace("'", "\\'"),
                            cover['province_en'].replace("'", "\\'"),
                            cover['file'], cover['alt'].replace("'", "\\'"),
                            cover['title'].replace("'", "\\'")))

        def repl(m):
            body = m.group(2)
            body = re.sub(r"\t\t'cover'\s*=>\s*array\(.*?\n\t\t\),\n", '', body, flags=re.S)
            for skey in ( 'short_fa', 'short_en', 'city_en', 'province_en' ):
                body = re.sub( r"\t\t'%s'\s*=>\s*'[^']*',\n" % skey, '', body )
            body = re.sub(r"(\t\t)'slug'\s*=>\s*'[^']*',\n", lambda sm: sm.group(0) + new_block, body, count=1)
            return m.group(1) + body

        src, n = block.subn(repl, src, count=1)
        if not n:
            raise SystemExit('رکورد پیدا نشد: %s' % key)
    if not dry:
        open(path, 'w', encoding='utf-8').write(src)
    return src


def check_data(path, spec):
    """بررسی هم‌خوانی دادهٔ قالب با فایل‌های تولیدشده (برای CI یا پیش از انتشار)."""
    src = open(path, encoding='utf-8').read()
    problems = []
    for m in re.finditer(r"\n\t'([^']+)'\s*=>\s*array\((.*?)(?=\n\t'[^']+'\s*=>\s*array\(|\n\);)", src, re.S):
        key, block = m.group(1), m.group(2)
        cov = re.search(r"'cover'\s*=>\s*array\(\s*'file'\s*=>\s*'([^']+)',", block)
        if key not in spec or spec[key].get('_skip'):
            continue
        if not cov:
            problems.append('%s: کلید cover ندارد' % key)
            continue
        rel = cov.group(1)
        abs_path = os.path.join(CHILD, 'assets', 'img', rel)
        if not os.path.isfile(abs_path):
            problems.append('%s: فایل نیست — %s' % (key, rel))
            continue
        if not re.search(r"'short_fa'\s*=>\s*'%s'" % re.escape(spec[key]['short_fa']), block):
            problems.append('%s: short_fa با طراحی کارت یکی نیست' % key)
        if not re.search(r"'short_en'\s*=>\s*'%s'" % re.escape(spec[key]['short_en']), block):
            problems.append('%s: short_en با طراحی کارت یکی نیست' % key)
    return problems


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--only', default='', help='کلید مقاله‌ها با کاما')
    ap.add_argument('--out', default=OUT_DIR, help='پوشهٔ خروجی WebP')
    ap.add_argument('--png', action='store_true', help='خروجی PNG هم بساز (برای بازبینی)')
    ap.add_argument('--preview', default='', help='مسیر فایل HTML بازبینی')
    ap.add_argument('--spec', default=SPEC_PATH)
    ap.add_argument('--check', action='store_true',
                    help='بررسی هم‌خوانی data/ready-articles.php با کارت‌های ساخته‌شده و پایان بدون تولید')
    ap.add_argument('--apply-data', action='store_true',
                    help='درج کلید cover برای هر مقاله در data/ready-articles.php')
    ap.add_argument('--data', default=DATA_ARTICLES)
    args = ap.parse_args()

    if args.check:
        problems = check_data(args.data, json.load(open(args.spec, encoding='utf-8')))
        if problems:
            sys.exit('ایراد در دادهٔ کارت‌ها:\n- ' + '\n- '.join(problems))
        print('OK: کارت‌های تصویر شاخص با data/ready-articles.php هم‌خوان است.')
        return

    font_dir = ensure_fonts()
    if not ensure_orbitron(font_dir):
        sys.stderr.write('هشدار: Orbitron در دسترس نبود؛ نام انگلیسی با Vazirmatn بولد.\n')
    fonts = FontBook(font_dir)
    articles = load_articles(DATA_ARTICLES)
    provinces = load_provinces(DATA_PROVINCES)
    spec = json.load(open(args.spec, encoding='utf-8'))
    wanted = [k for k in args.only.split(',') if k]
    os.makedirs(args.out, exist_ok=True)

    missing = [a['key'] for a in articles if a['key'] not in spec]
    if missing:
        sys.exit('طراحی کارت برای این مقاله‌ها تعریف نشده: %s' % ', '.join(missing))

    rows, total_bytes, covers = [], 0, {}
    for record in articles:
        if wanted and record['key'] not in wanted:
            continue
        record['province_en'] = provinces.get(record['province'], {}).get('en', record['province'])
        card = draw_card(record, spec[record['key']], fonts)
        image = card.img.resize((CARD_W, CARD_H), Image.LANCZOS)
        out = os.path.join(args.out, '%s.webp' % record['slug'])
        image.save(out, 'WEBP', quality=90, method=6)
        size_kb = os.path.getsize(out) / 1024
        total_bytes += os.path.getsize(out)
        if args.png:
            image.save(os.path.join(args.out, '%s.png' % record['slug']), 'PNG', optimize=True)
        covers[record['key']] = {
            'short_fa': spec[record['key']]['short_fa'],
            'short_en': spec[record['key']]['short_en'],
            'city_en': spec[record['key']].get('city_en', ''),
            'province_en': record['province_en'],
            'file': 'attraction-covers/%s.webp' % record['slug'],
            'alt': cover_alt(record, spec[record['key']]),
            'title': cover_title(record),
        }
        rows.append((record, out, size_kb))
        print('%-38s %-26s %6.1f KB' % (record['slug'], spec[record['key']]['short_fa'], size_kb))

    if rows:
        print('— %d کارت، میانگین %.1f KB، مجموع %.2f MB'
              % (len(rows), total_bytes / 1024 / len(rows), total_bytes / 1048576))

    if rows and not wanted:
        manifest = os.path.join(HERE, 'featured-cards.manifest.json')
        json.dump({'note': 'تولیدشده با tools/images/make_featured_cards.py — دستی ویرایش نشود.',
                   'size': [CARD_W, CARD_H],
                   'cards': {k: covers[k] for k in [r[0]['key'] for r in rows]}},
                  open(manifest, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        print('مانیفست: %s' % manifest)

    if args.apply_data and covers:
        apply_to_data(args.data, covers)
        print('کلید cover برای %d مقاله در %s به‌روزرسانی شد.' % (len(covers), args.data))

    if args.preview and rows:
        write_preview(args.preview, rows, spec)


def write_preview(path, rows, spec):
    os.makedirs(os.path.dirname(os.path.abspath(path)), exist_ok=True)
    rel = os.path.relpath(os.path.dirname(rows[0][1]), os.path.dirname(os.path.abspath(path)))
    cards = []
    for record, out, size_kb in rows:
        cards.append(
            '<figure><img src="%s/%s" alt="%s" loading="lazy">'
            '<figcaption><b>%s</b> — %s<br><span>%s · %s · %s · %.0f KB</span></figcaption></figure>'
            % (rel, os.path.basename(out), record['title'], record['title'], record['english'],
               spec[record['key']]['scene'], record['city_name'], record['province_name'], size_kb))
    html = """<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">
<title>کارت‌های تصویر شاخص دیدنی‌ها</title>
<style>
 body{font-family:Tahoma,Arial,sans-serif;background:#eef3f7;margin:0;padding:28px;color:#0b2b4a}
 h1{font-size:22px;margin:0 0 6px}
 p.lead{margin:0 0 22px;color:#4d6b7d;font-size:14px}
 .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:20px}
 figure{margin:0;background:#fff;border:1px solid #dde7ef;border-radius:16px;padding:12px;
        box-shadow:0 10px 24px rgba(11,43,74,.07)}
 img{width:100%%;height:auto;display:block;border-radius:10px;background:#fff}
 figcaption{font-size:14px;margin-top:10px;line-height:1.7}
 figcaption span{color:#6b8296;font-size:12px}
</style>
<h1>کارت‌های تصویر شاخص «دیدنی‌ها» — %d کارت ۱۲۰۰×۶۰۰</h1>
<p class="lead">دو‌نیمه: نقاشی آبرنگ برگرفته از محتوای مقاله در یک نیمه، نام فارسی (آبی ناوی) و نام انگلیسی کوچک‌تر زیر آن در نیمهٔ دیگر.</p>
<div class="grid">%s</div></html>""" % (len(rows), ''.join(cards))
    open(path, 'w', encoding='utf-8').write(html)
    print('پیش‌نمایش: %s' % path)


if __name__ == '__main__':
    main()
