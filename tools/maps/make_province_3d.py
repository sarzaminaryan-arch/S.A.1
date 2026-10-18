#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""نقشهٔ سه‌بعدی هر استان ایران با مرز شهرستان‌ها (سبک داشبورد تیره).

هر استان یک «بلوک» سه‌بعدی از شهرستان‌هایش می‌شود: هر شهرستان یک ستون با ارتفاع
متناسب با مساحت، نمای بالا با گرادیان آبی، نمای کناری تیره‌تر (شبیه‌سازی نور از
بالا-چپ) و خط لبهٔ نارنجی. تصویر با تصویربرداری ایزومتریک و الگوریتم دکمهٔ نقاش
(رسم از دور به نزدیک) ساخته می‌شود، نه mplot3d — چون ترتیب عمق در mplot3d برای
چندضلعی‌های متقاطع قابل اتکا نیست.

نام شهرستان‌ها از مرجع مخزن (`data/counties.php`) می‌آید و هندسه از OSM؛ نگاشت پوشهٔ
داده به استان هم از داده استخراج می‌شود (تطبیق نام شهرستان با مرجع)، نه با جدول
دستی. نگاشت به تصویر معادل آلبرز ایران انجام می‌شود تا شکل درست بماند.

کاربرد:
    python3 tools/maps/make_province_3d.py                    # هر ۳۱ استان
    python3 tools/maps/make_province_3d.py --only fars,kerman
    python3 tools/maps/make_province_3d.py --no-labels        # بدون نام روی نقشه
    python3 tools/maps/make_province_3d.py --check
    python3 tools/maps/make_province_3d.py --report            # گزارش پوشش مرزها

مرزها از OSM و زیر پروانهٔ ODbL 1.0 است؛ انتساب «© OpenStreetMap contributors» روی
هر تصویر چاپ می‌شود.

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

import numpy as np

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]
CHILD = REPO / 'wp-content' / 'themes' / 'sarzaminaryan-child'
PROVINCES_PHP = CHILD / 'data' / 'provinces.php'
COUNTIES_PHP = CHILD / 'data' / 'counties.php'

CACHE_DIR = Path(os.environ.get('SA_MAP_CACHE') or
                 (Path.home() / '.cache' / 'sa-province-maps'))
FONT_DIR = Path(os.environ.get('SA_FONT_DIR') or
                (Path.home() / '.cache' / 'sa-fonts'))

# --------------------------------------------------------------- منبع داده ---
# همان منبعی که tools/maps/make_province_maps.py استفاده می‌کند: مرز استان‌ها.
PROVINCE_SOURCE = {
    'repo': 'ssepehrnoush/Iran-geojson-map-boundaries',
    'file': 'ir_states_boundaries_coordinates.geojson',
    'branch': 'master',
    'data_licence': 'ODbL 1.0',
    'attribution': '© OpenStreetMap contributors',
}
# مرز شهرستان‌ها: استخراج Overpass، سازمان‌دهی‌شده در مخزن زیر (دادهٔ OSM، ODbL).
COUNTY_SOURCE = {
    'repo': 'hosseinhabibi2004/iran-geojson',
    'branch': 'master',
    'path': 'data/counties',
}
CODELOAD_PROV = ('https://codeload.github.com/{repo}/zip/refs/heads/{branch}'
                 .format(**PROVINCE_SOURCE))
RAW_PROV = ('https://raw.githubusercontent.com/{repo}/{branch}/{file}'
            .format(**PROVINCE_SOURCE))
CODELOAD_COUNTY = ('https://codeload.github.com/{repo}/zip/refs/heads/{branch}'
                   .format(**COUNTY_SOURCE))

IRAN_ALBERS = '+proj=aea +lat_1=29.5 +lat_2=35.5 +lat_0=32 +lon_0=53 +datum=WGS84 +units=m +no_defs'

# --------------------------------------------------------- پالت داشبورد تیره -
BG_TOP = '#050a12'
BG_BOTTOM = '#0a1420'
PANEL = '#0d1b2a'
TOP_FACE_HI = '#1d5f7a'     # نمای بالا
TOP_FACE_LO = '#103a52'     # پرکردن سوراخ داخلی شهرستان
SIDE_FACE = '#0a2233'
RIM = '#f0a04b'              # خط لبهٔ نارنجی
RIM_SOFT = '#b8763a'
LABEL = '#eef4fa'
LABEL_DIM = '#8fa3b5'
GRID = '#12283a'
TITLE_FA = '#ffffff'

# ---------------------------------------------- پالت سایبری (سبک cyber) ----
CY_BG_TOP = '#030a17'        # سرمه‌ای تیره
CY_BG_BOT = '#07182e'
CY_GRID = '#0d2b48'
CY_GRID_HOT = '#17496f'
CY_NAVY = '#16417f'          # نمای بالا — آبی ناوی
CY_NAVY_LO = '#0d2a56'      # سوراخ داخلی شهرستان
CY_SIDE = '#08182f'         # دیوارهٔ ضخامت
CY_GOLD = '#f3c455'         # خط طلایی
CY_GOLD_HI = '#ffe493'
CY_GOLD_SOFT = '#a97b30'
CY_CYAN = '#3fe0ff'
CY_TEXT = '#e6f6ff'
CY_TEXT_DIM = '#6d95b3'
CY_MONO = 'DejaVu Sans Mono'  # قلم تکنولوژیک همراه خود matplotlib
# سر و ته خط هر شهرستان هم‌رنگ؛ هر شهر یک رنگ از این فهرست می‌گیرد.
CY_CALL = ('#3fe0ff', '#57f7c8', '#ffc24d', '#7fcdff', '#b39bff')

FIG_SIZE = (11.0, 9.0)
CY_FIG_SIZE = (16.0, 8.0)   # مقیاس دو در یک
DPI = 200
MAX_LABEL_ROWS = 44          # بیش از این، کوچک‌ترین شهرستان‌ها بی‌برچسب می‌مانند

