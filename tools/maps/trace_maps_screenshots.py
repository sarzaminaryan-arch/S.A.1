#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""رَردیابی نقشه از اسکرین‌شات گوگل‌مپ و رندر آن به سبک پوستر deMAr.

گوگل‌مپ API عمومی ندارد، اما اسکرین‌شات دارد هندسهٔ واقعی را: پیکسل‌های رنگیِ
جاده را می‌خوانیم، شبکه را اسکلت‌سازی و به خطوط برداری تبدیل می‌کنیم و همان را
با پالت و قابِ `make_city_views.py` دوباره می‌کشیم.

چه چیزی از عکس خوانده می‌شود:

* **جاده** — پیکسل‌های آبی‌فام (کنارهٔ تیره + پرکنندهٔ روشن). رنگ متنِ نگارگان
  خنثی است، پس برچسب‌ها خودبه‌خود حذف می‌شوند.
* **پارک** — سبز روشن. **آب** — آبیِ روشن با سبز هم‌سطح.
* **بافت ساخت‌وسان** — خاکستری/سفید خنثی.
* **مرز** — خط‌چین قرمز گوگل‌مپ (محدودهٔ شهر/شهرستان).

پهنای هر خط از «تبدیل فاصله» گرفته می‌شود و به سلسله‌مراتب بزرگراه تا کوچه
تبدیل می‌گردد؛ یعنی همان چیزی که گوگل با ضخامت خط نشان می‌دهد.

روی خودِ تصویر نوشته می‌شود که هندسه از اسکرین‌شات بازسازی شده و برای ناوبری
نیست. نام و برچسب گوگل کپی نمی‌شود؛ فقط هندسه بازتولید می‌گردد.

اجرا::

    python3 tools/maps/trace_maps_screenshots.py --list INPUT_DIR
    python3 tools/maps/trace_maps_screenshots.py INPUT_DIR --out-dir outputs/traced
