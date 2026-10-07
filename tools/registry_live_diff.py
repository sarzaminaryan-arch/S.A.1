#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""گزارشِ فقط‌خواندنیِ اختلافِ رجیستری جغرافیا با خروجی واقعی WXR.

ورودی:
  --registry  فایل `data/counties.php` (ردیف‌های `array( 'slug' => …, 'name' => …, 'province' => …, 'status' => … )`)
  --wxr       خروجی شهرستان‌ها (`s-aryan-shar.xml`)
  --wxr-provinces (اختیاری) خروجی استان‌ها، فقط برای شمارش لینک‌های `/city/`

خروجی: markdown (پیش‌فرض: stdout) و/یا JSON. هیچ متنی از مقاله چاپ نمی‌شود؛ فقط slug/عنوان/وضعیت.
این ابزار ۴۰۴، ایندکسِ Google، درستیِ رسمیِ نام‌ها یا شمارشِ قطعی تقسیمات کشوری را تعیین نمی‌کند.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import unicodedata
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict

NS = '{http://wordpress.org/export/1.2/}'
CONTENT = '{http://purl.org/rss/1.0/modules/content/}encoded'
REGISTRY_ROW = re.compile(
    r"array\(\s*'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']+)'\s*,\s*"
    r"'province'\s*=>\s*'([^']+)'\s*,\s*'status'\s*=>\s*'([^']*)'\s*\)"
)
CITY_HREF = re.compile(r'/city/[^"\'\s/]+/', re.I)


def _local(tag: str) -> str:
    return tag.split('}')[-1]


def normalize_name(value: str) -> str:
    """یکسان‌سازی نام فارسی برای تطبیق: ی/ک عربی، نیم‌فاصله، پیشوند «شهرستان» و فاصله‌ها."""
    text = unicodedata.normalize('NFC', value or '')
    text = (text.replace('\u064a', '\u06cc').replace('\u0649', '\u06cc')
            .replace('\u0643', '\u06a9').replace('\u200c', ' '))
    text = re.sub(r'\s*شهرستان\s*', ' ', text)
    return re.sub(r'\s+', ' ', text).strip()


def read_registry(path: str) -> list[dict]:
    source = open(path, encoding='utf-8').read()
    rows = [
        {'slug': s, 'name': n, 'province': p, 'status': st}
        for s, n, p, st in REGISTRY_ROW.findall(source)
    ]
    if not rows:
        raise SystemExit('هیچ ردیفی در رجیستری خوانده نشد؛ قالب فایل تغییر کرده است.')
    return rows


def read_items(path: str, post_type: str) -> list[dict]:
    items: list[dict] = []
    for _, el in ET.iterparse(path, events=('end',)):
        if _local(el.tag) == 'item':
            if (el.findtext(NS + 'post_type') or '') == post_type:
                province = ''
                for category in el.findall('category'):
                    if (category.get('domain') or '') == 'province_tax':
                        province = category.get('nicename') or ''
                        break
                items.append({
                    'slug': el.findtext(NS + 'post_name') or '',
                    'status': el.findtext(NS + 'status') or '',
                    'title': (el.findtext('title') or '').strip(),
                    'content': el.findtext(CONTENT) or '',
                    'province': province,
                })
            el.clear()
    return items


def slug_divergences(registry: list[dict], live: list[dict]) -> list[dict]:
    """ردیف‌های رجیستری که نامشان با یک صفحهٔ **منتشرشده** یکی است اما slug متفاوت دارند.

    اگر خودِ slugِ رجیستری هم بین صفحه‌های هم‌نام باشد، ردیف سالم است و اینجا گزارش
    نمی‌شود؛ آن حالت (چند صفحهٔ هم‌نام) در `duplicate_published` می‌آید.
    پیش‌نویس‌ها/سطل‌زباله عمداً کنار گذاشته می‌شوند؛ آن‌ها در `nonpublish_collisions` می‌آیند.
    """
    by_name: dict[str, list[dict]] = defaultdict(list)
    for item in live:
        if item['status'] != 'publish':
            continue
        by_name[normalize_name(item['title'])].append(item)
    out = []
    for row in registry:
        matches = by_name.get(normalize_name(row['name']), [])
        if not matches:
            continue
        # ردیفِ خودش صفحه دارد؟ پس هم‌راستاست؛ صفحهٔ دیگرِ هم‌نام در duplicate_published گزارش می‌شود.
        if any(match['slug'] == row['slug'] for match in matches):
            continue
        target = matches[-1]
        if row['slug'] != target['slug']:
            out.append({'registry_slug': row['slug'], 'registry_status': row['status'],
                        'name': row['name'], 'live_slug': target['slug']})
    return out