# ------------------------------------------------------------------ قلم ----
VAZIR_VERSION = '33.0.3'
VAZIR_TARBALL = 'https://registry.npmjs.org/vazirmatn/-/vazirmatn-%s.tgz' % VAZIR_VERSION
VAZIR_FILES = {'regular': 'Vazirmatn-Regular.ttf', 'bold': 'Vazirmatn-Bold.ttf'}


# ------------------------------------------------------- نرمال‌سازی نام -----
_ZERO_WIDTH = '‎‏‪‫‬‭‮'
_MAP = {
    'ي': 'ی', 'ى': 'ی', 'ك': 'ک', 'ڪ': 'ک',
    'ة': 'ه', 'ۀ': 'ه', 'ۂ': 'ه', 'ۃ': 'ه',
    '‌': ' ',   # نیم‌فاصله → فاصله (در فهرست حذف نیست)
    '‏': '', '‪': '', '‫': '', '‬': '', '؜': '',
}


def norm(text):
    text = unicodedata.normalize('NFKC', str(text))
    text = ''.join(ch for ch in text if ch not in _ZERO_WIDTH)
    for src, dst in _MAP.items():
        text = text.replace(src, dst)
    return re.sub(r'\s+', ' ', text).strip().casefold()


ZWNJ = '‌'
# جای‌نگهدار نیم‌فاصله: reshape نیم‌فاصله را کامل حذف می‌کند و دو واژه را به هم
# می‌چسباند («چنگه‌ماغانه» → «چنگهماغانه»). پس نیم‌فاصله موقتاً به یک نویسهٔ
# نامتغیر تبدیل و در پایان برگردانده می‌شود.
_ZWNJ_SENTINEL = '\ue000'


def fa(text):
    """متن فارسی برای matplotlib (اتصال حروف + ترتیب دوسویه + حفظ نیم‌فاصله)."""
    if not text:
        return None
    try:
        import arabic_reshaper
        from bidi.algorithm import get_display
    except ImportError:
        return None
    guarded = text.replace(ZWNJ, _ZWNJ_SENTINEL)
    shaped = get_display(arabic_reshaper.reshape(guarded))
    return shaped.replace(_ZWNJ_SENTINEL, ZWNJ)


def _fetch(url, timeout=120):
    req = urllib.request.Request(url, headers={'User-Agent': 'sarzaminaryan-build'})
    with urllib.request.urlopen(req, timeout=timeout) as response:
        return response.read()


# ------------------------------------------------------- مرجع مخزن ---------

def load_provinces(path=PROVINCES_PHP):
    src = Path(path).read_text(encoding='utf-8')
    rows = re.findall(
        r"'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']*)'\s*,"
        r"\s*'en'\s*=>\s*'([^']*)'", src)
    if not rows:
        raise SystemExit('استانی در %s پیدا نشد.' % path)
    return [{'slug': s, 'name_fa': n, 'en': e} for s, n, e in rows]


def load_counties(path=COUNTIES_PHP):
    """۴۸۳ شهرستان مرجع مخزن: نامک، نام فارسی، استان."""
    if not os.path.isfile(path):
        return []
    src = Path(path).read_text(encoding='utf-8')
    rows = re.findall(
        r"'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']*)'\s*,"
        r"\s*'province'\s*=>\s*'([^']+)'", src)
    return [{'slug': s, 'name_fa': n, 'province': p} for s, n, p in rows]


# --------------------------------------------------------- دریافت داده ------

def ensure_provinces(path_arg=None):
    if path_arg:
        p = Path(path_arg).expanduser()
        if not p.is_file():
            raise SystemExit('فایل مرز استان‌ها پیدا نشد: %s' % p)
        return p
    target = CACHE_DIR / PROVINCE_SOURCE['file']
    if target.is_file():
        return target
    CACHE_DIR.mkdir(parents=True, exist_ok=True)
    sys.stderr.write('مرز استان‌ها دریافت می‌شود…\n')
    try:
        blob = _fetch(RAW_PROV)
        if len(blob) > 10_000:
            target.write_bytes(blob)
            return target
    except Exception as exc:  # noqa: BLE001
        sys.stderr.write('دریافت مستقیم ناموفق بود (%s)\n' % exc)
    with zipfile.ZipFile(io.BytesIO(_fetch(CODELOAD_PROV))) as archive:
        name = next(n for n in archive.namelist()
                    if n.endswith('/' + PROVINCE_SOURCE['file']))
        target.write_bytes(archive.read(name))
    return target


def ensure_counties():
    """پوشهٔ شهرستان‌ها را در کش می‌سازد (فقط فایل‌های GeoJSON، نه کل مخزن)."""
    target = CACHE_DIR / 'counties'
    if target.is_dir() and len(list(target.glob('*/*.geojson'))) >= 31:
        return target
    target.mkdir(parents=True, exist_ok=True)
    sys.stderr.write('مرز شهرستان‌ها دریافت می‌شود (چند مگابایت، یک بار)…\n')
    blob = _fetch(CODELOAD_COUNTY)
    marker = '/%s/' % COUNTY_SOURCE['path']          # «data/counties»
    with zipfile.ZipFile(io.BytesIO(blob)) as archive:
        for info in archive.infolist():
            member = info.filename
            if not member.endswith('.all.geojson') or marker not in member:
                continue
            code = member.rsplit('/', 2)[-2]
            out_dir = target / code
            out_dir.mkdir(exist_ok=True)
            (out_dir / (code + '.geojson')).write_bytes(archive.read(member))
    found = len(list(target.glob('*/*.geojson')))
    if found < 31:
        raise SystemExit('پوشهٔ شهرستان‌ها کامل استخراج نشد (%d پوشه).' % found)
    return target


def build_folder_index(county_dir):
    """فهرست پوشه‌های دادهٔ شهرستان."""
    index = {}
    for folder in sorted(p for p in county_dir.iterdir() if p.is_dir()):
        path = folder / (folder.name + '.geojson')
        if path.is_file():
            index[folder.name] = path
    return index


