#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
featured_counties_manifest.py — builds assets/featured/counties/<province>/manifest.json
(and README.md) from the article files + the WEBP files on disk.

ALT/caption/title come from BLOCK 2 (FEATURED IMAGE) of content/cities/<slug>.md so the
manifest always matches the article; bytes/sha1/dimensions are read from the real file
(ImageMagick `identify`).

Usage:
  python3 content-templates/tools/featured_counties_manifest.py --province tehran --built 2026-09-30
  python3 content-templates/tools/featured_counties_manifest.py --province bushehr --built 2026-09-30 --require-all
"""
import argparse
import datetime
import hashlib
import json
import os
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
COUNTIES = os.path.join(ROOT, 'wp-content', 'plugins', 'sa-province-importer-b01', 'data', 'counties.json')
CITY_DIR = os.path.join(ROOT, 'content', 'cities')
FEATURED_DIR = os.path.join(ROOT, 'assets', 'featured', 'counties')
MAX_BYTES = 100000
EXPECT_W, EXPECT_H = 1200, 675


FA_DIGITS = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')


def fa(text):
    """Latin digits -> Persian digits for user-facing Persian text (manifest numbers stay numeric)."""
    return str(text).translate(FA_DIGITS)

def field(block2, key):
    for line in block2.split('\n'):
        if line.startswith(key + ':'):
            return line.split(':', 1)[1].strip()
    return ''


def block2_of(md_path):
    text = open(md_path, encoding='utf-8').read()
    m = text.split('=== BLOCK 2: FEATURED IMAGE ===', 1)
    if len(m) < 2:
        sys.exit('BLOCK 2 marker not found in %s' % md_path)
    return m[1].split('=== BLOCK 3', 1)[0]


def image_size(path):
    out = subprocess.run(['identify', '-format', '%wx%h', path], capture_output=True, text=True)
    return out.stdout.strip() if out.returncode == 0 else ''


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--province', required=True)
    ap.add_argument('--built', default=datetime.date.today().isoformat())
    ap.add_argument('--jalali', default='', help='e.g. ۱۴۰۵/۰۷/۰۹ (only used in the note)')
    ap.add_argument('--label', default='', help='Persian province label used in README.md, e.g. استان تهران')
    ap.add_argument('--require-all', action='store_true', help='exit non-zero if an image is missing')
    args = ap.parse_args()

    data = json.load(open(COUNTIES, encoding='utf-8'))
    rows = [r for r in data['counties'] if r.get('province') == args.province]
    if not rows:
        sys.exit('no counties for %s in counties.json' % args.province)

    img_dir = os.path.join(FEATURED_DIR, args.province)
    os.makedirs(img_dir, exist_ok=True)
    images, missing, sizes = {}, [], []
    for r in rows:
        slug = r['slug']
        md = os.path.join(CITY_DIR, slug + '.md')
        img = os.path.join(img_dir, slug + '.webp')
        if not os.path.isfile(md):
            sys.exit('article missing: %s' % md)
        b2 = block2_of(md)
        alt = field(b2, 'alt')
        if not os.path.isfile(img):
            missing.append(slug)
            continue
        raw = open(img, 'rb').read()
        size = image_size(img)
        if size != '%dx%d' % (EXPECT_W, EXPECT_H):
            print('WARN %s: %s (expected %dx%d)' % (slug, size, EXPECT_W, EXPECT_H))
        if len(raw) >= MAX_BYTES:
            print('WARN %s: %d bytes (>= %d)' % (slug, len(raw), MAX_BYTES))
        sizes.append(len(raw))
        images[slug] = {
            'file': slug + '.webp',
            'title': field(b2, 'overlay_text_fa').replace('\\n', ' '),
            'alt': alt,
            'caption': field(b2, 'caption'),
            'bytes': len(raw),
            'sha1': hashlib.sha1(raw).hexdigest(),
            'description': alt,
        }

    note = '%s فایل webp، %s×%s (۱۶:۹)، هر فایل زیر ۱۰۰ کیلوبایت؛ ALT/زیرنویس/عنوان از بلوک ۲ مقاله‌ها' % (
        fa(len(images)), fa(EXPECT_W), fa(EXPECT_H))
    if sizes:
        note += '؛ حجم %s تا %s بایت' % (fa('{:,}'.format(min(sizes))), fa('{:,}'.format(max(sizes))))
    if missing:
        note += '؛ در انتظار ساخت: ' + '، '.join(missing)

    manifest = {
        'format': '1.0',
        'province': args.province,
        'built': args.built,
        'images_count': len(images),
        'note': note,
        'images': images,
    }
    out = os.path.join(img_dir, 'manifest.json')
    with open(out, 'w', encoding='utf-8') as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=1)
        fh.write('\n')

    lines = ['# تصاویر شاخص شهرستان‌های %s' % (args.label or args.province),
             '',
             '> ساخت: %s%s · %s' % (args.built, (' (' + args.jalali + ')') if args.jalali else '', note),
             '',
             '| # | نامک | عنوان | ALT | حجم (بایت) | ابعاد |',
             '|---|---|---|---|---|---|']
    for i, r in enumerate([x for x in rows if x['slug'] in images], 1):
        it = images[r['slug']]
        lines.append('| %s | `%s` | %s | %s | %s | %s×%s |' % (
            fa(i), r['slug'], it['title'], it['alt'], fa('{:,}'.format(it['bytes'])), fa(EXPECT_W), fa(EXPECT_H)))
    with open(os.path.join(img_dir, 'README.md'), 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(lines) + '\n')

    print('province=%s images=%d/%d missing=%d manifest=%s' % (
        args.province, len(images), len(rows), len(missing), out))
    if missing:
        print('missing:', ' '.join(missing))
    if args.require_all and missing:
        sys.exit(1)


if __name__ == '__main__':
    main()
