#!/usr/bin/env python3
"""نقشهٔ شهری/استانی به سبک deMAr برای دو استان قم و فارس.

دو مدل کارتایوگرافی پیاده شده است:

* ``sand`` — کاغذ کرم، جنگل سبز زیتونی، جادهٔ سیاه، قاب عنوان مشکی (نمونهٔ رم)
* ``ink``  — زمین آبی‌فام روشن، بافت شهری نارنجی مرجانی، جوهر سرمه‌ای، قاب نارنجی (نمونهٔ تهران)

هر دو مدل هم **خطوط شهری** (خیابان‌های ریز) و هم **خطوط جاده‌ای** (بزرگراه، راه اصلی)
را با سلسله‌مراتب ضخامت متفاوت می‌کشند.

لایهٔ خیابان از Overpass API گرفته می‌شود و در ``~/.cache/sa-province-maps/streets``
کش می‌شود. اگر Overpass در دسترس نباشد (شبکهٔ بسته، DNS بلاک)، ابزار با هشدار
واضح **فقط مرز شهرستان‌ها** را می‌کشد و هرگز جادهٔ ساختگی نمی‌سازد. با
``--require-streets`` در آن حالت کار با خطا متوقف می‌شود.

اجرا::

    python3 tools/maps/make_city_maps.py                 # قم و فارس، هر دو مدل
    python3 tools/maps/make_city_maps.py --only fars --styles ink
    python3 tools/maps/make_city_maps.py --require-streets
    python3 tools/maps/make_city_maps.py --check
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import make_province_3d as core  # noqa: E402  (نیم‌فاصله، مرجع استان‌ها، برش هندسه)

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
CACHE_DIR = Path(os.environ.get('SA_MAP_CACHE') or
                 Path.home() / '.cache' / 'sa-province-maps')
STREET_DIR = CACHE_DIR / 'streets'

DEFAULT_PROVINCES = ('qom', 'fars')
DEFAULT_STYLES = ('sand', 'ink')

OVERPASS_ENDPOINTS = (
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass.osm.ch/api/interpreter',
)

ROAD_CLASSES = (
    ('motorway', 3.4), ('motorway_link', 2.6), ('trunk', 2.8), ('trunk_link', 2.1),
    ('primary', 2.0), ('primary_link', 1.6), ('secondary', 1.35), ('secondary_link', 1.1),
    ('tertiary', 1.0), ('tertiary_link', 0.85), ('residential', 0.62),
    ('unclassified', 0.55), ('living_street', 0.5), ('service', 0.36),
    ('pedestrian', 0.4), ('footway', 0.3), ('path', 0.28), ('track', 0.3),
)

# ------------------------------------------------------------------ پالت ----
STYLES = {
    'sand': {
        'paper': '#ece6d6', 'land': '#e4dcc6', 'county': '#ded5bd',
        'wood': '#6d7a4e', 'scrub': '#9aa37a', 'grass': '#aab18a',
        'park': '#7d8a5c', 'water': '#9dbdb8', 'river': '#8fb4ae',
        'built': '#d9c9a8', 'built_alt': '#cdb894', 'industrial': '#cfc0a0',
        'ink': '#17181a', 'ink_soft': '#4c4e50', 'ink_faint': '#8b8d8f',
        'road_core': '#f2efe4', 'panel': '#f4f0e4', 'frame': '#17181a',
        'accent': '#17181a', 'note': '#6d6a60',
    },
    'ink': {
        'paper': '#e9edef', 'land': '#e6ebec', 'county': '#e2e8e9',
        'wood': '#8fa88c', 'scrub': '#b3c2ab', 'grass': '#c2cdb8',
        'park': '#9db394', 'water': '#b9cfd6', 'river': '#a8c3cc',
        'built': '#e9a883', 'built_alt': '#f0bd9c', 'industrial': '#e0906a',
        'ink': '#16242a', 'ink_soft': '#43575e', 'ink_faint': '#8a9aa1',
        'road_core': '#f7f8f7', 'panel': '#f6f1e4', 'frame': '#dd6230',
        'accent': '#dd6230', 'note': '#8a8f92',
    },
}


# --------------------------------------------------------------- Overpass ---
def overpass_query(bbox):
    """پرس‌وجوی یک‌بارهٔ جاده، کاربری، آب و پارک در یک کادر."""
    south, west, north, east = bbox
    box = '%s,%s,%s,%s' % (south, west, north, east)
    return """
