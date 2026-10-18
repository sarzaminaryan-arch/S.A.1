#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""نمای شهری گرافیکی به سبک deMAr برای چند شهر ایران.

این ابزار **عکس واقعی ماهواره‌ای نیست و خیابان واقعی هم نیست**. گوگل‌مپ API
عمومی برای استخراج ندارد و اسکرپ آن در این محیط در دسترس نیست، از همین‌رو
الگوی خیابان‌ها **ترسیم‌شده (stylized)** است: شبکهٔ شطرنجی یا دره‌ای با بذرِ
ثابت روی مختصات واقعی مرکز شهر ساخته می‌شود تا حال‌وهوای شهر دربیاید.

تنها چیز واقعیِ نقشه، **مرز شهرستان‌ها از OSM** است که برش می‌خورد و روی همان
کادرِ شهر کشیده می‌شود. روی خودِ تصویر هم با خط درشت نوشته می‌شود که نقشه
«سبک‌شده» است تا هرگز شبیه دادهٔ ناوبری اشتباه گرفته نشود.

برای نقشهٔ واقعی، یا باید روی سیستم متصل به اینترنت
``python3 tools/maps/make_city_maps.py`` را اجرا کرد (Overpass)، یا اسکرین‌شات
گوگل‌مپ با نوار مقیاس داد تا هندسه از روی آن بازسازی شود.

اجرا::

    python3 tools/maps/make_city_views.py                     # همهٔ شهرها، دو مدل
    python3 tools/maps/make_city_views.py --only shiraz --styles ink
    python3 tools/maps/make_city_views.py --list
    python3 tools/maps/make_city_views.py --check