def duplicate_published(live: list[dict]) -> list[dict]:
    """عنوان‌های یکسان میان صفحه‌های منتشرشده."""
    names = Counter(normalize_name(i['title']) for i in live if i['status'] == 'publish')
    out = []
    for name, count in names.items():
        if count > 1:
            out.append({
                'name': name,
                'slugs': [i['slug'] for i in live if i['status'] == 'publish' and normalize_name(i['title']) == name],
            })
    return out


def nonpublish_collisions(live: list[dict]) -> list[dict]:
    """پیش‌نویس/سطل‌زباله‌ای که نامش با یک صفحهٔ منتشرشده یکی است."""
    published = Counter(normalize_name(i['title']) for i in live if i['status'] == 'publish')
    out = []
    for item in live:
        if item['status'] == 'publish':
            continue
        name = normalize_name(item['title'])
        if published.get(name):
            out.append({'slug': item['slug'], 'status': item['status'], 'title': item['title'],
                        'published_slugs': [i['slug'] for i in live
                                            if i['status'] == 'publish' and normalize_name(i['title']) == name]})
    return out


def name_variants_same_slug(registry: list[dict], live: list[dict]) -> list[dict]:
    """slug یکسان اما نام رجیستری و عنوان زنده متفاوت (مثلاً «انزلی» در برابر «بندر انزلی»)."""
    live_by_slug = {i['slug']: i for i in live}
    live_names = {normalize_name(i['title']) for i in live}
    out = []
    for row in registry:
        if normalize_name(row['name']) in live_names:
            continue
        item = live_by_slug.get(row['slug'])
        if item:
            out.append({'slug': row['slug'], 'registry_name': row['name'], 'live_title': item['title'],
                        'registry_status': row['status'], 'live_status': item['status']})
    return out


def orphans(registry: list[dict], live: list[dict]) -> dict:
    """slugهای زندهٔ بدون ردیفِ رجیستری و slugهای رجیستری بدون صفحهٔ منتشرشده."""
    registry_slugs = {r['slug'] for r in registry}
    registry_names = {normalize_name(r['name']) for r in registry}
    live_names = {normalize_name(i['title']) for i in live if i['status'] == 'publish'}
    published_slugs = {i['slug'] for i in live if i['status'] == 'publish'}
    other_slugs = {i['slug'] for i in live if i['status'] != 'publish'}
    return {
        # صفحه‌های منتشرشده‌ای که نامکشان در رجیستری نیست (شهرِ مرکزِ شهرستان، دو صفحهٔ هم‌نام).
        'live_slugs_not_in_registry': sorted(published_slugs - registry_slugs),
        # پیش‌نویس/سطل‌زباله — تصمیمِ جداگانه، نه خطای هم‌راستاسازی.
        'live_slugs_not_in_registry_nonpublish': sorted(other_slugs - registry_slugs),
        'live_published_names_not_in_registry': sorted(live_names - registry_names),
        'registry_names_without_live_page': sorted(registry_names - live_names),
        'registry_status_counts': dict(Counter(r['status'] for r in registry)),
    }


def province_link_stats(items: list[dict]) -> dict:
    """چند لینک به `/city/` در بدنهٔ صفحه‌های استان وجود دارد."""
    per_province = {}
    for item in items:
        if item['status'] != 'publish':
            continue
        per_province[item['slug']] = len(CITY_HREF.findall(item['content']))
    return {
        'provinces': len(per_province),
        'total_city_links': sum(per_province.values()),
        'provinces_with_zero': sorted(k for k, v in per_province.items() if v == 0),
        'per_province': per_province,
    }


def _table(rows: list[str], header: str) -> str:
    return header + '\n' + '\n'.join(rows) if rows else '_موردی یافت نشد._'