[out:json][timeout:180];
(
  way["highway"~"^(%s)"](%s);
  relation["highway"](%s);
  way["landuse"~"^(residential|industrial|commercial|retail|farmland)$"](%s);
  relation["landuse"](%s);
  way["leisure"~"^(park|garden|pitch|golf_course)$"](%s);
  relation["leisure"](%s);
  way["natural"~"^(wood|scrub|grassland|heath)$"](%s);
  relation["natural"](%s);
  way["waterway"](%s);
  way["natural"="water"](%s);
  relation["natural"="water"](%s);
);
out geom;
""" % ('|'.join(k for k, _ in ROAD_CLASSES), box, box, box, box, box, box,
       box, box, box, box, box)


def fetch_streets(slug, bbox, force=False, timeout=240):
    """GeoJSON خام OSM را از Overpass می‌گیرد و در حافظهٔ نهان نگه می‌دارد."""
    STREET_DIR.mkdir(parents=True, exist_ok=True)
    target = STREET_DIR / ('%s.json' % slug)
    if target.is_file() and not force:
        return json.loads(target.read_text(encoding='utf-8'))
    query = overpass_query(bbox).encode('utf-8')
    last = None
    for endpoint in OVERPASS_ENDPOINTS:
        for attempt in range(2):
            try:
                req = urllib.request.Request(
                    endpoint, data=query,
                    headers={'User-Agent': 'sarzaminaryan-maps/1.0'})
                with urllib.request.urlopen(req, timeout=timeout) as resp:
                    payload = json.loads(resp.read().decode('utf-8'))
                target.write_text(json.dumps(payload), encoding='utf-8')
                return payload
            except (urllib.error.URLError, OSError, ValueError,
                    TimeoutError) as exc:            # noqa: PERF203
                last = exc
                time.sleep(3 + attempt * 5)
    raise RuntimeError('Overpass در دسترس نیست (%s)' % last)


# ---------------------------------------------------------------- هندسه ----
def province_bbox_lonlat(slug, provinces):
    """کادر جغرافیایی استان از روی فایل مرز، برای محدود کردن پرس‌وجو."""
    import geopandas as gpd

    frame = gpd.read_file(core.ensure_provinces())
    fa2slug = {core.norm(p['name_fa']): p['slug'] for p in provinces}
    en2slug = {core.norm(p['en']): p['slug'] for p in provinces}
    frame['slug'] = frame['name'].map(lambda x: fa2slug.get(core.norm(x), ''))
    frame['slug'] = frame['slug'].where(
        frame['slug'] != '',
        frame['name:en'].map(lambda x: en2slug.get(core.norm(x), '')))
    row = frame[frame['slug'] == slug]
    if row.empty:
        raise SystemExit('مرز استان %s پیدا نشد.' % slug)
    minx, miny, maxx, maxy = row.total_bounds
    pad_x = (maxx - minx) * 0.02
    pad_y = (maxy - miny) * 0.02
    return (miny - pad_y, minx - pad_x, maxy + pad_y, maxx + pad_x), row.geometry.iloc[0]


def lonlat_to_geojson(payload, transformer):
    """Overpass «out geom» را به LineString/Polygon تبدیل می‌کند."""
    import shapely.geometry as sg

    roads, areas, rivers = [], [], []
    for element in payload.get('elements', []):
        tags = element.get('tags') or {}
        geom = element.get('geometry')
        if not geom:
            continue
        coords = [(g['lon'], g['lat']) for g in geom if 'lon' in g and 'lat' in g]
        if len(coords) < 2:
            continue
        try:
            projected = [transformer.transform(x, y) for x, y in coords]
        except Exception:                             # noqa: BLE001
            continue
        if element.get('type') == 'way':
            closed = len(coords) > 2 and coords[0] == coords[-1]
            if closed:
                poly = sg.Polygon(projected)
                if not poly.is_valid:
                    poly = poly.buffer(0)
                kind = 'area'
            else:
                poly = sg.LineString(projected)
                kind = 'line'
        else:                                          # relation → چندضلعی چندحلقه‌ای
            poly = sg.Polygon(projected)
            if not poly.is_valid:
                poly = poly.buffer(0)
            kind = 'area'

        if kind == 'line':
            if tags.get('highway') in dict(ROAD_CLASSES):
                roads.append({'class': tags['highway'], 'geometry': poly})
            elif tags.get('waterway'):
                rivers.append(poly)
        else:
            if not poly.is_empty and poly.area > 0:
                areas.append({'tags': tags, 'geometry': poly})
    return {'roads': roads, 'areas': areas, 'rivers': rivers}


def clip_to(counties, prov_geom, feature_map):
    """برش همهٔ هندسه‌های خیابان به مرز استان."""
    out = {key: [] for key in feature_map}
    for key, rows in feature_map.items():
        for row in rows:
            geom = row['geometry'] if isinstance(row, dict) else row
            if key == 'roads':
                clipped = geom.intersection(prov_geom)
                if not clipped.is_empty:
                    out[key].append({**row, 'geometry': clipped})
            else:
                if geom.intersects(prov_geom):
                    out[key].append(row)
    return out


def county_fallback(counties):
    """وقتی Overpass نیست: مرز شهرستان‌ها نقش خطوط شهری را بازی می‌کند."""
    roads = []
    for _, row in counties.iterrows():
        for ring in core._rings(row.geometry)[0]:
            pts = ring[:-1]
            for i in range(len(pts)):
                roads.append({'class': 'residential',
                              'geometry': _segment(pts[i], pts[(i + 1) % len(pts)])})
    return {'roads': roads, 'areas': [], 'rivers': []}


def _segment(a, b):
    import shapely.geometry as sg
    if a == b:
        return None
    return sg.LineString([a, b])


# ---------------------------------------------------------------- رسم ------
def _bounds_of(items):
    xs, ys = [], []
    for item in items:
        bounds = item.bounds if hasattr(item, 'bounds') else item['geometry'].bounds
        xs.extend((bounds[0], bounds[2]))
        ys.extend((bounds[1], bounds[3]))
    if not xs:
        return None
    return min(xs), min(ys), max(xs), max(ys)


def _paths(geoms):
    """هندسهٔ shapely را به فهرست آرایهٔ مختصات برای مجموعه‌های matplotlib تبدیل می‌کند.

    برش با مرز استان می‌تواند چندضلعی/چندخطی تولید کند، پس همهٔ حالت‌ها باز شده
    و حفره‌ها نادیده گرفته می‌شوند.
    """
    import shapely.geometry as sg

    out = []
    for geom in geoms:
        if geom is None or geom.is_empty:
            continue
        parts = getattr(geom, 'geoms', [geom])
        for part in parts:
            if isinstance(part, sg.Polygon):
                out.append(list(part.exterior.coords))
            elif isinstance(part, (sg.LineString, sg.LinearRing)):
                out.append(list(part.coords))
    return out


def draw_style(ax, palette, counties, prov_geom, layers, streets_ok):
    """کشیدن لایه‌ها به ترتیب کارتایوگرافی."""
    import matplotlib.collections as mc

    ax.set_facecolor(palette['paper'])
    _poly(ax, prov_geom, palette['land'], zorder=1)

    areas = layers['areas']
    groups = [
        ('natural', ('wood', 'forest'), palette['wood'], 2),
        ('natural', ('scrub', 'heath'), palette['scrub'], 2),
        ('natural', ('grassland',), palette['grass'], 2),
        ('leisure', ('park', 'garden'), palette['park'], 3),
        ('landuse', ('industrial',), palette['industrial'], 3),
        ('landuse', ('commercial', 'retail'), palette['built_alt'], 3),
        ('landuse', ('residential',), palette['built'], 3),
        ('natural', ('water',), palette['water'], 4),
    ]
    for key, values, colour, order in groups:
        polys = _paths(a['geometry'] for a in areas
                       if (a['tags'].get(key) or '') in values)
        if polys:
            ax.add_collection(mc.PolyCollection(
                polys, facecolors=colour, edgecolors='none', zorder=order))
    rivers = _paths(layers['rivers'])
    if rivers:
        ax.add_collection(mc.LineCollection(
            rivers, colors=palette['river'], linewidths=1.1, zorder=4))

    # شهرستان‌ها: خط نازک مرزی، زیر جاده‌ها. آفلاین که پوشش زمین نیست،
    # پس سطح شهرستان‌ها رنگ می‌گیرد تا نقشه خالی نباشد.
    county_paths = _paths(counties.geometry)
    if county_paths:
        ax.add_collection(mc.PolyCollection(
            county_paths,
            facecolors=palette['county'] if not streets_ok else 'none',
            edgecolors=palette['ink_faint'], linewidths=0.35, zorder=5))

    roads = layers['roads']
    for cls, width in ROAD_CLASSES:                     # جاده‌ها: درشت به نازک
        segs = _paths(r['geometry'] for r in roads if r['class'] == cls)
        if not segs:
            continue
        major = width >= 1.0
        ax.add_collection(mc.LineCollection(
            segs, colors=palette['ink'], linewidths=width + 1.0,
            zorder=8, capstyle='round'))
        ax.add_collection(mc.LineCollection(
            segs, colors=palette['road_core'] if major else palette['paper'],
            linewidths=width, zorder=9, capstyle='round'))

    # مرز استان روی همه‌چیز
    ax.add_collection(mc.PolyCollection(
        _paths([prov_geom]), facecolors='none', edgecolors=palette['ink'],
        linewidths=1.8, zorder=10))

    if not streets_ok:
        ax.figure.text(0.02, 0.995,
                       'OSM STREET LAYER UNAVAILABLE — '
                       'COUNTY BOUNDARIES ONLY',
                       ha='left', va='top', fontsize=8, color=palette['accent'],
                       fontfamily='DejaVu Sans Mono', zorder=13)


def _poly(ax, geom, colour, zorder):
    """چندضلعی ساده را به‌صورت مجموعه وصله اضافه می‌کند."""
    from matplotlib.patches import Polygon
    from matplotlib.collections import PatchCollection
    import shapely.geometry as sg

    polys = geom.geoms if isinstance(geom, sg.MultiPolygon) else [geom]
    patches = [Polygon(list(p.exterior.coords)) for p in polys if not p.is_empty]
    patch = PatchCollection(patches, facecolors=colour, edgecolors='none',
                            zorder=zorder)
    ax.add_collection(patch)
    return patch


def _letterspace(text, gap=' '):
    """فاصله‌گذاری حروف مثل تیترِ نمونه‌ها: R O M E."""
    chars = [gap * 3 if char == ' ' else char for char in text.upper()]
    return gap.join(chars)


def title_block(ax, province, palette, center_lat, center_lon):
    """قاب عنوانِ حاشیه‌نویسی، دقیقاً مثل نمونه‌ها."""
    x0, y0, w, h = 0.31, -0.175, 0.38, 0.135
    ax.add_patch(_frame(ax, x0, y0, w, h, palette))
    ax.text(x0 + w / 2, y0 + h * 0.68, _letterspace(province['en']),
            transform=ax.transAxes, ha='center', va='center',
            fontsize=17, color=palette['frame'], fontfamily='DejaVu Serif',
            fontweight='bold', zorder=12,
            clip_on=False)
    persian = core.fa(province['name_fa']) or province['name_fa']
    ax.text(x0 + w / 2, y0 + h * 0.32, persian,
            transform=ax.transAxes, ha='center', va='center',
            fontsize=11, color=palette['frame'],
            fontfamily='DejaVu Sans', zorder=12, clip_on=False)
    ax.text(x0 + w / 2, y0 + h * 0.10,
            '%.2f°%s / %.2f°%s' % (
                abs(center_lat), 'N' if center_lat >= 0 else 'S',
                abs(center_lon), 'E' if center_lon >= 0 else 'W'),
            transform=ax.transAxes, ha='center', va='center',
            fontsize=6.5, color=palette['note'],
            fontfamily='DejaVu Sans Mono', zorder=12, clip_on=False)
    ax.text(0.98, 0.995, 'OpenStreetMap contributors · ODbL 1.0',
            transform=ax.figure.transFigure, ha='right', va='top',
            fontsize=7, color=palette['note'],
            fontfamily='DejaVu Sans Mono', zorder=13, clip_on=False)


def _frame(ax, x0, y0, w, h, palette):
    from matplotlib.patches import Rectangle
    panel = Rectangle((x0, y0), w, h, transform=ax.transAxes, zorder=11,
                      facecolor=palette['panel'], edgecolor='none')
    edge = Rectangle((x0, y0), w, h, transform=ax.transAxes, zorder=12,
                     facecolor='none', edgecolor=palette['frame'],
                     linewidth=2.4)
    for patch in (panel, edge):
        patch.set_clip_on(False)      # قاب زیرِ کادرِ نقشه است
        ax.add_patch(patch)
    return panel


def scale_bar(ax, palette):
    """نوار مقیاس: طولی که روی کادر نقشه جا شود، از فهرست فاصله‌های گرد.

    طول واقعی یک درجهٔ طول جغرافیایی روی صفحه با Geod سنجیده می‌شود و بر
    نسبتِ تصویر معادل تقسیم می‌گردد تا نوار دقیقاً همان فاصله را نشان دهد.
    """
    from pyproj import Geod, Transformer

    x0, y0 = 0.02, 0.018
    box = ax.get_window_extent()                 # پیکسل، پس از fig.canvas.draw()
    data_x = ax.get_xlim()[1] - ax.get_xlim()[0]
    tr = Transformer.from_crs('EPSG:4326', core.IRAN_ALBERS, always_xy=True)
    origin = tr.transform(51.5, 32.0)
    edge = tr.transform(52.5, 32.0)
    _, _, ground_m = Geod(ellps='WGS84').inv(51.5, 32.0, 52.5, 32.0)
    page_m = abs(edge[0] - origin[0])
    if page_m <= 0 or ground_m <= 0:
        return
    metres_per_unit = ground_m / page_m
    metres_per_pixel = data_x * metres_per_unit / max(box.width, 1.0)
    choices = (1e3, 2e3, 5e3, 10e3, 20e3, 50e3, 100e3, 200e3, 500e3)
    distance = min(choices, key=lambda d: abs(d - metres_per_pixel * 140.0))
    frac = distance / metres_per_unit / data_x
    if not 0.02 < frac < 0.9:
        return
    ax.add_patch(_bar(ax, x0, y0, frac, palette))
    ax.text(x0 + frac / 2, y0 + 0.030, '%g km' % (distance / 1000.0),
            transform=ax.transAxes, ha='center', va='bottom', fontsize=7.5,
            color=palette['ink_soft'], fontfamily='DejaVu Sans Mono',
            zorder=12, clip_on=False)


def _bar(ax, x, y, w, palette):
    from matplotlib.patches import Rectangle
    patch = Rectangle((x, y), w, 0.008, transform=ax.transAxes, zorder=12,
                      facecolor=palette['ink'], edgecolor='none')
    patch.set_clip_on(False)
    ax.add_patch(patch)
    return patch


# ------------------------------------------------------------------ main ----
def build_parser():
    p = argparse.ArgumentParser(
        description='نقشهٔ شهری به سبک deMAr برای قم و فارس')
    p.add_argument('--only', default=','.join(DEFAULT_PROVINCES))
    p.add_argument('--styles', default=','.join(DEFAULT_STYLES),
                   help='sand,ink')
    p.add_argument('--out', default=str(REPO / 'outputs' / 'province-city'))
    p.add_argument('--prefix', default='iran-')
    p.add_argument('--size', type=float, nargs=2, default=(12.0, 9.0),
                   metavar=('W', 'H'))
    p.add_argument('--dpi', type=int, default=200)
    p.add_argument('--margin', type=float, default=0.06)
    p.add_argument('--require-streets', action='store_true',
                   help='اگر Overpass در دسترس نبود، خطا بده')
    p.add_argument('--refresh', action='store_true',
                   help='حافظهٔ نهان خیابان را دوباره بگیر')
    p.add_argument('--attribution', default=None)
    p.add_argument('--check', action='store_true')
    return p


def main(argv=None):
    args = build_parser().parse_args(argv)
    try:
        import geopandas  # noqa: F401
        import matplotlib  # noqa: F401
        import pyproj  # noqa: F401
    except ImportError:
        sys.stderr.write('نیازمندی نصب نیست: '
                         'pip install --break-system-packages geopandas matplotlib pyproj\n')
        return 2

    if args.attribution is None:
        args.attribution = '%s · %s' % (core.PROVINCE_SOURCE['attribution'],
                                         core.PROVINCE_SOURCE['data_licence'])
    provinces = core.load_provinces()
    wanted = [s.strip() for s in args.only.split(',') if s.strip()]
    known = {p['slug'] for p in provinces}
    unknown = [s for s in wanted if s not in known]
    if unknown:
        sys.stderr.write('نامک ناشناخته: %s\n' % ', '.join(unknown))
        return 2
    provinces = [p for p in provinces if p['slug'] in wanted]
    styles = [s.strip() for s in args.styles.split(',') if s.strip()]
    bad = [s for s in styles if s not in STYLES]
    if bad:
        sys.stderr.write('مدل ناشناخته: %s (مجاز: %s)\n'
                         % (', '.join(bad), ', '.join(STYLES)))
        return 2

    out_dir = Path(args.out).expanduser()
    if args.check:
        return _check(provinces, styles, out_dir, args)

    import geopandas as gpd
    transformer = pyproj.Transformer.from_crs(
        'EPSG:4326', core.IRAN_ALBERS, always_xy=True)

    frame = gpd.read_file(core.ensure_provinces())
    fa2slug = {core.norm(p['name_fa']): p['slug'] for p in core.provinces_all()}
    en2slug = {core.norm(p['en']): p['slug'] for p in core.provinces_all()}
    frame['slug'] = frame['name'].map(lambda x: fa2slug.get(core.norm(x), ''))
    frame['slug'] = frame['slug'].where(
        frame['slug'] != '', frame['name:en'].map(lambda x: en2slug.get(core.norm(x), '')))
    missing = frame[frame['slug'] == '']['name'].tolist()
    if missing:
        sys.stderr.write('مرز استان پیدا نشد: %s\n' % missing)
    projected = frame[frame['slug'] != ''].to_crs(core.IRAN_ALBERS)
    county_dir = core.ensure_counties()
    repo_counties = core.load_counties()
    out_dir.mkdir(parents=True, exist_ok=True)

    failures = 0
    for i, province in enumerate(provinces, 1):
        sys.stderr.write('[%d/%d] %s\n' % (i, len(provinces), province['name_fa']))
        row = projected[projected['slug'] == province['slug']]
        if row.empty:
            failures += 1
            continue
        geom = row.geometry.iloc[0]
        if not geom.is_valid:
            geom = geom.buffer(0)
        counties, _ = core.build_counties(county_dir, province, repo_counties,
                                          geom, projected.crs)
        bbox, lonlat_geom = province_bbox_lonlat(province['slug'], core.provinces_all())
        center_lat = lonlat_geom.centroid.y
        center_lon = lonlat_geom.centroid.x

        layers, streets_ok = None, False
        try:
            payload = fetch_streets(province['slug'], bbox, force=args.refresh)
            layers = lonlat_to_geojson(payload, transformer)
            layers = clip_to(counties, geom, layers)
            streets_ok = True
            sys.stderr.write('  OSM: %d جاده، %d پهنه\n'
                             % (len(layers['roads']), len(layers['areas'])))
        except RuntimeError as exc:
            sys.stderr.write('  %s\n' % exc)
            if args.require_streets:
                failures += 1
                continue
            layers = county_fallback(counties)
            sys.stderr.write('  ادامه با مرز شهرستان‌ها به‌جای خیابان‌ها.\n')

        for style in styles:
            try:
                _render(province, style, counties, geom, layers, streets_ok,
                        center_lat, center_lon, out_dir, args)
            except Exception as exc:                    # noqa: BLE001
                failures += 1
                sys.stderr.write('  ناموفق (%s): %s\n' % (style, exc))

    print('%d نقشه در %s' % ((len(provinces) - failures) * len(styles), out_dir))
    return 1 if failures else 0


def _render(province, style, counties, geom, layers, streets_ok,
            center_lat, center_lon, out_dir, args):
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt

    palette = STYLES[style]
    fig = plt.figure(figsize=args.size, facecolor=palette['paper'])
    ax = fig.add_axes([0.02, 0.15, 0.96, 0.83])
    ax.set_aspect('equal', adjustable='box')
    ax.axis('off')

    draw_style(ax, palette, counties, geom, layers, streets_ok)

    xs, ys = [], []
    for item in [geom] + [r['geometry'] for r in layers['roads']] + \
            [a['geometry'] for a in layers['areas']]:
        bounds = item.bounds
        xs.extend((bounds[0], bounds[2]))
        ys.extend((bounds[1], bounds[3]))
    if not xs:
        xs, ys = geom.bounds[0::2], geom.bounds[1::2]
    dx, dy = max(xs) - min(xs), max(ys) - min(ys)
    pad_x, pad_y = dx * args.margin, dy * args.margin
    ax.set_xlim(min(xs) - pad_x, max(xs) + pad_x)
    ax.set_ylim(min(ys) - pad_y, max(ys) + pad_y)
    fig.canvas.draw()
    scale_bar(ax, palette)
    title_block(ax, province, palette, center_lat, center_lon)

    suffix = '' if streets_ok else '-nostreets'
    path = out_dir / ('%s%s-%s%s.png' % (args.prefix, province['slug'], style, suffix))
    fig.savefig(path, dpi=args.dpi, facecolor=palette['paper'])
    plt.close(fig)
    return path


def _check(provinces, styles, out_dir, args):
    print('پوشه: %s' % out_dir)
    for province in provinces:
        cached = (STREET_DIR / ('%s.json' % province['slug'])).is_file()
        print('%s  ·  خیابان‌های OSM در حافظهٔ نهان: %s'
              % (province['slug'], 'بله' if cached else 'نه'))
    missing = 0
    for province in provinces:
        for style in styles:
            names = ['%s%s-%s.png' % (args.prefix, province['slug'], style)]
            if not any((out_dir / n).is_file() for n in names):
                missing += 1
    print('نقشه‌های موجود: %d از %d' % (len(provinces) * len(styles) - missing,
                                       len(provinces) * len(styles)))
    return 0 if missing == 0 else 1


if __name__ == '__main__':
    sys.exit(main())
