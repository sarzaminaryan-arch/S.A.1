#!/usr/bin/env python3
"""Generate a 1200x900 WordPress theme screenshot.png with a Persian mock layout (PIL + raqm)."""
import sys
from PIL import Image, ImageDraw, ImageFont

FONT_DIR = sys.argv[1]           # path containing Vazirmatn-Regular.woff2 / -Bold.woff2
OUT = sys.argv[2]                # output png
TITLE = sys.argv[3] if len(sys.argv) > 3 else 'سرزمین آریان'
SUB = sys.argv[4] if len(sys.argv) > 4 else 'دانشنامه‌ی سفر ایران'
BADGE = sys.argv[5] if len(sys.argv) > 5 else ''

W, H = 1200, 900
PRIMARY, DARK, ACCENT, SURFACE, TEXT, MUTED, BORDER = '#0e7490', '#155e75', '#c2410c', '#f6f8f9', '#1f2933', '#5b6770', '#dfe5e8'
reg = lambda s: ImageFont.truetype(f'{FONT_DIR}/Vazirmatn-Regular.woff2', s)
bold = lambda s: ImageFont.truetype(f'{FONT_DIR}/Vazirmatn-Bold.woff2', s)

im = Image.new('RGB', (W, H), 'white')
d = ImageDraw.Draw(im)
kw = dict(direction='rtl', language='fa')

# header
d.rectangle([0, 0, W, 86], fill='white')
d.line([0, 86, W, 86], fill=BORDER, width=2)
d.rounded_rectangle([W - 60 - 44, 21, W - 60, 65], radius=12, fill=PRIMARY)
d.text((W - 82, 43), 'س', font=bold(28), fill='white', anchor='mm', **kw)
d.text((W - 118, 32), TITLE, font=bold(24), fill=TEXT, anchor='rm', **kw)
d.text((W - 118, 62), SUB, font=reg(14), fill=MUTED, anchor='rm', **kw)
x = 700
for item in ['استان‌ها', 'شهرها', 'جاذبه‌ها', 'مسیرهای سفر', 'غذاها', 'سوغات']:
    d.text((x, 43), item, font=reg(17), fill=TEXT, anchor='rm', **kw)
    x -= int(d.textlength(item, font=reg(17), **kw)) + 36
d.rounded_rectangle([60, 22, 104, 66], radius=10, outline=BORDER, width=2)
d.ellipse([74, 34, 88, 48], outline=TEXT, width=2)
d.line([87, 47, 94, 54], fill=TEXT, width=2)

# hero
d.rectangle([0, 88, W, 380], fill=DARK)
for i in range(0, W, 40):
    d.line([i, 88, i + 40, 380], fill='#1a6b82', width=1)
d.text((W - 80, 170), 'ایران را استان به استان بشناسید', font=bold(40), fill='white', anchor='rm', **kw)
d.text((W - 80, 225), '۳۱ استان، صدها شهر و هزاران جاذبه — با اطلاعات دقیق، مسیر سفر، غذاها و سوغات', font=reg(18), fill='#d9f1f7', anchor='rm', **kw)
d.rounded_rectangle([W - 80 - 560, 270, W - 80, 326], radius=12, fill='white')
d.text((W - 100, 298), 'جست‌وجوی استان، شهر یا جاذبه…', font=reg(17), fill=MUTED, anchor='rm', **kw)
d.rounded_rectangle([W - 80 - 556, 276, W - 80 - 430, 320], radius=10, fill=ACCENT)
d.text((W - 80 - 493, 298), 'جست‌وجو', font=bold(16), fill='white', anchor='mm', **kw)

# section title + cards
d.text((W - 60, 430), 'استان‌های پربازدید', font=bold(26), fill=TEXT, anchor='rm', **kw)
d.line([60, 455, W - 60, 455], fill=BORDER, width=1)
cards = [('اصفهان', 'نصف جهان'), ('فارس', 'شیراز و تخت جمشید'), ('گیلان', 'سبزِ خزر'), ('یزد', 'شهر بادگیرها')]
cw, gap, top = 255, 20, 480
x = W - 60 - cw
for name, sub in cards:
    d.rounded_rectangle([x, top, x + cw, top + 250], radius=12, fill=SURFACE, outline=BORDER)
    d.rounded_rectangle([x, top, x + cw, top + 140], radius=12, fill='#8ec5d3')
    d.polygon([(x + 20, top + 130), (x + 90, top + 60), (x + 140, top + 110), (x + 180, top + 80), (x + 240, top + 130)], fill='#5fa9bb')
    d.text((x + cw - 16, top + 172), name, font=bold(20), fill=TEXT, anchor='rm', **kw)
    d.text((x + cw - 16, top + 204), sub, font=reg(14), fill=MUTED, anchor='rm', **kw)
    d.rounded_rectangle([x + 16, top + 220, x + 92, top + 240], radius=10, fill='#d9f1f7')
    d.text((x + 54, top + 230), 'استان', font=reg(12), fill=DARK, anchor='mm', **kw)
    x -= cw + gap

# footer
d.rectangle([0, 780, W, H], fill=SURFACE)
d.line([0, 780, W, 780], fill=BORDER, width=2)
d.text((W - 60, 815), TITLE, font=bold(18), fill=TEXT, anchor='rm', **kw)
d.text((W - 60, 848), '© ۱۴۰۵ سرزمین آریان — تمامی حقوق محفوظ است.', font=reg(13), fill=MUTED, anchor='rm', **kw)
d.text((60, 830), 'sarzaminaryan.ir', font=reg(15), fill=PRIMARY, anchor='lm')
if BADGE:
    d.rounded_rectangle([40, 100, 260, 140], radius=10, fill=ACCENT)
    d.text((150, 120), BADGE, font=bold(16), fill='white', anchor='mm', **kw)

im.save(OUT, optimize=True)
print('saved', OUT, im.size)
