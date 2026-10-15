#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""نقاشی آبرنگ برای کارت تصویر شاخص «دیدنی‌ها» (v2.18.18).

این ماژول «قلم آبرنگ» است: هر شکل با لایه‌های نیمه‌شفاف، لبه‌های نامنظم و
دانه‌بندی کاغذ کشیده می‌شود؛ نه شکل هندسی تخت. صحنه‌ها همان `scene`های پروندهٔ
`featured-cards.json` هستند (آبشار، دریاچه، روستا، تالاب، قلعه، دشت، غار، …) و
هر کدام از محتوای همان مقاله گرفته شده‌اند.

استفاده:
    from featured_card_watercolor import paint
    art = paint('waterfall', flags=['rail', 'oak'], size=(560, 520), seed=11)

خروجی: تصویر RGB به‌اندازهٔ `size` با کاغذ سفید و رنگ‌های شاداب.

@package Sarzaminaryan_Child
"""

import math
import random

from PIL import Image, ImageChops, ImageDraw, ImageFilter

SS = 2  # میزان بزرگ‌نمایی برای لبه‌های نرم

# --------------------------------------------------------------- پالت رنگ ---
C = {
    'sky_hi': (70, 152, 228), 'sky': (128, 198, 244), 'sky_lo': (208, 236, 252),
    'sky_dusk': (248, 204, 152), 'sky_night': (48, 68, 128),
    'sun': (255, 194, 58), 'sun_hi': (255, 142, 44), 'sun_halo': (255, 230, 150),
    'cloud': (255, 255, 255), 'cloud_soft': (228, 242, 253),
    'water_deep': (16, 92, 162), 'water': (36, 146, 210), 'water_light': (110, 204, 236),
    'water_foam': (250, 254, 255), 'turq': (48, 188, 186), 'turq_deep': (22, 138, 154),
    'grass': (108, 196, 88), 'grass_deep': (48, 136, 72), 'leaf': (76, 176, 84),
    'pine': (26, 106, 74), 'moss': (142, 204, 108), 'olive': (120, 152, 74),
    'rock': (186, 176, 194), 'rock_cool': (136, 146, 186), 'rock_shadow': (94, 102, 150),
    'rock_warm': (224, 156, 102), 'rock_rust': (188, 104, 66), 'rock_pale': (238, 214, 188),
    'sand': (246, 198, 112), 'sand_deep': (222, 156, 76), 'sand_pale': (253, 230, 180),
    'terra': (232, 106, 76), 'terra_deep': (192, 64, 52), 'clay': (208, 134, 88),
    'cream': (250, 240, 214), 'gold': (246, 176, 50), 'brick': (202, 128, 92),
    'blossom': (232, 72, 104), 'rose': (244, 138, 162), 'purple': (142, 96, 200),
    'violet': (176, 148, 220), 'lilac': (206, 186, 236),
    'navy': (14, 58, 96), 'ink': (18, 44, 74), 'snow': (247, 251, 255),
    'stone': (228, 218, 196), 'stone_deep': (186, 168, 142),
    'night_win': (58, 86, 140), 'star': (255, 240, 186), 'shadow': (86, 104, 140),
}
PAPER = (255, 255, 255)
SOFT = 0.40  # ضریب نرمی: لبه‌ها واضح بمانند (آبرنگ تمیز، نه محو)
INKLINE = (30, 68, 108)  # رنگ خط دور شکل‌ها


def _mix(c1, c2, t):
    return tuple(int(round(c1[i] + (c2[i] - c1[i]) * t)) for i in range(3))


class Art:
    """بوم آبرنگ: کاغذ سفید + لایه‌های رنگ نیمه‌شفاف."""

    def __init__(self, w, h, seed=7):
        self.rng = random.Random(seed)
        self.w, self.h = int(w), int(h)
        self.W, self.H = self.w * SS, self.h * SS
        self.img = Image.new('RGB', (self.W, self.H), PAPER)
        self._paper()

    # ------------------------------------------------------------- کاغذ ---
    def _paper(self):
        grain = Image.effect_noise((self.W, self.H), 11).convert('L')
        grain = grain.filter(ImageFilter.GaussianBlur(0.6))
        grain = grain.point(lambda v: int(252 + v * 4 / 255))
        mottle = Image.effect_noise((self.W, self.H), 24).convert('L')
        mottle = mottle.filter(ImageFilter.GaussianBlur(self.W / 22.0))
        mottle = mottle.point(lambda v: int(247 + v * 9 / 255))
        tex = ImageChops.multiply(grain, mottle)
        self.img = ImageChops.multiply(self.img, tex.convert('RGB'))

    def _noise(self, lo=0.76, hi=1.0, sigma=32, blur=None):
        n = Image.effect_noise((self.W, self.H), sigma).convert('L')
        n = n.filter(ImageFilter.GaussianBlur(blur if blur is not None else self.W / 52.0))
        return n.point(lambda v: int(255 * (lo + (hi - lo) * v / 255.0)))

    # ----------------------------------------------------------- ماسک‌ها ---
    def _px(self, pts, jitter):
        r = self.rng
        return [((x) * SS + r.uniform(-jitter, jitter) * SS,
                 (y) * SS + r.uniform(-jitter, jitter) * SS) for x, y in pts]

    @staticmethod
    def _edge_point(pts, r):
        i = r.randrange(len(pts))
        x0, y0 = pts[i]
        x1, y1 = pts[(i + 1) % len(pts)]
        t = r.random()
        return x0 + (x1 - x0) * t, y0 + (y1 - y0) * t

    def _jitter(self, pts, jitter):
        r = self.rng
        return [(x * SS + r.uniform(-jitter, jitter) * SS,
                 y * SS + r.uniform(-jitter, jitter) * SS) for x, y in pts]

    def _mask_from(self, jp, blur=5.0, bites=5, bite=0.05):
        m = Image.new('L', (self.W, self.H), 0)
        d = ImageDraw.Draw(m)
        d.polygon(jp, fill=255)
        if bites:
            span = min(self.W, self.H)
            for _ in range(bites):
                x, y = self._edge_point(jp, self.rng)
                rad = self.rng.uniform(bite * 0.20, bite * 0.55) * span
                d.ellipse([x - rad, y - rad, x + rad, y + rad], fill=0)
        return m.filter(ImageFilter.GaussianBlur(max(0.6, blur * SOFT) * SS))

    def _line_mask(self, p, width, blur=2.0):
        m = Image.new('L', (self.W, self.H), 0)
        d = ImageDraw.Draw(m)
        w = max(2, int(width * SS))
        d.line(p, fill=255, width=w, joint='curve')
        for x, y in (p[0], p[-1]):
            r = w * 0.5
            d.ellipse([x - r, y - r, x + r, y + r], fill=255)
        return m.filter(ImageFilter.GaussianBlur(max(0.5, blur * SOFT) * SS))

    def _paint_mask(self, mask, color, alpha, grain=True):
        if grain:
            mask = ImageChops.multiply(mask, self._noise())
        mask = mask.point(lambda v: int(min(255, v * min(1.0, alpha))))
        layer = Image.new('RGB', (self.W, self.H), tuple(color))
        self.img = Image.composite(layer, self.img, mask)

    # ------------------------------------------------------------ قلم‌ها ---
    def wash(self, pts, color, alpha=0.82, jitter=3.0, blur=5.0, bites=5, bite=0.05,
             grain=True, edge=None, edge_w=2.0, edge_alpha=0.62):
        """لکه‌رنگ آبرنگی با لبهٔ نامنظم و اختیاری خط دور واضح."""
        jp = self._jitter(pts, jitter)
        self._paint_mask(self._mask_from(jp, blur, bites, bite), color, alpha, grain)
        if edge is not None:
            ec = _mix(color, INKLINE, 0.68) if edge is True else edge
            self._paint_mask(self._line_mask(jp + [jp[0]], edge_w, 1.1), ec, edge_alpha, False)
        return jp

    def stroke(self, pts, width, color, alpha=0.88, blur=2.0, taper=True, grain=True):
        """ضربهٔ قلم (خط) با دو سر گرد."""
        p = [(x * SS + self.rng.uniform(-1.2, 1.2), y * SS + self.rng.uniform(-1.2, 1.2))
             for x, y in pts]
        if taper:
            m = self._line_mask(p, width, blur)
        else:
            m = Image.new('L', (self.W, self.H), 0)
            ImageDraw.Draw(m).line(p, fill=255, width=max(2, int(width * SS)), joint='curve')
            m = m.filter(ImageFilter.GaussianBlur(max(0.5, blur * SOFT) * SS))
        self._paint_mask(m, color, alpha, grain)

    def blob(self, cx, cy, r, color, alpha=0.82, ry=None, jitter=2.5, blur=4.0,
             grain=True, edge=None):
        """لکهٔ گرد (ابر، برگ، گل)."""
        ry = r if ry is None else ry
        pts = []
        n = 22
        for i in range(n):
            t = 2 * math.pi * i / n
            wob = 1 + self.rng.uniform(-0.10, 0.10)
            pts.append((cx + math.cos(t) * r * wob, cy + math.sin(t) * ry * wob))
        self.wash(pts, color, alpha, jitter=jitter, blur=blur, bites=2, bite=0.03,
                  grain=grain, edge=edge)

    def splatter(self, cx, cy, color, n=16, r0=2.0, r1=8.0, spread=48, alpha=0.75):
        """پاشیدگی رنگ (شادابی آبرنگ)."""
        m = Image.new('L', (self.W, self.H), 0)
        d = ImageDraw.Draw(m)
        for _ in range(int(n)):
            t = self.rng.uniform(0, 2 * math.pi)
            dist = abs(self.rng.gauss(0, spread / 2.0))
            x = (cx + math.cos(t) * dist) * SS
            y = (cy + math.sin(t) * dist) * SS
            r = self.rng.uniform(r0, r1) * SS
            d.ellipse([x - r, y - r, x + r, y + r], fill=255)
        m = m.filter(ImageFilter.GaussianBlur(1.2 * SS))
        self._paint_mask(m, color, alpha, True)

    def glow(self, cx, cy, r, color, alpha=0.5, ry=None, steps=9):
        """هالهٔ نرم (خورشید، بازتاب نور)."""
        ry = r if ry is None else ry
        for i in range(steps, 0, -1):
            t = i / steps
            m = Image.new('L', (self.W, self.H), 0)
            d = ImageDraw.Draw(m)
            rr, rry = r * t * SS, ry * t * SS
            d.ellipse([cx * SS - rr, cy * SS - rry, cx * SS + rr, cy * SS + rry], fill=255)
            m = m.filter(ImageFilter.GaussianBlur(r * 0.20 * SS))
            self._paint_mask(m, color, alpha * (1 - t) ** 0.9 + 0.04, False)

    # ------------------------------------------------------------ خروجی ---
    def finish(self):
        """پایان‌بندی آبرنگی: لکه‌های ملایم رنگ، دانهٔ کاغذ و کمی نرمی."""
        w, h = self.w, self.h
        for cx, cy, rr, col in ((0.04, 0.96, 0.24, C['turq']),
                                (0.96, 0.04, 0.20, C['rose']),
                                (0.50, -0.06, 0.26, C['sky'])):
            self.glow(cx * w, cy * h, rr * min(w, h), col, alpha=0.085, steps=4)
        grain = Image.effect_noise((self.W, self.H), 9).convert('L')
        grain = grain.filter(ImageFilter.GaussianBlur(0.5))
        grain = grain.point(lambda v: int(250 + v * 6 / 255))
        self.img = ImageChops.multiply(self.img, grain.convert('RGB'))

    def image(self):
        self.finish()
        out = self.img.resize((self.w, self.h), Image.LANCZOS)
        out = Image.blend(out, Image.new('RGB', out.size, PAPER), 0.02)
        return out


# ------------------------------------------------------------- اجزای صحنه ---

def sky(a, top=None, bottom=None, y_lo=0.74, y_hi=-0.06):
    w, h = a.w, a.h
    top = top or C['sky']
    bottom = bottom or C['sky_lo']
    a.wash([(w * -0.04, h * y_hi), (w * 1.04, h * y_hi), (w * 1.04, h * (y_lo - 0.16)),
            (w * 0.5, h * (y_lo - 0.10)), (w * -0.04, h * (y_lo - 0.16))],
           top, alpha=0.62, jitter=7, blur=11, bites=4, bite=0.07)
    a.wash([(w * -0.04, h * (y_lo - 0.30)), (w * 1.04, h * (y_lo - 0.26)),
            (w * 1.04, h * y_lo), (w * -0.04, h * y_lo)],
           bottom, alpha=0.5, jitter=7, blur=12, bites=4, bite=0.07)


def sun(a, cx, cy, r, core=None, halo=None, rays=False):
    core = core or C['sun']
    halo = halo or C['sun_halo']
    a.glow(cx, cy, r * 2.4, halo, alpha=0.5)
    pts = [(cx + math.cos(2 * math.pi * i / 26) * r, cy + math.sin(2 * math.pi * i / 26) * r)
           for i in range(26)]
    a.wash(pts, core, alpha=0.95, jitter=0.8, blur=2.0, bites=0, edge=C['sun_hi'],
           edge_w=1.4, edge_alpha=0.5)
    a.blob(cx - r * 0.22, cy - r * 0.24, r * 0.42, _mix(core, (255, 255, 255), 0.5),
           alpha=0.7, jitter=1.0, blur=2.0)
    if rays:
        for i in range(8):
            t = 2 * math.pi * i / 8 + 0.2
            a.stroke([(cx + math.cos(t) * r * 1.35, cy + math.sin(t) * r * 1.35),
                      (cx + math.cos(t) * r * 1.85, cy + math.sin(t) * r * 1.85)],
                     max(2, r * 0.16), C['sun_hi'], alpha=0.5, blur=2.0)


def clouds(a, y, n=3, x0=0.14, x1=0.86, color=None, r=0.075):
    color = color or C['cloud']
    for i in range(n):
        t = (i + 0.5) / n
        cx = a.w * (x0 + (x1 - x0) * t) + a.rng.uniform(-12, 12)
        cy = a.h * y + a.rng.uniform(-10, 10)
        base = min(a.w, a.h) * r
        a.blob(cx, cy, base * a.rng.uniform(0.9, 1.25), color, alpha=0.5,
               ry=base * 0.42, jitter=4, blur=7)
        a.blob(cx - base * 0.5, cy + base * 0.08, base * 0.6, color, alpha=0.42,
               ry=base * 0.3, jitter=4, blur=6)


def peaks(a, base_y, height, color=None, n=2, snow=True, snow_from=0.42, x_spread=(0.05, 0.95)):
    color = color or C['rock_cool']
    w, h = a.w, a.h
    base = h * base_y
    for i in range(n):
        cx = w * (x_spread[0] + (x_spread[1] - x_spread[0]) * (i + 0.5) / n)
        cx += a.rng.uniform(-w * 0.04, w * 0.04)
        hh = h * height * a.rng.uniform(0.82, 1.12)
        hw = hh * a.rng.uniform(0.75, 1.05)
        crest = (cx, base - hh)
        pts = [crest, (cx + hw * 0.55, base - hh * 0.5), (cx + hw, base + h * 0.03),
               (cx, base + h * 0.03), (cx - hw, base + h * 0.03), (cx - hw * 0.55, base - hh * 0.5)]
        a.wash(pts, color, alpha=a.rng.uniform(0.72, 0.88), jitter=4, blur=7, bites=3, bite=0.035,
               edge=True, edge_w=1.8, edge_alpha=0.45)
        a.wash([crest, (cx + hw * 0.30, base - hh * 0.55), (cx, base - hh * 0.5),
                (cx - hw * 0.30, base - hh * 0.55)],
               _mix(color, C['rock_shadow'], 0.45), alpha=0.55, jitter=4, blur=7, bites=2, bite=0.03)
        if snow:
            a.wash([crest, (cx + hw * 0.30, base - hh * (1 - snow_from)),
                    (cx + hw * 0.10, base - hh * (1 - snow_from) * 0.86),
                    (cx - hw * 0.06, base - hh * (1 - snow_from) * 1.06),
                    (cx - hw * 0.30, base - hh * (1 - snow_from))],
                   C['snow'], alpha=0.88, jitter=4, blur=7, bites=3, bite=0.035)


def hills(a, y, amp=0.09, color=None, n=3, alpha=0.6):
    color = color or C['rock_cool']
    w, h = a.w, a.h
    pts = [(w * -0.05, h * (y + amp))]
    for i in range(n * 2 + 1):
        t = i / (n * 2.0)
        pts.append((w * (-0.05 + 1.1 * t), h * (y - math.sin(t * math.pi * n) * amp)))
    pts += [(w * 1.05, h * (y + amp * 1.2)), (w * 1.05, h * 1.05), (w * -0.05, h * 1.05)]
    a.wash(pts, color, alpha=alpha, jitter=5, blur=8, bites=3, bite=0.035)


def ground(a, y, color=None, jitter=0.03, alpha=0.72):
    color = color or C['grass']
    w, h = a.w, a.h
    j = h * jitter
    pts = [(w * -0.05, h * y + j), (w * 0.28, h * y - j * 0.7), (w * 0.55, h * y + j * 0.5),
           (w * 0.78, h * y - j * 0.6), (w * 1.05, h * y + j * 0.4),
           (w * 1.05, h * 1.06), (w * -0.05, h * 1.06)]
    a.wash(pts, color, alpha=alpha, jitter=5, blur=8, bites=3, bite=0.04)


def water_body(a, box, deep=None, light=None, rim=True):
    deep = deep or C['water_deep']
    light = light or C['water_light']
    x0, y0, x1, y1 = [box[0] * a.w, box[1] * a.h, box[2] * a.w, box[3] * a.h]
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    rx, ry = (x1 - x0) / 2, (y1 - y0) / 2
    pts = [(cx + math.cos(2 * math.pi * i / 30) * rx, cy + math.sin(2 * math.pi * i / 30) * ry)
           for i in range(30)]
    a.wash(pts, C['water'], alpha=0.88, jitter=4, blur=6, bites=2, bite=0.025,
           edge=_mix(C['water_deep'], INKLINE, 0.35), edge_w=1.8, edge_alpha=0.5)
    inner = [(cx + math.cos(2 * math.pi * i / 30) * rx * 0.66,
              cy + math.sin(2 * math.pi * i / 30) * ry * 0.62) for i in range(30)]
    a.wash(inner, deep, alpha=0.7, jitter=4, blur=7, bites=2, bite=0.025)
    if rim:
        a.stroke([(cx - rx * 0.9, cy - ry * 0.25), (cx - rx * 0.3, cy - ry * 0.45),
                  (cx + rx * 0.35, cy - ry * 0.32), (cx + rx * 0.92, cy - ry * 0.5)],
                 max(3, (y1 - y0) * 0.035), light, alpha=0.5, blur=5)
    for i in range(4):
        t = -0.5 + i * 0.34
        a.stroke([(cx - rx * 0.6, cy + ry * t), (cx + rx * 0.55, cy + ry * (t + 0.06))],
                 max(2.5, (y1 - y0) * 0.022), _mix(light, C['cloud'], 0.35),
                 alpha=0.45, blur=4)


def cascade(a, cx, y0, y1, width, color=None, edge=None):
    color = color or C['water_foam']
    edge = edge or C['water_light']
    w, h = a.w, a.h
    x0, x1 = w * cx, w * (cx + width)
    top, bottom = h * y0, h * y1
    pts = [(x0, top), (x1, top), (x1 * 1.0, bottom), (x0 * 1.0, bottom)]
    a.wash(pts, color, alpha=0.9, jitter=3, blur=6, bites=4, bite=0.035)
    for i in range(5):
        x = x0 + (x1 - x0) * (i + 0.35) / 5
        a.stroke([(x, top + 4), (x + a.rng.uniform(-6, 6), (top + bottom) / 2),
                  (x + a.rng.uniform(-8, 8), bottom - 3)], width * w * 0.09, edge,
                 alpha=0.5, blur=4)
    a.splatter((x0 + x1) / 2, bottom, color, n=22, r0=2.5, r1=11, spread=width * w * 0.7, alpha=0.75)
    a.splatter((x0 + x1) / 2, bottom, edge, n=12, r0=2.0, r1=7, spread=width * w * 0.9, alpha=0.6)


def rock_texture(a, box, color=None, n=7, seed_jitter=18):
    """ترک‌ها و چین‌خوردگی سنگ (خطوط کوتاه تیره)."""
    color = color or C['rock_shadow']
    x0, y0, x1, y1 = box
    w, h = a.w, a.h
    for i in range(n):
        cx = w * (x0 + (x1 - x0) * a.rng.random())
        cy = h * (y0 + (y1 - y0) * a.rng.random())
        ln = w * (x1 - x0) * a.rng.uniform(0.10, 0.30)
        ang = a.rng.uniform(-0.5, 0.5)
        a.stroke([(cx, cy), (cx + math.cos(ang) * ln, cy + math.sin(ang) * ln * 0.6)],
                 max(2, w * 0.008), color, alpha=0.45, blur=2.6)


def ledge(a, y, x0, x1, color=None, thickness=0.035):
    """طبقهٔ سنگی افقی (طاقچهٔ صخره)."""
    color = color or C['rock_pale']
    w, h = a.w, a.h
    a.wash([(w * x0, h * y), (w * x1, h * y - h * 0.012),
            (w * x1, h * (y + thickness)), (w * x0, h * (y + thickness * 1.25))],
           color, alpha=0.85, jitter=5, blur=5, bites=2, bite=0.03, edge=True,
           edge_w=1.6, edge_alpha=0.4)


def rocks(a, box, colors=None, n=4):
    colors = colors or [C['rock_warm'], C['rock_rust'], C['clay']]
    x0, y0, x1, y1 = box
    w, h = a.w, a.h
    for i in range(n):
        cx = w * (x0 + (x1 - x0) * (i + 0.5) / n) + a.rng.uniform(-10, 10)
        cy = h * (y0 + (y1 - y0) * a.rng.uniform(0.35, 0.7))
        rw = w * (x1 - x0) / n * a.rng.uniform(0.55, 0.9)
        rh = h * (y1 - y0) * a.rng.uniform(0.35, 0.75)
        pts = [(cx - rw, cy + rh), (cx - rw * 0.75, cy - rh * 0.5), (cx - rw * 0.15, cy - rh),
               (cx + rw * 0.6, cy - rh * 0.75), (cx + rw, cy + rh * 0.2), (cx + rw * 0.4, cy + rh)]
        a.wash(pts, colors[i % len(colors)], alpha=0.84, jitter=4, blur=6, bites=2, bite=0.025,
               edge=True, edge_w=1.5, edge_alpha=0.4)


def cliff_wall(a, side, y_top, y_bottom, color=None, depth=0.34, strata=True):
    """صخرهٔ پرشیب با پروفیل ناهموار، لبهٔ داخلی پله‌ای و لایه‌های سنگی."""
    color = color or C['rock_warm']
    w, h = a.w, a.h
    inner = depth * w
    top, bot = y_top * h, y_bottom * h
    pts = [(-0.06 * w, top)]
    for i in range(9):  # پروفیل بالای صخره (ناهموار و ملایم)
        t = i / 8.0
        x = (-0.06 * w) + (inner + 0.06 * w) * t
        pts.append((x, top + math.sin(t * 3.1 + (0.0 if side == 'left' else 1.4))
                    * h * 0.022 + a.rng.uniform(-h * 0.008, h * 0.008)))
    for i in range(9):  # لبهٔ داخلی پله‌پله (از بالا به پایین)
        t = i / 8.0
        wob = 1.0 - (0.06 if i % 2 else 0.0) - a.rng.uniform(0, 0.05)
        pts.append((inner * wob, top + (bot - top) * t))
    pts.append((-0.06 * w, bot))
    if side == 'right':
        pts = [(w - x, y) for x, y in pts]
    a.wash(pts, color, alpha=0.93, jitter=3.5, blur=5.5, bites=2, bite=0.028,
           edge=True, edge_w=1.8, edge_alpha=0.45)

    def band(y, th, tone, alpha):
        y0, y1 = y * h, (y + th) * h
        if side == 'left':
            bp = [(-0.06 * w, y0), (inner * 1.04, y0 + h * 0.010),
                  (inner * 0.92, y1), (-0.06 * w, y1)]
        else:
            bp = [(w + 0.06 * w, y0), (w - inner * 1.04, y0 + h * 0.010),
                  (w - inner * 0.92, y1), (w + 0.06 * w, y1)]
        a.wash(bp, tone, alpha=alpha, jitter=3, blur=5, bites=2, bite=0.025)

    if strata:
        band(0.20, 0.030, _mix(color, C['rock_rust'], 0.60), 0.34)
        band(0.32, 0.024, _mix(color, C['rock_rust'], 0.70), 0.26)
        band(0.52, 0.030, _mix(color, C['rock_rust'], 0.62), 0.32)
        band(0.66, 0.022, _mix(color, C['rock_rust'], 0.72), 0.24)
        band(0.80, 0.040, _mix(color, C['rock_shadow'], 0.45), 0.34)
    rock_texture(a, (0.02 if side == 'left' else 1 - depth, 0.10,
                     0.02 + depth if side == 'left' else 0.98, 0.94),
                 _mix(color, C['rock_rust'], 0.7), n=6)


def tree(a, cx, base_y, r, color=None, trunk=True, leaf2=None):
    color = color or C['leaf']
    leaf2 = leaf2 or C['grass_deep']
    x, y = cx * a.w, base_y * a.h
    r = r * min(a.w, a.h)
    if trunk:
        a.stroke([(x, y), (x + a.rng.uniform(-2, 2), y - r * 0.75)], max(2.5, r * 0.16),
                 C['brick'], alpha=0.8, blur=3)
    a.blob(x, y - r * 0.95, r * 0.62, color, alpha=0.78, jitter=4, blur=6)
    a.blob(x - r * 0.36, y - r * 0.72, r * 0.42, leaf2, alpha=0.62, jitter=4, blur=6)
    a.blob(x + r * 0.34, y - r * 0.78, r * 0.38, _mix(color, C['moss'], 0.5), alpha=0.6, jitter=4, blur=6)


def cypress(a, cx, base_y, hgt, color=None, wdt=0.05):
    color = color or C['pine']
    x, y = cx * a.w, base_y * a.h
    hh = hgt * a.h
    a.wash([(x - a.w * wdt, y), (x, y - hh * 0.98), (x + a.w * wdt, y)], color,
           alpha=0.8, jitter=3, blur=5, bites=2, bite=0.02)
    a.stroke([(x, y), (x, y - hh * 0.9)], max(2, a.w * 0.012), C['brick'], alpha=0.6, blur=3)


def reeds(a, box, n=14, color=None):
    color = color or C['pine']
    x0, y0, x1, y1 = box
    for i in range(n):
        x = a.w * (x0 + (x1 - x0) * (i + 0.5) / n)
        base = a.h * y1
        top = a.h * (y0 + a.rng.uniform(0, 0.25) * (y1 - y0))
        a.stroke([(x, base), (x + a.rng.uniform(-6, 6), (base + top) / 2), (x + a.rng.uniform(-10, 10), top)],
                 max(2, a.w * 0.011), color, alpha=0.7, blur=3)
        if i % 3 == 0:
            a.blob(x, top, a.w * 0.012, C['olive'], alpha=0.8, jitter=2, blur=3)


def houses(a, box, rows=3, cols=3, wall=None, roof=None, stepped=False, shadow=True):
    """خانه‌های پله‌ای/روستایی: دیوار، شیروانی ذوزنقه‌ای، پنجره و سایه."""
    wall = wall or C['cream']
    roof = roof or C['terra']
    x0, y0, x1, y1 = box
    w, h = a.w, a.h
    row_h = (y1 - y0) / float(rows)
    for r in range(rows):
        # ردیف‌های پشتی کمی به سمت راست می‌روند (تراس‌بندی روی دامنه)
        squash = 0.78 + 0.22 * (r / float(max(1, rows - 1))) if stepped else 1.0
        shift = (x1 - x0) * (0.10 if stepped else 0.0) * ((rows - 1 - r) / float(max(1, rows - 1)))
        for c in range(cols):
            t = (c + 0.5) / cols
            cx = x0 + (x1 - x0) * t + shift + a.rng.uniform(-0.006, 0.006)
            bw = (x1 - x0) / cols * a.rng.uniform(0.60, 0.78)
            bh = row_h * a.rng.uniform(0.72, 0.92) * squash
            top = y0 + row_h * r + row_h * (1.0 - squash) * 0.55
            bx, by = w * (cx - bw / 2), h * top
            bw_px, bh_px = w * bw, h * bh
            # دیوار
            a.wash([(bx, by), (bx + bw_px, by), (bx + bw_px, by + bh_px), (bx, by + bh_px)],
                   wall, alpha=0.94, jitter=1.8, blur=3.5, bites=1, bite=0.010, edge=True,
                   edge_w=1.5, edge_alpha=0.5)
            # سایهٔ زیر شیروانی
            a.wash([(bx, by), (bx + bw_px, by), (bx + bw_px, by + bh_px * 0.14),
                    (bx, by + bh_px * 0.14)], _mix(wall, C['stone_deep'], 0.5),
                   alpha=0.35, jitter=1.6, blur=3, bites=0)
            # شیروانی: ذوزنقهٔ پهن‌تر از دیوار
            over = bw_px * 0.14
            a.wash([(bx - over, by + bh_px * 0.02), (bx + bw_px + over, by + bh_px * 0.02),
                    (bx + bw_px + over * 0.4, by + bh_px * 0.34),
                    (bx - over * 0.4, by + bh_px * 0.34)],
                   roof, alpha=0.95, jitter=1.6, blur=3.2, bites=1, bite=0.010, edge=True,
                   edge_w=1.5, edge_alpha=0.55)
            a.stroke([(bx - over * 0.2, by + bh_px * 0.06), (bx + bw_px + over * 0.2, by + bh_px * 0.06)],
                     max(2, bh_px * 0.05), _mix(roof, (255, 255, 255), 0.35), alpha=0.5, blur=2.5)
            # پنجره‌ها
            nwin = 2 if bw_px > w * 0.10 else 1
            for k in range(nwin):
                ww = bw_px * 0.16
                wh = bh_px * 0.26
                wx = bx + bw_px * ((k + 1) / (nwin + 1.0)) - ww / 2
                wy = by + bh_px * a.rng.uniform(0.44, 0.58)
                a.wash([(wx, wy), (wx + ww, wy), (wx + ww, wy + wh), (wx, wy + wh)],
                       C['night_win'], alpha=0.92, jitter=1.0, blur=1.6, bites=0,
                       edge=_mix(C['night_win'], INKLINE, 0.4), edge_w=1.1, edge_alpha=0.6)
            # سایهٔ پای دیوار
            if shadow:
                a.wash([(bx - bw_px * 0.06, by + bh_px * 0.98), (bx + bw_px * 1.06, by + bh_px * 0.98),
                        (bx + bw_px * 0.98, by + bh_px * 1.10), (bx - bw_px * 0.02, by + bh_px * 1.12)],
                       C['shadow'], alpha=0.22, jitter=4, blur=5, bites=2, bite=0.03)


def crenellated(a, x0, x1, y, height, color=None, merlon=20, tooth=0.16, gate=False):
    """دیوار قلعه با کنگره‌های ریز، ردیف سنگی و دروازهٔ هلالی."""
    color = color or C['sand_deep']
    w, h = a.w, a.h
    top = h * y
    bot = top + h * height
    th = min(h * height * tooth, h * 0.030)  # ارتفاع کنگره (کوتاه و طبیعی)
    pts = [(w * x0, bot), (w * x0, top)]
    n = max(4, int((x1 - x0) * merlon))
    while n > 4 and (x1 - x0) * w / n * 0.66 < th * 1.15:  # عرض دندانه ≥ ارتفاع
        n -= 1
    step = (x1 - x0) / float(n)
    for k in range(n):
        xm0 = w * (x0 + step * k)
        xm1 = w * (x0 + step * (k + 0.66))
        pts += [(xm0, top + th), (xm1, top + th), (xm1, top)]
    pts += [(w * x1, top), (w * x1, bot)]
    a.wash(pts, color, alpha=0.94, jitter=2.5, blur=4.5, bites=2, bite=0.02, edge=True,
           edge_w=1.7, edge_alpha=0.5)
    # ردیف سنگی زیر کنگره‌ها
    a.wash([(w * x0, top + th * 1.15), (w * x1, top + th * 1.15),
            (w * x1, top + th * 1.75), (w * x0, top + th * 1.75)],
           _mix(color, C['rock_rust'], 0.45), alpha=0.35, jitter=2.5, blur=4, bites=1, bite=0.015)
    if gate:
        cx = (x0 + x1) / 2
        gw, gh = (x1 - x0) * 0.10, height * 0.62
        gw_px, gh_px = w * gw, h * gh
        pts = [(w * cx - gw_px, bot), (w * cx - gw_px, top + h * height - gh_px)]
        for k in range(11):
            t = math.pi * k / 10
            pts.append((w * cx - math.cos(t) * gw_px,
                        top + h * height - gh_px - math.sin(t) * gh_px * 0.9))
        pts += [(w * cx + gw_px, bot)]
        a.wash(pts, C['rock_shadow'], alpha=0.85, jitter=2, blur=3.5, bites=0,
               edge=_mix(C['rock_shadow'], INKLINE, 0.4), edge_w=1.4, edge_alpha=0.5)


def dome(a, cx, base_y, r, color=None, finial=True):
    color = color or C['turq']
    x, y = cx * a.w, base_y * a.h
    rr = r * min(a.w, a.h)
    pts = []
    for i in range(20):
        t = math.pi * i / 19
        pts.append((x - math.cos(t) * rr, y - math.sin(t) * rr * 1.12))
    pts += [(x + rr, y + rr * 0.18), (x - rr, y + rr * 0.18)]
    a.wash(pts, color, alpha=0.92, jitter=2, blur=4, bites=2, bite=0.015, edge=True,
           edge_w=1.5, edge_alpha=0.5)
    a.stroke([(x - rr * 0.75, y - rr * 0.55), (x - rr * 0.1, y - rr * 1.0), (x + rr * 0.6, y - rr * 0.7)],
             max(3, rr * 0.16), _mix(color, (255, 255, 255), 0.45), alpha=0.5, blur=4)
    if finial:
        a.stroke([(x, y - rr * 1.08), (x, y - rr * 1.42)], max(2.5, rr * 0.1), C['gold'], alpha=0.9, blur=2)
        a.blob(x, y - rr * 1.5, rr * 0.14, C['gold'], alpha=0.95, jitter=1, blur=2)


def minaret(a, cx, base_y, hgt, color=None, cap=None):
    color = color or C['brick']
    cap = cap or C['turq']
    x, y = cx * a.w, base_y * a.h
    hh = hgt * a.h
    wd = max(6, a.w * 0.022)
    a.wash([(x - wd, y), (x - wd * 0.7, y - hh), (x + wd * 0.7, y - hh), (x + wd, y)],
           color, alpha=0.92, jitter=2, blur=4, bites=1, bite=0.012, edge=True,
           edge_w=1.4, edge_alpha=0.45)
    a.wash([(x - wd * 0.8, y - hh * 0.62), (x + wd * 0.8, y - hh * 0.62),
            (x + wd * 0.8, y - hh * 0.52), (x - wd * 0.8, y - hh * 0.52)],
           C['cream'], alpha=0.7, jitter=2, blur=3, bites=1, bite=0.01)
    a.wash([(x - wd * 1.5, y - hh), (x, y - hh * 1.22), (x + wd * 1.5, y - hh)], cap,
           alpha=0.85, jitter=2.5, blur=4, bites=2, bite=0.015)


def columns(a, box, n=5, color=None, base_y=0.86, capital=True):
    color = color or C['stone']
    x0, x1, y_top = box
    w, h = a.w, a.h
    a.wash([(w * (x0 - 0.02), h * base_y), (w * (x1 + 0.02), h * base_y),
            (w * (x1 + 0.02), h * (base_y + 0.1)), (w * (x0 - 0.02), h * (base_y + 0.1))],
           C['sand_deep'], alpha=0.7, jitter=4, blur=7, bites=3, bite=0.03)
    for i in range(n):
        cx = w * (x0 + (x1 - x0) * (i + 0.5) / n)
        top = h * y_top * a.rng.uniform(0.94, 1.06)
        bw = w * (x1 - x0) / n * 0.22
        a.wash([(cx - bw, h * base_y), (cx - bw * 0.8, top), (cx + bw * 0.8, top), (cx + bw, h * base_y)],
               color, alpha=0.94, jitter=2, blur=4, bites=1, bite=0.012, edge=True,
               edge_w=1.4, edge_alpha=0.5)
        a.stroke([(cx - bw * 1.1, top), (cx + bw * 1.1, top)], max(3, bw * 0.5),
                 C['stone_deep'], alpha=0.65, blur=3)
        if capital:
            a.blob(cx - bw * 0.9, top - bw * 0.5, bw * 0.7, C['stone_deep'], alpha=0.75,
                   ry=bw * 0.45, jitter=2, blur=3)
            a.blob(cx + bw * 0.9, top - bw * 0.5, bw * 0.7, C['stone_deep'], alpha=0.75,
                   ry=bw * 0.45, jitter=2, blur=3)


def arcade(a, box, n=5, color=None, arch=None):
    color = color or C['stone']
    arch = arch or C['navy']
    x0, y0, x1, y1 = box
    w, h = a.w, a.h
    a.wash([(w * x0, h * y0), (w * x1, h * y0), (w * x1, h * y1), (w * x0, h * y1)],
           color, alpha=0.7, jitter=4, blur=7, bites=4, bite=0.03)
    for i in range(n):
        cx = w * (x0 + (x1 - x0) * (i + 0.5) / n)
        aw = w * (x1 - x0) / n * 0.36
        top = h * y0 + h * (y1 - y0) * 0.10
        bot = h * y1 - h * (y1 - y0) * 0.06
        pts = [(cx - aw, bot), (cx - aw, top + aw * 0.5)]
        for k in range(12):
            t = math.pi * k / 11
            pts.append((cx - math.cos(t) * aw, top + aw * 0.5 - math.sin(t) * aw * 0.9))
        pts += [(cx + aw, bot)]
        a.wash(pts, arch, alpha=0.62, jitter=2.5, blur=5, bites=2, bite=0.02)


def palms(a, box, n=3, color=None):
    color = color or C['pine']
    x0, x1 = box
    for i in range(n):
        x = a.w * (x0 + (x1 - x0) * (i + 0.5) / n)
        base = a.h * 0.93
        top = base - a.h * a.rng.uniform(0.20, 0.30)
        a.stroke([(x, base), (x + a.rng.uniform(-8, 8), top)], max(3, a.w * 0.014),
                 C['clay'], alpha=0.85, blur=3)
        for k in range(6):
            t = math.pi * (0.12 + 0.76 * k / 5.0)
            a.stroke([(x, top), (x - math.cos(t) * a.w * 0.075, top - math.sin(t) * a.h * 0.06)],
                     max(3, a.w * 0.014), color, alpha=0.72, blur=4)


def dunes(a, box, colors=None, n=4):
    colors = colors or [C['sand'], C['sand_deep'], C['sand_pale']]
    x0, y0, x1, y1 = box
    w, h = a.w, a.h
    for i in range(n):
        t = i / float(n)
        y = h * (y0 + (y1 - y0) * t)
        a.wash([(w * (x0 - 0.05), y), (w * (x0 + (x1 - x0) * 0.35), y - h * 0.05),
                (w * (x0 + (x1 - x0) * 0.7), y + h * 0.015), (w * (x1 + 0.05), y - h * 0.035),
                (w * (x1 + 0.05), y + h * 0.2), (w * (x0 - 0.05), y + h * 0.2)],
               colors[i % len(colors)], alpha=0.68, jitter=6, blur=8, bites=4, bite=0.045)
    a.stroke([(w * x0, h * (y0 + (y1 - y0) * 0.45)), (w * (x0 + (x1 - x0) * 0.45), h * (y0 + (y1 - y0) * 0.36)),
              (w * x1, h * (y0 + (y1 - y0) * 0.5))], max(2.5, h * 0.008), C['sand_deep'],
             alpha=0.5, blur=4)


def kaluts(a, box, n=6, colors=None):
    """کلوت‌های لوت: ستون‌های فرسایشی نامنظم با پایهٔ مشترک."""
    colors = colors or [C['sand'], C['sand_deep'], C['rock_rust']]
    x0, y0, x1, y1 = box
    w, h = a.w, a.h
    # پایهٔ شنی مشترک
    a.wash([(w * (x0 - 0.04), h * (y1 - 0.07)), (w * (x1 + 0.04), h * (y1 - 0.09)),
            (w * (x1 + 0.04), h * y1), (w * (x0 - 0.04), h * y1)],
           C['sand_deep'], alpha=0.75, jitter=5, blur=6, bites=2, bite=0.03)
    for i in range(n):
        cx = w * (x0 + (x1 - x0) * (i + 0.5 + a.rng.uniform(-0.06, 0.06)) / n)
        bw = w * (x1 - x0) / n * a.rng.uniform(0.26, 0.42)
        top = h * (y0 + a.rng.uniform(0.02, 0.22) * (y1 - y0))
        bot = h * y1
        lean = a.rng.uniform(-0.10, 0.10) * bw
        pts = [(cx - bw, bot), (cx - bw * (0.9 + a.rng.uniform(0, 0.2)), (top + bot) / 2),
               (cx - bw * 0.42 + lean, top), (cx + bw * 0.42 + lean, top + (bot - top) * 0.05),
               (cx + bw * (0.9 + a.rng.uniform(0, 0.2)), (top + bot) / 2), (cx + bw, bot)]
        a.wash(pts, colors[i % len(colors)], alpha=0.95, jitter=4, blur=5, bites=2, bite=0.02,
               edge=_mix(colors[i % len(colors)], INKLINE, 0.55), edge_w=1.8, edge_alpha=0.5)
        a.wash([(cx - bw * 1.05, bot), (cx + bw * 1.05, bot), (cx + bw * 1.25, bot + h * 0.03),
                (cx - bw * 1.25, bot + h * 0.035)], C['rock_rust'], alpha=0.30, jitter=4,
               blur=5, bites=2, bite=0.03)
        for k in range(3):  # شیارهای افقی
            yy = top + (bot - top) * (0.28 + 0.24 * k)
            a.stroke([(cx - bw * 0.62, yy), (cx + bw * 0.62, yy)], max(2, bw * 0.14),
                     C['sand_deep'], alpha=0.35, blur=3)


def tulips(a, box, n=26, colors=None):
    colors = colors or [C['blossom'], C['gold'], C['purple']]
    x0, y0, x1, y1 = box
    for i in range(n):
        x = a.w * (x0 + (x1 - x0) * a.rng.random())
        y = a.h * (y0 + (y1 - y0) * a.rng.random())
        r = min(a.w, a.h) * a.rng.uniform(0.012, 0.022)
        a.blob(x, y, r, colors[i % len(colors)], alpha=0.85, jitter=1.4, blur=2.4)


def stars(a, box, n=26, color=None):
    color = color or C['star']
    x0, y0, x1, y1 = box
    for i in range(n):
        x = a.w * (x0 + (x1 - x0) * a.rng.random())
        y = a.h * (y0 + (y1 - y0) * a.rng.random())
        r = min(a.w, a.h) * a.rng.uniform(0.006, 0.014)
        a.blob(x, y, r, color, alpha=0.95, jitter=1.0, blur=1.8)
        a.glow(x, y, r * 2.8, color, alpha=0.22, steps=3)


def tent(a, cx, base_y, s=0.09, color=None):
    color = color or C['terra']
    x, y = cx * a.w, base_y * a.h
    rr = s * min(a.w, a.h)
    a.wash([(x - rr, y), (x, y - rr * 0.95), (x + rr, y)], color, alpha=0.94, jitter=2, blur=4,
           bites=1, bite=0.012, edge=True, edge_w=1.5, edge_alpha=0.5)
    a.wash([(x - rr * 0.22, y), (x, y - rr * 0.9), (x + rr * 0.22, y)], C['cream'],
           alpha=0.55, jitter=2, blur=4, bites=1, bite=0.015)


def boat(a, cx, cy, s=0.07, hull=None, sail=True):
    hull = hull or C['brick']
    x, y = cx * a.w, cy * a.h
    rr = s * min(a.w, a.h)
    a.wash([(x - rr, y), (x + rr, y), (x + rr * 0.6, y + rr * 0.35), (x - rr * 0.6, y + rr * 0.35)],
           hull, alpha=0.85, jitter=2, blur=4, bites=2, bite=0.02)
    if sail:
        a.wash([(x, y - rr * 1.25), (x + rr * 0.7, y - rr * 0.1), (x, y - rr * 0.1)],
               C['cloud'], alpha=0.85, jitter=2, blur=4, bites=2, bite=0.02)
    a.stroke([(x - rr * 1.2, y + rr * 0.45), (x + rr * 1.2, y + rr * 0.45)], max(2, rr * 0.14),
             C['water_light'], alpha=0.6, blur=3)


def rail(a, y, x0=0.02, x1=0.98, color=None):
    color = color or C['ink']
    w, h = a.w, a.h
    yy = h * y
    a.stroke([(w * x0, yy), (w * x1, yy + a.rng.uniform(-4, 4))], max(2.5, h * 0.010), color,
             alpha=0.6, blur=3)
    n = 14
    for i in range(n):
        x = w * (x0 + (x1 - x0) * (i + 0.5) / n)
        a.stroke([(x, yy - h * 0.012), (x, yy + h * 0.012)], max(2, h * 0.009), color,
                 alpha=0.45, blur=3)


def _smooth(pts, n=16):
    """میان‌یابی Catmull-Rom برای منحنی نرم رود/جاده."""
    if len(pts) < 3:
        return list(pts)
    ext = [pts[0]] + list(pts) + [pts[-1]]
    out = []
    for i in range(len(ext) - 3):
        p0, p1, p2, p3 = ext[i], ext[i + 1], ext[i + 2], ext[i + 3]
        for k in range(n):
            t = k / float(n)
            t2, t3 = t * t, t * t * t
            x = 0.5 * ((2 * p1[0]) + (-p0[0] + p2[0]) * t
                       + (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2
                       + (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3)
            y = 0.5 * ((2 * p1[1]) + (-p0[1] + p2[1]) * t
                       + (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2
                       + (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3)
            out.append((x, y))
    out.append(pts[-1])
    return out


def river(a, pts, width=0.05, color=None, foam=True):
    """رودِ خمیده: لایهٔ تیره، لایهٔ روشن و کف‌سفید روی موج‌ها."""
    color = color or C['water_light']
    path = _smooth([(x * a.w, y * a.h) for x, y in pts], n=14)
    a.stroke(path, max(6, a.w * width * 1.5), C['water_deep'], alpha=0.55, blur=5, taper=True)
    a.stroke(path, max(4, a.w * width), color, alpha=0.9, blur=4, taper=True)
    if foam:
        for i in range(4):
            j = int(len(path) * (0.15 + 0.22 * i))
            if 0 < j < len(path) - 1:
                x, y = path[j]
                a.blob(x, y, max(3, a.w * width * 0.35), C['water_foam'], alpha=0.55,
                       ry=max(2, a.w * width * 0.18), jitter=3, blur=4)


def gazelle(a, cx, base_y, s=0.06, color=None):
    color = color or C['clay']
    x, y = cx * a.w, base_y * a.h
    rr = s * min(a.w, a.h)
    a.blob(x, y - rr * 0.7, rr * 0.55, color, alpha=0.85, ry=rr * 0.38, jitter=2, blur=3)
    a.blob(x + rr * 0.75, y - rr * 1.15, rr * 0.24, color, alpha=0.85, jitter=1.4, blur=2.5)
    for dx in (-0.35, 0.35):
        a.stroke([(x + rr * dx, y - rr * 0.5), (x + rr * dx, y)], max(2, rr * 0.1), color,
                 alpha=0.7, blur=2.5)
    a.stroke([(x + rr * 0.85, y - rr * 1.35), (x + rr * 1.05, y - rr * 1.75)], max(2, rr * 0.07),
             C['ink'], alpha=0.6, blur=2)


def smoke(a, cx, cy, r, color=None):
    color = color or C['cloud_soft']
    x, y = cx * a.w, cy * a.h
    for i in range(4):
        t = i / 4.0
        a.blob(x + a.rng.uniform(-14, 14), y - r * a.h * i * 0.9, (0.35 + t * 0.35) * r * a.w,
               color, alpha=0.42, ry=(0.28 + t * 0.22) * r * a.w, jitter=5, blur=8)


def tablet(a, cx, cy, s=0.10, color=None):
    color = color or C['stone']
    x, y = cx * a.w, cy * a.h
    rr = s * min(a.w, a.h)
    a.wash([(x - rr * 0.8, y - rr), (x + rr * 0.8, y - rr), (x + rr * 0.8, y + rr),
            (x - rr * 0.8, y + rr)], color, alpha=0.94, jitter=1.6, blur=3.5, bites=1,
           bite=0.012, edge=True, edge_w=1.5, edge_alpha=0.5)
    for k in range(3):
        a.stroke([(x - rr * 0.5, y - rr * 0.5 + k * rr * 0.45),
                  (x + rr * 0.5, y - rr * 0.5 + k * rr * 0.45)], max(2, rr * 0.1),
                 C['stone_deep'], alpha=0.7, blur=2)


# ----------------------------------------------------------------- صحنه‌ها ---

def scene_waterfall(a, flags):
    """آبشار جنگلی: صخره‌های لایه‌دار، ستون آب، حوض کف و پوشش گیاهی."""
    tall = 'tall' in flags
    karst = 'karst' in flags
    sky(a, C['sky'], C['sky_lo'], y_lo=0.70)
    sun(a, a.w * 0.20, a.h * 0.14, min(a.w, a.h) * 0.052, rays=tall)
    clouds(a, 0.15, n=2, x0=0.56, x1=0.86, r=0.045)

    # صخرهٔ چپ: دیوار + طبقه‌های سنگی + ترک
    cliff_wall(a, 'left', 0.06, 1.04, C['rock_warm'], depth=0.26 if tall else 0.32)
    rock_texture(a, (0.02, 0.16, 0.26, 0.92), C['rock_rust'], n=6)

    # صخرهٔ راست
    cliff_wall(a, 'right', 0.04, 1.04, C['rock_pale'] if karst else C['rock_rust'],
               depth=0.28 if tall else 0.34)
    rock_texture(a, (0.74, 0.14, 0.98, 0.88), C['rock_rust'], n=6)

    # دیوارهٔ سنگیِ زیر آبشار و آبشار
    rocks(a, (0.28, 0.50, 0.74, 0.74), [C['rock_warm'], C['rock_rust']], n=3)
    cascade(a, 0.42, 0.08 if tall else 0.14, 0.78, 0.17 if tall else 0.21)

    # حوض کف آبشار + کف سفید
    water_body(a, (0.16, 0.74, 0.88, 0.99), deep=C['water_deep'], light=C['water_light'])
    a.wash([(a.w * 0.30, a.h * 0.80), (a.w * 0.70, a.h * 0.78), (a.w * 0.76, a.h * 0.90),
            (a.w * 0.26, a.h * 0.92)], C['water_foam'], alpha=0.55, jitter=5, blur=7,
           bites=3, bite=0.04)

    # گیاهان روی صخره‌ها و کنار حوض
    tree(a, 0.86, 0.96, 0.13, C['leaf'])
    tree(a, 0.13, 0.99, 0.14, C['grass_deep'], leaf2=C['leaf'])
    for i in range(4):
        x = 0.05 + i * 0.045
        cypress(a, x, 0.30 + (i % 2) * 0.03, 0.10, C['pine'], wdt=0.016)
    if 'spring' in flags:
        a.splatter(a.w * 0.74, a.h * 0.70, C['water_foam'], n=18, r0=2, r1=8,
                   spread=a.w * 0.12, alpha=0.7)
    if 'oak' in flags:
        tree(a, 0.30, 0.76, 0.11, C['grass_deep'], leaf2=C['leaf'])
    if 'rail' in flags:
        rail(a, 0.945, 0.015, 0.34, C['ink'])
    a.splatter(a.w * 0.52, a.h * 0.22, C['water_light'], n=12, r0=1.5, r1=5,
               spread=a.w * 0.16, alpha=0.5)
    a.splatter(a.w * 0.5, a.h * 0.72, C['water_foam'], n=14, r0=2, r1=9,
               spread=a.w * 0.22, alpha=0.6)


def scene_lake(a, flags):
    sky(a, C['sky'], C['sky_lo'], y_lo=0.62)
    sun(a, a.w * 0.78, a.h * 0.14, min(a.w, a.h) * 0.05)
    peaks(a, 0.60, 0.26, _mix(C['rock_cool'], C['snow'], 0.45), n=2, snow=False,
          x_spread=(0.02, 1.00))                      # خط‌الرأس پشتی محو
    peaks(a, 0.62, 0.40, C['rock_cool'], n=1, snow=True, x_spread=(0.00, 0.46))
    peaks(a, 0.62, 0.45, _mix(C['rock_cool'], C['rock_shadow'], 0.25), n=1, snow=True,
          x_spread=(0.44, 0.94))
    ground(a, 0.62, C['grass'], jitter=0.02, alpha=0.6)
    water_body(a, (0.06, 0.62, 0.94, 0.92), deep=C['water_deep'], light=C['water_light'])
    if 'two_lakes' in flags:
        pond = [(a.w * (0.74 + math.cos(2 * math.pi * i / 24) * 0.16),
                 a.h * (0.90 + math.sin(2 * math.pi * i / 24) * 0.055)) for i in range(24)]
        a.wash(pond, C['water'], alpha=0.8, jitter=4, blur=5, bites=2, bite=0.02,
               edge=C['water_deep'], edge_w=1.5, edge_alpha=0.45)
    tree(a, 0.09, 0.99, 0.15, C['leaf'])
    tree(a, 0.93, 0.99, 0.13, C['grass_deep'])
    if 'tent' in flags:
        tent(a, 0.40, 0.965, 0.070, C['terra'])
    if 'boat' in flags:
        boat(a, 0.44, 0.79, 0.050)


def scene_city(a, flags):
    sky(a, C['sky'], C['sky_lo'], y_lo=0.68)
    sun(a, a.w * 0.18, a.h * 0.16, min(a.w, a.h) * 0.05)
    peaks(a, 0.66, 0.26, C['rock_cool'], n=3, snow=False)
    ground(a, 0.70, C['sand_pale'], jitter=0.02, alpha=0.5)
    houses(a, (0.10, 0.42, 0.92, 0.90), rows=2, cols=4, wall=C['cream'],
           roof=C['terra'] if 'palms' not in flags else C['sand_deep'])
    if 'rivers' in flags:
        river(a, [(0.0, 0.88), (0.3, 0.86), (0.6, 0.90), (1.0, 0.86)], width=0.045)
    if 'rail' in flags:
        rail(a, 0.95, 0.02, 0.98, C['ink'])
    if 'palms' in flags:
        palms(a, (0.08, 0.92), n=3)
    else:
        tree(a, 0.06, 0.99, 0.13, C['leaf'])
        tree(a, 0.96, 0.99, 0.12, C['grass_deep'])


def scene_wetland(a, flags):
    sky(a, C['sky'], C['sky_lo'], y_lo=0.60)
    sun(a, a.w * 0.24, a.h * 0.15, min(a.w, a.h) * 0.05)
    if 'mountains_back' in flags:
        peaks(a, 0.58, 0.30, C['rock_cool'], n=2, snow=False)
    ground(a, 0.62, C['moss'], jitter=0.02, alpha=0.45)
    water_body(a, (0.03, 0.60, 0.97, 0.92), deep=C['water'], light=C['water_light'])
    reeds(a, (0.04, 0.44, 0.34, 0.72), n=12, color=C['pine'])
    reeds(a, (0.68, 0.46, 0.97, 0.74), n=12, color=C['grass_deep'])
    for i in range(4):
        a.blob(a.w * (0.42 + 0.06 * i), a.h * (0.78 + 0.03 * (i % 2)), min(a.w, a.h) * 0.022,
               C['rose'], alpha=0.85, jitter=2, blur=3)
    if 'boat' in flags:
        boat(a, 0.62, 0.80, 0.05, C['terra'])
    a.splatter(a.w * 0.5, a.h * 0.5, C['water_foam'], n=10, r0=2, r1=6, spread=a.w * 0.3, alpha=0.4)


def scene_gorge(a, flags):
    """تنگهٔ سنگی: دو دیوارهٔ بلند، کف سبز، حوض‌های فیروزه‌ای و کتیبه روی سنگ."""
    sky(a, C['sky_hi'], C['sky_lo'], y_lo=0.72)
    sun(a, a.w * 0.5, a.h * 0.12, min(a.w, a.h) * 0.040)
    cliff_wall(a, 'left', 0.01, 1.04, C['rock_warm'], depth=0.44)
    cliff_wall(a, 'right', 0.01, 1.04, C['rock_rust'], depth=0.45)
    rock_texture(a, (0.04, 0.20, 0.30, 0.80), C['rock_rust'], n=7)
    rock_texture(a, (0.70, 0.20, 0.96, 0.80), C['rock_rust'], n=7)
    # کف تنگه: سبزی و خزه
    ground(a, 0.66, C['moss'], jitter=0.02, alpha=0.6)
    # آب: نهر باریک شکاف تنگه تا حوض‌های پله‌ای
    river(a, [(0.50, 0.06), (0.47, 0.24), (0.53, 0.40), (0.48, 0.56), (0.51, 0.70)],
          width=0.028, foam=False)
    if 'pools' in flags:
        water_body(a, (0.22, 0.68, 0.80, 0.90), deep=C['turq_deep'], light=C['turq'])
        water_body(a, (0.28, 0.88, 0.58, 0.99), deep=C['turq_deep'], light=C['turq'], rim=False)
    else:
        water_body(a, (0.22, 0.72, 0.80, 0.98), deep=C['water_deep'], light=C['water_light'])
    rocks(a, (0.28, 0.60, 0.72, 0.74), [C['rock_rust'], C['rock_warm']], n=3)
    reeds(a, (0.02, 0.52, 0.20, 0.76), n=7, color=C['pine'])
    reeds(a, (0.80, 0.52, 0.98, 0.76), n=7, color=C['grass_deep'])
    if 'inscription' in flags:
        tablet(a, 0.19, 0.40, 0.068, C['stone'])
        a.stroke([(a.w * 0.16, a.h * 0.50), (a.w * 0.30, a.h * 0.52)], max(3, a.w * 0.010),
                 C['stone_deep'], alpha=0.6, blur=3)
    tree(a, 0.10, 0.66, 0.11, C['leaf'])
    tree(a, 0.90, 0.64, 0.10, C['grass_deep'])
    a.splatter(a.w * 0.5, a.h * 0.86, C['water_foam'], n=14, r0=2, r1=7, spread=a.w * 0.2, alpha=0.5)


def scene_castle(a, flags):
    """قلعه روی تپه: برج مرکزی بلند، دو برج کناری، دیوار کنگره‌دار و دروازه."""
    mud = 'mudbrick' in flags
    ruins = 'ruins' in flags
    stone = C['sand_deep'] if mud else C['clay']
    tons = C['sand'] if mud else C['rock_rust']
    sky(a, C['sky'], C['sky_lo'], y_lo=0.70)
    sun(a, a.w * 0.82, a.h * 0.14, min(a.w, a.h) * 0.05, rays=True)
    clouds(a, 0.15, n=2, x0=0.10, x1=0.44, r=0.04)
    hills(a, 0.68, 0.09, C['moss'], n=2, alpha=0.5)
    ground(a, 0.72, C['sand'] if mud else C['grass'], jitter=0.025, alpha=0.6)
    # تپهٔ سنگی زیر قلعه
    a.wash([(a.w * 0.06, a.h * 1.02), (a.w * 0.18, a.h * 0.86), (a.w * 0.36, a.h * 0.78),
            (a.w * 0.52, a.h * 0.76), (a.w * 0.68, a.h * 0.79), (a.w * 0.86, a.h * 0.88),
            (a.w * 0.98, a.h * 1.02)],
           _mix(stone, C['rock_shadow'], 0.30), alpha=0.85, jitter=6, blur=8, bites=3,
           bite=0.04, edge=True, edge_w=1.8, edge_alpha=0.35)
    rock_texture(a, (0.10, 0.80, 0.92, 1.00), C['rock_shadow'], n=6)
    if ruins:
        for i in range(4):
            x = 0.26 + i * 0.16
            crenellated(a, x, x + 0.10, 0.50 + (i % 2) * 0.05, 0.26, stone, merlon=7, tooth=0.22)
        rocks(a, (0.16, 0.70, 0.90, 0.94), [C['clay'], C['sand_deep']], n=4)
        rock_texture(a, (0.18, 0.50, 0.88, 0.90), C['rock_rust'], n=7)
    else:
        # دیوار پایین + دروازه
        crenellated(a, 0.24, 0.76, 0.60, 0.20, stone, merlon=13, tooth=0.20, gate=True)
        # برج‌های کناری
        for cx, hh in ((0.25, 0.36), (0.75, 0.36),):
            crenellated(a, cx - 0.055, cx + 0.055, 0.60 - hh, hh + 0.20, stone,
                        merlon=4, tooth=0.22)
            a.wash([(a.w * (cx - 0.012), a.h * (0.42)), (a.w * (cx + 0.012), a.h * 0.42),
                    (a.w * (cx + 0.012), a.h * 0.50), (a.w * (cx - 0.012), a.h * 0.50)],
                   C['night_win'], alpha=0.7, jitter=1.4, blur=3, bites=0)
        # برج مرکزی بلندتر
        crenellated(a, 0.44, 0.56, 0.30, 0.50, stone, merlon=6, tooth=0.20)
        a.wash([(a.w * 0.485, a.h * 0.36), (a.w * 0.515, a.h * 0.36),
                (a.w * 0.515, a.h * 0.44), (a.w * 0.485, a.h * 0.44)],
               C['night_win'], alpha=0.75, jitter=1.4, blur=3, bites=0)
    if mud:
        rock_texture(a, (0.24, 0.44, 0.76, 0.68), C['sand'], n=6)
    tree(a, 0.05, 1.0, 0.16, C['leaf'])
    tree(a, 0.96, 1.0, 0.14, C['grass_deep'])


def scene_mountain(a, flags):
    sky(a, C['sky'], C['sky_dusk'] if 'tulips' in flags else C['sky_lo'], y_lo=0.66)
    sun(a, a.w * 0.20, a.h * 0.16, min(a.w, a.h) * 0.05, core=C['sun'], halo=C['sun_halo'])
    peaks(a, 0.70, 0.62, C['rock_cool'], n=1, snow=True, snow_from=0.34, x_spread=(0.4, 0.6))
    peaks(a, 0.72, 0.36, C['rock_shadow'], n=2, snow='snowpatch' in flags, snow_from=0.5,
          x_spread=(0.08, 0.92))
    if 'volcano' in flags:
        smoke(a, 0.5, 0.22, 0.05)
    ground(a, 0.74, C['grass'], jitter=0.03, alpha=0.62)
    if 'tulips' in flags:
        tulips(a, (0.06, 0.76, 0.94, 0.98), n=30)
    if 'stream' in flags:
        river(a, [(0.45, 0.76), (0.52, 0.84), (0.44, 0.92), (0.5, 1.0)], width=0.03)
    if 'oak' in flags:
        tree(a, 0.12, 1.0, 0.17, C['grass_deep'], leaf2=C['leaf'])
    if 'tent' in flags:
        tent(a, 0.84, 1.0, 0.08, C['terra'])


def scene_cave(a, flags):
    """غار کوهستانی (علی‌صدر): دهانهٔ قوسی در دل صخره، قندیل، برکهٔ فیروزه‌ای."""
    w, h = a.w, a.h
    sky(a, C['sky'], C['sky_lo'], y_lo=0.36)
    sun(a, w * 0.82, h * 0.12, min(w, h) * 0.045)
    clouds(a, 0.13, n=2, x0=0.06, x1=0.38, r=0.038)
    # کوه سنگی
    a.wash([(w * -0.04, h * 1.04), (w * -0.04, h * 0.44), (w * 0.16, h * 0.26),
            (w * 0.40, h * 0.18), (w * 0.62, h * 0.22), (w * 0.86, h * 0.34),
            (w * 1.04, h * 0.50), (w * 1.04, h * 1.04)],
           C['rock_warm'], alpha=0.93, jitter=6, blur=8, bites=3, bite=0.05, edge=True,
           edge_w=2.0, edge_alpha=0.4)
    rock_texture(a, (0.02, 0.30, 0.98, 0.88), C['rock_rust'], n=10)
    a.wash([(w * -0.04, h * 0.60), (w * 1.04, h * 0.56), (w * 1.04, h * 1.04),
            (w * -0.04, h * 1.04)], _mix(C['rock_warm'], C['sand_deep'], 0.35),
           alpha=0.6, jitter=6, blur=9, bites=3, bite=0.05)
    # دهانهٔ قوسی غار
    cx, cy, rw, rh = 0.46 * w, 0.60 * h, 0.21 * w, 0.30 * h
    pts = [(cx - rw, cy + rh)]
    for i in range(15):
        t = math.pi * i / 14.0
        pts.append((cx - math.cos(t) * rw, cy - math.sin(t) * rh))
    pts.append((cx + rw, cy + rh))
    a.wash(pts, C['rock_shadow'], alpha=0.92, jitter=3, blur=6, bites=2, bite=0.022,
           edge=_mix(C['rock_shadow'], INKLINE, 0.55), edge_w=2.0, edge_alpha=0.5)
    inner = [(cx - rw * 0.78, cy + rh), ]
    for i in range(15):
        t = math.pi * i / 14.0
        inner.append((cx - math.cos(t) * rw * 0.78, cy - math.sin(t) * rh * 0.76))
    inner.append((cx + rw * 0.78, cy + rh))
    a.wash(inner, C['ink'], alpha=0.9, jitter=3, blur=6, bites=2, bite=0.02)
    # قندیل‌های درون غار
    for i in range(5):
        x = cx - rw * 0.5 + i * rw * 0.25
        top = cy - rh * 0.72 + a.rng.uniform(0, rh * 0.10)
        ln = a.rng.uniform(0.05, 0.10) * h
        a.wash([(x - w * 0.010, top), (x + w * 0.010, top), (x + w * 0.005, top + ln),
                (x - w * 0.005, top + ln)], C['rock_pale'], alpha=0.85, jitter=1.4, blur=3,
               bites=0, edge=True, edge_w=1.1, edge_alpha=0.4)
    a.glow(cx, cy - rh * 0.35, min(w, h) * 0.12, C['sun_halo'], alpha=0.3, steps=5)
    # برکهٔ فیروزه‌ای جلوی غار
    water_body(a, (0.20, 0.74, 0.88, 0.99), deep=C['water_deep'], light=C['turq'])
    reeds(a, (0.02, 0.66, 0.16, 0.84), n=6, color=C['pine'])
    tree(a, 0.90, 0.92, 0.11, C['grass_deep'], leaf2=C['leaf'])
    if 'boat' in flags:
        boat(a, 0.50, 0.86, 0.048, C['terra'])
    a.splatter(cx, cy + rh * 0.75, C['water_foam'], n=12, r0=2, r1=7, spread=w * 0.16, alpha=0.5)


def scene_forest(a, flags):
    sky(a, C['sky'], C['sky_lo'], y_lo=0.56)
    sun(a, a.w * 0.28, a.h * 0.13, min(a.w, a.h) * 0.05)
    hills(a, 0.52, 0.10, C['moss'], n=2, alpha=0.6)
    ground(a, 0.60, C['grass_deep'], jitter=0.03, alpha=0.7)
    for i in range(7):
        x = 0.06 + i * 0.14
        r = 0.16 + (i % 3) * 0.03
        tree(a, x, 0.86 + (i % 2) * 0.06, r, C['leaf'] if i % 2 else C['grass_deep'],
             leaf2=C['pine'])
    for i in range(4):
        cypress(a, 0.14 + i * 0.24, 0.98, 0.26 + (i % 2) * 0.05, C['pine'])
    if 'waterfall' in flags:
        cascade(a, 0.62, 0.18, 0.62, 0.09, C['water_foam'], C['water_light'])
        water_body(a, (0.50, 0.56, 0.80, 0.74), deep=C['water'], light=C['water_light'])
    if 'climb' in flags:
        a.stroke([(0.30 * a.w, 0.92 * a.h), (0.36 * a.w, 0.84 * a.h)], a.w * 0.010,
                 C['ink'], alpha=0.5, blur=3)


def scene_valley(a, flags):
    if 'sun' in flags:
        sky(a, C['sky'], C['sky_dusk'], y_lo=0.66)
        sun(a, a.w * 0.76, a.h * 0.16, min(a.w, a.h) * 0.06, core=C['sun_hi'], halo=C['sun_halo'])
    else:
        sky(a, C['sky_hi'], C['sky_lo'], y_lo=0.66)
    peaks(a, 0.70, 0.44, C['rock_warm'], n=2, snow=False)
    ground(a, 0.74, C['sand'], jitter=0.03, alpha=0.66)
    rocks(a, (0.10, 0.56, 0.92, 0.92), [C['rock_rust'], C['rock_warm'], C['clay']], n=5)
    river(a, [(0.0, 0.86), (0.28, 0.80), (0.55, 0.90), (1.0, 0.84)], width=0.035,
          color=C['water_light'])
    tree(a, 0.08, 1.0, 0.14, C['olive'])
    if 'sun' in flags:
        a.splatter(a.w * 0.7, a.h * 0.24, C['sun_halo'], n=14, r0=2, r1=7, spread=a.w * 0.2, alpha=0.5)


def scene_park(a, flags):
    if 'sunset' in flags:
        sky(a, C['sky_dusk'], C['sun_halo'], y_lo=0.62)
        sun(a, a.w * 0.74, a.h * 0.26, min(a.w, a.h) * 0.055, core=C['sun_hi'])
    else:
        sky(a, C['sky'], C['sky_lo'], y_lo=0.62)
        sun(a, a.w * 0.24, a.h * 0.15, min(a.w, a.h) * 0.05)
    peaks(a, 0.62, 0.30, C['rock_cool'], n=2, snow=False)
    ground(a, 0.66, C['grass'], jitter=0.03, alpha=0.68)
    for i in range(4):
        tree(a, 0.12 + i * 0.24, 0.94 + (i % 2) * 0.04, 0.15, C['grass_deep'], leaf2=C['leaf'])
    if 'tent' in flags:
        tent(a, 0.30, 0.99, 0.075, C['terra'])
    if 'gazelle' in flags:
        gazelle(a, 0.62, 0.99, 0.055)
        gazelle(a, 0.72, 0.97, 0.045)
    if 'city_lights' in flags:
        for i in range(9):
            a.blob(a.w * (0.06 + i * 0.06), a.h * (0.70 + (i % 3) * 0.02),
                   min(a.w, a.h) * 0.008, C['gold'], alpha=0.85, jitter=1, blur=1.8)


def scene_monument(a, flags):
    sky(a, C['sky'], C['sky_lo'], y_lo=0.70)
    sun(a, a.w * 0.18, a.h * 0.14, min(a.w, a.h) * 0.045)
    ground(a, 0.74, C['sand_pale'], jitter=0.02, alpha=0.55)
    a.wash([(a.w * 0.26, a.h * 0.52), (a.w * 0.74, a.h * 0.52), (a.w * 0.78, a.h * 0.86),
            (a.w * 0.22, a.h * 0.86)], C['brick'], alpha=0.8, jitter=4, blur=6, bites=3, bite=0.03)
    dome(a, 0.50, 0.54, 0.16, C['turq'])
    minaret(a, 0.26, 0.80, 0.36, C['brick'], C['turq'])
    minaret(a, 0.74, 0.80, 0.36, C['brick'], C['turq'])
    a.wash([(a.w * 0.44, a.h * 0.72), (a.w * 0.56, a.h * 0.72), (a.w * 0.56, a.h * 0.86),
            (a.w * 0.44, a.h * 0.86)], C['navy'], alpha=0.6, jitter=3, blur=5, bites=2, bite=0.02)
    for i in range(2):
        tree(a, 0.10 + i * 0.8, 1.0, 0.13, C['grass_deep'], leaf2=C['leaf'])


def scene_desert(a, flags):
    if 'stars' in flags:
        a.wash([(a.w * -0.04, a.h * -0.06), (a.w * 1.04, a.h * -0.06),
                (a.w * 1.04, a.h * 0.60), (a.w * 0.5, a.h * 0.56), (a.w * -0.04, a.h * 0.60)],
               C['sky_night'], alpha=0.88, jitter=7, blur=12, bites=4, bite=0.06)
        stars(a, (0.02, 0.02, 0.98, 0.50), n=28)
        a.glow(a.w * 0.22, a.h * 0.16, min(a.w, a.h) * 0.06, C['star'], alpha=0.3, steps=5)
    else:
        sky(a, C['sky'], C['sky_dusk'], y_lo=0.54)
        sun(a, a.w * 0.26, a.h * 0.16, min(a.w, a.h) * 0.05, core=C['sun_hi'])
    ground(a, 0.54, C['sand'], jitter=0.02, alpha=0.6)
    if 'kaluts' in flags:
        kaluts(a, (0.08, 0.40, 0.94, 0.84), n=5)
    dunes(a, (0.02, 0.62, 0.98, 0.98), n=3)
    if 'stars' in flags:
        stars(a, (0.05, 0.02, 0.95, 0.30), n=12)


def scene_village(a, flags):
    """روستای پله‌ای (ماسوله/سرآقا سید): خانه‌های تراس‌شده روی دامنه."""
    stepped = ('stepped' in flags) or ('rooftop' in flags)
    sky(a, C['sky'], C['sky_lo'], y_lo=0.50)
    sun(a, a.w * 0.80, a.h * 0.14, min(a.w, a.h) * 0.05, rays=True)
    clouds(a, 0.16, n=2, x0=0.12, x1=0.42, r=0.04)
    hills(a, 0.54, 0.11, C['moss'], n=2, alpha=0.6)
    # دامنه: چمن پله‌ای
    ground(a, 0.60, C['grass'], jitter=0.03, alpha=0.65)
    if stepped:
        for i in range(4):
            y = 0.62 + i * 0.09
            a.wash([(a.w * -0.05, a.h * y), (a.w * (0.36 + i * 0.1), a.h * (y - 0.02)),
                    (a.w * 1.05, a.h * y), (a.w * 1.05, a.h * (y + 0.05)),
                    (a.w * -0.05, a.h * (y + 0.05))],
                   C['moss'] if i % 2 else C['grass'], alpha=0.5, jitter=5, blur=7,
                   bites=3, bite=0.035)
    houses(a, (0.12, 0.30, 0.90, 0.88), rows=4 if stepped else 2, cols=4 if stepped else 3,
           wall=C['cream'], roof=C['terra'], stepped=stepped)
    if 'rooftop' in flags:
        # بند تراس‌ها میان ردیف‌های خانه (پله‌های ماسوله)
        for i in range(4):
            y = 0.42 + i * 0.115
            a.wash([(a.w * -0.05, a.h * y), (a.w * 1.05, a.h * (y - 0.010)),
                    (a.w * 1.05, a.h * (y + 0.030)), (a.w * -0.05, a.h * (y + 0.045))],
                   C['moss'] if i % 2 else C['stone'], alpha=0.45, jitter=4, blur=6,
                   bites=3, bite=0.03)
    tree(a, 0.05, 1.0, 0.17, C['leaf'])
    tree(a, 0.97, 0.98, 0.15, C['grass_deep'])
    cypress(a, 0.13, 1.0, 0.22, C['pine'])
    if 'oak' in flags:
        tree(a, 0.86, 0.99, 0.13, C['grass_deep'], leaf2=C['leaf'])
    if 'sun' in flags:
        a.splatter(a.w * 0.7, a.h * 0.2, C['sun_halo'], n=12, r0=2, r1=6,
                   spread=a.w * 0.18, alpha=0.45)


def scene_garden(a, flags):
    sky(a, C['sky'], C['sky_lo'], y_lo=0.56)
    sun(a, a.w * 0.22, a.h * 0.14, min(a.w, a.h) * 0.05)
    ground(a, 0.60, C['grass'], jitter=0.02, alpha=0.6)
    a.wash([(a.w * 0.34, a.h * 0.34), (a.w * 0.66, a.h * 0.34), (a.w * 0.72, a.h * 0.60),
            (a.w * 0.28, a.h * 0.60)], C['cream'], alpha=0.85, jitter=4, blur=6, bites=3, bite=0.03)
    dome(a, 0.50, 0.36, 0.10, C['turq'] if 'fin' in flags else C['terra'], finial='fin' in flags)
    a.wash([(a.w * 0.42, a.h * 0.50), (a.w * 0.58, a.h * 0.50), (a.w * 0.58, a.h * 0.62),
            (a.w * 0.42, a.h * 0.62)], C['navy'], alpha=0.6, jitter=3, blur=5, bites=2, bite=0.02)
    for i in range(3):
        cypress(a, 0.16 + i * 0.06, 0.86, 0.34, C['pine'])
        cypress(a, 0.78 + i * 0.06, 0.86, 0.32, C['pine'])
    a.wash([(a.w * 0.40, a.h * 0.70), (a.w * 0.60, a.h * 0.70), (a.w * 0.64, a.h * 0.99),
            (a.w * 0.36, a.h * 0.99)], C['water_light'], alpha=0.75, jitter=4, blur=6, bites=3, bite=0.03)
    if 'palms' in flags:
        palms(a, (0.06, 0.30), n=2)
    tulips(a, (0.02, 0.72, 0.30, 0.98), n=14)
    tulips(a, (0.70, 0.72, 0.98, 0.98), n=14)


def scene_columns(a, flags):
    sky(a, C['sky'], C['sky_lo'], y_lo=0.72)
    sun(a, a.w * 0.80, a.h * 0.14, min(a.w, a.h) * 0.05)
    ground(a, 0.76, C['sand'], jitter=0.02, alpha=0.5)
    if 'climb' in flags:
        for i in range(6):
            a.wash([(a.w * (0.16 + i * 0.028), a.h * (0.80 + i * 0.028)),
                    (a.w * (0.24 + i * 0.028), a.h * (0.78 + i * 0.028)),
                    (a.w * (0.24 + i * 0.028), a.h * (0.86 + i * 0.028)),
                    (a.w * (0.16 + i * 0.028), a.h * (0.88 + i * 0.028))],
                   C['stone'], alpha=0.7, jitter=2, blur=4, bites=1, bite=0.012)
    columns(a, (0.30, 0.88, 0.44), n=4, base_y=0.80)
    a.wash([(a.w * 0.26, a.h * 0.30), (a.w * 0.92, a.h * 0.30), (a.w * 0.92, a.h * 0.36),
            (a.w * 0.26, a.h * 0.36)], C['stone_deep'], alpha=0.7, jitter=4, blur=6, bites=3, bite=0.03)


def scene_square(a, flags):
    sky(a, C['sky'], C['sky_lo'], y_lo=0.52)
    sun(a, a.w * 0.14, a.h * 0.12, min(a.w, a.h) * 0.045)
    dome(a, 0.50, 0.34, 0.15, C['turq'])
    arcade(a, (0.06, 0.44, 0.94, 0.72), n=6, color=C['stone'], arch=C['navy'])
    arcade(a, (0.30, 0.26, 0.70, 0.50), n=3, color=C['stone'], arch=C['turq_deep'])
    columns(a, (0.32, 0.68, 0.40), n=3, base_y=0.72, capital=False)
    ground(a, 0.72, C['stone'], jitter=0.02, alpha=0.55)
    a.wash([(a.w * 0.38, a.h * 0.80), (a.w * 0.62, a.h * 0.80), (a.w * 0.68, a.h * 0.99),
            (a.w * 0.32, a.h * 0.99)], C['water_light'], alpha=0.7, jitter=4, blur=6, bites=3, bite=0.03)
    for i in range(2):
        tree(a, 0.08 + i * 0.84, 0.99, 0.12, C['grass_deep'], leaf2=C['leaf'])


SCENES = {
    'waterfall': scene_waterfall, 'lake': scene_lake, 'city': scene_city,
    'wetland': scene_wetland, 'gorge': scene_gorge, 'castle': scene_castle,
    'mountain': scene_mountain, 'cave': scene_cave, 'forest': scene_forest,
    'valley': scene_valley, 'park': scene_park, 'monument': scene_monument,
    'desert': scene_desert, 'village': scene_village, 'garden': scene_garden,
    'columns': scene_columns, 'square': scene_square,
}


def paint(scene, flags=None, size=(560, 520), seed=7):
    """نقاشی آبرنگ یک صحنه. صحنهٔ ناشناخته → دریاچه."""
    a = Art(size[0], size[1], seed=seed)
    fn = SCENES.get(scene) or scene_lake
    fn(a, list(flags or []))
    return a.image()
