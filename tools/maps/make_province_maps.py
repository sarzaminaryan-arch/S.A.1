#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت نقشهٔ هر استان ایران از روی مرزهای GeoJSON (بدون نیاز به Overpass).

برای هر استان یک نقشهٔ تمیز می‌سازد: مرز استان پررنگ، استان‌های همسایه کم‌رنگ به‌عنوان
زمینه، برچسب نام فارسی و انگلیسی، و خط انتساب منبع داده. خروجی PNG با ۳۰۰ dpi و SVG.

نام استان‌ها از مرجع واحد مخزن خوانده می‌شود، نه از ستون `name` فایل GeoJSON:
`wp-content/themes/sarzaminaryan-child/data/provinces.php` (نامک + نام فارسی + انگلیسی
+ مرکز). تطبیق مرزها با «نام فارسی نرمال‌شده» و در صورت ناموفقیت با «نام انگلیسی» انجام
می‌شود، پس نگارش‌های مختلف GeoJSON (نیم‌فاصله، «ي/ك» عربی، کاراکترهای جهت‌دهی) هم
به همان ۳۱ استان می‌رسند.

مرزها از OSM است و زیر پروانهٔ ODbL 1.0 است؛ منتشر کردن نقشه بدون ذکر انتساب
«© OpenStreetMap contributors» مجاز نیست. همین انتساب پایین هر نقشه چاپ می‌شود.

کاربرد:
    python3 tools/maps/make_province_maps.py                      # هر ۳۱ استان
    python3 tools/maps/make_province_maps.py --only lorestan,kerman
    python3 tools/maps/make_province_maps.py --contact-sheet      # یک تصویر از همه
    python3 tools/maps/make_province_maps.py --check              # بدون ساخت؛ گزارش وضعیت
    python3 tools/maps/make_province_maps.py --list               # فهرست نامک‌ها
    python3 tools/maps/make_province_maps.py --geojson path/to/boundaries.geojson

نیازمندی‌ها: geopandas، matplotlib و pyproj (به‌همراه pyogrio/fiona برای خواندن GeoJSON).
قلم وازیرمتن از بستهٔ npm وازیرمتن (registry.npmjs.org) در `~/.cache/sa-fonts/` کش می‌شود؛
هیچ فایل قلمی داخل مخزن نگه داشته نمی‌شود. در نبود قلم یا نبود شبکه، ساخت متوقف نمی‌شود و
برچسب فارسی حذف می‌شود.

مختصات مرکز استان‌ها در این ابزار حدس زده نمی‌شود؛ اگر فایل مختصات مستند داشته باشید با
`--centers path.json` بدهید تا نشانهٔ شهر مرکزی روی نقشه بیفتد.