def _iter_county_tags(path):
    """فقط ویژگی‌های سطح شهرستان (admin_level=5) با برچسب‌هایشان.

    فایل مستقیم با json خوانده می‌شود، نه geopandas: ویژگی‌های بدون برچسب در geopandas
    به NaN (float) تبدیل می‌شوند و `dict()` روی آن‌ها خطا می‌دهد.
    """
    for tags, _ in _iter_county_records(path, want_geometry=False):
        yield tags


def _iter_county_records(path, want_geometry=True):
    """(برچسب‌ها، هندسه) برای هر ویژگی سطح شهرستان."""
    from shapely.geometry import shape
    data = json.loads(Path(path).read_text(encoding='utf-8'))
    for feature in data.get('features', []):
        tags = feature.get('properties', {}).get('tags')
        if not isinstance(tags, dict) or str(tags.get('admin_level')) != '5':
            continue
        yield (tags, shape(feature['geometry']) if want_geometry else None)


def _strip_county_prefix(name):
    return re.sub(r'^(شهرستان|بخش|شهرستانِ)\s+', '', str(name).strip())


# ------------------------------------------------------------ هندسه ---------

def build_counties(county_dir, province, repo_counties, prov_geom, crs):
    """GeoDataFrame شهرستان‌های یک استان، بریده‌شده به مرز همان استان.

    هندسهٔ خام شهرستان‌ها در EPSG:4326 است و باید پیش از برش، به همان تصویر معادلی
    برود که مرز استان در آن قرار دارد — وگرنه برش بین درجه و متر بی‌معنا می‌شود.
    """
    import geopandas as gpd

    by_name = {norm(c['name_fa']): c for c in repo_counties
               if c['province'] == province['slug']}
    folder = resolve_folder(county_dir, province)
    path = county_dir / folder / (folder + '.geojson')

    raw, unmatched = [], []
    for tags, geom in _iter_county_records(path):
        hit = by_name.get(norm(_strip_county_prefix(tags.get('name', ''))))
        if not hit:
            unmatched.append(_strip_county_prefix(tags.get('name', '')))
            continue
        if not geom.is_valid:
            geom = geom.buffer(0)
        raw.append({'slug': hit['slug'], 'name_fa': hit['name_fa'],
                    'name_en': tags.get('name:en', ''), 'geometry': geom})

    if not raw:
        return gpd.GeoDataFrame({'slug': [], 'name_fa': [], 'name_en': [],
                                 'geometry': []}, crs=crs), sorted(set(unmatched))

    frame = gpd.GeoDataFrame(raw, geometry='geometry', crs='EPSG:4326').to_crs(crs)
    # بریدن به مرز استان: سطح دریایی و سرریز دادهٔ OSM بیرون از کادر حذف می‌شود
    frame['geometry'] = frame.geometry.intersection(prov_geom)
    frame = frame[~frame.geometry.is_empty & frame.geometry.notna()].copy()
    frame['geometry'] = frame.geometry.buffer(0)
    frame['area_m2'] = frame.geometry.area
    return frame, sorted(set(unmatched))




def resolve_folder(county_dir, province):
    """پوشهٔ دادهٔ این استان را با تطبیق نام شهرستان‌ها پیدا می‌کند."""
    by_name = {}
    for county in load_counties():
        if county['province'] == province['slug']:
            by_name[norm(county['name_fa'])] = True
    for folder in sorted(p for p in county_dir.iterdir() if p.is_dir()):
        path = folder / (folder.name + '.geojson')
        if not path.is_file():
            continue
        hits = 0
        for tags in _iter_county_tags(path):
            if norm(_strip_county_prefix(tags.get('name', ''))) in by_name:
                hits += 1
        if hits >= 2:
            return folder.name
    raise SystemExit('پوشهٔ داده برای استان %s پیدا نشد.' % province['slug'])


# ------------------------------------------------- تصویربرداری ایزومتریک --
class Iso:
    """تصویربرداری ایزومتریک دستی؛ کنترل کامل بر ترتیب عمق."""

    def __init__(self, azimuth=38.0, elevation=34.0, scale=1.0):
        import math
        self.t = math.radians(azimuth)
        self.p = math.radians(elevation)
        self.scale = scale
        self.cos_t, self.sin_t = math.cos(self.t), math.sin(self.t)
        self.cos_p, self.sin_p = math.cos(self.p), math.sin(self.p)

    def right(self, x, y):
        return x * self.cos_t + y * self.sin_t

    def depth(self, x, y):
        return -x * self.sin_t + y * self.cos_t     # بزرگ‌تر = دورتر

    def project(self, x, y, z=0.0):
        u = self.right(x, y)
        v = self.depth(x, y)
        sx = u
        sy = -(v * self.cos_p - z * self.sin_p)
        return sx * self.scale, sy * self.scale


def _rings(geom):
    """حلقه‌های بیرونی و داخلی یک چندضلعی (سوراخ‌ها هم)."""
    import shapely.geometry as sg
    if geom.is_empty:
        return [], []
    if isinstance(geom, sg.Polygon):
        return [list(geom.exterior.coords)], [list(r.coords) for r in geom.interiors]
    outers, inners = [], []
    for poly in geom.geoms:
        outers.append(list(poly.exterior.coords))
        inners.extend(list(r.coords) for r in poly.interiors)
    return outers, inners


def _shade(base, amount):
    """روشن/تیره کردن یک رنگ hex به اندازهٔ amount (‎-۱..۱)."""
    import matplotlib.colors as mcolors
    rgb = np.array(mcolors.to_rgb(base))
    return tuple(np.clip(rgb * (1.0 + amount), 0, 1))


def _clip_halfplane(poly, nx, ny, c):
    """برش ساترمن-هاوگمن: فقط نقاطی با nx·x + ny·y ≤ c نگه داشته می‌شوند."""
    out = []
    for i in range(len(poly)):
        cur, nxt = poly[i], poly[(i + 1) % len(poly)]
        dc = nx * cur[0] + ny * cur[1] - c
        dn = nx * nxt[0] + ny * nxt[1] - c
        if dc <= 0:
            out.append(cur)
        if (dc < 0 < dn) or (dc > 0 > dn):
            t = dc / (dc - dn)
            out.append((cur[0] + (nxt[0] - cur[0]) * t,
                        cur[1] + (nxt[1] - cur[1]) * t))
    return out