"""

from __future__ import annotations

import argparse
import json
import math
import sys
from pathlib import Path

import numpy as np

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
sys.path.insert(0, str(HERE))

import make_city_views as views  # noqa: E402

# پنجرهٔ نقشه: بالای نوار جست‌وجو و چیپ‌ها، پایینِ کارتِ پایین صفحه.
WINDOW = (400, 1840)
NOTE = 'GEOMETRY TRACED FROM GOOGLE MAPS SCREENSHOT — NOT FOR NAVIGATION'

# آستانه‌های رنگ (RGB ۰..۲۵۵) بر پایهٔ نمونه‌برداری از اسکرین‌شات‌های شیراز
ROAD_TINT = 10              # b - r : فیلمِ جاده آبی‌فام است، متن خنثی
LIGHT_MIN = 234             # روشناییِ پیکسل برای هستهٔ روشنِ جاده


def load_rgb(path):
    from PIL import Image
    img = Image.open(path).convert('RGB')
    return np.asarray(img)[WINDOW[0]:WINDOW[1]]


def classify(rgb):
    """تفکیک پیکسل‌های نقشهٔ گوگل‌مپ به لایه‌های رنگی."""
    r = rgb[..., 0].astype(np.int16)
    g = rgb[..., 1].astype(np.int16)
    b = rgb[..., 2].astype(np.int16)
    lo = rgb.min(axis=2).astype(np.int16)
    hi = rgb.max(axis=2).astype(np.int16)
    tint = b - r
    water = (tint >= 18) & (g - r >= 10) & (b > g) & (lo >= 140)
    park = (g - r >= 12) & ((g - b) >= 12)
    road = (tint >= ROAD_TINT) & ~water & ~park & (lo < 250)
    boundary = ((r - g) >= 50) & ((r - b) >= 50) & (r >= 120)
    # متنِ برچسب‌ها خنثی و تیره است؛ هالهٔ روشنِ اطرافشان باید از جاده پاک شود
    glyph = ((hi - lo) <= 12) & (lo < 150)
    return {'road': road & ~glyph, 'water': water, 'park': park,
            'boundary': boundary}


def _disk(radius):
    y, x = np.mgrid[-radius:radius + 1, -radius:radius + 1]
    return (x * x + y * y) <= radius * radius + 0.5


def clean_roads(mask, max_half_width=9.0):
    """جدا کردن شبکهٔ جاده از دکمه‌ها، آیکن‌ها و دانه‌های نویز.

    معیار، «پهنا» است نه بیشینهٔ کل مؤلفه: شبکهٔ خیابان یک مؤلفهٔ بزرگ است و
    اگر دکمهٔ رابط کاربری به آن چسبیده باشد، بیشینهٔ کل مؤلفه بزرگ می‌شود و کل
    شبکه حذف می‌گردد. پس پهنای زیاد پیکسل‌به‌پیکسل حذف می‌شود و بعد فقط
    شبکه‌ای که به آن چسبیده نگه داشته می‌شود.
    """
    from scipy import ndimage as ndi

    work = ndi.binary_closing(mask, np.ones((3, 3)))
    if not work.any():
        return work
    dist = ndi.distance_transform_edt(work)
    thin = work & (dist <= max_half_width)
    labels, count = ndi.label(thin)
    if count == 0:
        return thin
    sizes = ndi.sum(thin, labels, range(1, count + 1))
    objects = ndi.find_objects(labels)
    keep = np.zeros(count + 1, bool)
    for i, sl in enumerate(objects):
        if sizes[i] < 40:
            continue
        box = (sl[0].stop - sl[0].start) * (sl[1].stop - sl[1].start)
        h = sl[0].stop - sl[0].start
        w = sl[1].stop - sl[1].start
        if box and sizes[i] / float(box) > 0.45 and min(h, w) < 24:
            continue                       # حرفِ برچسب که هاله‌اش ماند
        keep[i + 1] = True
    net = keep[labels]
    if not net.any():
        return net
    return net


def vectorise_roads(mask, pixel_scale=1.0, width_mask=None):
    """اسکلت را به فهرست خطوط با پهنای هر خط تبدیل می‌کند."""
    from scipy import ndimage as ndi
    from skimage.morphology import skeletonize

    if not mask.any():
        return []
    dist = ndi.distance_transform_edt(mask if width_mask is None else width_mask)
    skel = skeletonize(mask)
    lines = _trace_skeleton(skel, dist)
    out = []
    for pts, width in lines:
        if len(pts) < 3:
            continue
        out.append({'class': _road_class(width),
                    'points': [[round(x * pixel_scale, 1),
                                round(-y * pixel_scale, 1)] for x, y in pts],
                    'width': round(width * pixel_scale, 2)})
    return out


def _trace_skeleton(skel, dist, min_len=14):
    """خطوط را از اسکلت بیرون می‌کشد: هر پیکسل با درجهٔ ۲ داخل یک شاخه می‌ماند."""
    pts = np.argwhere(skel)                     # (y, x)
    index = {tuple(p): i for i, p in enumerate(pts)}
    neighbours = {}
    for p in pts:
        y, x = p
        nb = []
        for dy in (-1, 0, 1):
            for dx in (-1, 0, 1):
                if dy == 0 and dx == 0:
                    continue
                q = (y + dy, x + dx)
                if q in index:
                    nb.append(index[q])
        neighbours[index[tuple(p)]] = nb
    visited = np.zeros(len(pts), bool)
    lines = []
    for start, nb in neighbours.items():
        if visited[start] or len(nb) > 2:
            continue
        chain = [start]
        visited[start] = True
        cur = start
        while True:
            nxt = [n for n in neighbours[cur] if not visited[n]]
            if not nxt:
                break
            n = nxt[0]
            visited[n] = True
            chain.append(n)
            cur = n
        if len(chain) < min_len:
            continue
        coords = [(float(pts[i][1]), float(pts[i][0])) for i in chain]
        width = 2.0 * float(np.median(
            [dist[int(y), int(x)] for x, y in coords]))
        lines.append((coords, width))
    # شاخه‌های متصل به گره (junction) جدا رها می‌شوند؛ برای پوستر کافی است
    return lines


WIDTH_CLASS = ((9.0, 'motorway'), (6.0, 'trunk'), (4.0, 'primary'),
               (2.6, 'secondary'))


def _road_class(width):
    for limit, name in WIDTH_CLASS:
        if width >= limit:
            return name
    return 'street'


def vectorise_areas(mask, kind, pixel_scale=1.0, min_area=250.0):
    """پهنه‌های سبز/آب/ساخت‌وسان را به چندضلعی ساده تبدیل می‌کند."""
    from scipy import ndimage as ndi
    from skimage.measure import find_contours

    if not mask.any():
        return []
    labels, count = ndi.label(mask)
    sizes = ndi.sum(mask, labels, range(1, count + 1))
    out = []
    for i in range(count):
        if sizes[i] < min_area:
            continue
        contours = find_contours(labels == (i + 1), 0.5)
        if not contours:
            continue
        contour = max(contours, key=len)
        if len(contour) < 6:
            continue
        pts = [[round((x + 0.5) * pixel_scale, 1),
                round(-(y + 0.5) * pixel_scale, 1)] for y, x in contour]
        out.append({'kind': kind, 'points': _simplify(pts, 2.0)})
    return out


def _simplify(points, tolerance=1.5):
    """دو-پیکل-پیکل ساده‌سازی تا حجم برداری قابل‌قبول بماند."""
    if len(points) < 3:
        return points
    keep = [0]
    px, py = points[0]
    for i, (x, y) in enumerate(points[1:], 1):
        if math_dist((x, y), (px, py)) >= tolerance:
            keep.append(i)
            px, py = x, y
    if keep[-1] != len(points) - 1:
        keep.append(len(points) - 1)
    return [points[i] for i in keep]


def math_dist(a, b):
    return float(np.hypot(a[0] - b[0], a[1] - b[1]))


def trace_boundary(mask, pixel_scale=1.0, bridge=12):
    """خط‌چین قرمز را به حلقهٔ بستهٔ شهر تبدیل می‌کند."""
    from scipy import ndimage as ndi
    from skimage.measure import find_contours

    if not mask.any():
        return None
    joined = ndi.binary_dilation(mask, _disk(bridge))
    joined = ndi.binary_closing(joined, np.ones((9, 9)))
    labels, count = ndi.label(joined)
    sizes = ndi.sum(joined, labels, range(1, count + 1))
    if count == 0:
        return None
    best = int(np.argmax(sizes)) + 1
    filled = ndi.binary_fill_holes(labels == best)
    # حلقه‌ای که به لبهٔ کادر نرسیده باشد ارزش اعتماد دارد
    if filled[0, :].any() or filled[-1, :].any() or filled[:, 0].any() or filled[:, -1].any():
        return None
    contours = find_contours(filled, 0.5)
    if not contours:
        return None
    contour = max(contours, key=len)
    pts = [[round((x + 0.5) * pixel_scale, 1),
            round(-(y + 0.5) * pixel_scale, 1)] for y, x in contour]
    return _simplify(pts, 2.5)


def urban_mask(rgb, road, min_block=40000.0):
    """بافت ساخت‌وسان: بلوک‌های روشنِ خنثی که گوگل برای داخل شهر می‌گذارد.

    تراکم جاده برای این کار بد بود چون هر بزرگراهی «شهر» می‌ساخت. رنگ
    بهتر جواب می‌دهد: زمینِ بیرون شهر بژ است (کانال آبی کمتر از قرمز) و
    بلوک‌های داخل شهر خاکستریِ خنثی‌اند (آبی برابر یا بیشتر از قرمز).
    """
    from scipy import ndimage as ndi

    r, _g, b = (rgb[..., i].astype(np.int16) for i in range(3))
    lo = rgb.min(axis=2).astype(np.int16)
    hi = rgb.max(axis=2).astype(np.int16)
    glyph = ((hi - lo) <= 12) & (lo < 150)
    zone = ndi.binary_dilation(ndi.binary_closing(glyph, np.ones((5, 5))),
                               _disk(12))
    mask = (lo >= LIGHT_MIN) & ((hi - lo) <= 10) & (b >= r - 4)
    mask = mask & ~road & ~zone
    mask = ndi.binary_closing(mask, _disk(17))
    mask = ndi.binary_fill_holes(mask)
    mask = ndi.binary_opening(mask, np.ones((7, 7)))
    labels, count = ndi.label(mask)
    if count == 0:
        return mask
    sizes = ndi.sum(mask, labels, range(1, count + 1))
    boxes = ndi.find_objects(labels)
    keep = np.zeros(count + 1, bool)      # صفر برای پس‌زمینه
    for i, sl in enumerate(boxes):
        if sizes[i] < min_block:
            continue
        height = sl[0].stop - sl[0].start
        width = sl[1].stop - sl[1].start
        area = height * width
        diameter = 2.0 * math.sqrt(sizes[i] / math.pi)
        if area and sizes[i] / float(area) > 0.62 and diameter < 260:
            continue        # تودهٔ گردِ کوچک = آیکن یا ساختمان، نه بافت شهری
        keep[i + 1] = True
    return keep[labels]


def trace_image(path, metre_per_px, note=''):
    """یک اسکرین‌شات را کامل ردیابی می‌کند و ساختار برداری می‌دهد."""
    from scipy import ndimage as ndi

    rgb = load_rgb(path)
    layers = classify(rgb)
    raw = ndi.binary_closing(layers['road'], np.ones((3, 3)))
    road = clean_roads(layers['road'])
    h, w = road.shape
    traced = {
        'source': Path(path).name,
        'size': [w, h],
        'metre_per_px': metre_per_px,
        'note': note or NOTE,
        'roads': vectorise_roads(road, metre_per_px, raw),
        'parks': vectorise_areas(
            ndi.binary_opening(layers['park'], np.ones((5, 5))),
            'park', metre_per_px, 900.0),
        'water': vectorise_areas(
            ndi.binary_opening(layers['water'], np.ones((5, 5))),
            'water', metre_per_px, 900.0),
        'urban': vectorise_areas(urban_mask(rgb, road), 'urban',
                                  metre_per_px, 20000.0),
    }
    traced['boundary'] = trace_boundary(layers['boundary'], metre_per_px)
    return traced


# ------------------------------------------------------------------ رندر ----
ROAD_STYLE = {'motorway': (3.0, True), 'trunk': (2.4, True),
              'primary': (1.8, True), 'secondary': (1.3, True),
              'street': (0.9, False), 'service': (0.7, False)}


def render(traced, style, out_path, title, subtitle, dpi=200, height=9.0):
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.collections as mc
    import matplotlib.pyplot as plt

    palette = dict(views.base.STYLES[style])
    palette.update(views.EXTRA_PALETTE[style])
    width_px, height_px = traced['size']
    m = traced['metre_per_px']
    half_x, half_y = width_px * m / 2.0, height_px * m / 2.0
    # بوم هم‌نسبتِ کادر نقشه، وگرنه نقشه در حاشیه‌ها گم می‌شود
    page_w = max(5.0, height * 0.84 / 0.96 * (half_x / max(half_y, 1e-6)))

    fig = plt.figure(figsize=(page_w, height), facecolor=palette['paper'])
    ax = fig.add_axes([0.02, 0.10, 0.96, 0.84])
    ax.set_aspect('equal', adjustable='box')
    ax.axis('off')
    ax.set_xlim(-half_x, half_x)
    ax.set_ylim(-half_y, half_y)

    from matplotlib.patches import Rectangle
    ax.add_patch(Rectangle((-half_x * 1.05, -half_y * 1.05), half_x * 2.1,
                           half_y * 2.1, facecolor=palette['land'],
                           edgecolor='none', zorder=0))

    # بردارها شمال‌بالا هستند (y رو به شمال)، پس مرکز مختصات y صفر است
    def shift(points):
        return [(x - half_x, y + half_y) for x, y in points]

    for key, order in (('parks', 3.0), ('water', 3.5), ('urban', 4.0)):
        colour = {'parks': palette['park'], 'water': palette['water'],
                  'urban': palette['built']}[key]
        polys = [shift(a['points']) for a in traced[key]]
        if polys:
            ax.add_collection(mc.PolyCollection(
                polys, facecolors=colour, edgecolors='none', zorder=order))

    for cls in ('street', 'service', 'secondary', 'primary', 'trunk', 'motorway'):
        segs = [shift(r['points']) for r in traced['roads'] if r['class'] == cls]
        if not segs:
            continue
        w, cased = ROAD_STYLE[cls]
        if cased:
            ax.add_collection(mc.LineCollection(
                segs, colors=palette['ink'], linewidths=w + 0.9, zorder=8,
                capstyle='round'))
            ax.add_collection(mc.LineCollection(
                segs, colors=palette['road_core'], linewidths=w, zorder=9,
                capstyle='round'))
        else:
            ax.add_collection(mc.LineCollection(
                segs, colors=palette['ink'], linewidths=w, zorder=8,
                capstyle='round'))

    if traced.get('boundary'):
        ring = shift(traced['boundary'])
        ax.add_collection(mc.LineCollection(
            [ring], colors=palette['accent'], linewidths=1.6,
            linestyles=(0, (7.0, 4.0)), zorder=10))

    fig.canvas.draw()
    _scale_bar(ax, palette, m, width_px)
    _north(ax, palette)
    _caption(fig, palette, title, subtitle, traced)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    fig.savefig(out_path, dpi=dpi, facecolor=palette['paper'])
    plt.close(fig)
    return out_path


def _scale_bar(ax, palette, m, width_px):
    span_m = width_px * m
    box = ax.get_window_extent()
    metres_per_px = span_m / max(box.width, 1.0)
    choices = (100, 250, 500, 1000, 2000, 5000, 10000, 20000)
    distance = min(choices, key=lambda d: abs(d - metres_per_px * 150.0))
    frac = distance / span_m
    if not 0.03 < frac < 0.8:
        return
    x0, y0 = 0.02, 0.018
    ax.add_patch(views.base._bar(ax, x0, y0, frac, palette))
    ax.text(x0 + frac / 2, y0 + 0.030, '%g km' % (distance / 1000.0),
            transform=ax.transAxes, ha='center', va='bottom', fontsize=7.5,
            color=palette['ink_soft'], fontfamily='DejaVu Sans Mono',
            zorder=12, clip_on=False)


def _north(ax, palette):
    ax.add_patch(views._north_arrow(ax, 0.028, 0.90, palette))


def _caption(fig, palette, title, subtitle, traced):
    fig.text(0.02, 0.040, traced['note'], ha='left', va='bottom', fontsize=7,
             color=palette['accent'], fontfamily='DejaVu Sans Mono', zorder=13)
    fig.text(0.50, 0.995, views.base._letterspace(title), ha='center',
             va='top', fontsize=14, color=palette['frame'],
             fontfamily='DejaVu Serif', fontweight='bold', zorder=13)
    fig.text(0.50, 0.963, subtitle, ha='center', va='top', fontsize=10,
             color=palette['frame'], fontfamily='DejaVu Sans', zorder=13)
    fig.text(0.98, 0.014, '© OpenStreetMap contributors · ODbL 1.0',
             ha='right', va='bottom', fontsize=7, color=palette['note'],
             fontfamily='DejaVu Sans Mono', zorder=13)


# ------------------------------------------------------------------- CLI ----
def build_parser():
    p = argparse.ArgumentParser(
        description='رَردیابی اسکرین‌شات گوگل‌مپ و رندر پوستر')
    p.add_argument('inputs', nargs='*', help='پرونده‌های تصویر')
    p.add_argument('--list', metavar='DIR', help='فهرست تصاویر یک پوشه')
    p.add_argument('--out-dir', default=str(REPO / 'outputs' / 'traced'))
    p.add_argument('--json', default=None, help='مسیر ذخیرهٔ JSON برداری')
    p.add_argument('--dpi', type=int, default=200)
    p.add_argument('--no-render', action='store_true')
    p.add_argument('--title', default='Shiraz')
    p.add_argument('--subtitle', default='بازسازی‌شده از گوگل‌مپ')
    p.add_argument('--metres-per-px', type=float, default=None,
                   help='مقیاسِ دستی (متر بر پیکسل)؛ بر جدولِ نوارِ مقیاس مقدم است')
    return p


def main(argv=None):
    args = build_parser().parse_args(argv)
    paths = [Path(p) for p in args.inputs]
    if args.list:
        paths = sorted(Path(args.list).glob('*.jpg')) + \
            sorted(Path(args.list).glob('*.png'))
    if not paths:
        sys.stderr.write('تصویری داده نشده است.\n')
        return 2
    out_dir = Path(args.out_dir).expanduser()
    out_dir.mkdir(parents=True, exist_ok=True)
    for path in paths:
        # مقیاس: از نوار مقیاس خودِ گوگل‌مپ در همان اسکرین‌شات
        bar = args.metres_per_px or SCALE_BARS.get(path.name)
        if bar is None:
            sys.stderr.write('مقیاسِ «%s» تعریف نشده؛ رد می‌شود.\n' % path.name)
            continue
        traced = trace_image(path, bar)
        stem = _stamp(path)
        if args.json:
            Path(args.json).parent.mkdir(parents=True, exist_ok=True)
            Path(args.json).write_text(json.dumps(traced), encoding='utf-8')
        if not args.no_render:
            for style in ('sand', 'ink'):
                out = out_dir / ('traced-%s-%s-%s.png'
                                 % (args.title.lower(), stem, style))
                render(traced, style, out, args.title, args.subtitle,
                       dpi=args.dpi)
                sys.stderr.write('→ %s\n' % out.name)
        sys.stderr.write('%s: %d جاده، %d پهنه، مرز: %s\n'
                         % (path.name, len(traced['roads']),
                            len(traced['urban']) + len(traced['parks']),
                            'بله' if traced.get('boundary') else 'نه'))
    return 0


def _stamp(path):
    """مهر زمانی نام اسکرین‌شات: Screenshot_YYYY-MM-DD-HH-MM-SS… → 07-22-45."""
    token = Path(path).stem.split('_')
    for part in token[1:]:
        chunks = part.split('-')
        if len(chunks) >= 5 and chunks[0].isdigit() and len(chunks[0]) == 4:
            return '-'.join(chunks[3:6])
    return Path(path).stem


# مقیاس هر اسکرین‌شات از نوار مقیاسِ خودِ گوگل‌مپ در همان تصویر خوانده شده:
# طول خطِ نوار بر حسب پیکسل و مقدارِ درج‌شده روی آن. چهار تصویرِ نزدیک
# (۰۷-۲۳-۰۶ تا ۰۷-۲۳-۲۰) همگی یک بزرگ‌نمایی‌اند — با هم‌ترازی هم‌پوشانی
# تأیید شد (ncc ۰٫۷۵) و اندازهٔ یکسانِ باند فرودگاه هم این را می‌گوید.
SCALE_BARS = {
    'Screenshot_2026-10-18-07-22-45-505_com.google.android.apps.maps.jpg': 10000.0 / 224,
    'Screenshot_2026-10-18-07-22-52-881_com.google.android.apps.maps.jpg': 10000.0 / 224,
    'Screenshot_2026-10-18-07-23-06-015_com.google.android.apps.maps.jpg': 5000.0 / 250,
    'Screenshot_2026-10-18-07-23-12-398_com.google.android.apps.maps.jpg': 5000.0 / 250,
    'Screenshot_2026-10-18-07-23-16-436_com.google.android.apps.maps.jpg': 5000.0 / 250,
    'Screenshot_2026-10-18-07-23-20-220_com.google.android.apps.maps.jpg': 5000.0 / 250,
}


if __name__ == '__main__':
    sys.exit(main())