@package Sarzaminaryan_Child
"""

from __future__ import annotations

import argparse
import io
import json
import os
import re
import sys
import tarfile
import unicodedata
import urllib.request
import zipfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]
CHILD = REPO / 'wp-content' / 'themes' / 'sarzaminaryan-child'
PROVINCES_PHP = CHILD / 'data' / 'provinces.php'

CACHE_DIR = Path(os.environ.get('SA_MAP_CACHE') or
                 (Path.home() / '.cache' / 'sa-province-maps'))
FONT_DIR = Path(os.environ.get('SA_FONT_DIR') or
                (Path.home() / '.cache' / 'sa-fonts'))

# --------------------------------------------------------------- منبع مرزها ---

BOUNDARY_SOURCE = {
    'repo': 'ssepehrnoush/Iran-geojson-map-boundaries',
    'file': 'ir_states_boundaries_coordinates.geojson',
    'branch': 'master',
    'data_licence': 'ODbL 1.0',
    'attribution': '© OpenStreetMap contributors',
    'home': 'https://github.com/ssepehrnoush/Iran-geojson-map-boundaries',
}
RAW_URL = ('https://raw.githubusercontent.com/{repo}/{branch}/{file}'
           .format(**BOUNDARY_SOURCE))
CODELOAD_URL = ('https://codeload.github.com/{repo}/zip/refs/heads/{branch}'
                .format(**BOUNDARY_SOURCE))

# ------------------------------------------------------------- پالت رنگی ---
# پالت از اسکریپت اولیهٔ کاربر حفظ شده است؛ رنگ تأکیدی حالا نشانهٔ شهر مرکزی است
# چون دیگر لایهٔ خیابان (Overpass) در این نسخه رسم نمی‌شود.

BG_COLOR = '#F4F1EA'        # زمینهٔ کرم
LAND_COLOR = '#FFFFFF'      # داخل استان
CONTEXT_COLOR = '#E6DECC'   # استان‌های همسایه
BORDER_COLOR = '#333333'    # مرز استان
CONTEXT_EDGE = '#CFC5AE'    # خط مرز همسایه‌ها
ACCENT_COLOR = '#2A9D8F'    # نشانهٔ شهر مرکزی
TEXT_COLOR = '#1F1F1F'
MUTED_COLOR = '#6B6459'

# --------------------------------------------------------------- قلم‌ها -----

VAZIR_VERSION = '33.0.3'
VAZIR_TARBALL = 'https://registry.npmjs.org/vazirmatn/-/vazirmatn-%s.tgz' % VAZIR_VERSION
VAZIR_FILES = {
    'regular': 'Vazirmatn-Regular.ttf',
    'bold': 'Vazirmatn-Bold.ttf',
}
ORBITRON_FILE = 'Orbitron-Variable.ttf'
ORBITRON_URLS = (
    'https://raw.githubusercontent.com/google/fonts/main/ofl/orbitron/Orbitron%5Bwght%5D.ttf',
    'https://api.github.com/repos/google/fonts/contents/ofl/orbitron/Orbitron%5Bwght%5D.ttf',
)

# ----------------------------------------------- تصویر معادل ایران (آلبرز) --
# بدون تصویر کردن، طول جغرافیایی روی نقشه کشیده می‌شود؛ این مخروطِ آلبرتِ هم‌مساحت
# با دو عرض مرجع ۲۹٫۵ و ۳۵٫۵ درجه، همان پیش‌فرض نقشه‌های ملی ایران است.

IRAN_ALBERS = '+proj=aea +lat_1=29.5 +lat_2=35.5 +lat_0=32 +lon_0=53 +datum=WGS84 +units=m +no_defs'

FIG_SIZE = (10.0, 10.0)
DPI = 300
CONTACT_COLS = 6             # تعداد ستون برگهٔ تماس (سطر خودکار)


# ------------------------------------------------------------ متن فارسی -----

def fa(text):
    """آماده‌سازی متن فارسی برای matplotlib (اتصال حروف + ترتیب دوسویه)."""
    if not text:
        return ''
    try:
        import arabic_reshaper
        from bidi.algorithm import get_display
    except ImportError:  # pragma: no cover — نبودش فقط برچسب فارسی را حذف می‌کند
        return None
    return get_display(arabic_reshaper.reshape(text))


# ------------------------------------------------------- نرمال‌سازی نام -----

# نگاشت‌های صریح با کد یونیکد؛ نوشتنِ خودِ کاراکترها در کد فریبنده است (نیم‌فاصله و
# کاراکترهای جهت‌دهی در ویرایشگر نامرئی‌اند و به‌سادگی جابه‌جا یا حذف می‌شوند).
# نیم‌فاصله (U+200C) عمداً در این فهرست نیست: باید به فاصله تبدیل شود نه اینکه
# حذف شود، وگرنه «بویر‌احمد» و «بویر احمد» یکی می‌شوند ولی «بویراحمد» با آن‌ها فرق می‌کند.
_ZERO_WIDTH = ('‎‏'   # LRM، RLM
                '‪‫‬'   # LRE، RLE، PDF
                '‭‮')  # LRO، RLO
_MAP = {
    'ي': 'ی',    # ي عربی → ی فارسی
    'ى': 'ی',    # ى → ی
    'ك': 'ک',    # ك عربی → ک فارسی
    'ڪ': 'ک',
    'ة': 'ه',                              # تربی گرد → ه
    'ۀ': 'ه', 'ۂ': 'ه', 'ۃ': 'ه',
    '‌': ' ',   # نیم‌فاصله → فاصلهٔ معمولی
    '‏': '',                                # RLM → حذف
    '‪': '', '‫': '', '‬': '',    # LRE، RLE، PDF → حذف
    '؜': '',                                # ALM → حذف
}


def norm(text):
    """نام استان را برای تطبیق بی‌تفاوت به یک شکل واحد می‌آورد."""
    text = unicodedata.normalize('NFKC', str(text))
    text = ''.join(ch for ch in text if ch not in _ZERO_WIDTH)
    for src, dst in _MAP.items():
        text = text.replace(src, dst)
    return re.sub(r'\s+', ' ', text).strip().casefold()


# ------------------------------------------------------ مرجع استان‌ها ------

def load_provinces(path=PROVINCES_PHP):
    """۳۱ استان را از فایل دادهٔ تم مادرخوانی می‌کند (بدون نیاز به PHP)."""
    if not os.path.isfile(path):
        raise SystemExit('فایل مرجع استان‌ها پیدا نشد: %s' % path)
    src = Path(path).read_text(encoding='utf-8')
    rows = re.findall(
        r"'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']*)'\s*,"
        r"\s*'en'\s*=>\s*'([^']*)'\s*,\s*'center'\s*=>\s*'([^']*)'", src)
    if not rows:
        raise SystemExit('هیچ استانی در %s پیدا نشد؛ ساختار فایل عوض شده است.' % path)
    return [{'slug': slug, 'name_fa': name, 'en': en, 'center': center}
            for slug, name, en, center in rows]


# ------------------------------------------------------- دریافت مرزها ------

def _fetch(url, timeout=90):
    request = urllib.request.Request(url, headers={'User-Agent': 'sarzaminaryan-build'})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return response.read()


def ensure_boundaries(explicit=None, force=False):
    """مسیر GeoJSON مرزها را برمی‌گرداند و در نبودش، از منبع ثبت‌شده دریافت می‌کند."""
    if explicit:
        path = Path(explicit).expanduser()
        if not path.is_file():
            raise SystemExit('فایل مرزها پیدا نشد: %s' % path)
        return path

    target = CACHE_DIR / BOUNDARY_SOURCE['file']
    if target.is_file() and not force:
        return target

    CACHE_DIR.mkdir(parents=True, exist_ok=True)
    sys.stderr.write('مرزها در کش نیست؛ دریافت از %s\n' % BOUNDARY_SOURCE['repo'])
    try:
        target.write_bytes(_fetch(RAW_URL))
        if target.stat().st_size > 10_000:
            return target
    except Exception as exc:  # noqa: BLE001 — به مسیر پشتیبان می‌رویم
        sys.stderr.write('دریافت مستقیم ناموفق بود (%s)؛ مسیر codeload…\n' % exc)

    blob = _fetch(CODELOAD_URL)
    with zipfile.ZipFile(io.BytesIO(blob)) as archive:
        name = next(n for n in archive.namelist()
                    if n.endswith('/' + BOUNDARY_SOURCE['file']))
        target.write_bytes(archive.read(name))
    return target


def load_boundaries(path):
    import geopandas as gpd
    gdf = gpd.read_file(path)
    if gdf.crs is None:
        sys.stderr.write('CRS در فایل مرزها نبود؛ EPSG:4326 فرض شد.\n')
        gdf = gdf.set_crs('EPSG:4326')
    if gdf.crs.to_epsg() != 4326:
        gdf = gdf.to_crs('EPSG:4326')
    gdf = gdf[gdf.geometry.notna() & ~gdf.geometry.is_empty]
    bad = gdf[~gdf.geometry.is_valid]
    if len(bad):
        sys.stderr.write('هشدار: %d چندضلعی نامعتبر بود و اصلاح شد.\n' % len(bad))
        gdf.loc[~gdf.geometry.is_valid, 'geometry'] = gdf.geometry.buffer(0)
    return gdf


def match_provinces(gdf, provinces):
    """مرز هر استان را به رکورد مرجع وصل می‌کند؛ تطبیق اول نام فارسی، بعد انگلیسی."""
    def columns(frame):
        return {c.lower(): c for c in frame.columns}

    names = columns(gdf)
    fa_col = names.get('name') or names.get('name:fa') or names.get('name_fa')
    en_col = names.get('name:en') or names.get('name_en') or names.get('en')

    index_fa, index_en = {}, {}
    for i in range(len(gdf)):
        if fa_col:
            index_fa.setdefault(norm(gdf.iloc[i][fa_col]), i)
        if en_col:
            index_en.setdefault(norm(gdf.iloc[i][en_col]), i)

    matched, missing = {}, []
    for province in provinces:
        index = index_fa.get(norm(province['name_fa']))
        source = 'name:fa'
        if index is None and en_col:
            index = index_en.get(norm(province['en']))
            source = 'name:en'
        if index is None:
            missing.append(province)
            continue
        province = dict(province)
        province['geometry'] = gdf.iloc[index]['geometry']
        province['matched_by'] = source
        matched[province['slug']] = province
    return matched, missing


# ----------------------------------------------------------------- قلم -----

class Fonts:
    """قلم فارسی (وازیرمتن) و قلم لاتین؛ در نبودشان ساخت با برچسب انگلیسی ادامه می‌یابد."""

    def __init__(self):
        self.dir = None
        self.family = None
        self.cache = {}
        self._prepare()

    def _prepare(self):
        try:
            from matplotlib import font_manager
        except ImportError:  # pragma: no cover
            return
        path = self._ensure_vazirmatn()
        if not path:
            sys.stderr.write('قلم وازیرمتن یافت نشد؛ برچسب فارسی حذف می‌شود.\n')
            return
        for weight, name in VAZIR_FILES.items():
            font_manager.fontManager.addfont(str(path / name))
        self.dir = path
        self.family = font_manager.FontProperties(
            fname=str(path / VAZIR_FILES['regular'])).get_name()

    def _ensure_vazirmatn(self):
        for candidate in (FONT_DIR, HERE / 'fonts'):
            if all((candidate / name).is_file() for name in VAZIR_FILES.values()):
                return candidate
        FONT_DIR.mkdir(parents=True, exist_ok=True)
        try:
            blob = _fetch(VAZIR_TARBALL)
        except Exception as exc:  # noqa: BLE001 — بدون شبکه هم ساخت ادامه می‌یابد
            sys.stderr.write('دریافت وازیرمتن ناموفق بود: %s\n' % exc)
            return None
        with tarfile.open(fileobj=io.BytesIO(blob)) as tar:
            for member in tar.getmembers():
                name = os.path.basename(member.name)
                if member.name.endswith('/fonts/ttf/' + name) and name in VAZIR_FILES.values():
                    with tar.extractfile(member) as src, open(FONT_DIR / name, 'wb') as dst:
                        dst.write(src.read())
        return FONT_DIR

    def props(self, size, weight='regular', family=None):
        from matplotlib.font_manager import FontProperties
        key = (family or self.family, size, weight)
        if key not in self.cache:
            self.cache[key] = FontProperties(
                family=family or self.family,
                size=size,
                weight='bold' if weight == 'bold' else 'normal')
        return self.cache[key]

    def latin(self, size, weight='bold'):
        """قلم لاتین نمایشی (Orbitron)؛ در نبودش وازیرمتن بولد."""
        from matplotlib.font_manager import FontProperties
        if self.dir and (self.dir / ORBITRON_FILE).is_file():
            return FontProperties(fname=str(self.dir / ORBITRON_FILE), size=size)
        return self.props(size, weight)

    def ready(self):
        return bool(self.family)


# --------------------------------------------------------------- رسم -------

def draw_base(ax, gdf, target, show_context=True, footer=0.0, square=False):
    """لایهٔ زمینه: مرز کشور و استان‌های همسایه، و پر کردن استان هدف.

    `footer` نسبتی از ارتفاع محدوده است که پایین نقشه خالی می‌ماند تا نام استان
    روی خودِ شکل نیفتد؛ `square` کاشی را مربع می‌کند (برای برگهٔ تماس).
    """
    if show_context:
        gdf.boundary.plot(ax=ax, color=CONTEXT_EDGE, linewidth=0.6, zorder=1)
        gdf.plot(ax=ax, color=CONTEXT_COLOR, edgecolor='none', zorder=0)

    geom = target['geometry']
    _as_gseries(geom).plot(ax=ax, color=LAND_COLOR, edgecolor=BORDER_COLOR,
                           linewidth=1.6, zorder=2)

    xmin, ymin, xmax, ymax = geom.bounds
    width, height = xmax - xmin, ymax - ymin
    pad = 0.04 * max(width, height) if max(width, height) else 0.5
    ax.set_xlim(xmin - pad, xmax + pad)
    ax.set_ylim(ymin - pad - footer * (height + 2 * pad), ymax + pad)

    if square:
        cx, cy = (xmin + xmax) / 2.0, (ymin + ymax) / 2.0
        half = max(width, height) / 2.0 + pad
        ax.set_xlim(cx - half, cx + half)
        ax.set_ylim(cy - half, cy + half)

    ax.set_aspect('equal')
    ax.set_axis_off()
    ax.set_facecolor(BG_COLOR)


def _as_gseries(geometry):
    import geopandas as gpd
    return gpd.GeoSeries([geometry])


def draw_label(ax, target, fonts, scale, attribution):
    """نام فارسی و انگلیسی استان + خط انتساب، در نوار پایین نقشه.

    `scale` عرض بوم بر حسب پوینت است؛ اندازهٔ قلم‌ها نسبتی از آن می‌آید تا با
    `--size` بزرگ و کوچک هر دو خوانا بمانند.
    """
    shaped = fa(target['name_fa'])
    if shaped and fonts.ready():
        ax.text(0.055, 0.105, shaped, transform=ax.transAxes,
                fontproperties=fonts.props(scale * 0.040, 'bold'),
                color=TEXT_COLOR, ha='left', va='center', zorder=5)
    ax.text(0.055, 0.058, target['en'],
            transform=ax.transAxes,
            fontproperties=fonts.latin(scale * 0.018, 'regular'),
            color=MUTED_COLOR, ha='left', va='center', zorder=5)
    ax.text(0.055, 0.016, attribution, transform=ax.transAxes,
            fontproperties=fonts.props(scale * 0.0105, 'regular'),
            color=MUTED_COLOR, ha='left', va='center', zorder=5)


def draw_center(ax, target, centers, fonts, scale, project=True):
    """نشانهٔ شهر مرکزی — فقط وقتی مختصات مستند داده شده باشد.

    مختصات خام (lon, lat) می‌آید و اینجا تبدیل می‌شود؛ `project=False` وقتی که
    کاربر `--no-project` داده و محورها درجهٔ جغرافیایی‌اند.
    """
    point = centers.get(target['slug'])
    if not point:
        return
    from shapely.geometry import Point
    geom = Point(float(point[0]), float(point[1]))
    if project:
        geom = _project_geometry(geom)
    ax.plot(geom.x, geom.y, marker='o', markersize=scale * 0.010,
            markerfacecolor=ACCENT_COLOR, markeredgecolor=LAND_COLOR,
            markeredgewidth=scale * 0.0026, zorder=6, linestyle='none')
    shaped = fa(target['center']) if target.get('center') else None
    ax.annotate(shaped or target.get('center') or '', xy=(geom.x, geom.y),
                xytext=(scale * 0.014, scale * 0.012),
                textcoords='offset points',
                fontproperties=fonts.props(scale * 0.016, 'bold'),
                color=ACCENT_COLOR, ha='left', va='bottom', zorder=7)


# ------------------------------------------------------------- خروجی -------

def render(province, gdf, out_dir, args, fonts, centers, projected):
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt

    target = dict(province)
    if projected is not None:
        target['geometry'] = _project_geometry(target['geometry'])
        context = projected
    else:
        context = gdf

    fig, ax = plt.subplots(figsize=args.size, facecolor=BG_COLOR)
    scale = args.size[0] * 72.0          # عرض بوم بر حسب پوینت
    draw_base(ax, context, target, show_context=not args.no_context,
              footer=0.20)

    draw_center(ax, target, centers, fonts, scale,
                project=projected is not None)
    draw_label(ax, target, fonts, scale, args.attribution)

    stem = args.prefix + province['slug']
    written = []
    for fmt in args.formats:
        suffix = 'png' if fmt == 'png' else fmt
        path = out_dir / ('%s.%s' % (stem, suffix))
        fig.savefig(path, format=fmt, dpi=args.dpi, bbox_inches='tight',
                    pad_inches=0.06, facecolor=BG_COLOR)
        written.append(path)
    plt.close(fig)
    return written


def contact_sheet(gdf, provinces, args, fonts, projected, path):
    """یک تصویر از همهٔ استان‌ها برای بازبینی چشمی کل مجموعه.

    همهٔ کاشی‌ها مربع و هم‌اندازه‌اند (پنجرهٔ مربع حول مرکز هر استان) تا شبکه
    منظم بماند؛ نقشهٔ تک‌استانی‌ها نسبت واقعی شکل را نگه می‌دارد.
    """
    import math

    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt

    context = projected if projected is not None else gdf
    cols = min(CONTACT_COLS, len(provinces))
    rows = math.ceil(len(provinces) / cols)
    fig, axes = plt.subplots(rows, cols, figsize=(cols * 3.2, rows * 3.6),
                             facecolor=BG_COLOR)
    flat = list(axes.ravel()) if hasattr(axes, 'ravel') else [axes]

    for slot, province in enumerate(provinces):
        ax = flat[slot]
        target = dict(province)
        target['geometry'] = (_project_geometry(province['geometry'])
                              if projected is not None else province['geometry'])
        draw_base(ax, context, target, show_context=not args.no_context,
                  footer=0.0, square=True)
        shaped = fa(province['name_fa']) if fonts.ready() else None
        title = '%s\n%s' % (shaped, province['en']) if shaped else province['en']
        ax.set_title(title, fontproperties=fonts.props(9, 'bold'),
                     color=TEXT_COLOR, pad=4)
    for slot in range(len(provinces), len(flat)):
        flat[slot].axis('off')

    fig.suptitle('Iran provinces · %s'
                 % BOUNDARY_SOURCE['data_licence'],
                 fontproperties=fonts.latin(12, 'bold'), color=TEXT_COLOR, y=0.997)
    fig.text(0.5, 0.004, args.attribution, ha='center', color=MUTED_COLOR,
             fontproperties=fonts.props(7, 'regular'))
    fig.savefig(path, dpi=args.dpi, bbox_inches='tight', pad_inches=0.1,
                facecolor=BG_COLOR)
    plt.close(fig)
    return path


# ------------------------------------------------------ کمکی‌های هندسی ------

def _project_geometry(geometry):
    """تبدیل یک شیء هندسی به تصویر معادل آلبرت ایران.

    از `to_crs` خود geopandas استفاده می‌شود (نه `shapely.ops.transform` که در
    shapely 2.x منسوخ شده) تا رفتار بین نسخه‌های shapely/pyproj یکسان بماند.
    """
    import geopandas as gpd
    return gpd.GeoSeries([geometry], crs='EPSG:4326').to_crs(IRAN_ALBERS).iloc[0]


def project_frame(gdf):
    """یک کپی از GeoDataFrame در تصویر معادل (برای زمینه و محدوده)."""
    return gdf.to_crs(IRAN_ALBERS)


def load_centers(path):
    """مختصات اختیاری شهرهای مرکزی: {slug: [lon, lat]} — دادهٔ مستند، نه حدس."""
    if not path:
        return {}
    data = json.loads(Path(path).read_text(encoding='utf-8'))
    centers = data.get('centers', data)
    return {k: (v if isinstance(v, (list, tuple)) else [v['lon'], v['lat']])
            for k, v in centers.items()}


# --------------------------------------------------------------- main ------

def build_parser():
    parser = argparse.ArgumentParser(
        description='ساخت نقشهٔ استان‌های ایران از روی مرزهای GeoJSON',
        formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--geojson',
                        help='مسیر فایل مرزها؛ در نبودش از کش/منبع ثبت‌شده دریافت می‌شود')
    parser.add_argument('--refresh', action='store_true',
                        help='کش مرزها را نادیده بگیر و دوباره دریافت کن')
    parser.add_argument('--out', default=str(REPO / 'outputs' / 'province-maps'),
                        help='پوشهٔ خروجی (پیش‌فرض: outputs/province-maps که کامیت نمی‌شود)')
    parser.add_argument('--only', default='',
                        help='فقط این نامک‌ها، جدا شده با ویرگول: lorestan,kerman')
    parser.add_argument('--formats', default='png,svg',
                        help='قالب‌های خروجی: png,svg یا هر دو (پیش‌فرض: png,svg)')
    parser.add_argument('--prefix', default='map-', help='پیشوند نام فایل (پیش‌فرض: map-)')
    parser.add_argument('--size', type=float, nargs=2, default=FIG_SIZE, metavar=('W', 'H'))
    parser.add_argument('--dpi', type=int, default=DPI)
    parser.add_argument('--no-context', action='store_true',
                        help='استان‌های همسایه رسم نشوند')
    parser.add_argument('--no-project', action='store_true',
                        help='بدون تصویر معادل؛ مختصات خام درجه (رفتار اسکریپت اولیه)')
    parser.add_argument('--centers', help='JSON مختصات مستند شهرهای مرکزی: {slug: [lon, lat]}')
    parser.add_argument('--attribution', default=None,
                        help='خط انتساب پایین نقشه؛ پیش‌فرض از منبع داده ساخته می‌شود')
    parser.add_argument('--contact-sheet', action='store_true',
                        help='به‌جای ۳۱ فایل، یک تصویر از همهٔ استان‌ها')
    parser.add_argument('--check', action='store_true',
                        help='بدون ساخت؛ فقط گزارش وضعیت خروجی‌های موجود')
    parser.add_argument('--list', action='store_true', help='فهرست نامک‌های استان‌ها')
    parser.add_argument('--strict', action='store_true',
                        help='اگر مرز همهٔ استان‌ها پیدا نشد، اجرا با کد ۲ متوقف شود')
    return parser


def main(argv=None):
    args = build_parser().parse_args(argv)

    try:
        import geopandas  # noqa: F401
        import matplotlib  # noqa: F401
    except ImportError:
        sys.stderr.write('نیازمندی‌ها نصب نیست: '
                         'pip install --break-system-packages geopandas matplotlib pyproj\n')
        return 2

    provinces = load_provinces()
    if args.list:
        for province in provinces:
            print('%-26s %-28s %s' % (province['slug'], province['name_fa'], province['en']))
        return 0

    if args.only:
        wanted = [s.strip() for s in args.only.split(',') if s.strip()]
        known = {p['slug'] for p in provinces}
        unknown = [s for s in wanted if s not in known]
        if unknown:
            sys.stderr.write('نامک ناشناخته: %s\n' % ', '.join(unknown))
            return 2
        provinces = [p for p in provinces if p['slug'] in wanted]

    if args.attribution is None:
        args.attribution = '%s · %s' % (BOUNDARY_SOURCE['attribution'],
                                         BOUNDARY_SOURCE['data_licence'])
    formats = [f.strip() for f in args.formats.split(',') if f.strip()] or ['png']
    for fmt in formats:
        if fmt not in ('png', 'svg'):
            sys.stderr.write('قالب پشتیبانی‌نشده: %s (فقط png و svg)\n' % fmt)
            return 2
    args.formats = formats

    out_dir = Path(args.out).expanduser()
    if args.check:
        return check_outputs(provinces, out_dir, args)

    path = ensure_boundaries(args.geojson, force=args.refresh)
    gdf = load_boundaries(path)
    sys.stderr.write('%d چندضلعی از %s\n' % (len(gdf), path.name))

    matched, missing = match_provinces(gdf, provinces)
    if missing:
        for province in missing:
            sys.stderr.write('مرز پیدا نشد: %s (%s / %s)\n'
                             % (province['slug'], province['name_fa'], province['en']))
        sys.stderr.write('۳۱ استان باید پیدا شود؛ %d مورد ناموفق بود.\n' % len(missing))
        if args.strict:
            return 2
    if not matched:
        sys.stderr.write('هیچ استانی تطبیق نخورد؛ نام‌های فایل مرزها را بررسی کنید.\n')
        return 2

    ordered = [matched[p['slug']] for p in provinces if p['slug'] in matched]
    by_matched = {p['matched_by'] for p in ordered}
    sys.stderr.write('%d استان تطبیق یافت (%s)\n'
                     % (len(ordered), '/'.join(sorted(by_matched))))

    projected = None if args.no_project else project_frame(gdf)
    # مختصات خام (lon, lat) نگه داشته می‌شوند؛ تبدیل به تصویر معادل در draw_center
    # انجام می‌شود تا یک‌بار تبدیل شود، نه دو بار.
    centers = load_centers(args.centers) if args.centers else {}

    fonts = Fonts()
    out_dir.mkdir(parents=True, exist_ok=True)

    if args.contact_sheet:
        target = out_dir / (args.prefix + 'contact-sheet.png')
        print(contact_sheet(gdf, ordered, args, fonts, projected, target))
        return 0

    failures = 0
    for index, province in enumerate(ordered, 1):
        sys.stderr.write('[%d/%d] %s\n' % (index, len(ordered), province['name_fa']))
        try:
            render(province, gdf, out_dir, args, fonts, centers, projected)
        except Exception as exc:  # noqa: BLE001 — یک استان نباید کل اجرا را متوقف کند
            failures += 1
            sys.stderr.write('  ناموفق: %s\n' % exc)
    if failures:
        sys.stderr.write('%d استان ساخته نشد.\n' % failures)
        return 1
    print('%d نقشه در %s' % (len(ordered), out_dir))
    return 0


def check_outputs(provinces, out_dir, args):
    """گزارش وضعیت خروجی‌های موجود؛ ساختی انجام نمی‌دهد."""
    missing, present = [], 0
    for province in provinces:
        for fmt in args.formats:
            path = out_dir / ('%s%s.%s' % (args.prefix, province['slug'], fmt))
            if path.is_file():
                present += 1
            else:
                missing.append(path.name)
    print('پوشه: %s' % out_dir)
    print('موجود: %d فایل' % present)
    if missing:
        print('ناموجود (%d): %s' % (len(missing), ', '.join(missing)))
        return 1
    print('همهٔ استان‌ها موجودند.')
    return 0


if __name__ == '__main__':
    sys.exit(main())