def hatch_wall(b0, b1, lift, ratio=0.34, angle=45.0):
    """هاشور مورب طلایی روی یک دیوارهٔ عمودی، برای نشان دادن ضخامت ستون.

    دیواره همیشه یک بالابری عمودیِ ثابت `lift` است (چون تصویر ایزومتریک فقط
    ارتفاع را روی محور عمودی جابه‌جا می‌کند)، پس هاشور در فضای خودِ صفحه و با
    زاویهٔ ثابت کشیده و به چهارضلعی برش می‌خورد.
    """
    if abs(lift[1]) < 1e-9:
        return []
    quad = [b0, b1, (b1[0] + lift[0], b1[1] + lift[1]),
            (b0[0] + lift[0], b0[1] + lift[1])]
    rad = np.radians(angle)
    dx, dy = np.cos(rad), np.sin(rad)
    nx, ny = -dy, dx
    values = [nx * p[0] + ny * p[1] for p in quad]
    lo, hi = min(values), max(values)
    spacing = abs(lift[1]) * ratio
    if spacing <= 0:
        return []
    segs = []
    step = 1
    while lo + step * spacing < hi:
        clipped = _clip_halfplane(quad, nx, ny, lo + step * spacing)
        if len(clipped) >= 2:
            proj = [dx * p[0] + dy * p[1] for p in clipped]
            i0, i1 = int(np.argmin(proj)), int(np.argmax(proj))
            segs.append([clipped[i0], clipped[i1]])
        step += 1
    return segs


def draw_county(ax, geom, height, iso, base_z=0.0, tone=0.0, style='cyber'):
    """ستون سه‌بعدی یک شهرستان: نمای کناری با سایه‌روشن، سپس نمای بالا."""
    import matplotlib.collections as mc

    cyber = style == 'cyber'
    top_hi, top_lo = (CY_NAVY, CY_NAVY_LO) if cyber else (TOP_FACE_HI, TOP_FACE_LO)
    side_base = CY_SIDE if cyber else SIDE_FACE

    outers, inners = _rings(geom)
    if not outers:
        return []

    sides, colors, hatches = [], [], []
    light = np.array([-0.55, -0.83])          # نور از بالا-چپ صفحه
    for ring in outers:
        pts = ring[:-1]
        for i in range(len(pts)):
            x0, y0 = pts[i]
            x1, y1 = pts[(i + 1) % len(pts)]
            dx, dy = x1 - x0, y1 - y0
            length = (dx * dx + dy * dy) ** 0.5 or 1.0
            normal = np.array([dy / length, -dx / length])
            facing = float(np.dot(normal, light))
            a = iso.project(x0, y0, base_z)
            b = iso.project(x1, y1, base_z)
            c = iso.project(x1, y1, base_z + height)
            d = iso.project(x0, y0, base_z + height)
            sides.append([a, b, c, d])
            colors.append(_shade(side_base, 0.55 * facing))
            if cyber and height > 0:
                hatches += hatch_wall(a, b, (c[0] - b[0], c[1] - b[1]))
    if sides:
        ax.add_collection(mc.PolyCollection(sides, facecolors=colors,
                                            edgecolors='none', zorder=2))
    if hatches:
        ax.add_collection(mc.LineCollection(hatches, colors=CY_GOLD,
                                            linewidths=0.45, alpha=0.40,
                                            zorder=3))
    tops = [[iso.project(x, y, base_z + height) for x, y in ring] for ring in outers]
    holes = [[iso.project(x, y, base_z + height) for x, y in ring] for ring in inners]
    if holes:
        for hole in holes:
            ax.add_collection(mc.PolyCollection([hole], facecolors=top_lo,
                                                edgecolors='none', zorder=4))
    ax.add_collection(mc.PolyCollection(tops, facecolors=_shade(top_hi, tone),
                                        edgecolors='none', zorder=5))
    return tops + sides


def draw_rim(ax, geom, height, iso, args_slab=0.0, style='cyber'):
    """خط لبهٔ طلایی روی نمای بالا و خط‌های عمودی لبه — «سیم‌کشی» سه‌بعدی."""
    outers, _ = _rings(geom)
    import matplotlib.collections as mc
    cyber = style == 'cyber'
    rim = CY_GOLD if cyber else RIM
    rim_soft = CY_GOLD_SOFT if cyber else RIM_SOFT
    for ring in outers:
        top = [iso.project(x, y, height) for x, y in ring]
        ax.add_collection(mc.LineCollection([top], colors=rim,
                                            linewidths=1.7 if cyber else 1.1,
                                            zorder=7))
        pts = ring[:-1]
        verticals = []
        for i in range(len(pts)):
            x0, y0 = pts[i]
            verticals.append([iso.project(x0, y0, -args_slab),
                              iso.project(x0, y0, height)])
        if verticals:
            ax.add_collection(mc.LineCollection(verticals, colors=rim_soft,
                                                linewidths=1.0 if cyber else 0.7,
                                                zorder=6))


def draw_base_slab(ax, geom, iso, height=1.0, style='cyber'):
    """صفحهٔ زیرین استان — پایهٔ نمایش سه‌بعدی."""
    return draw_county(ax, geom, height, iso, base_z=-height, style=style)