def build_markdown(report: dict) -> str:
    t1 = [f"| {i} | `{r['registry_slug']}` | {r['registry_status']} | {r['name']} | `{r['live_slug']}` |"
          for i, r in enumerate(report['slug_divergences'], 1)]
    t2 = [f"| {r['name']} | " + '، '.join(f"`{s}`" for s in r['slugs']) + ' |'
          for r in report['duplicate_published']]
    t2b = [f"| `{r['slug']}` | {r['status']} | {r['title']} | "
           + '، '.join(f"`{s}`" for s in r['published_slugs']) + ' |'
           for r in report['nonpublish_collisions']]
    t3 = [f"| `{r['slug']}` | {r['registry_name']} | {r['live_title']} | {r['registry_status']} | {r['live_status']} |"
          for r in report['name_variants_same_slug']]
    prov = report.get('provinces')
    prov_line = ''
    if prov and prov.get('provinces'):
        prov_line = (f"\n- **هابِ استان:** {prov['provinces']} صفحهٔ استان روی‌هم {prov['total_city_links']} لینک به `/city/` دارند؛ "
                     f"{len(prov['provinces_with_zero'])} استان صفر لینک.")
    sections = [
        '## جدول ۱ — اختلاف slug (نام یکسان، slug متفاوت)',
        _table(t1, '| # | slug رجیستری | وضعیت رجیستری | نام | slug زنده |\n|---|---|---|---|---|'),
        '\n## جدول ۲ — صفحه‌های تکراری',
        _table(t2, '| نام | slugها |\n|---|---|'),
        '',
        _table(t2b, '| slug | وضعیت | عنوان | صفحهٔ منتشرشده |\n|---|---|---|---|'),
        '\n## جدول ۳ — اختلاف نام با slug یکسان',
        _table(t3, '| slug | نام رجیستری | عنوان زنده | وضعیت رجیستری | وضعیت زنده |\n|---|---|---|---|---|'),
        '\n## خلاصه',
        f"- ردیف‌های رجیستری: {report['orphans']['registry_status_counts']}",
        f"- نامک‌های منتشرشدهٔ بدون ردیفِ رجیستری: {len(report['orphans']['live_slugs_not_in_registry'])}",
        f"- عنوان‌های منتشرشدهٔ بدون نامِ متناظر در رجیستری: {len(report['orphans']['live_published_names_not_in_registry'])}",
        f"- نام‌های رجیستری بدون صفحهٔ منتشرشده: {len(report['orphans']['registry_names_without_live_page'])}" + prov_line,
        '',
        '> این گزارش فقط اختلافِ داخلیِ رجیستری و export را نشان می‌دهد؛ هیچ رکوردی را تغییر نمی‌دهد و',
        '> جایگزین فهرست رسمیِ شماره‌دار تقسیمات کشوری یا بررسی HTTP سایت زنده نیست.',
    ]
    return '\n'.join(sections) + '\n'


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description='اختلاف رجیستری شهرستان‌ها با خروجی WXR')
    parser.add_argument('--registry', default='wp-content/themes/sarzaminaryan-child/data/counties.php')
    parser.add_argument('--wxr', required=True, help='خروجی شهرستان‌ها')
    parser.add_argument('--wxr-provinces', default=None, help='خروجی استان‌ها (اختیاری)')
    parser.add_argument('--json', dest='json_out', default=None)
    parser.add_argument('--markdown', dest='md_out', default=None)
    args = parser.parse_args(argv)

    registry = read_registry(args.registry)
    live = read_items(args.wxr, 'city')
    report = {
        'slug_divergences': slug_divergences(registry, live),
        'duplicate_published': duplicate_published(live),
        'nonpublish_collisions': nonpublish_collisions(live),
        'name_variants_same_slug': name_variants_same_slug(registry, live),
        'orphans': orphans(registry, live),
    }
    if args.wxr_provinces:
        report['provinces'] = province_link_stats(read_items(args.wxr_provinces, 'province'))
    markdown = build_markdown(report)
    if args.json_out:
        with open(args.json_out, 'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=2)
    if args.md_out:
        with open(args.md_out, 'w', encoding='utf-8') as fh:
            fh.write(markdown)
    else:
        sys.stdout.write(markdown)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
