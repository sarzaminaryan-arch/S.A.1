#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت کارت‌های تصویری topic-cards از PNGهای خام.

برش مرکزی ۴:۳ ← تغییر اندازه به ۷۲۰×۵۴۰ ← واترمارک متنی `sarzaminaryan.ir`
گوشهٔ پایین‌چپ ← خروجی WebP کیفیت ۸۲ (قرارداد کارت‌های ARTICLE-STANDARD 1.2).

کاربرد:
    python3 tools/images/make_topic_cards.py [نام …]
    # بدون آرگومان: همهٔ PNGهای --src
    python3 tools/images/make_topic_cards.py --src ../imgsrc --out ../../wp-content/themes/sarzaminaryan-child/assets/img/guides/topic-cards
"""
import argparse
import os
import sys

from PIL import Image, ImageDraw, ImageFont

W, H = 720, 540
WATERMARK = 'sarzaminaryan.ir'
FONT_CANDIDATES = (
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
)


def watermark(img):
    draw = ImageDraw.Draw(img, 'RGBA')
    font = None
    for path in FONT_CANDIDATES:
        if os.path.isfile(path):
            font = ImageFont.truetype(path, 15)
            break
    if font is None:
        font = ImageFont.load_default()
    tw, th = draw.textbbox((0, 0), WATERMARK, font=font)[2:]
    pad_x, pad_y = 9, 6
    x0, y0 = 12, H - th - pad_y * 2 - 12
    x1, y1 = x0 + tw + pad_x * 2, y0 + th + pad_y * 2
    draw.rounded_rectangle([x0, y0, x1, y1], radius=8, fill=(255, 255, 255, 150))
    draw.text((x0 + pad_x, y0 + pad_y - 1), WATERMARK, fill=(10, 10, 10, 235), font=font)
    return img


def crop_43(im):
    w, h = im.size
    tw, th = (h * 4) // 3, h
    if tw > w:
        tw, th = w, (w * 3) // 4
    left, top = (w - tw) // 2, (h - th) // 2
    return im.crop((left, top, left + tw, top + th)).resize((W, H), Image.LANCZOS)


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    repo = os.path.abspath(os.path.join(here, '..', '..'))
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('names', nargs='*', help='نام فایل‌های PNG بدون پسوند')
    ap.add_argument('--src', default=os.path.join(repo, 'imgsrc'),
                    help='پوشهٔ PNGهای خام (پیش‌فرض: imgsrc/ ریشه)')
    ap.add_argument('--out', default=os.path.join(
        repo, 'wp-content/themes/sarzaminaryan-child/assets/img/guides/topic-cards'),
        help='پوشهٔ خروجی WebP')
    ap.add_argument('--quality', type=int, default=82)
    args = ap.parse_args()

    os.makedirs(args.out, exist_ok=True)
    names = args.names or sorted(f[:-4] for f in os.listdir(args.src) if f.endswith('.png'))
    if not names:
        print('هیچ PNGای در منبع نیست:', args.src, file=sys.stderr)
        return 1

    for name in names:
        src = os.path.join(args.src, name + '.png')
        if not os.path.isfile(src):
            print('MISSING', name)
            continue
        im = crop_43(Image.open(src).convert('RGB'))
        im = watermark(im)
        out = os.path.join(args.out, name + '.webp')
        im.save(out, 'WEBP', quality=args.quality, method=6)
        kb = os.path.getsize(out) / 1024
        print(f'{name}.webp  {kb:.0f} KB')
    return 0


if __name__ == '__main__':
    sys.exit(main())