# ------------------------------------------------------------------ قلم -----
class Fonts:
    def __init__(self):
        self.family = None
        self.cache = {}
        self._prepare()

    def _prepare(self):
        try:
            from matplotlib import font_manager
        except ImportError:
            return
        path = self._ensure()
        if not path:
            sys.stderr.write('قلم وازیرمتن یافت نشد؛ برچسب فارسی حذف می‌شود.\n')
            return
        for name in VAZIR_FILES.values():
            font_manager.fontManager.addfont(str(path / name))
        self.family = font_manager.FontProperties(
            fname=str(path / VAZIR_FILES['regular'])).get_name()

    def _ensure(self):
        for candidate in (FONT_DIR, HERE / 'fonts'):
            if all((candidate / n).is_file() for n in VAZIR_FILES.values()):
                return candidate
        FONT_DIR.mkdir(parents=True, exist_ok=True)
        try:
            blob = _fetch(VAZIR_TARBALL)
        except Exception as exc:  # noqa: BLE001
            sys.stderr.write('دریافت قلم ناموفق بود: %s\n' % exc)
            return None
        with tarfile.open(fileobj=io.BytesIO(blob)) as tar:
            for member in tar.getmembers():
                name = os.path.basename(member.name)
                if member.name.endswith('/fonts/ttf/' + name) and name in VAZIR_FILES.values():
                    with tar.extractfile(member) as src, open(FONT_DIR / name, 'wb') as dst:
                        dst.write(src.read())
        return FONT_DIR

    def props(self, size, weight='regular'):
        from matplotlib.font_manager import FontProperties
        key = (size, weight)
        if key not in self.cache:
            self.cache[key] = FontProperties(family=self.family, size=size,
                                             weight='bold' if weight == 'bold'
                                             else 'normal')
        return self.cache[key]

    def ready(self):
        return bool(self.family)


# ------------------------------------------------------------------ رسم ----
def render(province, counties, out_dir, args, fonts, prov_geom):
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt

    iso = Iso(azimuth=args.azimuth, elevation=args.elevation)

    # ارتفاع‌ها نسبت به بزرگی خودِ استان سنجیده می‌شوند؛ واحد مختصات متر است و
    # ارتفاع «مطلق» یعنی عدد نمایشی که در نقشه اصلاً دیده نمی‌شود.
    minx, miny, maxx, maxy = prov_geom.bounds
    unit = max(maxx - minx, maxy - miny) or 1.0

    total = counties['area_m2'].sum() or 1.0
    share = (counties['area_m2'] / total) ** args.height_power
    share = share.replace(0, share.mean())
    heights = (args.height * unit) * share / share.max()

    centroids = counties.geometry.representative_point()
    order = np.argsort([iso.depth(c.x, c.y) for c in centroids])  # دورترین اول

    fig = plt.figure(figsize=args.size, facecolor=BG_TOP)
    if args.style == 'cyber':
        cyber_bg(fig)
        ax = fig.add_axes([0.006, 0.062, 0.694, 0.822], zorder=2)
    else:
        ax = fig.add_axes([0, 0, 1, 1])
        _gradient_bg(fig)
    ax.set_facecolor('none')
    ax.axis('off')

    slab = args.height * unit * 0.20
    draw_base_slab(ax, prov_geom, iso, height=slab, style=args.style)

    drawn = []
    for i in order:
        drawn += draw_county(ax, counties.geometry.iloc[i], float(heights.iloc[i]),
                             iso, tone=0.10 * ((i % 3) - 1), style=args.style)
    for i in order:
        draw_rim(ax, counties.geometry.iloc[i], float(heights.iloc[i]), iso,
                 args_slab=slab, style=args.style)

    _fit(ax, drawn, args.margin, equal=args.style == 'cyber')
    if args.style == 'cyber':
        fig.canvas.draw()
        labelled = cyber_callouts(fig, ax, counties, heights, iso, fonts, args)
        cyber_hud(fig, province, args, labelled or len(counties))
        cyber_footer(fig, args)
    else:
        _labels(ax, counties, heights, iso, fonts, args, unit)
        _title(fig, province, fonts, args, len(counties))
        _footer(fig, fonts, args)

    out_dir.mkdir(parents=True, exist_ok=True)
    path = out_dir / ('%s%s.png' % (args.prefix, province['slug']))
    fig.savefig(path, dpi=args.dpi, facecolor=BG_TOP)
    plt.close(fig)
    return path


def cyber_footer(fig, args):
    """خط‌کش پایین صفحه، منبع داده و شناسهٔ نسخه."""
    from matplotlib.lines import Line2D
    fig.add_artist(Line2D([0.012, 0.688], [0.038, 0.038], color=CY_CYAN, lw=0.7,
                          alpha=0.6, zorder=20))
    for i in range(21):                       # خط‌کش فنی زیر نقشه
        x = 0.012 + i * 0.0338
        long_tick = (i % 5 == 0)
        fig.add_artist(Line2D([x, x], [0.038, 0.038 + (0.013 if long_tick else 0.007)],
                              color=CY_GOLD if long_tick else CY_GRID_HOT,
                              lw=0.7, alpha=0.8, zorder=20))
    fig.text(0.012, 0.020, args.attribution, ha='left', va='center',
             color=CY_TEXT_DIM, fontsize=7.5, fontproperties=_mono(None, 7.5),
             zorder=20)
    fig.text(0.688, 0.020, 'RENDERED BY tools/maps/make_province_3d.py  ·  v2',
             ha='right', va='center', color=CY_TEXT_DIM, fontsize=7.5,
             fontproperties=_mono(None, 7.5), zorder=20)


def _fit(ax, polygons, margin=0.06, equal=False):
    """کادر نقشه را روی شکل رسم‌شده می‌نشاند تا حاشیهٔ یکنواخت بماند."""
    if not polygons:
        return
    xs = [p[0] for poly in polygons for p in poly]
    ys = [p[1] for poly in polygons for p in poly]
    dx, dy = max(xs) - min(xs), max(ys) - min(ys)
    pad_x = dx * margin
    pad_top = dy * margin * (0.35 if equal else 2.6)      # بالا جای عنوان
    pad_bottom = dy * margin * (0.35 if equal else 1.1)
    ax.set_xlim(min(xs) - pad_x, max(xs) + pad_x)
    ax.set_ylim(min(ys) - pad_bottom, max(ys) + pad_top)
    if equal:
        # تصویر ایزومتری نباید کشیده شود؛ کادر محور با نسبت درست تنظیم می‌شود.
        ax.set_aspect('equal', adjustable='box')
        ax.set_anchor('C')


