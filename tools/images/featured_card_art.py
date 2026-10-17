#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""هندسه‌های نقاشی «کارت تصویر شاخص» مقالات دیدنی‌ها.

این ماژول فقط نقاشی می‌کشد: زمینه‌های روشن، شکل‌های هندسی، خطوط مرتب و منظم.
هر «صحنه» (waterfall / lake / wetland / city / mountain / gorge / castle /
cave / forest / valley / desert / village / garden / monument / columns /
square / park) یک تابع مستقل است و از پرچم‌های هر مقاله الهام می‌گیرد.

واحد مختصات: پیکسل منطقی (کارت ۱۲۰۰×۶۰۰)؛ بزرگ‌نمایی و کوچک‌سازی در
`Canvas` انجام می‌شود تا لبه‌ها نرم شوند.

@package Sarzaminaryan_Child
"""

from PIL import Image, ImageDraw

# --------------------------------------------------------------- palette ---

PAL = {
    'ink':        '#0b2b4a',   # آبی ناوی متن اصلی
    'green':      '#0f5132',   # سبز تیره
    'gold':       '#9b6a16',   # طلایی تیره
    'muted':      '#5b7a86',
    'line':       '#dbe7f0',
    'white':      '#ffffff',
    'sky':        '#d9f0fd',
    'sky_deep':   '#a8def8',
    'water':      '#0f86c4',
    'water_lite': '#6fd0f2',
    'water_pale': '#c9eefc',
    'sand':       '#ecd6a8',
    'sand_deep':  '#d9b478',
    'ochre':      '#c98a2e',
    'brick':      '#c2703f',
    'stone':      '#dcd3c3',
    'stone_deep': '#b9a98d',
    'slate':      '#5b6b7a',
    'slate_lite': '#8b9aa7',
    'leaf':       '#3f9d3a',
    'leaf_lite':  '#7cc45a',
    'leaf_pale':  '#bfe3a4',
    'pine':       '#2b7a45',
    'rose':       '#e2585f',
    'orange':     '#ef8b34',
    'plum':       '#8e5bb5',
    'tulip':      '#e0453f',
    'tile':       '#2f8fbf',
    'tile_lite':  '#8fd4ee',
}
PAL['sky_soft'] = '#eaf7ff'
PAL['desert_sky'] = '#fdf6e7'
PAL['garden_sky'] = '#f3fbf3'
PAL['stone_sky'] = '#fdf8f0'


def mix(hex_a, hex_b, t):
    """ترکیب دو رنگ ساده."""
    a = [int(hex_a[i:i + 2], 16) for i in (1, 3, 5)]
    b = [int(hex_b[i:i + 2], 16) for i in (1, 3, 5)]
    return '#%02x%02x%02x' % tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))


# ----------------------------------------------------------------- canvas ---

class Canvas:
    """بوم با نرم‌سازی لبه (supersampling) و مختصات منطقی."""

    def __init__(self, w, h, ss=3, bg='#ffffff'):
        self.w, self.h, self.ss = w, h, ss
        self.img = Image.new('RGB', (w * ss, h * ss), bg)
        self.d = ImageDraw.Draw(self.img)

    # --- تبدیل مختصات -----------------------------------------------------
    def s(self, v):
        return v * self.ss

    def _box(self, box):
        return [self.s(v) for v in box]

    def _pts(self, pts):
        return [(self.s(x), self.s(y)) for x, y in pts]

    # --- شکل‌های پایه -----------------------------------------------------
    def rect(self, box, fill=None, outline=None, width=1):
        self.d.rectangle(self._box(box), fill=fill, outline=outline, width=max(1, int(self.s(width))))

    def rrect(self, box, radius=12, fill=None, outline=None, width=1):
        self.d.rounded_rectangle(
            self._box(box), radius=self.s(radius), fill=fill,
            outline=outline, width=max(1, int(self.s(width))),
        )

    def ellipse(self, box, fill=None, outline=None, width=1):
        self.d.ellipse(self._box(box), fill=fill, outline=outline, width=max(1, int(self.s(width))))

    def circle(self, cx, cy, r, fill=None, outline=None, width=1):
        self.ellipse([cx - r, cy - r, cx + r, cy + r], fill=fill, outline=outline, width=width)

    def poly(self, pts, fill=None, outline=None, width=1):
        self.d.polygon(self._pts(pts), fill=fill, outline=outline, width=max(1, int(self.s(width))))

    def line(self, pts, fill='#000000', width=2, joint='curve'):
        self.d.line(self._pts(pts), fill=fill, width=max(1, int(self.s(width))), joint=joint)

    def arc(self, box, start, end, fill='#000000', width=2):
        self.d.arc(self._box(box), start, end, fill=fill, width=max(1, int(self.s(width))))

    def pie(self, box, start, end, fill=None, outline=None, width=1):
        self.d.pieslice(self._box(box), start, end, fill=fill, outline=outline, width=max(1, int(self.s(width))))

    def dashed(self, p0, p1, fill, width=2, dash=10, gap=8):
        (x0, y0), (x1, y1) = p0, p1
        dx, dy = x1 - x0, y1 - y0
        length = (dx * dx + dy * dy) ** 0.5
        if length <= 0:
            return
        ux, uy = dx / length, dy / length
        pos = 0.0
        while pos < length:
            end = min(pos + dash, length)
            self.line([(x0 + ux * pos, y0 + uy * pos), (x0 + ux * end, y0 + uy * end)],
                      fill=fill, width=width)
            pos = end + gap

    def grid(self, box, step=24, fill='#eaf2f8', width=1, dash=None):
        x0, y0, x1, y1 = box
        x = x0
        while x <= x1:
            if dash:
                self.dashed((x, y0), (x, y1), fill, width=width, dash=dash, gap=dash)
            else:
                self.line([(x, y0), (x, y1)], fill=fill, width=width)
            x += step
        y = y0
        while y <= y1:
            if dash:
                self.dashed((x0, y), (x1, y), fill, width=width, dash=dash, gap=dash)
            else:
                self.line([(x0, y), (x1, y)], fill=fill, width=width)
            y += step

    def text(self, xy, txt, font, fill, anchor='mm'):
        self.d.text((self.s(xy[0]), self.s(xy[1])), txt, font=font, fill=fill, anchor=anchor)

    def text_size(self, txt, font):
        box = self.d.textbbox((0, 0), txt, font=font)
        return (box[2] - box[0]) / self.ss, (box[3] - box[1]) / self.ss

    # --- لایه و ماسک ------------------------------------------------------
    def layer(self, w, h, bg=None):
        return Canvas(w, h, ss=self.ss, bg=bg or '#ffffff')

    def paste(self, other, xy, radius=0, mask=None):
        """چسباندن بوم دیگر با گوشه‌های گرد اختیاری."""
        src = other.img
        if mask is None and radius:
            m = Image.new('L', src.size, 0)
            ImageDraw.Draw(m).rounded_rectangle(
                [0, 0, src.size[0] - 1, src.size[1] - 1], radius=self.s(radius), fill=255)
            mask = m
        self.img.paste(src, (int(self.s(xy[0])), int(self.s(xy[1]))), mask)

    def frame_into(self, outer, xy, size, radius=0):
        """برش لبه‌های بیرونِ کادر پیش از چسباندن."""
        raise NotImplementedError


# ------------------------------------------------------------------ motifs --
# «المان‌های نقاشی رنگی» برگرفته از محتوای مقاله؛ کوچک، رنگی و منظم.

def motif(name, c, cx, cy, r, color=None):
    """یک المان رنگی کوچک با مرکز (cx, cy) و شعاع r."""
    col = color or PAL['tile']

    if name == 'droplet':
        c.poly([(cx, cy - r), (cx + r * 0.72, cy + r * 0.28), (cx, cy + r),
                (cx - r * 0.72, cy + r * 0.28)], fill=col)

    elif name == 'wave':
        c.arc([cx - r, cy - r * 0.7, cx, cy + r * 0.7], 0, 180, fill=col, width=max(2, r * 0.34))
        c.arc([cx, cy - r * 0.7, cx + r, cy + r * 0.7], 180, 360, fill=col, width=max(2, r * 0.34))

    elif name == 'peak':
        c.poly([(cx - r, cy + r * 0.75), (cx - r * 0.15, cy - r), (cx + r * 0.72, cy + r * 0.75)],
               fill=col)
        c.poly([(cx - r * 0.15, cy - r), (cx + r * 0.14, cy - r * 0.3), (cx - r * 0.42, cy - r * 0.34)],
               fill='#ffffff')

    elif name == 'snow':
        for angle in (0, 60, 120):
            import math
            a = math.radians(angle)
            dx, dy = math.cos(a) * r, math.sin(a) * r
            c.line([(cx - dx, cy - dy), (cx + dx, cy + dy)], fill=col, width=max(2, r * 0.26))

    elif name == 'tent':
        c.poly([(cx - r, cy + r * 0.6), (cx, cy - r * 0.85), (cx + r, cy + r * 0.6)], fill=col)
        c.poly([(cx - r * 0.3, cy + r * 0.6), (cx, cy - r * 0.1), (cx + r * 0.3, cy + r * 0.6)],
               fill='#ffffff')

    elif name == 'oak':
        c.rect([cx - r * 0.14, cy, cx + r * 0.14, cy + r], fill=PAL['brick'])
        c.circle(cx, cy - r * 0.2, r * 0.66, fill=col)
        c.circle(cx - r * 0.5, cy + r * 0.1, r * 0.44, fill=col)
        c.circle(cx + r * 0.5, cy + r * 0.1, r * 0.44, fill=col)

    elif name == 'pine':
        c.rect([cx - r * 0.1, cy + r * 0.4, cx + r * 0.1, cy + r], fill=PAL['brick'])
        c.poly([(cx - r * 0.8, cy + r * 0.45), (cx, cy - r * 0.95), (cx + r * 0.8, cy + r * 0.45)],
               fill=col)

    elif name == 'leaf':
        c.poly([(cx, cy - r), (cx + r * 0.62, cy), (cx, cy + r), (cx - r * 0.62, cy)], fill=col)
        c.line([(cx, cy - r * 0.62), (cx, cy + r * 0.62)], fill='#ffffff', width=max(1, r * 0.16))

    elif name == 'rail':
        c.line([(cx - r, cy + r * 0.55), (cx + r, cy + r * 0.55)], fill=col, width=max(2, r * 0.2))
        for i in range(5):
            x = cx - r + i * (r * 0.5)
            c.rect([x, cy + r * 0.3, x + r * 0.12, cy + r * 0.8], fill=col)

    elif name == 'bridge':
        c.line([(cx - r, cy - r * 0.2), (cx + r, cy - r * 0.2)], fill=col, width=max(3, r * 0.3))
        c.line([(cx - r, cy + r * 0.75), (cx + r, cy + r * 0.75)], fill=col, width=max(2, r * 0.18))
        for i in range(4):
            x = cx - r + i * (r * 0.66)
            c.line([(x, cy + r * 0.75), (x + r * 0.33, cy - r * 0.2)], fill=col, width=max(1, r * 0.12))

    elif name == 'reed':
        for dx, top in ((-r * 0.5, -r), (0, -r * 1.15), (r * 0.5, -r * 0.85)):
            c.line([(cx + dx, cy + r), (cx + dx, cy + top)], fill=col, width=max(2, r * 0.16))
            c.poly([(cx + dx, cy + top), (cx + dx + r * 0.34, cy + top + r * 0.3),
                    (cx + dx, cy + top + r * 0.5)], fill=col)

    elif name == 'bird':
        c.arc([cx - r, cy - r * 0.3, cx, cy + r * 0.6], 200, 340, fill=col, width=max(2, r * 0.24))
        c.arc([cx, cy - r * 0.3, cx + r, cy + r * 0.6], 200, 340, fill=col, width=max(2, r * 0.24))

    elif name == 'lotus':
        for i in range(6):
            import math
            a = math.radians(i * 60)
            c.poly([(cx, cy), (cx + math.cos(a) * r, cy + math.sin(a) * r),
                    (cx + math.cos(a + 0.5) * r * 0.5, cy + math.sin(a + 0.5) * r * 0.5)], fill=col)
        c.circle(cx, cy, r * 0.3, fill='#ffe9a8')

    elif name == 'boat':
        c.poly([(cx - r, cy + r * 0.24), (cx + r, cy + r * 0.24), (cx + r * 0.6, cy + r * 0.7),
                (cx - r * 0.6, cy + r * 0.7)], fill=col)
        c.line([(cx, cy - r * 0.85), (cx, cy + r * 0.24)], fill=PAL['slate'], width=max(2, r * 0.14))
        c.poly([(cx, cy - r * 0.8), (cx + r * 0.62, cy + r * 0.05), (cx, cy + r * 0.05)], fill=PAL['sand'])

    elif name == 'spring':  # چشمه
        c.circle(cx, cy, r * 0.7, fill=col)
        c.circle(cx, cy, r * 0.34, fill='#ffffff')
        c.arc([cx - r * 1.05, cy - r * 1.05, cx + r * 1.05, cy + r * 1.05], 190, 350,
              fill=col, width=max(2, r * 0.2))

    elif name == 'rock':
        c.poly([(cx - r, cy + r * 0.7), (cx - r * 0.35, cy - r * 0.7), (cx + r * 0.4, cy - r * 0.35),
                (cx + r, cy + r * 0.7)], fill=col)

    elif name == 'tablet':
        c.rrect([cx - r * 0.8, cy - r, cx + r * 0.8, cy + r], radius=r * 0.2, fill=col)
        for i in range(3):
            c.line([(cx - r * 0.5, cy - r * 0.4 + i * r * 0.4), (cx + r * 0.5, cy - r * 0.4 + i * r * 0.4)],
                   fill='#ffffff', width=max(1, r * 0.14))

    elif name == 'pool':
        c.rrect([cx - r, cy - r * 0.55, cx + r, cy + r * 0.55], radius=r * 0.5, fill=col)
        c.line([(cx - r * 0.5, cy - r * 0.15), (cx + r * 0.3, cy - r * 0.15)], fill='#ffffff',
               width=max(1, r * 0.14))

    elif name == 'crenel':
        c.rect([cx - r, cy + r * 0.1, cx + r, cy + r * 0.7], fill=col)
        for i in range(4):
            c.rect([cx - r + i * (r * 0.5), cy - r * 0.4, cx - r + i * (r * 0.5) + r * 0.3, cy + r * 0.1],
                   fill=col)

    elif name == 'brick':
        c.rect([cx - r, cy - r * 0.9, cx + r, cy + r * 0.9], fill=col)
        for row in range(3):
            y = cy - r * 0.6 + row * r * 0.6
            c.line([(cx - r, y), (cx + r, y)], fill='#ffffff', width=max(1, r * 0.1))
            c.line([(cx - r * 0.34 if row % 2 else cx + r * 0.34, y),
                    (cx - r * 0.34 if row % 2 else cx + r * 0.34, y + r * 0.6)],
                   fill='#ffffff', width=max(1, r * 0.1))

    elif name == 'arch':
        c.rrect([cx - r * 0.8, cy - r * 0.2, cx + r * 0.8, cy + r], radius=r * 0.12, fill=col)
        c.pie([cx - r * 0.8, cy - r * 1.3, cx + r * 0.8, cy + r * 0.3], 180, 360, fill=col)
        c.rrect([cx - r * 0.4, cy - r * 0.1, cx + r * 0.4, cy + r], radius=r * 0.1, fill='#ffffff')

    elif name == 'dome':
        c.pie([cx - r, cy - r * 0.5, cx + r, cy + r * 1.5], 180, 360, fill=col)
        c.rect([cx - r, cy, cx + r, cy + r * 0.9], fill=col)
        c.line([(cx, cy - r * 0.5), (cx, cy - r * 1.1)], fill=PAL['gold'], width=max(2, r * 0.14))
        c.circle(cx, cy - r * 1.2, r * 0.16, fill=PAL['gold'])

    elif name == 'minaret':
        c.rect([cx - r * 0.34, cy - r, cx + r * 0.34, cy + r], fill=col)
        c.poly([(cx - r * 0.5, cy - r), (cx, cy - r * 1.7), (cx + r * 0.5, cy - r)], fill=PAL['gold'])
        c.line([(cx - r * 0.34, cy - r * 0.3), (cx + r * 0.34, cy - r * 0.3)], fill='#ffffff',
               width=max(1, r * 0.16))
        c.line([(cx - r * 0.34, cy + r * 0.3), (cx + r * 0.34, cy + r * 0.3)], fill='#ffffff',
               width=max(1, r * 0.16))

    elif name == 'column':
        c.rect([cx - r * 0.30, cy - r * 0.62, cx + r * 0.30, cy + r * 0.9], fill=col,
               outline=mix(col, '#000000', .25), width=max(1, r * 0.05))
        for i in range(3):
            c.line([(cx - r * 0.16 + i * r * 0.16, cy - r * 0.55), (cx - r * 0.16 + i * r * 0.16, cy + r * 0.85)],
                   fill=mix(col, '#000000', .16), width=max(1, r * 0.06))
        c.rect([cx - r * 0.46, cy - r * 0.72, cx + r * 0.46, cy - r * 0.60], fill=mix(col, '#ffffff', .3))
        c.rect([cx - r * 0.44, cy + r * 0.86, cx + r * 0.44, cy + r * 1.0], fill=mix(col, '#000000', .1))
        c.circle(cx - r * 0.36, cy - r * 0.92, r * 0.20, fill=mix(col, '#000000', .18))
        c.circle(cx + r * 0.36, cy - r * 0.92, r * 0.20, fill=mix(col, '#000000', .18))
        c.poly([(cx - r * 0.56, cy - r * 0.72), (cx, cy - r * 1.02), (cx + r * 0.56, cy - r * 0.72)],
               fill=mix(col, '#000000', .1))

    elif name == 'star':
        import math
        pts = []
        for i in range(8):
            a = math.radians(i * 45)
            rad = r if i % 2 == 0 else r * 0.4
            pts.append((cx + math.cos(a) * rad, cy + math.sin(a) * rad))
        c.poly(pts, fill=col)

    elif name == 'sun':
        c.circle(cx, cy, r * 0.62, fill=col)
        import math
        for i in range(8):
            a = math.radians(i * 45)
            c.line([(cx + math.cos(a) * r * 0.82, cy + math.sin(a) * r * 0.82),
                    (cx + math.cos(a) * r * 1.15, cy + math.sin(a) * r * 1.15)],
                   fill=col, width=max(2, r * 0.16))

    elif name == 'dune':
        c.pie([cx - r, cy - r * 1.2, cx + r * 0.4, cy + r], 180, 360, fill=col)
        c.pie([cx - r * 0.3, cy - r * 1.2, cx + r, cy + r], 180, 360, fill=mix(col, '#ffffff', .2))

    elif name == 'tulip':
        # لالهٔ واژگون: ساقهٔ راست و سرِ آویزان به سمت پایین
        c.line([(cx, cy + r), (cx, cy - r * 0.72)], fill=PAL['pine'], width=max(2, r * 0.16))
        c.poly([(cx - r * 0.52, cy - r * 0.30), (cx, cy - r * 0.40), (cx + r * 0.52, cy - r * 0.30),
                (cx + r * 0.34, cy - r * 1.02), (cx - r * 0.34, cy - r * 1.02)], fill=col)
        c.line([(cx, cy - r * 1.0), (cx, cy - r * 0.46)], fill=mix(col, '#000000', .22),
               width=max(1, r * 0.10))
        c.circle(cx, cy - r * 0.30, r * 0.16, fill=mix(col, '#ffffff', .25))

    elif name == 'flower':
        import math
        for i in range(5):
            a = math.radians(i * 72)
            c.circle(cx + math.cos(a) * r * 0.45, cy + math.sin(a) * r * 0.45, r * 0.34, fill=col)
        c.circle(cx, cy, r * 0.26, fill=PAL['gold'])

    elif name == 'cypress':
        c.rect([cx - r * 0.08, cy + r * 0.55, cx + r * 0.08, cy + r], fill=PAL['brick'])
        c.poly([(cx, cy - r), (cx + r * 0.34, cy - r * 0.48), (cx + r * 0.40, cy + r * 0.35),
                (cx + r * 0.16, cy + r * 0.62), (cx - r * 0.16, cy + r * 0.62),
                (cx - r * 0.40, cy + r * 0.35), (cx - r * 0.34, cy - r * 0.48)], fill=col)
        c.line([(cx - r * 0.10, cy - r * 0.7), (cx - r * 0.10, cy + r * 0.5)],
               fill=mix(col, '#ffffff', .28), width=max(1, r * 0.14))

    elif name == 'stair':
        for i in range(4):
            c.rect([cx - r + i * (r * 0.5), cy - r * 0.6 + i * (r * 0.42),
                    cx - r + (i + 0.8) * (r * 0.5), cy + r], fill=col)

    elif name == 'house':
        c.rect([cx - r * 0.8, cy - r * 0.15, cx + r * 0.8, cy + r * 0.75], fill='#fdf6e6')
        c.poly([(cx - r, cy - r * 0.1), (cx, cy - r * 1), (cx + r, cy - r * 0.1)], fill=col)
        c.rect([cx - r * 0.22, cy + r * 0.26, cx + r * 0.22, cy + r * 0.75], fill=PAL['brick'])

    elif name == 'palm':
        c.rect([cx - r * 0.1, cy - r * 0.3, cx + r * 0.1, cy + r], fill=PAL['brick'])
        for dx, dy in ((-1, -0.3), (-0.6, -1), (0.6, -1), (1, -0.3), (0, -1.2)):
            c.line([(cx, cy - r * 0.35), (cx + dx * r * 0.8, cy - r * 0.35 + dy * r * 0.6)],
                   fill=col, width=max(2, r * 0.16))

    elif name == 'gazelle':
        c.ellipse([cx - r, cy - r * 0.35, cx + r, cy + r * 0.35], fill=col)
        c.poly([(cx + r * 0.6, cy - r * 0.2), (cx + r * 1.1, cy - r), (cx + r * 1.2, cy - r * 0.6),
                (cx + r * 0.9, cy + r * 0.05)], fill=col)
        c.line([(cx + r * 1.05, cy - r * 0.9), (cx + r * 1.25, cy - r * 1.5)], fill=col,
               width=max(2, r * 0.1))
        c.line([(cx + r * 1.0, cy - r * 0.95), (cx + r * 0.8, cy - r * 1.6)], fill=col,
               width=max(2, r * 0.1))
        for dx in (-0.6, -0.2, 0.4):
            c.line([(cx + dx * r, cy + r * 0.3), (cx + dx * r, cy + r)], fill=col, width=max(2, r * 0.12))

    elif name == 'strata':
        c.rrect([cx - r, cy - r * 0.7, cx + r, cy + r * 0.7], radius=r * 0.2, fill=col)
        for i in range(3):
            c.line([(cx - r, cy - r * 0.35 + i * r * 0.35), (cx + r, cy - r * 0.35 + i * r * 0.35)],
                   fill=mix(col, '#ffffff', .45), width=max(1, r * 0.1))

    elif name == 'moon':
        c.pie([cx - r, cy - r, cx + r, cy + r], 0, 360, fill=col)
        c.pie([cx - r * 0.55, cy - r, cx + r * 1.45, cy + r], 0, 360, fill='#ffffff')

    return None


MOTIF_COLORS = {
    'droplet': PAL['water'],
    'wave': PAL['water_lite'],
    'peak': PAL['slate'],
    'snow': PAL['sky_deep'],
    'tent': PAL['orange'],
    'oak': PAL['leaf'],
    'pine': PAL['pine'],
    'leaf': PAL['leaf_lite'],
    'rail': PAL['slate'],
    'bridge': PAL['brick'],
    'reed': PAL['pine'],
    'bird': PAL['muted'],
    'lotus': PAL['rose'],
    'boat': PAL['ochre'],
    'spring': PAL['water'],
    'rock': PAL['stone_deep'],
    'tablet': PAL['sand_deep'],
    'pool': PAL['water_lite'],
    'crenel': PAL['stone_deep'],
    'brick': PAL['brick'],
    'arch': PAL['tile'],
    'dome': PAL['tile'],
    'minaret': PAL['tile'],
    'column': PAL['stone_deep'],
    'star': PAL['gold'],
    'sun': PAL['orange'],
    'dune': PAL['sand_deep'],
    'tulip': PAL['tulip'],
    'flower': PAL['rose'],
    'cypress': PAL['pine'],
    'stair': PAL['stone_deep'],
    'house': PAL['brick'],
    'palm': PAL['pine'],
    'gazelle': PAL['stone_deep'],
    'strata': PAL['ochre'],
    'moon': PAL['gold'],
}


def draw_motif(c, name, cx, cy, r, color=None):
    motif(name, c, cx, cy, r, color or MOTIF_COLORS.get(name, PAL['tile']))


def motif_row(c, names, cx, cy, r, gap=26, color=None):
    """ردیف منظمی از المان‌ها، وسط‌چین حول cx."""
    if not names:
        return
    total = (len(names) - 1) * (r * 2 + gap)
    x = cx - total / 2
    for n in names:
        draw_motif(c, n, x, cy, r, color)
        x += r * 2 + gap
