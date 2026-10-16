#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""صحنه‌های هندسی «کارت تصویر شاخص» — نمای هر نوع دیدنی.

هر صحنه در یک کادر مربع‌مانند (پیش‌فرض ۳۲۷×۳۸۴) کشیده می‌شود: آسمان روشن،
شکل‌های هندسی تمیز، خطوط منظم و چند المان رنگی برگرفته از محتوای مقاله.

@package Sarzaminaryan_Child
"""

import math

from featured_card_art import PAL, mix, draw_motif

# ------------------------------------------------------------- primitives ---

def sky(c, W, H, top, horizon=None, line=None):
    c.rect([0, 0, W, H], fill=top)
    if horizon is not None:
        c.rect([0, horizon, W, H], fill=line or mix(top, '#ffffff', .35))


def ground(c, W, H, y, color, edge=None):
    c.rect([0, y, W, H], fill=color)
    if edge:
        c.line([(0, y), (W, y)], fill=edge, width=1.5)


def sun(c, cx, cy, r, color=None, rays=True):
    color = color or PAL['orange']
    c.circle(cx, cy, r * 0.66, fill=color)
    c.circle(cx, cy, r * 0.66, outline=mix(color, '#ffffff', .45), width=1.5)
    if rays:
        for i in range(8):
            a = math.radians(i * 45)
            c.line([(cx + math.cos(a) * r * 0.92, cy + math.sin(a) * r * 0.92),
                    (cx + math.cos(a) * r * 1.32, cy + math.sin(a) * r * 1.32)],
                   fill=mix(color, '#ffffff', .25), width=2)


def peak(c, cx, base_y, half, height, fill, snow=0.34, strata=False):
    apex = (cx, base_y - height)
    c.poly([(cx - half, base_y), apex, (cx + half, base_y)], fill=fill)
    if snow:
        sh = height * snow
        c.poly([apex, (cx + half * snow, base_y - height + sh),
                (cx + half * snow * 0.35, base_y - height + sh * 0.55),
                (cx - half * snow * 0.5, base_y - height + sh * 0.8),
                (cx - half * snow, base_y - height + sh)], fill='#ffffff')
    if strata:
        c.line([(cx - half * 0.55, base_y - height * 0.32), (cx, base_y - height * 0.02)],
               fill=mix(fill, '#ffffff', .30), width=2)


def range_back(c, W, base_y, color):
    c.poly([(0, base_y), (W * 0.16, base_y - 54), (W * 0.34, base_y - 16),
            (W * 0.55, base_y - 62), (W * 0.78, base_y - 20), (W, base_y - 46),
            (W, base_y), (0, base_y)], fill=color)


def oak(c, x, y, s, canopy=None, trunk=None):
    canopy = canopy or PAL['leaf']
    trunk = trunk or PAL['brick']
    c.rect([x - s * 0.09, y - s * 0.55, x + s * 0.09, y], fill=trunk)
    c.circle(x, y - s * 0.85, s * 0.42, fill=canopy)
    c.circle(x - s * 0.34, y - s * 0.6, s * 0.3, fill=mix(canopy, '#ffffff', .12))
    c.circle(x + s * 0.34, y - s * 0.6, s * 0.3, fill=mix(canopy, '#000000', .10))


def pine(c, x, y, s, color=None):
    color = color or PAL['pine']
    c.rect([x - s * 0.06, y - s * 0.32, x + s * 0.06, y], fill=PAL['brick'])
    for i in range(3):
        w = s * (0.5 - i * 0.1)
        top = y - s * (0.32 + (i + 1) * 0.26)
        c.poly([(x - w, top + s * 0.3), (x, top), (x + w, top + s * 0.3)],
               fill=mix(color, '#ffffff', i * 0.08))


def water_lines(c, x0, y0, x1, y1, color, rows=4, width=2):
    step = (y1 - y0) / max(1, rows)
    for i in range(rows):
        y = y0 + step * (i + 0.5)
        inset = (i % 2) * ((x1 - x0) * 0.06)
        c.line([(x0 + inset, y), (x1 - inset, y)], fill=color, width=width)


def reeds(c, x, y, s, color=None):
    color = color or PAL['pine']
    for dx, h in ((0, 1.0), (0.3, 0.78), (-0.28, 0.86)):
        base = y
        top = y - s * h
        c.line([(x + s * dx, base), (x + s * dx, top)], fill=color, width=2)
        c.poly([(x + s * dx, top), (x + s * dx + s * 0.2, top + s * 0.16),
                (x + s * dx, top + s * 0.28)], fill=mix(color, '#ffffff', .15))


def bird(c, x, y, s, color=None):
    color = color or PAL['muted']
    c.arc([x - s, y - s * 0.5, x, y + s * 0.6], 200, 340, fill=color, width=2)
    c.arc([x, y - s * 0.5, x + s, y + s * 0.6], 200, 340, fill=color, width=2)


def clouds(c, W, H, specs):
    for x, y, s in specs:
        c.circle(W * x, H * y, s, fill='#ffffff')
        c.circle(W * x + s * 0.9, H * y - s * 0.35, s * 0.78, fill='#ffffff')
        c.circle(W * x - s * 0.9, H * y + s * 0.18, s * 0.64, fill='#ffffff')
        c.rrect([W * x - s * 1.5, H * y, W * x + s * 1.5, H * y + s * 0.6], radius=s * 0.3,
                fill='#ffffff')


def star4(c, x, y, s, color):
    c.poly([(x, y - s), (x + s * 0.3, y - s * 0.3), (x + s, y), (x + s * 0.3, y + s * 0.3),
            (x, y + s), (x - s * 0.3, y + s * 0.3), (x - s, y), (x - s * 0.3, y - s * 0.3)],
           fill=color)


def crenels(c, x0, x1, y, h, merlon, color, filled=False):
    """دندانه‌های کنگره روی خط y."""
    if filled:
        c.rect([x0, y + h * 0.35, x1, y + h], fill=color)
    step = merlon * 2
    x = x0
    while x < x1:
        w = min(merlon, x1 - x)
        c.rect([x, y, x + w, y + h * 0.5], fill=color)
        x += step


def brick_lines(c, x0, y0, x1, y1, rows, cols, color, width=1):
    step = (y1 - y0) / rows
    for i in range(1, rows):
        c.line([(x0, y0 + step * i), (x1, y0 + step * i)], fill=color, width=width)
    for i in range(rows):
        for j in range(cols):
            xx = x0 + (x1 - x0) / cols * (j + (0.5 if i % 2 else 0))
            if x0 < xx < x1:
                c.line([(xx, y0 + step * i), (xx, min(y1, y0 + step * (i + 1)))],
                       fill=color, width=width)


# ---------------------------------------------------------------- scenes ----

def scene_lake(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.62, line=mix(PAL['sky'], '#ffffff', .3))
    sun(c, W * 0.80, H * 0.16, 20)
    clouds(c, W, H, [(0.18, 0.14, 9), (0.62, 0.10, 7)])
    range_back(c, W, H * 0.60, PAL['sky_deep'])
    peak(c, W * 0.30, H * 0.62, W * 0.30, H * 0.34, PAL['slate'])
    peak(c, W * 0.62, H * 0.62, W * 0.24, H * 0.24, mix(PAL['slate'], '#ffffff', .22))

    # دریاچهٔ اصلی
    c.ellipse([W * 0.05, H * 0.64, W * 0.95, H * 0.88], fill=mix(PAL['sand'], '#ffffff', .45))
    c.ellipse([W * 0.07, H * 0.652, W * 0.93, H * 0.860], fill=PAL['water_lite'])
    c.ellipse([W * 0.10, H * 0.668, W * 0.90, H * 0.842], fill=PAL['water'])
    for i in range(3):
        y = H * (0.70 + i * 0.045)
        c.line([(W * (0.26 + i * 0.04), y), (W * (0.74 - i * 0.04), y)],
               fill=mix(PAL['water'], '#ffffff', .5), width=1.6)
    if 'two_lakes' in f:
        c.ellipse([W * 0.30, H * 0.70, W * 0.44, H * 0.752], fill=PAL['water_lite'])
        c.ellipse([W * 0.32, H * 0.708, W * 0.42, H * 0.744], fill=mix(PAL['water'], '#ffffff', .25))
        c.ellipse([W * 0.56, H * 0.72, W * 0.70, H * 0.772], fill=PAL['water_lite'])
        c.ellipse([W * 0.58, H * 0.728, W * 0.68, H * 0.764], fill=mix(PAL['water'], '#ffffff', .25))
        c.dashed((W * 0.44, H * 0.726), (W * 0.56, H * 0.746), PAL['orange'], width=1.6, dash=6, gap=6)

    ground(c, W, H, H * 0.90, mix(PAL['leaf_pale'], '#ffffff', .25), edge=mix(PAL['leaf'], '#ffffff', .5))
    oak(c, W * 0.09, H * 1.00, 54)
    oak(c, W * 0.93, H * 1.00, 46)
    if 'tent' in f:
        c.poly([(W * 0.80, H * 1.0), (W * 0.88, H * 0.85), (W * 0.96, H * 1.0)], fill=PAL['orange'])
        c.poly([(W * 0.845, H * 1.0), (W * 0.88, H * 0.92), (W * 0.915, H * 1.0)], fill='#ffffff')
    if 'boat' in f:
        draw_motif(c, 'boat', W * 0.50, H * 0.78, 15)


def scene_waterfall(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.52, line=mix(PAL['sky'], '#ffffff', .3))
    sun(c, W * 0.18, H * 0.14, 18)
    clouds(c, W, H, [(0.62, 0.12, 9), (0.86, 0.20, 7)])
    tall = 'tall' in f
    top_y = H * (0.06 if tall else 0.14)
    fall_bottom = H * (0.72 if tall else 0.66)
    left_fill = PAL['slate']
    right_fill = mix(PAL['slate'], '#ffffff', .16)
    # صخره‌های دو طرف
    c.poly([(0, H * 0.20), (W * 0.30, top_y), (W * 0.40, H * 0.30), (W * 0.34, H * 0.52),
            (W * 0.30, fall_bottom), (0, H * 0.82)], fill=left_fill)
    c.poly([(W, H * 0.16), (W * 0.70, top_y), (W * 0.62, H * 0.34), (W * 0.68, H * 0.56),
            (W * 0.72, fall_bottom), (W, H * 0.86)], fill=right_fill)
    # وجوه روشن
    c.poly([(W * 0.30, top_y), (W * 0.44, H * 0.34), (W * 0.40, H * 0.46), (W * 0.34, H * 0.30)],
           fill=mix(left_fill, '#ffffff', .22))
    c.poly([(W * 0.70, top_y), (W * 0.58, H * 0.36), (W * 0.62, H * 0.50), (W * 0.68, H * 0.34)],
           fill=mix(right_fill, '#ffffff', .3))
    if 'karst' in f or 'strata' in f:
        for i in range(4):
            y = H * (0.24 + i * 0.08)
            c.line([(W * 0.02 + i * 2, y), (W * 0.30, y - H * 0.02)], fill=mix(left_fill, '#000000', .12),
                   width=1.5)
            c.line([(W * 0.72, y - H * 0.02), (W * 0.98 - i * 2, y)], fill=mix(right_fill, '#000000', .08),
                   width=1.5)
    # آبشار
    band = [(W * 0.42, top_y + H * 0.02), (W * 0.58, top_y + H * 0.02),
            (W * 0.60, fall_bottom), (W * 0.40, fall_bottom)]
    c.poly(band, fill=mix(PAL['water_pale'], '#ffffff', .35))
    for i in range(4):
        x = W * (0.44 + i * 0.04)
        c.line([(x, top_y + H * 0.05), (x - W * 0.01, fall_bottom - H * 0.02)],
               fill=PAL['water_lite'], width=3)
    c.line([(W * 0.42, top_y + H * 0.03), (W * 0.58, top_y + H * 0.03)], fill='#ffffff', width=3)
    # حوضچه
    c.ellipse([W * 0.24, fall_bottom - H * 0.02, W * 0.76, fall_bottom + H * 0.14], fill=PAL['water_lite'])
    c.ellipse([W * 0.30, fall_bottom + H * 0.01, W * 0.70, fall_bottom + H * 0.10], fill=PAL['water'])
    for i in range(3):
        c.arc([W * (0.36 - i * 0.02), fall_bottom - i * 4, W * (0.64 + i * 0.02), fall_bottom + H * 0.10 + i * 4],
              200, 340, fill='#ffffff', width=2)
    for i in range(6):
        x = W * (0.30 + i * 0.07)
        c.circle(x, fall_bottom - H * 0.03 - (i % 3) * 6, 3 + (i % 2), fill='#ffffff')
    # زمین و درخت‌ها
    ground(c, W, H, fall_bottom + H * 0.10, mix(PAL['leaf_pale'], '#ffffff', .2))
    c.poly([(0, H), (W * 0.18, H * 0.86), (W * 0.30, H)], fill=PAL['slate_lite'])
    c.poly([(W, H), (W * 0.80, H * 0.88), (W * 0.68, H)], fill=mix(PAL['slate_lite'], '#ffffff', .2))
    oak(c, W * 0.13, H, 62)
    oak(c, W * 0.90, H, 54, canopy=PAL['pine'])
    if 'rail' in f:
        y = top_y + H * 0.05
        c.rect([0, y, W, y + 6], fill=PAL['brick'])
        c.rect([0, y + 22, W, y + 27], fill=mix(PAL['brick'], '#000000', .18))
        for i in range(7):
            x = i * (W / 6)
            c.line([(x, y + 4), (x + W / 6, y + 21)], fill=PAL['slate_lite'], width=2)
        c.rrect([W * 0.34, y - 18, W * 0.62, y + 2], radius=3, fill=PAL['orange'])
        for i in range(4):
            c.rect([W * 0.37 + i * W * 0.06, y - 13, W * 0.40 + i * W * 0.06, y - 6], fill='#ffffff')
        c.rect([W * 0.60, y - 26, W * 0.645, y + 2], fill=PAL['brick'])


def scene_wetland(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.56, line=mix(PAL['sky'], '#ffffff', .3))
    sun(c, W * 0.78, H * 0.15, 20)
    if 'mountains_back' in f or True:
        range_back(c, W, H * 0.56, mix(PAL['sky_deep'], '#ffffff', .25))
    c.rect([0, H * 0.50, W, H * 0.62], fill=mix(PAL['leaf_pale'], '#ffffff', .35))
    c.ellipse([-W * 0.05, H * 0.58, W * 1.05, H * 0.92], fill=PAL['water_pale'])
    c.ellipse([0, H * 0.62, W, H * 0.89], fill=PAL['water_lite'])
    water_lines(c, W * 0.10, H * 0.66, W * 0.90, H * 0.84, mix(PAL['water'], '#ffffff', .55), rows=4)
    # نیزارها
    reeds(c, W * 0.13, H * 0.72, 60)
    reeds(c, W * 0.22, H * 0.76, 48)
    reeds(c, W * 0.88, H * 0.70, 56)
    reeds(c, W * 0.80, H * 0.75, 44)
    # نیلوفرها
    for i, x in enumerate((0.36, 0.53, 0.66)):
        draw_motif(c, 'lotus', W * x, H * (0.72 + (i % 2) * 0.05), 9, PAL['rose'])
    # پرنده‌ها
    for x, y, s in ((0.42, 0.24, 11), (0.55, 0.18, 9), (0.66, 0.26, 8)):
        bird(c, W * x, H * y, s)
    if 'boat' in f:
        draw_motif(c, 'boat', W * 0.66, H * 0.80, 20, PAL['ochre'])
        c.circle(W * 0.66, H * 0.755, 4, fill=PAL['ink'])
    ground(c, W, H, H * 0.90, mix(PAL['leaf'], '#ffffff', .55))
    c.poly([(0, H), (W * 0.16, H * 0.90), (W * 0.34, H)], fill=mix(PAL['leaf_pale'], '#ffffff', .2))


def scene_city(c, W, H, f):
    oasis = 'palms' in f
    sky(c, W, H, PAL['desert_sky'] if oasis else PAL['sky_soft'], horizon=H * 0.66,
        line=mix(PAL['orange'] if oasis else PAL['sky'], '#ffffff', .72))
    sun(c, W * (0.80 if oasis else 0.18), H * 0.14, 20)
    if not oasis:
        range_back(c, W, H * 0.64, mix(PAL['sky_deep'], '#ffffff', .3))
        peak(c, W * 0.22, H * 0.66, W * 0.26, H * 0.24, mix(PAL['slate'], '#ffffff', .12))
        peak(c, W * 0.66, H * 0.66, W * 0.22, H * 0.18, mix(PAL['slate'], '#ffffff', .3))
    else:
        c.poly([(0, H * 0.66), (W * 0.26, H * 0.58), (W * 0.52, H * 0.64), (W * 0.78, H * 0.56),
                (W, H * 0.63), (W, H * 0.70), (0, H * 0.70)], fill=mix(PAL['sand_deep'], '#ffffff', .45))

    ground(c, W, H, H * 0.66, mix(PAL['sand'], '#ffffff', .35) if oasis else '#f4f8fb')

    if oasis:
        # بادگیر خشتی
        c.rect([W * 0.06, H * 0.22, W * 0.19, H * 0.66], fill=PAL['sand_deep'],
               outline=mix(PAL['ochre'], '#000000', .2), width=1.6)
        c.poly([(W * 0.045, H * 0.22), (W * 0.125, H * 0.13), (W * 0.205, H * 0.22)],
               fill=mix(PAL['ochre'], '#000000', .12))
        for i in range(3):
            c.rect([W * (0.075 + i * 0.04), H * 0.26, W * (0.09 + i * 0.04), H * 0.44], fill=PAL['ink'])
        c.line([(W * 0.06, H * 0.50), (W * 0.19, H * 0.50)], fill=mix(PAL['ochre'], '#000000', .1), width=1.5)
        # دیوار و سردر طاق‌دار
        c.rect([W * 0.22, H * 0.42, W * 0.80, H * 0.70], fill='#f6e3c0')
        c.rect([W * 0.22, H * 0.42, W * 0.80, H * 0.46], fill=PAL['sand_deep'])
        brick_lines(c, W * 0.22, H * 0.46, W * 0.80, H * 0.70, 4, 6, mix(PAL['sand_deep'], '#000000', .1), 1)
        draw_motif(c, 'arch', W * 0.51, H * 0.60, 34, PAL['tile'])
        c.rect([W * 0.475, H * 0.62, W * 0.545, H * 0.70], fill=PAL['ink'])
        # گنبد کوچک پشت دیوار
        draw_motif(c, 'dome', W * 0.66, H * 0.34, 26, PAL['tile'])
        # نخل‌ها
        for x, y, s in ((0.28, 0.88, 44), (0.38, 0.94, 38), (0.84, 0.86, 48), (0.94, 0.94, 40),
                        (0.10, 0.96, 34)):
            draw_motif(c, 'palm', W * x, H * y, s, PAL['pine'])
        # نهر آب
        c.poly([(0, H * 0.86), (W * 0.42, H * 0.84), (W, H * 0.88), (W, H * 0.94),
                (W * 0.40, H * 0.90), (0, H * 0.92)], fill=PAL['water_lite'])
        c.line([(W * 0.04, H * 0.872), (W * 0.40, H * 0.856)], fill='#ffffff', width=2)
        return

    # بافت کم‌ارتفاع شهر کوهستانی
    wall, roof = '#f8fafc', PAL['slate_lite']
    for x0, top, w in ((0.06, 0.56, 0.14), (0.55, 0.60, 0.13), (0.86, 0.64, 0.12)):
        x, ww = W * x0, W * w
        c.rect([x, H * top, x + ww, H * 0.84], fill=wall, outline=PAL['stone_deep'], width=1)
        c.poly([(x - ww * 0.10, H * top), (x + ww / 2, H * (top - 0.055)), (x + ww * 1.10, H * top)], fill=roof)
        for r in range(2):
            for k in range(2):
                wx = x + ww * (0.22 + k * 0.40)
                wy = H * (top + 0.06 + r * 0.10)
                c.rrect([wx, wy, wx + ww * 0.24, wy + H * 0.06], radius=2, fill=PAL['tile_lite'])
    draw_motif(c, 'dome', W * 0.44, H * 0.52, 28, PAL['tile'])
    draw_motif(c, 'minaret', W * 0.33, H * 0.46, 24, PAL['tile'])
    c.rect([W * 0.36, H * 0.62, W * 0.52, H * 0.84], fill=wall, outline=PAL['stone_deep'], width=1)
    draw_motif(c, 'arch', W * 0.44, H * 0.74, 20, PAL['tile'])
    for x, y, s in ((0.09, 0.92, 46), (0.93, 0.94, 42)):
        oak(c, W * x, H * y, s)
    if 'rivers' in f:
        c.poly([(0, H * 0.84), (W * 0.30, H * 0.81), (W * 0.62, H * 0.85), (W, H * 0.82),
                (W, H * 0.88), (W * 0.60, H * 0.91), (W * 0.28, H * 0.87), (0, H * 0.90)],
               fill=PAL['water_lite'])
        c.line([(0, H * 0.835), (W * 0.30, H * 0.825)], fill='#ffffff', width=2)
    if 'rail' in f:
        y = H * 0.94
        c.line([(0, y), (W, y)], fill=PAL['slate'], width=3)
        for i in range(9):
            c.line([(i * W / 8, y), (i * W / 8, y + 7)], fill=PAL['slate_lite'], width=2)
        c.rrect([W * 0.42, y - 20, W * 0.74, y - 2], radius=4, fill=PAL['orange'])
        for i in range(4):
            c.rect([W * 0.46 + i * W * 0.07, y - 15, W * 0.49 + i * W * 0.07, y - 7], fill='#ffffff')


def scene_mountain(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.70, line=mix(PAL['sky'], '#ffffff', .35))
    sun(c, W * 0.80, H * 0.15, 20)
    clouds(c, W, H, [(0.16, 0.13, 9), (0.50, 0.09, 7)])
    peak(c, W * 0.32, H * 0.74, W * 0.34, H * 0.50, PAL['slate'])
    peak(c, W * 0.70, H * 0.76, W * 0.28, H * 0.36, mix(PAL['slate'], '#ffffff', .22))
    if 'snowpatch' in f:
        for x, y, s in ((0.14, 0.86, 20), (0.60, 0.90, 16), (0.90, 0.86, 14)):
            c.ellipse([W * x - s, H * y, W * x + s, H * y + s * 0.45],
                      fill=mix('#ffffff', PAL['sky_deep'], .22))
        c.line([(W * 0.36, H * 0.70), (W * 0.44, H * 0.66)], fill='#ffffff', width=2)
        c.line([(W * 0.70, H * 0.56), (W * 0.78, H * 0.52)], fill='#ffffff', width=2)
    if 'volcano' in f:
        c.ellipse([W * 0.44, H * 0.22, W * 0.62, H * 0.30], fill=mix(PAL['slate'], '#ffffff', .3))
        c.ellipse([W * 0.46, H * 0.235, W * 0.60, H * 0.285], fill=mix(PAL['gold'], '#ffffff', .55))
        for i in range(3):
            c.circle(W * (0.49 + i * 0.05), H * (0.16 - i * 0.02), 5 + i, fill='#ffffff')
    ground(c, W, H, H * 0.76, mix(PAL['leaf_pale'], '#ffffff', .25), edge=mix(PAL['leaf'], '#ffffff', .5))
    if 'stream' in f:
        c.poly([(W * 0.44, H * 0.78), (W * 0.56, H * 0.78), (W * 0.66, H), (W * 0.34, H)],
               fill=PAL['water_lite'])
        for i in range(3):
            y = H * (0.84 + i * 0.05)
            c.line([(W * (0.44 - i * 0.03), y), (W * (0.56 + i * 0.03), y)], fill='#ffffff', width=2)
    if 'tulips' in f:
        for row in range(2):
            for i in range(4):
                x = W * (0.14 + i * 0.24 + (row % 2) * 0.12)
                y = H * (0.86 + row * 0.10)
                draw_motif(c, 'tulip', x, y, 22, PAL['tulip'] if i % 2 == 0 else PAL['orange'])
    else:
        for i in range(4):
            draw_motif(c, 'flower', W * (0.12 + i * 0.12), H * (0.90 + (i % 2) * 0.05), 7,
                       PAL['rose'] if i % 2 else PAL['plum'])
    oak(c, W * 0.90, H * 0.99, 52)
    if 'tent' in f:
        c.poly([(W * 0.06, H * 0.99), (W * 0.16, H * 0.86), (W * 0.26, H * 0.99)], fill=PAL['orange'])


def scene_gorge(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.30, line=mix(PAL['sky'], '#ffffff', .35))
    sun(c, W * 0.50, H * 0.12, 16)
    left = PAL['slate']
    right = mix(PAL['slate'], '#ffffff', .18)
    c.poly([(0, 0), (W * 0.34, 0), (W * 0.30, H * 0.26), (W * 0.36, H * 0.50), (W * 0.30, H * 0.74),
            (W * 0.34, H), (0, H)], fill=left)
    c.poly([(W, 0), (W * 0.66, 0), (W * 0.70, H * 0.22), (W * 0.64, H * 0.46), (W * 0.70, H * 0.70),
            (W * 0.66, H), (W, H)], fill=right)
    for i in range(5):
        y = H * (0.16 + i * 0.17)
        c.line([(W * 0.04, y), (W * 0.29 - i * 2, y - 5)], fill=mix(left, '#ffffff', .30), width=2)
        c.line([(W * 0.71 + i * 2, y - 5), (W * 0.96, y)], fill=mix(right, '#ffffff', .34), width=2)
    c.poly([(W * 0.34, H * 0.24), (W * 0.66, H * 0.20), (W * 0.66, H * 0.62), (W * 0.34, H * 0.62)],
           fill=mix(PAL['sky'], '#ffffff', .55))
    if 'inscription' in f:
        c.rrect([W * 0.02, H * 0.30, W * 0.14, H * 0.42], radius=4, fill=PAL['sand'])
        for i in range(3):
            c.line([(W * 0.04, H * (0.33 + i * 0.03)), (W * 0.12, H * (0.33 + i * 0.03))],
                   fill=PAL['ochre'], width=2)
    # آب و حوضچه‌ها
    c.poly([(W * 0.34, H * 0.62), (W * 0.66, H * 0.62), (W * 0.74, H), (W * 0.26, H)],
           fill=PAL['water_pale'])
    pools = [(0.50, 0.66, 26, 12), (0.46, 0.78, 32, 15), (0.56, 0.90, 36, 17)]
    for cx, cy, rx, ry in pools:
        c.ellipse([W * cx - rx, H * cy - ry, W * cx + rx, H * cy + ry], fill=PAL['water_lite'])
        c.ellipse([W * cx - rx * 0.6, H * cy - ry * 0.5, W * cx + rx * 0.6, H * cy + ry * 0.5],
                  fill=PAL['water'])
        c.arc([W * cx - rx * 0.8, H * cy - ry * 0.9, W * cx + rx * 0.8, H * cy + ry * 1.4], 200, 340,
              fill='#ffffff', width=2)
    water_lines(c, W * 0.36, H * 0.66, W * 0.64, H * 0.72, '#ffffff', rows=2)
    for x, y, s in ((0.44, 0.20, 10), (0.58, 0.15, 8)):
        bird(c, W * x, H * y, s)


def scene_castle(c, W, H, f):
    brick = 'mudbrick' in f
    wall = PAL['sand_deep'] if brick else PAL['stone_deep']
    wall_lite = mix(wall, '#ffffff', .25)
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.74, line=mix(PAL['sky'], '#ffffff', .35))
    sun(c, W * 0.82, H * 0.14, 18)
    # تپه
    c.poly([(0, H), (W * 0.30, H * 0.70), (W * 0.70, H * 0.72), (W, H * 0.94), (W, H)], fill=PAL['leaf_lite'])
    c.poly([(0, H), (W * 0.32, H * 0.78), (W * 0.66, H * 0.80), (W, H * 0.94), (W, H)],
           fill=mix(PAL['leaf'], '#ffffff', .25))
    # دیوار و برج‌ها
    c.rect([W * 0.18, H * 0.50, W * 0.82, H * 0.78], fill=wall)
    crenels(c, W * 0.18, W * 0.82, H * 0.45, H * 0.06, W * 0.045, wall, filled=True)
    towers = [(0.20, 0.34), (0.74, 0.32)]
    if not brick:
        towers = [(0.18, 0.32), (0.50, 0.40), (0.82, 0.32)]
    for tx, ty in towers:
        tw = W * 0.13
        c.rect([W * tx - tw / 2, H * ty, W * tx + tw / 2, H * 0.78], fill=wall_lite)
        crenels(c, W * tx - tw / 2, W * tx + tw / 2, H * (ty - 0.055), H * 0.06, tw / 4, wall_lite,
                filled=True)
        if 'ruins' in f and tx > 0.5:
            c.rect([W * tx - tw / 2, H * (ty - 0.03), W * tx + tw * 0.1, H * ty], fill=PAL['sky_soft'])
        c.rect([W * tx - tw * 0.1, H * (ty + 0.06), W * tx + tw * 0.1, H * (ty + 0.16)],
               fill=PAL['ink'])
    # دروازه
    c.rrect([W * 0.44, H * 0.58, W * 0.56, H * 0.78], radius=8, fill=PAL['slate'])
    c.pie([W * 0.44, H * 0.53, W * 0.56, H * 0.72], 180, 360, fill=PAL['slate'])
    brick_lines(c, W * 0.20, H * 0.52, W * 0.80, H * 0.76, 6, 8, mix(wall, '#000000', .16), width=1)
    # پرچم
    c.line([(W * 0.50, H * 0.34), (W * 0.50, H * 0.22)], fill=PAL['ink'], width=3)
    c.poly([(W * 0.50, H * 0.22), (W * 0.64, H * 0.26), (W * 0.50, H * 0.30)], fill=PAL['rose'])
    oak(c, W * 0.08, H * 0.96, 44)
    oak(c, W * 0.94, H * 0.98, 40, canopy=PAL['pine'])


def scene_cave(c, W, H, f):
    sky(c, W, H, mix(PAL['ink'], '#ffffff', .82))
    c.rect([0, 0, W, H], fill=mix(PAL['ink'], '#ffffff', .86))
    # دهانهٔ غار
    c.pie([W * 0.10, H * 0.24, W * 0.90, H * 1.10], 180, 360, fill=mix(PAL['ink'], '#ffffff', .45))
    c.rect([W * 0.10, H * 0.66, W * 0.90, H], fill=mix(PAL['ink'], '#ffffff', .45))
    c.pie([W * 0.18, H * 0.32, W * 0.82, H * 1.02], 180, 360, fill=mix(PAL['ink'], '#ffffff', .16))
    c.rect([W * 0.18, H * 0.66, W * 0.82, H], fill=mix(PAL['ink'], '#ffffff', .16))
    c.pie([W * 0.26, H * 0.42, W * 0.74, H * 0.98], 180, 360, fill=PAL['ink'])
    c.rect([W * 0.26, H * 0.70, W * 0.74, H], fill=PAL['ink'])
    # قندیل‌ها
    for i, (x, s) in enumerate(((0.32, 22), (0.40, 34), (0.50, 26), (0.60, 30), (0.68, 20))):
        c.poly([(W * x - 6, H * 0.52), (W * x + 6, H * 0.52), (W * x, H * 0.52 + s)],
               fill=mix(PAL['tile_lite'], '#ffffff', .35))
    for i, (x, s) in enumerate(((0.30, 16), (0.44, 22), (0.58, 18), (0.70, 14))):
        c.poly([(W * x - 7, H * 0.86), (W * x + 7, H * 0.86), (W * x, H * 0.86 - s)],
               fill=mix(PAL['tile'], '#ffffff', .45))
    # آب
    c.rect([W * 0.20, H * 0.80, W * 0.80, H * 0.94], fill=mix(PAL['water'], '#ffffff', .35))
    water_lines(c, W * 0.22, H * 0.81, W * 0.78, H * 0.92, '#ffffff', rows=3)
    if 'boat' in f:
        draw_motif(c, 'boat', W * 0.50, H * 0.84, 22, PAL['ochre'])
        c.circle(W * 0.46, H * 0.80, 5, fill=PAL['ink'])
        c.circle(W * 0.56, H * 0.80, 5, fill=PAL['ink'])
    for x, y, s in ((0.24, 0.46, 3), (0.78, 0.50, 3), (0.30, 0.60, 2), (0.72, 0.38, 2)):
        c.circle(W * x, H * y, s, fill=mix(PAL['tile_lite'], '#ffffff', .2))
    # نور ورودی
    c.poly([(W * 0.16, 0), (W * 0.30, 0), (W * 0.46, H * 0.62), (W * 0.28, H * 0.62)],
           fill=mix(PAL['sky'], '#ffffff', .55))


def scene_forest(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.60, line=mix(PAL['sky'], '#ffffff', .3))
    sun(c, W * 0.18, H * 0.13, 16)
    clouds(c, W, H, [(0.40, 0.10, 8), (0.72, 0.16, 7)])
    range_back(c, W, H * 0.58, mix(PAL['sky_deep'], '#ffffff', .3))
    # ردیف درختان دور
    for i in range(7):
        x = W * (0.04 + i * 0.16)
        pine(c, x, H * 0.70, 62, mix(PAL['pine'], '#ffffff', .3))
    # ردیف درختان نزدیک
    for i in range(5):
        x = W * (0.10 + i * 0.21)
        oak(c, x, H * (0.92 + (i % 2) * 0.03), 104, PAL['leaf'] if i % 2 else PAL['pine'])
    # جاده
    c.poly([(W * 0.40, H * 0.72), (W * 0.62, H * 0.72), (W * 0.82, H), (W * 0.20, H)],
           fill=mix(PAL['stone'], '#ffffff', .45))
    c.dashed((W * 0.51, H * 0.76), (W * 0.50, H), '#ffffff', width=4, dash=14, gap=12)
    ground(c, W, H, H * 0.94, mix(PAL['leaf'], '#ffffff', .5))
    if 'deer' in f:
        draw_motif(c, 'gazelle', W * 0.28, H * 0.86, 26, PAL['ochre'])
    if 'waterfall' in f:
        c.poly([(W * 0.72, H * 0.52), (W * 0.80, H * 0.52), (W * 0.80, H * 0.72), (W * 0.72, H * 0.72)],
               fill=mix(PAL['water_pale'], '#ffffff', .3))
        for i in range(2):
            c.line([(W * (0.74 + i * 0.03), H * 0.54), (W * (0.74 + i * 0.03), H * 0.70)],
                   fill=PAL['water_lite'], width=3)


def scene_valley(c, W, H, f):
    sky(c, W, H, '#f6ecff', horizon=H * 0.56, line=mix(PAL['plum'], '#ffffff', .82))
    for x, y, s in ((0.16, 0.12, 7), (0.34, 0.20, 5), (0.62, 0.10, 6), (0.82, 0.22, 5), (0.48, 0.06, 4)):
        star4(c, W * x, H * y, s, PAL['gold'])
    c.circle(W * 0.86, H * 0.10, 12, fill=mix(PAL['gold'], '#ffffff', .2))
    # ستون‌های سنگی لایه‌لایه
    pillars = [(0.10, 0.42, 0.20), (0.24, 0.34, 0.26), (0.40, 0.46, 0.22), (0.56, 0.30, 0.28),
               (0.72, 0.44, 0.20), (0.86, 0.36, 0.24)]
    for x0, top, half in pillars:
        x = W * x0
        w = W * half / 2
        c.rrect([x - w, H * top, x + w, H * 0.82], radius=w * 0.35, fill=PAL['sand_deep'])
        c.rrect([x - w * 0.72, H * (top - 0.02), x + w * 0.72, H * 0.82], radius=w * 0.3,
                fill=PAL['sand'])
        for i in range(4):
            y = H * (top + 0.04 + i * 0.07)
            c.line([(x - w * 0.9, y), (x + w * 0.9, y)], fill=mix(PAL['ochre'], '#ffffff', .45),
                   width=1.5)
    ground(c, W, H, H * 0.82, PAL['sand'])
    c.rect([0, H * 0.90, W, H], fill=mix(PAL['sand_deep'], '#ffffff', .2))
    for i in range(5):
        c.ellipse([W * (0.05 + i * 0.2), H * 0.92, W * (0.15 + i * 0.2), H * 0.96],
                  fill=mix(PAL['ochre'], '#ffffff', .55))
    if 'sun' in f:
        sun(c, W * 0.14, H * 0.14, 16)


def scene_desert(c, W, H, f):
    kaluts = 'kaluts' in f
    sky(c, W, H, PAL['desert_sky'], horizon=H * 0.66, line=mix(PAL['orange'], '#ffffff', .8))
    sun(c, W * 0.78, H * 0.16, 22)
    if 'stars' in f:
        for x, y, s in ((0.14, 0.12, 6), (0.30, 0.22, 5), (0.52, 0.08, 4), (0.66, 0.24, 5)):
            star4(c, W * x, H * y, s, PAL['gold'])
    ground(c, W, H, H * 0.66, PAL['sand'])
    if kaluts:
        blocks = [(0.06, 0.34, 0.20), (0.30, 0.44, 0.16), (0.50, 0.28, 0.22), (0.78, 0.40, 0.18)]
        for x0, top, w in blocks:
            x = W * x0
            ww = W * w
            c.poly([(x, H * top), (x + ww, H * (top + 0.05)), (x + ww, H * 0.70), (x, H * 0.68)],
                   fill=PAL['ochre'])
            for i in range(4):
                y = H * (top + 0.06 + i * 0.05)
                c.line([(x + 2, y), (x + ww - 2, y - 4)], fill=mix(PAL['sand'], '#ffffff', .4), width=2)
            c.poly([(x, H * 0.68), (x + ww, H * 0.70), (x + ww * 1.1, H * 0.76), (x - ww * 0.06, H * 0.75)],
                   fill=mix(PAL['sand_deep'], '#000000', .08))
    else:
        c.pie([-W * 0.1, H * 0.49, W * 0.63, H * 0.97], 180, 360, fill=PAL['sand_deep'])
        c.pie([W * 0.38, H * 0.55, W * 1.12, H * 1.03], 180, 360,
              fill=mix(PAL['sand_deep'], '#ffffff', .2))
    for i in range(4):
        x = W * (0.06 + i * 0.24)
        y = H * (0.90 + (i % 2) * 0.03)
        c.ellipse([x, y, x + W * 0.13, y + H * 0.05], fill=PAL['sand_deep'])
    for i in range(3):
        x = W * (0.28 + i * 0.22)
        c.ellipse([x, H * 0.965, x + W * 0.12, H * 1.0], fill=mix(PAL['sand_deep'], '#ffffff', .3))
    clouds(c, W, H, [(0.24, 0.14, 8), (0.62, 0.10, 7)])
    if 'caravan' in f:
        for i in range(3):
            draw_motif(c, 'gazelle', W * (0.30 + i * 0.18), H * 0.80, 18, PAL['sand_deep'])


def scene_village(c, W, H, f):
    stepped = 'stepped' in f
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.70, line=mix(PAL['sky'], '#ffffff', .3))
    sun(c, W * 0.82, H * 0.13, 18)
    peak(c, W * 0.24, H * 0.72, W * 0.30, H * 0.34, mix(PAL['slate'], '#ffffff', .25))
    c.poly([(0, H), (W * 0.22, H * 0.56), (W, H * 0.42), (W, H)], fill=PAL['leaf_lite'])
    c.poly([(0, H), (W * 0.26, H * 0.66), (W, H * 0.54), (W, H)], fill=mix(PAL['leaf'], '#ffffff', .2))
    rooftop = 'rooftop' in f
    tiers = [(0.16, 0.56), (0.34, 0.48), (0.52, 0.40), (0.70, 0.32)] if stepped else [
        (0.14, 0.60), (0.36, 0.52), (0.58, 0.62), (0.78, 0.50)]
    for i, (x, y) in enumerate(tiers):
        w = W * 0.20
        c.rect([W * x - w / 2, H * y, W * x + w / 2, H * (y + 0.12)], fill='#fdf6e6')
        if rooftop:
            # معماری بام‌به‌گذر: بام تختِ خانهٔ پایین، حیاط/گذر خانهٔ بالاست
            c.rect([W * x - w * 0.60, H * (y - 0.03), W * x + w * 0.60, H * (y + 0.005)],
                   fill=mix(PAL['stone'], '#ffffff', .35))
            c.rect([W * x - w * 0.60, H * (y - 0.03), W * x + w * 0.60, H * (y - 0.015)], fill=PAL['brick'])
            c.rect([W * x + w * 0.34, H * (y - 0.075), W * x + w * 0.56, H * (y - 0.03)],
                   fill=mix(PAL['stone'], '#ffffff', .2))
            c.line([(W * x - w * 0.60, H * (y - 0.035)), (W * (x + 0.02), H * (y - 0.10))],
                   fill=PAL['stone_deep'], width=3)
        else:
            c.poly([(W * x - w * 0.56, H * y), (W * x, H * (y - 0.07)), (W * x + w * 0.56, H * y)],
                   fill=PAL['brick'] if i % 2 else PAL['rose'])
        c.rect([W * x - w * 0.14, H * (y + 0.05), W * x + w * 0.14, H * (y + 0.12)], fill=PAL['brick'])
        c.rect([W * x - w * 0.42, H * (y + 0.02), W * x - w * 0.24, H * (y + 0.06)], fill=PAL['tile_lite'])
        if stepped and i < len(tiers) - 1:
            c.line([(W * x + w * 0.3, H * (y + 0.13)), (W * (x + 0.16), H * (y + 0.22))],
                   fill=PAL['stone_deep'], width=3)
    if rooftop:
        for i in range(3):
            c.rect([W * (0.24 + i * 0.18), H * (0.48 + i * 0.09), W * (0.34 + i * 0.18), H * (0.50 + i * 0.09)],
                   fill=PAL['rose'] if i % 2 else PAL['gold'])
    # پله‌ها
    for i in range(6):
        c.line([(W * (0.06 + i * 0.055), H * (0.94 - i * 0.035)),
                (W * (0.24 + i * 0.055), H * (0.94 - i * 0.035))], fill=PAL['stone'], width=4)
    oak(c, W * 0.06, H * 1.0, 52)
    oak(c, W * 0.94, H * 0.96, 46, canopy=PAL['pine'])
    for x, y, s in ((0.16, 0.12, 9), (0.26, 0.15, 7), (0.78, 0.10, 8)):
        c.circle(W * x, H * y, s, fill='#ffffff')
        c.circle(W * x + s, H * y - s * 0.4, s * 0.8, fill='#ffffff')
        c.circle(W * x - s, H * y + s * 0.2, s * 0.7, fill='#ffffff')


def scene_garden(c, W, H, f):
    fin = 'fin' in f
    sky(c, W, H, PAL['garden_sky'], horizon=H * 0.30, line=mix(PAL['leaf_pale'], '#ffffff', .45))
    clouds(c, W, H, [(0.06, 0.06, 6), (0.94, 0.08, 6)])

    # دیوار پشتی باغ با طاق‌های منظم
    c.rect([0, H * 0.18, W, H * 0.34], fill=mix(PAL['stone'], '#ffffff', .45))
    c.rect([0, H * 0.18, W, H * 0.21], fill=PAL['brick'])
    c.rect([0, H * 0.32, W, H * 0.34], fill=mix(PAL['brick'], '#ffffff', .35))
    for i in range(7):
        ax = W * (0.03 + i * 0.14)
        c.pie([ax, H * 0.21, ax + W * 0.07, H * 0.31], 180, 360, fill=PAL['tile_lite'])
        c.rect([ax, H * 0.26, ax + W * 0.07, H * 0.34], fill=PAL['tile_lite'])

    # کوشک مرکزی روی سکو
    c.rect([W * 0.30, H * 0.44, W * 0.70, H * 0.52], fill=PAL['brick'])
    c.rect([W * 0.34, H * 0.52, W * 0.66, H * 0.56], fill=mix(PAL['stone'], '#ffffff', .2))
    c.rect([W * 0.34, H * 0.28, W * 0.66, H * 0.44], fill='#fdf6e6', outline=PAL['stone_deep'], width=1.2)
    for i in range(4):
        cx = W * (0.36 + i * 0.095)
        c.rect([cx, H * 0.28, cx + W * 0.022, H * 0.44], fill=PAL['tile_lite'],
               outline=mix(PAL['tile'], '#ffffff', .4), width=1)
        c.rrect([cx - 4, H * 0.28, cx + W * 0.022 + 4, H * 0.30], radius=3, fill=PAL['sand'])
    c.pie([W * 0.33, H * 0.22, W * 0.67, H * 0.42], 180, 360, fill=PAL['tile'])
    c.rect([W * 0.33, H * 0.30, W * 0.67, H * 0.34], fill=PAL['tile'])
    c.line([(W * 0.50, H * 0.13), (W * 0.50, H * 0.22)], fill=PAL['gold'], width=2.5)
    c.poly([(W * 0.50, H * 0.13), (W * 0.545, H * 0.155), (W * 0.50, H * 0.18)], fill=PAL['gold'])
    # کاشی‌های رنگی نوار کوشک
    for i in range(8):
        kx = W * (0.35 + i * 0.042)
        c.poly([(kx, H * 0.355), (kx + 5, H * 0.375), (kx, H * 0.395), (kx - 5, H * 0.375)],
               fill=PAL['tile_lite'])

    # محور آب: جوی مرکزی + دو حوض هشت‌ضلعی + فواره
    c.rect([W * 0.455, H * 0.56, W * 0.545, H], fill=mix(PAL['stone'], '#ffffff', .35))
    c.rect([W * 0.465, H * 0.56, W * 0.535, H], fill=PAL['water_lite'])
    for i in range(7):
        y = H * (0.60 + i * 0.055)
        c.line([(W * 0.465, y), (W * 0.535, y)], fill='#ffffff', width=1.6)
    for cy, r in ((0.64, 34), (0.86, 40)):
        pts = [(W * 0.50 + r * math.cos(math.radians(45 * i + 22.5)),
                H * cy + r * 0.62 * math.sin(math.radians(45 * i + 22.5))) for i in range(8)]
        c.poly(pts, fill=mix(PAL['stone'], '#ffffff', .25))
        pts2 = [(W * 0.50 + (r - 7) * math.cos(math.radians(45 * i + 22.5)),
                 H * cy + (r - 7) * 0.62 * math.sin(math.radians(45 * i + 22.5))) for i in range(8)]
        c.poly(pts2, fill=PAL['water_lite'])
        for dx in (-1, 0, 1):
            c.arc([W * 0.50 + dx * 13 - 7, H * cy - 26, W * 0.50 + dx * 13 + 7, H * cy - 4],
                  200, 340, fill='#ffffff', width=2)

    # سروها (ردیف منظم دو طرف) و گل‌ها
    h_scale = 1.0 if not fin else 1.15
    for i in range(3):
        y = H * (0.62 + i * 0.11)
        for x in (0.145 + i * 0.055, 0.855 - i * 0.055):
            draw_motif(c, 'cypress', W * x, y, (33 + i * 3) * h_scale, PAL['pine'])
    for row in range(3):
        for i in range(4):
            x = W * (0.07 + i * 0.05)
            y = H * (0.86 + row * 0.045)
            draw_motif(c, 'flower', x, y, 6, PAL['rose'] if (i + row) % 2 else PAL['plum'])
            draw_motif(c, 'flower', W - x, y, 6, PAL['gold'] if (i + row) % 2 else PAL['rose'])
    ground(c, W, H, H * 0.98, mix(PAL['leaf_lite'], '#ffffff', .5))
    c.rect([0, H * 0.96, W, H], fill=mix(PAL['stone'], '#ffffff', .45))

    if fin:
        # حمام/بناهای جانبی باغ فین
        c.rect([W * 0.72, H * 0.30, W * 0.90, H * 0.44], fill='#fdf6e6', outline=PAL['stone_deep'], width=1.2)
        c.pie([W * 0.74, H * 0.22, W * 0.88, H * 0.38], 180, 360, fill=mix(PAL['tile'], '#000000', .18))
        c.rect([W * 0.74, H * 0.30, W * 0.88, H * 0.36], fill=PAL['tile'])
        for i in range(3):
            c.rrect([W * (0.755 + i * 0.045), H * 0.36, W * (0.775 + i * 0.045), H * 0.44], radius=3,
                    fill=PAL['tile_lite'])
    else:
        # باغ گلشن طبس: نخل‌های واحه‌ای در دو گوشه
        for x, y, s in ((0.10, 0.94, 34), (0.90, 0.96, 30)):
            draw_motif(c, 'palm', W * x, H * y, s, PAL['pine'])


def scene_monument(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.78, line=mix(PAL['sky'], '#ffffff', .3))
    # ایوان و گنبد
    c.rect([W * 0.26, H * 0.42, W * 0.74, H * 0.82], fill='#fdf6e6')
    c.rect([W * 0.30, H * 0.46, W * 0.70, H * 0.82], fill=mix(PAL['tile_lite'], '#ffffff', .35))
    c.pie([W * 0.30, H * 0.22, W * 0.70, H * 0.62], 180, 360, fill=PAL['tile'])
    c.rect([W * 0.30, H * 0.42, W * 0.70, H * 0.50], fill=PAL['tile'])
    c.line([(W * 0.50, H * 0.14), (W * 0.50, H * 0.22)], fill=PAL['gold'], width=2)
    c.circle(W * 0.50, H * 0.12, 4, fill=PAL['gold'])
    # گلدسته‌ها
    for x in (0.21, 0.79):
        draw_motif(c, 'minaret', W * x, H * 0.46, 30, PAL['tile'])
    # طاق‌نمای ورودی
    c.rrect([W * 0.42, H * 0.58, W * 0.58, H * 0.82], radius=6, fill=PAL['ink'])
    c.pie([W * 0.42, H * 0.50, W * 0.58, H * 0.74], 180, 360, fill=PAL['ink'])
    c.rrect([W * 0.33, H * 0.70, W * 0.39, H * 0.82], radius=3, fill=PAL['tile'])
    c.rrect([W * 0.61, H * 0.70, W * 0.67, H * 0.82], radius=3, fill=PAL['tile'])
    # نوار کاشی
    for i in range(9):
        x = W * (0.28 + i * 0.055)
        c.poly([(x, H * 0.34), (x + 6, H * 0.365), (x, H * 0.39), (x - 6, H * 0.365)], fill=PAL['tile_lite'])
    ground(c, W, H, H * 0.82, mix(PAL['stone'], '#ffffff', .35))
    c.rect([0, H * 0.94, W, H], fill=mix(PAL['stone'], '#ffffff', .5))
    for i in range(4):
        c.rect([W * (0.08 + i * 0.24), H * 0.86, W * (0.14 + i * 0.24), H * 0.94], fill=PAL['stone_deep'])


def scene_columns(c, W, H, f):
    sky(c, W, H, PAL['stone_sky'], horizon=H * 0.72, line=mix(PAL['sand'], '#ffffff', .5))
    sun(c, W * 0.16, H * 0.14, 16)
    # سکوی پلکانی
    c.rect([W * 0.06, H * 0.72, W * 0.94, H * 0.86], fill=PAL['stone'])
    c.rect([W * 0.02, H * 0.86, W * 0.98, H], fill=PAL['stone_deep'])
    for i in range(5):
        c.line([(W * (0.04 + i * 0.02), H * (0.76 + i * 0.03)), (W * (0.96 - i * 0.02), H * (0.76 + i * 0.03))],
               fill=mix(PAL['stone_deep'], '#ffffff', .3), width=2)
    # ستون‌ها
    for i, x in enumerate((0.26, 0.46, 0.66)):
        draw_motif(c, 'column', W * x, H * 0.62, 46, PAL['stone'])
        c.rect([W * x - 9, H * 0.24, W * x + 9, H * 0.40], fill=PAL['stone'])
        for k in range(4):
            c.line([(W * x - 9, H * (0.27 + k * 0.035)), (W * x + 9, H * (0.27 + k * 0.035))],
                   fill=mix(PAL['stone_deep'], '#ffffff', .35), width=1.5)
    # بال دوبال
    c.circle(W * 0.50, H * 0.10, 8, fill=PAL['stone_deep'])
    c.pie([W * 0.455, H * 0.095, W * 0.545, H * 0.165], 180, 360, fill=PAL['stone_deep'])
    # نقش‌برجستهٔ پلکانی
    for i in range(6):
        x = W * (0.28 + i * 0.075)
        c.poly([(x, H * 0.70), (x + 12, H * 0.64), (x + 20, H * 0.70)], fill=PAL['stone_deep'])
        c.circle(x + 10, H * 0.615, 4, fill=PAL['stone_deep'])
    # گل نیلوفر سنگی
    draw_motif(c, 'flower', W * 0.12, H * 0.62, 12, PAL['ochre'])


def scene_square(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.36, line=mix(PAL['sky'], '#ffffff', .3))
    sun(c, W * 0.50, H * 0.10, 15)

    # رواق‌های دوطبقه در دو سوی میدان
    for side, (x0, x1) in enumerate(((0.00, 0.20), (0.80, 1.00))):
        c.rect([W * x0, H * 0.20, W * x1, H * 0.62], fill='#fdf6e6')
        c.rect([W * x0, H * 0.18, W * x1, H * 0.22], fill=PAL['brick'])
        c.rect([W * x0, H * 0.40, W * x1, H * 0.43], fill=PAL['brick'])
        for row in range(2):
            for i in range(3):
                ax = W * (x0 + 0.035 + i * 0.055)
                ay = H * (0.25 if row == 0 else 0.47)
                c.rrect([ax, ay, ax + W * 0.032, ay + H * 0.10], radius=4, fill=PAL['tile'])
                c.pie([ax, ay - H * 0.06, ax + W * 0.032, ay + H * 0.06], 180, 360, fill=PAL['tile'])
        c.rect([W * x0, H * 0.62, W * x1, H * 0.66], fill=mix(PAL['stone'], '#ffffff', .3))

    # مسجد شاه: گنبد بزرگ و دو گلدسته (سمت چپ میدان)
    c.rect([W * 0.05, H * 0.44, W * 0.24, H * 0.66], fill='#fdf6e6', outline=PAL['stone_deep'], width=1.2)
    draw_motif(c, 'dome', W * 0.14, H * 0.32, 44, PAL['tile'])
    for x in (0.045, 0.235):
        draw_motif(c, 'minaret', W * x, H * 0.30, 30, PAL['tile'])
    c.rrect([W * 0.085, H * 0.50, W * 0.195, H * 0.66], radius=5, fill=PAL['tile'])

    # عالی‌قاپو: سردر بلند با ایوان چوبی (سمت راست میدان)
    c.rect([W * 0.76, H * 0.26, W * 0.90, H * 0.66], fill='#fdf6e6', outline=PAL['stone_deep'], width=1.2)
    c.rect([W * 0.76, H * 0.24, W * 0.90, H * 0.28], fill=PAL['brick'])
    c.rrect([W * 0.785, H * 0.40, W * 0.875, H * 0.50], radius=4, fill=PAL['sand'])
    c.rect([W * 0.78, H * 0.50, W * 0.88, H * 0.54], fill=PAL['brick'])
    for i in range(3):
        c.line([(W * 0.80 + i * 0.03, H * 0.40), (W * 0.80 + i * 0.03, H * 0.50)],
               fill=mix(PAL['brick'], '#000000', .2), width=1.5)
    c.rrect([W * 0.80, H * 0.56, W * 0.86, H * 0.66], radius=4, fill=PAL['ink'])

    # کف میدان با سنگ‌فرش منظم
    c.rect([0, H * 0.66, W, H], fill=mix(PAL['stone'], '#ffffff', .5))
    for i in range(7):
        c.line([(W * (0.06 + i * 0.145), H * 0.66), (W * (0.30 + i * 0.12), H)],
               fill=mix(PAL['stone_deep'], '#ffffff', .5), width=1.2)
    # حوض مرکزی با فواره
    c.ellipse([W * 0.33, H * 0.70, W * 0.67, H * 0.88], fill=mix(PAL['stone'], '#ffffff', .3))
    c.ellipse([W * 0.345, H * 0.715, W * 0.655, H * 0.865], fill=PAL['water_pale'])
    c.ellipse([W * 0.37, H * 0.73, W * 0.63, H * 0.85], fill=PAL['water_lite'])
    c.ellipse([W * 0.42, H * 0.755, W * 0.58, H * 0.825], fill=PAL['water'])
    for dx in (-1, 0, 1):
        c.arc([W * 0.50 + dx * 16 - 8, H * 0.69, W * 0.50 + dx * 16 + 8, H * 0.77], 200, 340,
              fill=PAL['water_lite'], width=2)
        c.line([(W * 0.50 + dx * 16, H * 0.755), (W * 0.50 + dx * 16, H * 0.79)],
               fill=mix(PAL['water'], '#ffffff', .3), width=2)
    # درختان و چراغ‌های میدان
    for i in range(3):
        for x in (0.24 + i * 0.06, 0.70 - i * 0.06):
            c.rect([W * x - 3, H * 0.88, W * x + 3, H * 0.98], fill=PAL['brick'])
            c.circle(W * x, H * 0.84, 9, fill=PAL['pine'])


def scene_park(c, W, H, f):
    sky(c, W, H, PAL['sky_soft'], horizon=H * 0.66, line=mix(PAL['sky'], '#ffffff', .3))
    if 'sunset' in f:
        c.pie([W * 0.30, H * 0.42, W * 0.70, H * 0.82], 180, 360, fill=PAL['orange'])
        for i in range(7):
            a = math.radians(180 + i * 30)
            c.line([(W * 0.50 + math.cos(a) * W * 0.26, H * 0.62 + math.sin(a) * W * 0.26),
                    (W * 0.50 + math.cos(a) * W * 0.32, H * 0.62 + math.sin(a) * W * 0.32)],
                   fill=mix(PAL['orange'], '#ffffff', .35), width=3)
    else:
        sun(c, W * 0.80, H * 0.16, 18)
    if 'gazelle' in f:
        range_back(c, W, H * 0.62, mix(PAL['sky_deep'], '#ffffff', .3))
        peak(c, W * 0.30, H * 0.64, W * 0.28, H * 0.26, mix(PAL['slate'], '#ffffff', .2))
    ground(c, W, H, H * 0.62, mix(PAL['leaf_pale'], '#ffffff', .3))
    c.rect([0, H * 0.80, W, H], fill=mix(PAL['leaf'], '#ffffff', .35))
    if 'city_lights' in f:
        for i in range(9):
            c.rect([W * (0.06 + i * 0.10), H * (0.70 + (i % 3) * 0.03), W * (0.10 + i * 0.10), H * 0.80],
                   fill=mix(PAL['ink'], '#ffffff', .55))
            c.circle(W * (0.08 + i * 0.10), H * (0.73 + (i % 3) * 0.03), 2.5, fill=PAL['gold'])
    if 'gazelle' in f:
        for i in range(3):
            draw_motif(c, 'gazelle', W * (0.26 + i * 0.20), H * (0.86 + (i % 2) * 0.04), 22,
                       PAL['sand_deep'])
    # نردهٔ منظره + درختان
    for i in range(11):
        c.line([(W * (0.04 + i * 0.092), H * 0.90), (W * (0.04 + i * 0.092), H * 0.99)],
               fill=PAL['brick'], width=3)
    c.line([(0, H * 0.92), (W, H * 0.92)], fill=PAL['brick'], width=3)
    oak(c, W * 0.10, H * 0.92, 60, PAL['pine'])
    oak(c, W * 0.88, H * 0.94, 54)
    if 'tent' in f:
        draw_motif(c, 'tent', W * 0.50, H * 0.86, 20, PAL['orange'])


SCENES = {
    'waterfall': scene_waterfall,
    'lake': scene_lake,
    'wetland': scene_wetland,
    'city': scene_city,
    'mountain': scene_mountain,
    'gorge': scene_gorge,
    'castle': scene_castle,
    'cave': scene_cave,
    'forest': scene_forest,
    'valley': scene_valley,
    'desert': scene_desert,
    'village': scene_village,
    'garden': scene_garden,
    'monument': scene_monument,
    'columns': scene_columns,
    'square': scene_square,
    'park': scene_park,
}


def draw_scene(c, name, W, H, flags=()):
    """کشیدن صحنه با نام؛ ناشناخته → کوهستان."""
    fn = SCENES.get(name) or SCENES['mountain']
    fn(c, W, H, set(flags))