def _gradient_bg(fig):
    """پس‌زمینهٔ عمودی تیره."""
    from matplotlib.colors import to_rgb
    top, bottom = np.array(to_rgb(BG_TOP)), np.array(to_rgb(BG_BOTTOM))
    grad = np.linspace(0, 1, 256).reshape(-1, 1)
    img = (top * (1 - grad) + bottom * grad).reshape(256, 1, 3)
    fig.figimage(np.repeat(img, 8, axis=1), xo=0, yo=0, origin='lower', zorder=-10)


# ------------------------------------------------------------- سبک سایبری --
def cyber_bg(fig, cell=0.021):
    """پس‌زمینهٔ سرمه‌ای با شبکهٔ فنی و خطوط ریز اسکن."""
    import matplotlib.pyplot as plt
    bg = fig.add_axes([0, 0, 1, 1], zorder=0)
    bg.set_xlim(0, 1)
    bg.set_ylim(0, 1)
    bg.set_axis_off()
    grad = np.linspace(0, 1, 256).reshape(-1, 1)
    rgb_top = np.array(matplotlib_colors_to_rgb(CY_BG_TOP))
    rgb_bot = np.array(matplotlib_colors_to_rgb(CY_BG_BOT))
    img = (rgb_top * (1 - grad) + rgb_bot * grad).reshape(256, 1, 3)
    bg.imshow(img, extent=(0, 1, 0, 1), aspect='auto', origin='upper',
              interpolation='bilinear', zorder=0)
    for x in np.arange(0, 1 + cell, cell):
        bg.axvline(x, color=CY_GRID, lw=0.5, zorder=1)
    for y in np.arange(0, 1 + cell, cell):
        bg.axhline(y, color=CY_GRID, lw=0.5, zorder=1)
    for x in np.arange(0, 1 + cell * 5, cell * 5):
        bg.axvline(x, color=CY_GRID_HOT, lw=0.8, alpha=0.8, zorder=2)
    for y in np.arange(0, 1 + cell * 5, cell * 5):
        bg.axhline(y, color=CY_GRID_HOT, lw=0.8, alpha=0.8, zorder=2)
    for y in np.arange(0, 1, 0.006):          # خطوط اسکن
        bg.axhline(y, color='#0b2138', lw=0.3, alpha=0.35, zorder=3)
    plt.setp(bg, frame_on=False)
    return bg


def matplotlib_colors_to_rgb(value):
    from matplotlib.colors import to_rgb
    return to_rgb(value)


def _mono(fonts, size, bold=False):
    """قلم تکنولوژیک تک‌عرض؛ اگر در دسترس نبود به قلم پیش‌فرض می‌افتد."""
    if fonts is not None and fonts.ready():
        return fonts.props(size, 'bold' if bold else 'regular')
    from matplotlib.font_manager import FontProperties
    return FontProperties(family=CY_MONO, size=size,
                          weight='bold' if bold else 'normal')


def cyber_hud(fig, province, args, n_counties):
    """قاب و نوشته‌های تکنولوژیک بالای نقشه."""
    from matplotlib.lines import Line2D
    title = ('%s PROVINCE' % province['en']).upper()
    fig.text(0.014, 0.962, 'IRAN // %s' % title, ha='left', va='top',
             color=CY_TEXT, fontsize=15, fontproperties=_mono(None, 15, True),
             zorder=20)
    fig.text(0.014, 0.912, 'COUNTY TOPOLOGY  ·  ISO 3166-2: IR-%s' % _iso_code(province),
             ha='left', va='top', color=CY_TEXT_DIM, fontsize=8.5,
             fontproperties=_mono(None, 8.5), zorder=20)
    fig.text(0.688, 0.962, 'NODES %02d' % n_counties, ha='right', va='top',
             color=CY_GOLD, fontsize=11, fontproperties=_mono(None, 11, True),
             zorder=20)
    fig.text(0.688, 0.912, 'DEPTH  EXTRUDED  ·  GOLD EDGES', ha='right', va='top',
             color=CY_TEXT_DIM, fontsize=8.5, fontproperties=_mono(None, 8.5),
             zorder=20)
    # خط زیر عنوان و گوشه‌های تزئینی
    fig.add_artist(Line2D([0.012, 0.688], [0.898, 0.898], color=CY_GOLD,
                          lw=1.0, alpha=0.75, zorder=20))
    for x, dx, y, dy in ((0.012, 0.016, 0.975, 0.0), (0.688, -0.016, 0.975, 0.0)):
        fig.add_artist(Line2D([x, x + dx], [y, y], color=CY_CYAN, lw=1.4, zorder=20))
    fig.text(0.702, 0.962, 'COUNTIES', ha='left', va='top', color=CY_CYAN,
             fontsize=11, fontproperties=_mono(None, 11, True), zorder=20)


def _iso_code(province):
    """کد دو حرفی استان از روی نام انگلیسی، فقط برای نمایش."""
    table = {
        'alborz': 'AL', 'ardabil': 'AR', 'bushehr': 'BS', 'chaharmahal-bakhtiari': 'CB',
        'east-azerbaijan': 'EA', 'fars': 'FA', 'gilan': 'GI', 'golestan': 'GO',
        'hamadan': 'HD', 'hormozgan': 'HR', 'ilam': 'IL', 'isfahan': 'ES',
        'kerman': 'KM', 'kermanshah': 'KS', 'khuzestan': 'KH',
        'kohgiluyeh-boyer-ahmad': 'KB', 'kurdistan': 'KD', 'lorestan': 'LO',
        'markazi': 'MK', 'mazandaran': 'MZ', 'north-khorasan': 'NK', 'qazvin': 'QZ',
        'qom': 'QM', 'razavi-khorasan': 'RK', 'semnan': 'SM',
        'sistan-baluchestan': 'SB', 'south-khorasan': 'SK', 'tehran': 'TH',
        'west-azerbaijan': 'WA', 'yazd': 'YZ', 'zanjan': 'ZJ',
    }
    return table.get(province['slug'], '--')