"""

from __future__ import annotations

import argparse
import math
import random
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import make_province_3d as core  # noqa: E402  (مرجع استان/شهرستان، فارسی‌ساز)
import make_city_maps as base    # noqa: E402  (پالت و قاب عنوان مشترک)

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent

DEFAULT_CITIES = ('shiraz', 'tabriz', 'dorud', 'hamadan', 'qom')
DEFAULT_STYLES = ('sand', 'ink')

# رنگ‌های افزوده بر پالت make_city_maps: نمایش کوه، دره و سایهٔ خط‌پردازی.
EXTRA_PALETTE = {
    'sand': {'relief': '#ddd0a8', 'relief_shade': '#cbb88d', 'relief_line': '#87764f',
             'relief_hatch': '#ab9669', 'county': '#8d7f5f', 'label': '#5c5540',
             'built': '#d5c19c', 'built_alt': '#c9b189', 'industrial': '#c3ad86'},
    'ink': {'relief': '#ccd7db', 'relief_shade': '#b9c6cc', 'relief_line': '#7b8f97',
            'relief_hatch': '#94a6ae', 'county': '#7d9099', 'label': '#4c6068'},
}

# پهنای خط‌ها به «پوینت» است، مثل نقشه‌های چاپی: بزرگراه پررنگ، کوچه نازک.
ROAD_WIDTH = {
    'motorway': 2.5, 'trunk': 2.0, 'primary': 1.5, 'secondary': 1.05,
    'street': 0.5, 'service': 0.28,
}
CASED = ('motorway', 'trunk', 'primary', 'secondary')

DISCLAIMER = 'STYLIZED STREET PATTERN — NOT SURVEY ACCURATE'

# ------------------------------------------------------------------ شهرها ---
# مختصات مرکز شهر واقعی است؛ بقیهٔ اعداد (کوه، رود، پهنه) تقریب هنری‌اند.
CITIES = {
    'shiraz': {
        'en': 'Shiraz', 'fa': 'شیراز', 'province': 'fars',
        'lat': 29.5918, 'lon': 52.5837, 'span': (15500, 11800), 'seed': 1017,
        'layout': 'grid',
        'grid': {'angle': 28.0, 'spacing': 300.0, 'arterial_every': 4,
                 'radius': 6200.0, 'ring': 0.8, 'diagonals': (62.0,),
                 'approaches': (8.0, 118.0, 205.0)},
        'relief': [{'p0': (-8600, 5200), 'p1': (7600, 8200), 'amp': 1500.0,
                    'rough': 0.6, 'depth': 6, 'band': 700.0, 'hachure': 1150.0}],
        'rivers': [{'p0': (7200, -2800), 'p1': (8200, 6000), 'width': 7.0,
                    'meander': 700.0, 'amp': 520.0}],
        'lakes': [{'cx': 4700.0, 'cy': 5000.0, 'rx': 760.0, 'ry': 520.0,
                   'rot': 25.0}],
        'labels': [(0, 0, 'MAYDAN EMAM', 1.0), (1200, 3700, 'HAFEZIEH', 0.9),
                   (1500, 4600, 'CHEHEL SOTOUN', 0.9),
                   (4200, 6300, 'BASIRVAN', 0.8),
                   (4900, 4900, 'MOKHABERAT L.', 0.8),
                   (-4300, 6400, 'KOOHESTAN', 1.1)],
    },
    'tabriz': {
        'en': 'Tabriz', 'fa': 'تبریز', 'province': 'east-azerbaijan',
        'lat': 38.0800, 'lon': 46.2919, 'span': (15000, 11800), 'seed': 2038,
        'layout': 'grid',
        'grid': {'angle': 10.0, 'spacing': 285.0, 'arterial_every': 4,
                 'radius': 5700.0, 'ring': 0.84, 'diagonals': (55.0, 120.0),
                 'approaches': (35.0, 200.0, 320.0)},
        'relief': [{'p0': (-8200, -4900), 'p1': (8400, -4100), 'amp': 1900.0,
                    'rough': 0.58, 'depth': 6, 'band': 820.0, 'hachure': 1350.0},
                   {'p0': (-8400, 4300), 'p1': (5200, 5600), 'amp': 1300.0,
                    'rough': 0.6, 'depth': 6, 'band': 560.0, 'hachure': 900.0}],
        'rivers': [{'p0': (-6800, 3600), 'p1': (6400, -3200), 'width': 7.0,
                    'meander': 900.0, 'amp': 800.0}],
        'lakes': [{'cx': -6100.0, 'cy': -1500.0, 'rx': 620.0, 'ry': 460.0,
                   'rot': -20.0}],
        'labels': [(-2900, 1100, 'KHWAJA', 0.9), (5200, -5100, 'SAHAND', 1.3),
                   (2200, 3300, 'ARASXANJ', 0.8), (-6300, -1500, 'SHIR AMRUD', 0.8)],
    },
    'dorud': {
        'en': 'Dorud', 'fa': 'دورود', 'province': 'lorestan',
        'lat': 33.4950, 'lon': 49.0560, 'span': (12000, 12000), 'seed': 4142,
        'layout': 'valley',
        'valley': {'angle': 74.0, 'length': 12500.0, 'width': 1550.0,
                   'river_width': 8.0,
                   'streets': 9, 'cross_spacing': 165.0, 'meander': 340.0,
                   'river_side': 0.34, 'road_side': -0.62},
        'relief': [{'p0': (-7200, 3400), 'p1': (7000, 5200), 'amp': 1500.0,
                    'rough': 0.62, 'depth': 6, 'band': 620.0, 'hachure': 1250.0},
                   {'p0': (-6600, -3700), 'p1': (7400, -4800), 'amp': 1600.0,
                    'rough': 0.62, 'depth': 6, 'band': 640.0, 'hachure': 1300.0}],
        'labels': [(0, 0, 'DORUD', 1.4), (-3600, 900, 'KAZRON', 0.9),
                   (3400, -800, 'ALIGUDARZ ROAD', 0.8)],
    },
    'hamadan': {
        'en': 'Hamadan', 'fa': 'همدان', 'province': 'hamadan',
        'lat': 34.7983, 'lon': 48.5148, 'span': (14000, 11000), 'seed': 5150,
        'layout': 'grid',
        'grid': {'angle': 4.0, 'spacing': 275.0, 'arterial_every': 4,
                 'radius': 5000.0, 'ring': 0.84, 'diagonals': (48.0,),
                 'approaches': (0.0, 150.0, 300.0)},
        'relief': [{'p0': (-7400, -3900), 'p1': (7600, -3500), 'amp': 1650.0,
                    'rough': 0.6, 'depth': 6, 'band': 700.0, 'hachure': 1200.0},
                   {'p0': (3900, 4200), 'p1': (7400, 2200), 'amp': 1100.0,
                    'rough': 0.62, 'depth': 5, 'band': 460.0, 'hachure': 780.0}],
        'rivers': [{'p0': (-4300, 3200), 'p1': (6600, 2100), 'width': 5.0,
                    'meander': 500.0, 'amp': 420.0}],
        'labels': [(250, -3700, 'GHAR MINARET', 0.9), (600, -5200, 'ALVAND', 1.3),
                   (-3400, 1500, 'SULTANABAD', 0.8)],
    },
    'qom': {
        'en': 'Qom', 'fa': 'قم', 'province': 'qom',
        'lat': 34.6416, 'lon': 50.8746, 'span': (13000, 10500), 'seed': 6060,
        'layout': 'grid',
        'grid': {'angle': 6.0, 'spacing': 270.0, 'arterial_every': 4,
                 'radius': 4500.0, 'ring': 0.86, 'diagonals': (52.0,),
                 'approaches': (355.0, 140.0, 250.0)},
        'relief': [{'p0': (-6200, 4600), 'p1': (-5400, -4600), 'amp': 1400.0,
                    'rough': 0.6, 'depth': 6, 'band': 600.0, 'hachure': 1050.0},
                   {'p0': (5200, 5600), 'p1': (7600, 2600), 'amp': 900.0,
                    'rough': 0.62, 'depth': 5, 'band': 420.0, 'hachure': 700.0}],
        'labels': [(-5600, 500, 'SORKHEH KUH', 1.1), (1500, -2600, 'GHALTAN', 0.8)],
    },
}


# --------------------------------------------------------------- ریاضی پایه ---
def _unit(deg):
    a = math.radians(deg)
    return (math.cos(a), math.sin(a))


def _add(p, q):
    return (p[0] + q[0], p[1] + q[1])


def _scale(p, k):
    return (p[0] * k, p[1] * k)


def _len(p):
    return math.hypot(p[0], p[1])


def _norm(p):
    size = _len(p) or 1.0
    return (p[0] / size, p[1] / size)


def _fractal(rng, p0, p1, disp, rough=0.58, depth=6):
    """خط شکسته با جابه‌جایی میانی: شکل کوه، رود و بزرگراه طبیعی به نظر می‌رسد."""
    pts = [p0]

    def recurse(a, b, d, level):
        if level <= 0:
            pts.append(b)
            return
        mx, my = (a[0] + b[0]) / 2.0, (a[1] + b[1]) / 2.0
        dx, dy = b[0] - a[0], b[1] - a[1]
        size = math.hypot(dx, dy) or 1.0
        off = rng.uniform(-1.0, 1.0) * d
        mid = (mx - dy / size * off, my + dx / size * off)
        recurse(a, mid, d * rough, level - 1)
        recurse(mid, b, d * rough, level - 1)

    recurse(p0, p1, disp, depth)
    return pts


def _smooth_noise(rng, n, amp, octaves=3):
    """نویز نرم (جمع سینوس) برای انحراف رودخانه و لبهٔ ناهموار شهر."""
    waves = []
    for i in range(octaves):
        waves.append((amp * (0.55 ** i), rng.uniform(0.0, math.tau), i + 2))
    norm = sum(w[0] for w in waves) or 1.0
    out = []
    for i in range(max(n, 1)):
        u = i / max(n - 1, 1)
        value = sum(a * math.sin(k * math.tau * u + p) for a, p, k in waves)
        out.append(value / norm)
    return out


def _shrink(poly, factor):
    """چهارضلعی را به سمت مرکزش کوچک می‌کند تا خیابان بین بلوک‌ها باز بماند."""
    cx = sum(p[0] for p in poly) / len(poly)
    cy = sum(p[1] for p in poly) / len(poly)
    return [(cx + (p[0] - cx) * factor, cy + (p[1] - cy) * factor)
            for p in poly]


def _blob(rng, cx, cy, rx, ry, rot=0.0, amp=0.14, n=44):
    """لکهٔ نامنظم (دریاچه، پارک) با نویز شعاعی."""
    ux, uy = _unit(rot)
    noise = _smooth_noise(rng, n, amp, octaves=3)
    pts = []
    for i in range(n):
        t = math.tau * i / n
        k = 1.0 + noise[i]
        x, y = rx * math.cos(t) * k, ry * math.sin(t) * k
        pts.append((cx + x * ux - y * uy, cy + x * uy + y * ux))
    return pts


def _offset_ring(centre, radius, n=72):
    return [(centre[0] + radius * math.cos(math.tau * i / n),
             centre[1] + radius * math.sin(math.tau * i / n)) for i in range(n)]


def _boundary(rng, radius):
    """شعاع ناهموار شهر: لبهٔ ارگانیک به‌جای دایرهٔ بی‌روح."""
    harmonics = [(rng.uniform(0.05, 0.17), rng.uniform(0.0, math.tau))
                 for _ in range(5)]

    def radius_at(theta):
        scale = 1.0
        for amp, phase in harmonics:
            scale += amp * math.cos(phase + 2.0 * theta)
        return radius * max(0.55, scale)

    return radius_at


def _chord(radius_at, base, direction, reach, steps=320):
    """بازهٔ خطی که داخل مرز شهر می‌ماند؛ صفر یعنی شهر آنجا نیست."""
    keep = []
    for i in range(steps + 1):
        t = -reach + 2.0 * reach * i / steps
        p = (base[0] + direction[0] * t, base[1] + direction[1] * t)
        r = _len(p)
        keep.append(r <= radius_at(math.atan2(p[1], p[0])))
    if not any(keep):
        return None
    mid = steps // 2
    if not keep[mid]:
        near = min(range(steps + 1), key=lambda i: abs(i - mid))
        if not keep[near]:
            return None
        mid = near
    lo = mid
    while lo > 0 and keep[lo - 1]:
        lo -= 1
    hi = mid
    while hi < steps and keep[hi + 1]:
        hi += 1
    return (-reach + 2.0 * reach * lo / steps,
            -reach + 2.0 * reach * hi / steps)


def _grid_class(k, every):
    if every and k % (every * 3) == 0:
        return 'primary'
    if every and k % every == 0:
        return 'secondary'
    return 'street'


# --------------------------------------------------------- شبکهٔ شطرنجی ------
def _grid_network(rng, cfg, half_x, half_y):
    """شهرِ مشبک: بلوک‌های منظم، حلقهٔ دور شهر، خیابان‌های مورب و بزرگراه ورودی."""
    theta = math.radians(cfg.get('angle', 0.0))
    ux, uy = math.cos(theta), math.sin(theta)          # محور طولی شبکه
    vx, vy = -uy, ux                                  # محور عرضی شبکه
    spacing = float(cfg.get('spacing', 300.0))
    every = int(cfg.get('arterial_every', 4))
    ring_factor = float(cfg.get('ring', 0.88))
    radius = float(cfg.get('radius', 4600.0))
    reach = math.hypot(half_x, half_y) * 1.35
    radius_at = _boundary(rng, radius)
    count = int(reach / spacing) + 1

    roads, blocks = [], []
    offsets_u, offsets_v = {}, {}
    for k in range(-count, count + 1):
        offsets_u[k] = k * spacing + rng.uniform(-0.14, 0.14) * spacing
        offsets_v[k] = k * spacing + rng.uniform(-0.14, 0.14) * spacing

    def line_along_u(k):
        run = _chord(radius_at,
                     (vx * offsets_u[k], vy * offsets_u[k]),
                     (ux, uy), reach)
        if run is None:
            return None
        t0, t1 = run
        return [(vx * offsets_u[k] + ux * t, vy * offsets_u[k] + uy * t)
                for t in (t0, (t0 + t1) / 2.0, t1)]
        return pts

    def line_along_v(k):
        run = _chord(radius_at,
                     (ux * offsets_v[k], uy * offsets_v[k]),
                     (vx, vy), reach)
        if run is None:
            return None
        t0, t1 = run
        return [(ux * offsets_v[k] + vx * t, uy * offsets_v[k] + vy * t)
                for t in (t0, (t0 + t1) / 2.0, t1)]
        return pts

    for k in range(-count, count + 1):
        pts = line_along_u(k)
        if pts:
            roads.append((_grid_class(k, every), pts))
        pts = line_along_v(k)
        if pts:
            roads.append((_grid_class(k, every), pts))

    ring_radius = radius * ring_factor
    ring_pts = _offset_ring((0.0, 0.0), 1.0, n=84)
    ring_pts = [(x * ring_radius, y * ring_radius) for x, y in ring_pts]
    roads.append(('trunk', ring_pts))

    # بلوک‌ها: هر سلولِ داخل مرز شهر که با حلقه یا خیابان مورب قاطی نشده باشد
    diags = []
    for deg in cfg.get('diagonals', ()):
        dx, dy = _unit(deg)
        base = (rng.uniform(-600, 600), rng.uniform(-600, 600))
        diags.append((base, (dx, dy)))
        roads.append(('primary', _fractal(
            rng, _add(base, _scale((dx, dy), -reach)),
            _add(base, _scale((dx, dy), reach)), 260.0, 0.5, 4)))

    for k in range(-count, count):
        for m in range(-count, count):
            u0, u1 = offsets_v[m], offsets_v[m + 1]
            v0, v1 = offsets_u[k], offsets_u[k + 1]
            cu, cv = (u0 + u1) / 2.0, (v0 + v1) / 2.0
            centre = (cu * ux + cv * vx, cu * uy + cv * vy)
            dist = _len(centre)
            if dist <= 0 or dist > radius_at(math.atan2(centre[1], centre[0])):
                continue
            if abs(dist - ring_radius) < spacing * 0.72:
                continue
            if any(_seg_distance(centre, base, d) < spacing * 0.55
                   for base, d in diags):
                continue
            if dist > ring_radius and k % 2 and m % 2:
                continue          # پیرامون حلقه خلوت‌تر است
            poly = [(u0 * ux + v0 * vx, u0 * uy + v0 * vy),
                    (u1 * ux + v0 * vx, u1 * uy + v0 * vy),
                    (u1 * ux + v1 * vx, u1 * uy + v1 * vy),
                    (u0 * ux + v1 * vx, u0 * uy + v1 * vy)]
            blocks.append((_shrink(poly, 0.75), _block_kind(rng, dist, ring_radius)))

    # بزرگراه‌هایی که از بیرون شهر وارد می‌شوند
    for deg in cfg.get('approaches', ()):
        deg = float(deg) + rng.uniform(-6.0, 6.0)
        dx, dy = _unit(deg)
        far = reach * 1.25
        end = _scale((dx, dy), ring_radius * 1.06)
        roads.append(('motorway', _fractal(
            rng, _scale((dx, dy), far), end, 420.0, 0.55, 5)))
        roads.append(('motorway', _fractal(
            rng, end, _scale((dx, dy), ring_radius * 0.42), 150.0, 0.5, 3)))

    return {'roads': roads, 'blocks': blocks}


def _block_kind(rng, dist, ring_radius):
    roll = rng.random()
    if roll < 0.055 and dist > ring_radius * 0.75:
        return 'industrial'
    if roll < 0.12:
        return 'park'
    return 'built_alt' if roll < 0.42 else 'built'


def _seg_distance(point, base, direction):
    """فاصلهٔ نقطه تا خطی که از base با جهت داده می‌گذرد."""
    vx, vy = point[0] - base[0], point[1] - base[1]
    t = vx * direction[0] + vy * direction[1]
    return math.hypot(vx - t * direction[0], vy - t * direction[1])


# ---------------------------------------------------------- شهرِ دره‌ای ------
def _valley_network(rng, cfg, half_x, half_y):
    """شهرِ دره‌ای (دورود): نوار طولی شهر میان دو رشته‌کوه، با رود و بزرگراه."""
    angle = float(cfg.get('angle', 70.0))
    ax_, ay_ = _unit(angle)
    px_, py_ = -ay_, ax_
    length = float(cfg.get('length', 10000.0))
    width = float(cfg.get('width', 950.0))
    lanes = int(cfg.get('streets', 7))
    cross_gap = float(cfg.get('cross_spacing', 170.0))
    meander = float(cfg.get('meander', 300.0))
    reach = length / 2.0 + math.hypot(half_x, half_y) * 0.4

    def half_width(t):
        q = abs(t) / (length / 2.0)
        base = max(0.10, max(0.0, 1.0 - q ** 2.6) ** 0.5)
        return width * base * (1.0 + 0.13 * math.sin(t / 620.0 + 1.1))

    def point(t, offset):
        return (ax_ * t + px_ * offset, ay_ * t + py_ * offset)

    steps = 90
    ts = [-reach + 2.0 * reach * i / steps for i in range(steps + 1)]
    roads, blocks = [], []

    # رودخانه و بزرگراه، هر دو با انحراف نرم نسبت به محور دره
    river_off = _smooth_noise(rng, steps + 1, meander * 0.35, 3)
    river_pts = [point(t, half_width(t) * float(cfg.get('river_side', 0.34))
                       + river_off[i]) for i, t in enumerate(ts)]
    road_off = _smooth_noise(rng, steps + 1, meander * 0.28, 3)
    roads.append(('motorway', [
        point(t, half_width(t) * float(cfg.get('road_side', -0.62)) + road_off[i])
        for i, t in enumerate(ts)]))

    # خیابان‌های موازی دره
    lines = []
    for k in range(-lanes, lanes + 1):
        frac = k / float(lanes) * 0.86
        noise = _smooth_noise(rng, steps + 1, meander * 0.10, 3)
        samples = [point(t, half_width(t) * frac + noise[i])
                   for i, t in enumerate(ts)]
        cls = 'secondary' if abs(frac) < 0.4 else 'street'
        roads.append((cls, samples))
        lines.append((frac, samples))

    # خیابان‌های عرضی
    cross_t = []
    t = -length / 2.0
    while t < length / 2.0:
        cross_t.append(t + rng.uniform(-30.0, 30.0))
        t += cross_gap
    for i, ct in enumerate(cross_t):
        w = half_width(ct)
        jitter = _smooth_noise(rng, 2, 40.0, 2)
        pts = [point(ct + jitter[0], -w * 0.95),
               point(ct + jitter[1] * 0.5, 0.0),
               point(ct + jitter[1], w * 0.95)]
        roads.append(('secondary' if i % 5 == 0 else 'street', pts))

    # بلوک‌های ساخته‌شده بین خیابان‌های موازی و عرضی
    def along(samples, t):
        """نمونهٔ خطِ خیابان موازی در موقعیت t روی محور دره."""
        pos = (t + reach) / (2.0 * reach) * steps
        i = max(0, min(steps - 1, int(pos)))
        f = pos - i
        return (samples[i][0] + (samples[i + 1][0] - samples[i][0]) * f,
                samples[i][1] + (samples[i + 1][1] - samples[i][1]) * f)

    for li in range(len(lines) - 1):
        s0, s1 = lines[li][1], lines[li + 1][1]
        for ci in range(len(cross_t) - 1):
            t0, t1 = cross_t[ci], cross_t[ci + 1]
            poly = [along(s0, t0), along(s1, t0), along(s1, t1), along(s0, t1)]
            roll = rng.random()
            kind = 'park' if roll < 0.03 else ('built_alt' if roll < 0.5 else 'built')
            blocks.append((_shrink(poly, 0.76), kind))
    return {'roads': roads, 'blocks': blocks,
            'river': {'pts': river_pts, 'width': float(cfg.get('river_width', 22.0))}}


# -------------------------------------------------------------- لایه‌ها -----
def _river_layers(rng, specs):
    return [{'pts': _fractal(rng, tuple(s['p0']), tuple(s['p1']),
                             float(s['amp']), 0.55, 5),
             'width': float(s['width'])} for s in specs]


def _relief_layers(rng, specs):
    """کوه: پهنهٔ رنگی + نوار روشن‌تر کنار خط‌الرأس + هچور کنار دامنه."""
    out = []
    for spec in specs:
        ridge = _fractal(rng, tuple(spec['p0']), tuple(spec['p1']),
                         float(spec['amp']), float(spec.get('rough', 0.6)),
                         int(spec.get('depth', 6)))
        out.append({'ridge': ridge, 'band': float(spec.get('band', 600.0)),
                    'hachure': float(spec.get('hachure', 1000.0))})
    return out


def _relief_polygon(ridge, far=1.0):
    """پهنهٔ کوه: از خط‌الرأس تا بیرونِ کادر، در سمتی که شهر نیست."""
    first, last = ridge[0], ridge[-1]
    start = _scale(_norm(first), far)
    end = _scale(_norm(last), far)
    return [start] + list(ridge) + [end]


def _shift(ridge, distance):
    """خط را به اندازهٔ distance به سمت مرکز جابه‌جا می‌کند (کنار دامنه)."""
    out = []
    for p in ridge:
        direction = _norm(_scale(p, -1.0))
        out.append((p[0] + direction[0] * distance, p[1] + direction[1] * distance))
    return out


def _draw_relief(ax, palette, layers, reach):
    import matplotlib.collections as mc

    for layer in layers:
        ridge = layer['ridge']
        band = layer['band']
        ax.add_collection(mc.PolyCollection(
            [_relief_polygon(ridge, reach * 1.4)], facecolors=palette['relief'],
            edgecolors='none', zorder=2))
        # یک نوار تیره‌تر چسبیده به خط‌الرأس: حجم کوه را می‌سازد
        ax.add_collection(mc.PolyCollection(
            [list(_shift(ridge, band)) + list(reversed(ridge))],
            facecolors=palette['relief_shade'], edgecolors='none', zorder=2.11))
        hachures = []
        for i, point in enumerate(ridge):
            inward = _norm(_scale(point, -1.0))
            length = layer['hachure'] * (0.35 + 0.65 * abs(
                math.sin(i * 0.83 + len(ridge) * 0.11)))
            hachures.append([point, (point[0] + inward[0] * length,
                                     point[1] + inward[1] * length)])
        ax.add_collection(mc.LineCollection(
            hachures, colors=palette['relief_hatch'], linewidths=0.3,
            zorder=2.3))
        ax.add_collection(mc.LineCollection(
            [ridge], colors=palette['relief_line'], linewidths=1.1, zorder=2.4))


# --------------------------------------------------------- شهرستان واقعی ----
def _local_projector(city):
    """تبدیل طول/عرض به متر در دستگاه مختصات محلی با مبدأ مرکز شهر."""
    m_lat = 110574.0
    m_lon = 111320.0 * math.cos(math.radians(city['lat']))

    def convert(lon, lat):
        return ((lon - city['lon']) * m_lon, (lat - city['lat']) * m_lat)

    return convert


def _city_counties(city, half_x, half_y):
    """مرز شهرستان‌های واقعی OSM که با کادر شهر تلاقی دارند، به مترِ محلی."""
    from shapely.geometry import box
    from shapely.ops import transform as shp_transform

    province = next((p for p in core.load_provinces()
                     if p['slug'] == city['province']), None)
    if province is None:
        return [], None
    county_dir = core.ensure_counties()
    folder = core.resolve_folder(county_dir, province)
    path = county_dir / folder / (folder + '.geojson')
    want = {core.norm(c['name_fa']): True for c in core.load_counties()
            if c['province'] == province['slug']}
    convert = _local_projector(city)
    window = box(-half_x, -half_y, half_x, half_y)
    out = []
    for tags, geom in core._iter_county_records(path):
        key = core.norm(core._strip_county_prefix(tags.get('name', '')))
        if key not in want or geom is None:
            continue
        local = shp_transform(lambda x, y, z=None: convert(x, y), geom)
        if not local.intersects(window):
            continue
        name = core._COUNTY_SUFFIX.sub('', str(tags.get('name:en', '')).strip())
        out.append((name, local))
    centre_county = None
    for name, geom in out:
        if geom.contains(window.centroid):
            centre_county = name
            break
    return out, centre_county


def _draw_counties(ax, palette, counties):
    import matplotlib.collections as mc

    for _, geom in counties:
        ax.add_collection(mc.LineCollection(
            base._paths([geom]), colors=palette['county'], linewidths=0.7,
            linestyles=(0, (5.0, 3.0)), zorder=6))


# ------------------------------------------------------------------ رسم -----
def build_scene(rng, city):
    half_x = city['span'][0] / 2.0
    half_y = city['span'][1] / 2.0
    layout = city.get('layout', 'grid')
    if layout == 'valley':
        scene = _valley_network(rng, city.get('valley', {}), half_x, half_y)
    else:
        scene = _grid_network(rng, city.get('grid', {}), half_x, half_y)
    rivers = []
    valley_river = scene.pop('river', None)
    if valley_river:
        rivers.append(valley_river)
    rivers.extend(_river_layers(rng, city.get('rivers', [])))
    scene['rivers'] = rivers
    scene['relief'] = _relief_layers(rng, city.get('relief', []))
    scene['lakes'] = [_blob(rng, float(s['cx']), float(s['cy']), float(s['rx']),
                            float(s['ry']), float(s.get('rot', 0.0)))
                      for s in city.get('lakes', [])]
    scene['greens'] = _greens(rng, city, half_x, half_y)
    return scene


def _greens(rng, city, half_x, half_y):
    """لکه‌های سبزِ پیرامون شهر؛ شهر را از زمینِ خالی جدا می‌کند."""
    core = float(city.get('grid', {}).get('radius', 4500.0))
    limit = math.hypot(half_x, half_y) * 0.96
    out = []
    for _ in range(int(city.get('greens', 10))):
        for _try in range(24):
            x = rng.uniform(-half_x, half_x)
            y = rng.uniform(-half_y, half_y)
            if core * 1.02 < math.hypot(x, y) < limit:
                break
        else:
            continue
        poly = _blob(rng, x, y, rng.uniform(220.0, 620.0),
                     rng.uniform(160.0, 460.0), rng.uniform(0.0, 180.0))
        out.append((poly, 'park' if rng.random() < 0.45 else 'scrub'))
    return out


def _land_backdrop(ax, palette, half_x, half_y):
    """پس‌زمینهٔ رنگ زمین؛ با axis('off') لکهٔ محور رسم نمی‌شود."""
    from matplotlib.patches import Rectangle

    patch = Rectangle((-half_x * 1.06, -half_y * 1.06), half_x * 2.12,
                      half_y * 2.12, facecolor=palette['land'],
                      edgecolor='none', zorder=0)
    ax.add_patch(patch)
    return patch


def draw_city(ax, palette, scene, half_x, half_y):
    import matplotlib.collections as mc

    _land_backdrop(ax, palette, half_x, half_y)
    reach = math.hypot(half_x, half_y)
    _draw_relief(ax, palette, scene['relief'], reach)

    if scene['lakes']:
        ax.add_collection(mc.PolyCollection(
            scene['lakes'], facecolors=palette['water'],
            edgecolors=palette['ink_faint'], linewidths=0.5, zorder=4))

    for tone, order in (('scrub', 2.6), ('park', 2.7)):
        polys = [poly for poly, key in scene.get('greens', []) if key == tone]
        if polys:
            ax.add_collection(mc.PolyCollection(
                polys, facecolors=palette[tone], edgecolors='none',
                zorder=order))

    kinds = (
        ('park', palette['park'], 4.0),
        ('industrial', palette['industrial'], 4.1),
        ('built_alt', palette['built_alt'], 4.2),
        ('built', palette['built'], 4.2),
    )
    for kind, colour, order in kinds:
        polys = [poly for poly, k in scene['blocks'] if k == kind]
        if polys:
            ax.add_collection(mc.PolyCollection(
                polys, facecolors=colour, edgecolors='none', zorder=order))

    if scene['rivers']:
        paths = [r['pts'] for r in scene['rivers']]
        widths = [r['width'] for r in scene['rivers']]
        ax.add_collection(mc.LineCollection(
            paths, colors=palette['ink_faint'],
            linewidths=[w + 3.0 for w in widths], zorder=5, capstyle='round'))
        ax.add_collection(mc.LineCollection(
            paths, colors=palette['river'], linewidths=widths,
            zorder=5.1, capstyle='round'))

    roads = [(cls, pts) for cls, pts in scene['roads'] if cls != 'river']
    for cls in ('street', 'service', 'secondary', 'primary', 'trunk', 'motorway'):
        segs = [pts for k, pts in roads if k == cls]
        if not segs:
            continue
        width = ROAD_WIDTH[cls]
        if cls in CASED:
            ax.add_collection(mc.LineCollection(
                segs, colors=palette['ink'], linewidths=width + 0.95,
                zorder=8, capstyle='round'))
            ax.add_collection(mc.LineCollection(
                segs, colors=palette['road_core'], linewidths=width,
                zorder=9, capstyle='round'))
        else:
            ax.add_collection(mc.LineCollection(
                segs, colors=palette['ink'], linewidths=width, zorder=8,
                capstyle='round'))


def draw_labels(ax, palette, city):
    import matplotlib.patheffects as pe

    halo = [pe.withStroke(linewidth=2.2, foreground=palette['paper'])]
    for x, y, text, weight in city.get('labels', []):
        ax.text(x, y, text, ha='center', va='center', zorder=11,
                fontsize=7.2 * weight, color=palette['label'],
                fontfamily='DejaVu Serif', fontstyle='italic',
                path_effects=halo)


def draw_county_labels(ax, palette, counties, centre_county):
    import matplotlib.patheffects as pe

    halo = [pe.withStroke(linewidth=2.0, foreground=palette['paper'])]
    for name, geom in counties:
        if not name or name == centre_county:
            continue
        point = geom.representative_point()
        ax.text(point.x, point.y, base._letterspace(name), ha='center',
                va='center', zorder=10, fontsize=6.4, color=palette['county'],
                fontfamily='DejaVu Sans', path_effects=halo)


def scale_bar(ax, palette):
    """نوار مقیاس؛ واحد داده متر است، پس نوار از روی پهنای کادر خوانده می‌شود."""
    box = ax.get_window_extent()          # پیکسل، پس از fig.canvas.draw()
    span_m = (ax.get_xlim()[1] - ax.get_xlim()[0]) or 1.0
    metres_per_px = span_m / max(box.width, 1.0)
    choices = (100, 200, 250, 500, 1000, 2000, 5000)
    distance = min(choices, key=lambda d: abs(d - metres_per_px * 150.0))
    frac = distance / span_m
    if not 0.02 < frac < 0.85:
        return
    x0, y0 = 0.02, 0.018
    ax.add_patch(base._bar(ax, x0, y0, frac, palette))
    ax.text(x0 + frac / 2, y0 + 0.030, '%g km' % (distance / 1000.0),
            transform=ax.transAxes, ha='center', va='bottom', fontsize=7.5,
            color=palette['ink_soft'], fontfamily='DejaVu Sans Mono',
            zorder=12, clip_on=False)
    ax.add_patch(_north_arrow(ax, 0.028, 0.90, palette))


def _north_arrow(ax, x, y, palette):
    from matplotlib.patches import Polygon

    arrow = Polygon([(x, y + 0.075), (x - 0.013, y + 0.022), (x, y + 0.042),
                     (x + 0.013, y + 0.022)], closed=True, zorder=12,
                    facecolor=palette['ink'], edgecolor='none')
    ax.add_patch(arrow)
    ax.text(x, y + 0.086, 'N', transform=ax.transAxes, ha='center',
            va='bottom', fontsize=8.5, color=palette['ink_soft'],
            fontfamily='DejaVu Serif', fontweight='bold', zorder=12)
    return arrow


def _render(city_slug, style, out_dir, args, counties, centre_county):
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt

    city = CITIES[city_slug]
    palette = dict(base.STYLES[style])
    palette.update(EXTRA_PALETTE[style])
    rng = random.Random(city['seed'] + args.seed_offset)

    fig = plt.figure(figsize=tuple(args.size), facecolor=palette['paper'])
    ax = fig.add_axes([0.02, 0.15, 0.96, 0.83])
    ax.set_aspect('equal', adjustable='box')
    ax.axis('off')

    half_x = city['span'][0] / 2.0
    half_y = city['span'][1] / 2.0
    scene = build_scene(rng, city)
    draw_city(ax, palette, scene, half_x, half_y)
    if not args.no_counties and counties:
        _draw_counties(ax, palette, counties)
        draw_county_labels(ax, palette, counties, centre_county)
    draw_labels(ax, palette, city)

    ax.set_xlim(-half_x * 1.04, half_x * 1.04)
    ax.set_ylim(-half_y * 1.04, half_y * 1.04)
    fig.canvas.draw()
    scale_bar(ax, palette)

    block = {'en': city['en'], 'name_fa': city['fa']}
    base.title_block(ax, block, palette, city['lat'], city['lon'])
    ax.figure.text(0.02, 0.995, DISCLAIMER, ha='left', va='top', fontsize=8,
                   color=palette['accent'], fontfamily='DejaVu Sans Mono',
                   zorder=13)

    out_dir.mkdir(parents=True, exist_ok=True)
    path = out_dir / ('%s%s-%s.png' % (args.prefix, city_slug, style))
    fig.savefig(path, dpi=args.dpi, facecolor=palette['paper'])
    plt.close(fig)
    return path


# ------------------------------------------------------------------ main ----
def build_parser():
    p = argparse.ArgumentParser(
        description='نمای شهری گرافیکی (سبک‌شده) برای شهرهای ایران')
    p.add_argument('--only', default=','.join(DEFAULT_CITIES))
    p.add_argument('--styles', default=','.join(DEFAULT_STYLES),
                   help='sand,ink')
    p.add_argument('--out', default=str(REPO / 'outputs' / 'city-views'))
    p.add_argument('--prefix', default='city-')
    p.add_argument('--size', type=float, nargs=2, default=(12.0, 9.0),
                   metavar=('W', 'H'))
    p.add_argument('--dpi', type=int, default=200)
    p.add_argument('--seed-offset', type=int, default=0)
    p.add_argument('--no-counties', action='store_true',
                   help='مرز شهرستان‌ها نکِشیده شود')
    p.add_argument('--list', action='store_true')
    p.add_argument('--check', action='store_true')
    return p


def main(argv=None):
    args = build_parser().parse_args(argv)
    wanted = [s.strip() for s in args.only.split(',') if s.strip()]
    unknown = [s for s in wanted if s not in CITIES]
    if unknown:
        sys.stderr.write('شهر ناشناخته: %s (مجاز: %s)\n'
                         % (', '.join(unknown), ', '.join(CITIES)))
        return 2
    styles = [s.strip() for s in args.styles.split(',') if s.strip()]
    bad = [s for s in styles if s not in EXTRA_PALETTE]
    if bad:
        sys.stderr.write('مدل ناشناخته: %s (مجاز: %s)\n'
                         % (', '.join(bad), ', '.join(EXTRA_PALETTE)))
        return 2
    if args.list:
        for slug, city in CITIES.items():
            print('%-10s %-14s %s' % (slug, city['en'], city['fa']))
        return 0

    out_dir = Path(args.out).expanduser()
    if args.check:
        return _check(wanted, styles, out_dir, args)

    try:
        import matplotlib  # noqa: F401
    except ImportError:
        sys.stderr.write('نیازمندی نصب نیست: '
                         'pip install --break-system-packages matplotlib\n')
        return 2

    written = 0
    for index, slug in enumerate(wanted, 1):
        city = CITIES[slug]
        sys.stderr.write('[%d/%d] %s (%s)\n' % (index, len(wanted), city['fa'],
                                                city['en']))
        counties, centre_county = ([], None)
        if not args.no_counties:
            try:
                counties, centre_county = _city_counties(
                    city, city['span'][0] / 2.0, city['span'][1] / 2.0)
                sys.stderr.write('  مرز شهرستان‌ها: %d پهنه (%s)\n'
                                 % (len(counties), centre_county or '—'))
            except Exception as exc:                # noqa: BLE001
                sys.stderr.write('  مرز شهرستان‌ها در دسترس نبود (%s)\n' % exc)
        for style in styles:
            try:
                path = _render(slug, style, out_dir, args, counties, centre_county)
            except Exception as exc:                # noqa: BLE001
                sys.stderr.write('  ناموفق (%s): %s\n' % (style, exc))
                continue
            written += 1
            sys.stderr.write('  → %s\n' % path.name)

    print('%d تصویر در %s' % (written, out_dir))
    return 0 if written else 1


def _check(cities, styles, out_dir, args):
    print('پوشه: %s' % out_dir)
    missing = 0
    for slug in cities:
        for style in styles:
            name = '%s%s-%s.png' % (args.prefix, slug, style)
            if not (out_dir / name).is_file():
                missing += 1
    total = len(cities) * len(styles)
    print('تصویرهای موجود: %d از %d' % (total - missing, total))
    return 0 if missing == 0 else 1


if __name__ == '__main__':
    sys.exit(main())