def cyber_callouts(fig, ax, counties, heights, iso, fonts, args):
    """نقطه در مرکز هر شهرستان + خط منظم تا ستون راست + فهرست نام‌ها.

    سرِ خط (مرکز شهرستان) و تهِ خط (ابتدای فهرست) با یک رنگ رسم می‌شوند.
    فهرست از بالا به پایین بر اساس جای عمودی نقطه‌ها مرتب می‌شود تا خطوط
    یکدیگر را قطع نکنند.
    """
    from matplotlib.lines import Line2D
    if args.no_labels:
        return 0
    rows = counties if len(counties) <= MAX_LABEL_ROWS else \
        counties.nlargest(MAX_LABEL_ROWS, 'area_m2')
    inv = fig.transFigure.inverted()
    items = []
    for i, (label, row) in enumerate(rows.iterrows()):
        point = row.geometry.representative_point()
        sx, sy = iso.project(point.x, point.y, float(heights.loc[label]))
        fx, fy = inv.transform(ax.transData.transform((sx, sy)))
        items.append({'y': fy, 'x': fx, 'text': county_label(row, args.label_lang),
                      'color': CY_CALL[i % len(CY_CALL)]})
    items = [it for it in items if it['text']]
    items.sort(key=lambda it: -it['y'])
    n = len(items)
    if not n:
        return 0
    top, bottom = 0.875, 0.055
    rows_y = np.linspace(top, bottom, n)
    text_x = 0.736
    row_h = (top - bottom) / n
    size = max(4.6, min(9.5, row_h * fig.get_figheight() * 72 * 0.52))
    tick = min(row_h * 0.30, 0.008)            # فاصله‌ها ثابت‌اند تا هم‌تراز بمانند
    num_gap, name_gap = 0.010, 0.024
    elbow = max(0.700, min(0.717, max(it['x'] for it in items) + 0.010))
    for rank, (it, ty) in enumerate(zip(items, rows_y), 1):
        color = it['color']
        # بخش روی نقشه خط‌چین و کم‌رنگ است تا شلوغ نشود؛ از آرنج به بعد توپر.
        fig.add_artist(Line2D([it['x'], elbow], [it['y'], it['y']], color=color,
                              lw=0.7, alpha=0.45, linestyle=(0, (2.2, 2.4)),
                              zorder=17))
        fig.add_artist(Line2D([elbow, elbow, text_x], [it['y'], ty, ty],
                              color=color, lw=0.9, alpha=0.9,
                              solid_joinstyle='miter', zorder=18))
        for px, py in ((it['x'], it['y']), (text_x, ty)):
            fig.add_artist(Line2D([px, px], [py - tick, py + tick],
                                  color=color, lw=0.7, alpha=0.45, zorder=18))
            fig.add_artist(Line2D([px], [py], marker='o', markersize=4.4,
                                  color=color, markeredgecolor=CY_BG_TOP,
                                  markeredgewidth=0.7, zorder=19))
        fig.text(text_x + num_gap, ty, '%02d' % rank, ha='left', va='center',
                 color=CY_GOLD, fontsize=size * 0.85,
                 fontproperties=_mono(fonts, size * 0.85), zorder=19)
        fig.text(text_x + name_gap, ty, it['text'], ha='left',
                 va='center', color=CY_TEXT, fontsize=size,
                 fontproperties=_mono(fonts, size), zorder=19)
    fig.add_artist(Line2D([text_x - num_gap * 0.55, text_x - num_gap * 0.55],
                          [bottom - 0.010, top + 0.012], color=CY_CYAN, lw=0.7,
                          alpha=0.5, zorder=17))
    return n


_COUNTY_SUFFIX = re.compile(r'\s+(county|province|shahrestan)\.?$', re.I)
# سه شهرستان در OSM تگ name:en ندارند؛ نام رومنی استانداردشان دستی گذاشته می‌شود
# تا هیچ شهرستانی در نقشه بی‌برچسب نماند.
FALLBACK_EN = {'گنبکی': 'Gonbaki', 'جازموریان': 'Jazmurian', 'مروست': 'Marvast'}


def county_label(row, lang):
    """نام شهرستان برای نمایش؛ در انگلیسی پسوند «County» حذف می‌شود تا جای برچسب باز شود."""
    if lang == 'en':
        name = str(row.get('name_en') or '').strip() or \
            FALLBACK_EN.get(str(row.get('name_fa') or '').strip(), '')
        return _COUNTY_SUFFIX.sub('', name)
    return row.get('name_fa') or ''


def _labels(ax, counties, heights, iso, fonts, args, unit):
    """نام شهرستان روی نمای بالا؛ شهرستان‌های خیلی کوچک بی‌برچسب می‌مانند."""
    if args.no_labels:
        return
    if args.label_lang == 'fa' and not fonts.ready():
        return
    total = counties['area_m2'].sum()
    lift = args.height * unit * 0.12
    for i in range(len(counties)):
        share = counties['area_m2'].iloc[i] / total
        if share < args.min_label:
            continue
        raw = county_label(counties.iloc[i], args.label_lang)
        text = fa(raw) if args.label_lang == 'fa' else raw
        if not text:
            continue
        c = counties.geometry.iloc[i].representative_point()
        x, y = iso.project(c.x, c.y, float(heights.iloc[i]) + lift)
        size = args.label_size * (0.78 + 0.60 * min(1.0, share / 0.05))
        ax.text(x, y, text,
                fontproperties=fonts.props(size, 'regular') if fonts.ready() else None,
                color=LABEL, ha='center', va='center', zorder=8)


def _title(fig, province, fonts, args, n_counties):
    ready = fonts.ready()
    if args.label_lang == 'en':
        main, sub = '%s Province' % province['en'], 'Iran  ·  %d counties' % n_counties
    else:
        main = fa(province['name_fa']) if ready else province['name_fa']
        sub = '%s  ·  %d counties' % (province['en'], n_counties)
    fig.text(0.5, 0.972, main, ha='center', va='top',
             color=TITLE_FA, zorder=10,
             fontproperties=fonts.props(21, 'bold') if ready else None)
    fig.text(0.5, 0.918, sub, ha='center', va='top',
             color=LABEL_DIM, fontsize=10, zorder=10)


def _footer(fig, fonts, args):
    fig.text(0.985, 0.015, args.attribution, ha='right', va='bottom',
             color=LABEL_DIM, fontsize=7.5, zorder=10)


# ------------------------------------------------------------------- main ---
def build_parser():
    p = argparse.ArgumentParser(description='نقشهٔ سه‌بعدی استان‌های ایران با مرز شهرستان‌ها')
    p.add_argument('--only', default='', help='فقط این نامک‌ها، جدا شده با ویرگول')
    p.add_argument('--out', default=str(REPO / 'outputs' / 'province-3d'))
    p.add_argument('--prefix', default='iran-')
    p.add_argument('--size', type=float, nargs=2, default=None, metavar=('W', 'H'),
                   help='اندازهٔ بوم؛ پیش‌فرض سبک سایبری ۱۶×۸ (نسبت دو در یک)')
    p.add_argument('--dpi', type=int, default=DPI)
    p.add_argument('--azimuth', type=float, default=38.0)
    p.add_argument('--elevation', type=float, default=38.0)
    p.add_argument('--height', type=float, default=0.075,
                   help='ارتفاع ستون بزرگ‌ترین شهرستان، نسبت به بزرگی استان')
    p.add_argument('--height-power', type=float, default=0.5,
                   help='توان ارتفاع: ۰ یکنواخت، ۰٫۵ متناسب با مساحت')
    p.add_argument('--style', choices=('cyber', 'classic'), default='cyber',
                   help='cyber: چیدمان سایبری با ستون فهرست (پیش‌فرض)؛ '
                        'classic: نقشهٔ قبلی بدون فهرست')
    p.add_argument('--label-size', type=float, default=9.5)
    p.add_argument('--margin', type=float, default=0.06)
    p.add_argument('--min-label', type=float, default=0.004,
                   help='کمینهٔ سهم مساحت برای نمایش نام')
    p.add_argument('--label-lang', choices=('en', 'fa'), default='en',
                   help='زبان برچسب شهرستان‌ها و عنوان: انگلیسی (پیش‌فرض) یا فارسی')
    p.add_argument('--no-labels', action='store_true')
    p.add_argument('--attribution', default=None)
    p.add_argument('--check', action='store_true')
    p.add_argument('--report', action='store_true',
                   help='گزارش پوشش مرز شهرستان‌ها بدون ساخت تصویر')
    return p


def main(argv=None):
    args = build_parser().parse_args(argv)
    try:
        import geopandas  # noqa: F401
        import matplotlib  # noqa: F401
    except ImportError:
        sys.stderr.write('نیازمندی نصب نیست: '
                         'pip install --break-system-packages geopandas matplotlib pyproj\n')
        return 2

    if args.attribution is None:
        args.attribution = '%s · %s' % (PROVINCE_SOURCE['attribution'],
                                         PROVINCE_SOURCE['data_licence'])
    if args.size is None:
        args.size = CY_FIG_SIZE if args.style == 'cyber' else FIG_SIZE

    provinces = load_provinces()
    if args.only:
        wanted = [s.strip() for s in args.only.split(',') if s.strip()]
        known = {p['slug'] for p in provinces}
        unknown = [s for s in wanted if s not in known]
        if unknown:
            sys.stderr.write('نامک ناشناخته: %s\n' % ', '.join(unknown))
            return 2
        provinces = [p for p in provinces if p['slug'] in wanted]

    out_dir = Path(args.out).expanduser()
    if args.check:
        return _check(provinces, out_dir, args)

    import geopandas as gpd
    prov_gdf = gpd.read_file(ensure_provinces())
    fa2slug = {norm(p['name_fa']): p['slug'] for p in provinces_all()}
    prov_gdf['slug'] = prov_gdf['name'].map(lambda x: fa2slug.get(norm(x), ''))
    fallback = {norm(p['en']): p['slug'] for p in provinces_all()}
    prov_gdf['slug'] = prov_gdf['slug'].where(
        prov_gdf['slug'] != '', prov_gdf['name:en'].map(lambda x: fallback.get(norm(x), '')))
    missing = prov_gdf[prov_gdf['slug'] == '']['name'].tolist()
    if missing:
        sys.stderr.write('مرز استان پیدا نشد: %s\n' % missing)
    proj = prov_gdf[prov_gdf['slug'] != ''].to_crs(IRAN_ALBERS)

    county_dir = ensure_counties()
    repo_counties = load_counties()
    fonts = Fonts()
    out_dir.mkdir(parents=True, exist_ok=True)

    failures, warnings_total = 0, 0
    for i, province in enumerate(provinces, 1):
        sys.stderr.write('[%d/%d] %s\n' % (i, len(provinces), province['name_fa']))
        row = proj[proj['slug'] == province['slug']]
        if row.empty:
            failures += 1
            continue
        geom = row.geometry.iloc[0]
        if not geom.is_valid:
            geom = geom.buffer(0)
        try:
            counties, unmatched = build_counties(county_dir, province,
                                                 repo_counties, geom, proj.crs)
            if unmatched:
                warnings_total += len(unmatched)
                sys.stderr.write('  بدون تطابق با مرجع: %s\n' % ', '.join(unmatched))
            if counties.empty:
                failures += 1
                sys.stderr.write('  شهرستانی یافت نشد.\n')
                continue
            render(province, counties, out_dir, args, fonts, geom)
        except Exception as exc:  # noqa: BLE001
            failures += 1
            sys.stderr.write('  ناموفق: %s\n' % exc)

    print('%d نقشه در %s' % (len(provinces) - failures, out_dir))
    if warnings_total:
        print('%d نام شهرستان با مرجع مخزن تطبیق نداشت.' % warnings_total)
    return 1 if failures else 0


def provinces_all():
    return load_provinces()


def _check(provinces, out_dir, args):
    missing, present = [], 0
    for province in provinces:
        path = out_dir / ('%s%s.png' % (args.prefix, province['slug']))
        if path.is_file():
            present += 1
        else:
            missing.append(path.name)
    print('پوشه: %s' % out_dir)
    print('موجود: %d' % present)
    if missing:
        print('ناموجود (%d): %s' % (len(missing), ', '.join(missing)))
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())