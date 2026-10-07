#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""هم‌راستاسازیِ ردیف‌های رجیستریِ جغرافیا با صفحه‌های **منتشرشدهٔ زنده**.

مسئله: کلیدِ کارِ قالب (جعبهٔ شهرستان‌های استان، لینک‌سازیِ خودکار، `sa_county_of_post`)
بر پایهٔ ردیف‌های `data/counties.php` است و هر ردیف با «نامکِ» صفحهٔ واقعی به نوشته وصل
می‌شود. اگر نامکِ رجیستری با نامکِ صفحهٔ منتشرشده یکی نباشد، آن شهرستان در همهٔ این
مسیرها گم می‌شود. این ابزار فقط همان نامک را اصلاح می‌کند؛ **نام، وضعیت و URLِ هیچ صفحه‌ای
تغییر نمی‌کند.**

طبقه‌بندیِ هر ردیف:

  exact     نامکِ رجیستری همان صفحهٔ منتشرشده است (کاری لازم نیست).
  fix       یک صفحهٔ منتشرشدهٔ هم‌نامِ یگانه با نامکِ متفاوت وجود دارد → نامک اصلاح می‌شود.
  ambiguous چند صفحهٔ منتشرشدهٔ هم‌نام وجود دارد → تصمیمِ مالک، ابزار دست نمی‌زند.
  missing   صفحهٔ منتشرشدهٔ هم‌نامی پیدا نشد (نامِ رجیستری ممکن است کوتاه/کهنه باشد).

خروجی: JSON + جدولِ markdown. با `--apply` نامکِ ردیف‌های `fix` در فایلِ رجیستری
نوشته می‌شود (قالب‌بندیِ فایل، کامنت‌ها و ترتیبِ ردیف‌ها دست‌نخورده می‌ماند).

نکته: ابزار ۴۰۴، ایندکسِ گوگل، درستیِ رسمیِ تقسیمات کشوری یا «کدام صفحه درست است»
را تعیین نمی‌کند؛ فقط نامک‌ها را هم‌راستا می‌کند و مواردِ مبهم را گزارش می‌دهد.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import xml.etree.ElementTree as ET
from collections import defaultdict

NS = '{http://wordpress.org/export/1.2/}'
ROW = re.compile(
    r"array\(\s*'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']+)'\s*,"
    r"\s*'province'\s*=>\s*'([^']+)'\s*,\s*'status'\s*=>\s*'([^']*)'\s*\)"
)
SLUG_RE = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")


def normalize_name(value: str) -> str:
    """یکسان‌سازی نام: ی/ک عربی، نیم‌فاصله، پیشوند «استان/شهرستان» و فاصله‌ها."""
    text = (value or '').replace('\u064a', '\u06cc').replace('\u0649', '\u06cc')
    text = text.replace('\u0643', '\u06a9').replace('\u200c', ' ')
    text = re.sub(r'^\s*(?:استان|شهرستان)\s+', '', text)
    return re.sub(r'\s+', ' ', text).strip()


def key_variants(name: str) -> set[str]:
    """کلیدهای تطبیقِ یک نام: نامِ نرمال و نگارشِ بدونِ پیشوندِ «شهر»."""
    base = normalize_name(name)
    keys = {base} if base else set()
    without_city = normalize_name(re.sub(r'^شهر\s+', '', (name or '').strip()))
    if without_city:
        keys.add(without_city)
    return keys


def read_registry(path: str) -> tuple[list[dict], list[str]]:
    """ردیف‌های رجیستری + خطوطِ خام (برای نوشتنِ بی‌آسیبِ فایل)."""
    with open(path, encoding='utf-8') as handle:
        lines = handle.read().split('\n')
    rows: list[dict] = []
    for index, line in enumerate(lines):
        match = ROW.search(line)
        if match:
            rows.append({'slug': match.group(1), 'name': match.group(2),
                         'province': match.group(3), 'status': match.group(4),
                         'line': index})
    if not rows:
        raise SystemExit('هیچ ردیفی در رجیستری خوانده نشد؛ قالب فایل تغییر کرده است.')
    return rows, lines


def read_live(path: str) -> list[dict]:
    """صفحه‌های منتشرشدهٔ نوعِ `city` از خروجی WXR (نامک، عنوان، استان)."""
    items: list[dict] = []
    for _, el in ET.iterparse(path, events=('end',)):
        if el.tag.split('}')[-1] != 'item':
            continue
        if (el.findtext(NS + 'post_type') or '') == 'city' and (el.findtext(NS + 'status') or '') == 'publish':
            province = ''
            for category in el.findall('category'):
                if (category.get('domain') or '') == 'province_tax':
                    province = category.get('nicename') or ''
                    break
            title = (el.findtext('title') or '').strip()
            slug = (el.findtext(NS + 'post_name') or '').strip()
            if slug:
                items.append({'slug': slug, 'title': title, 'province': province,
                              'keys': key_variants(title)})
        el.clear()
    return items


def read_redirects(path: str) -> set[str]:
    """مسیرهای مبدأ در `data/redirects.php` (ادغام‌های تأییدشدهٔ مالک)."""
    try:
        with open(path, encoding='utf-8') as handle:
            text = handle.read()
    except OSError:
        return set()
    return {match.group(1) for match in re.finditer(r"'(/?[a-z0-9/_-]*city/[a-z0-9-]+/?)'\s*=>", text)} | \
           {('/' + m.group(1).strip('/') + '/') for m in re.finditer(r"'(/?city/[a-z0-9-]+/?)'\s*=>", text)}


def plan(registry: list[dict], live: list[dict]) -> tuple[list[dict], list[dict]]:
    """طبقه‌بندیِ ردیف‌ها + صفحه‌های زندهٔ بدونِ ردیف."""
    by_slug = {item['slug']: item for item in live}
    by_key: dict[str, list[dict]] = defaultdict(list)
    for item in live:
        for key in item['keys']:
            if item not in by_key[key]:
                by_key[key].append(item)

    used_slugs = {row['slug'] for row in registry}
    rows: list[dict] = []
    for row in registry:
        entry = {'slug': row['slug'], 'name': row['name'], 'province': row['province'],
                 'status': row['status'], 'action': 'keep', 'reason': 'exact',
                 'live_slug': row['slug'], 'matches': [], 'near': []}
        if row['slug'] in by_slug:
            rows.append(entry)
            continue

        matches: list[dict] = []
        for key in key_variants(row['name']):
            for item in by_key.get(key, []):
                if item not in matches:
                    matches.append(item)

        if len(matches) == 1:
            target = matches[0]['slug']
            entry['live_slug'] = target
            if target in used_slugs:
                entry['action'] = 'flag'
                entry['reason'] = 'slug-taken'
            else:
                entry['action'] = 'fix'
                entry['reason'] = 'single-name-match'
                entry['matches'] = [{'slug': target, 'title': matches[0]['title'],
                                     'province': matches[0]['province']}]
        elif len(matches) > 1:
            entry['action'] = 'flag'
            entry['reason'] = 'ambiguous-name'
            entry['matches'] = [{'slug': m['slug'], 'title': m['title'], 'province': m['province']}
                                for m in matches]
        else:
            entry['reason'] = 'missing-name'
        rows.append(entry)

    registry_slugs = {row['slug'] for row in registry}
    registry_names = {key for row in registry for key in key_variants(row['name'])}
    live_only = [{'slug': item['slug'], 'title': item['title'], 'province': item['province']}
                 for item in live
                 if item['slug'] not in registry_slugs and not (item['keys'] & registry_names)]
    live_only.sort(key=lambda x: (x['province'], x['slug']))

    # صفحه‌های زنده‌ای که نامشان با یک ردیفِ رجیستری یکی است ولی نامکِ دیگری دارند
    # (مثل «شهر بوشهر» در برابر ردیفِ شهرستانِ بوشهر) — این‌ها تصمیمِ مالک‌اند.
    mapped_slugs = {row['live_slug'] for row in rows if row['live_slug']}
    extra_name_pages = [{'slug': item['slug'], 'title': item['title'], 'province': item['province'],
                         'registry_slug': ''}
                        for item in live
                        if item['slug'] not in registry_slugs
                        and item['slug'] not in mapped_slugs
                        and (item['keys'] & registry_names)]
    for page in extra_name_pages:
        names = [row['slug'] for row in registry if key_variants(row['name']) & key_variants(page['title'])]
        page['registry_slug'] = '، '.join('`%s`' % slug for slug in names) if names else ''
    extra_name_pages.sort(key=lambda x: (x['province'], x['slug']))

    # نامزدهای نزدیک برای ردیف‌های بدونِ تطبیق (نامِ رجیستری زیررشتهٔ نامِ زنده یا برعکس).
    for entry in rows:
        if entry['reason'] != 'missing-name':
            continue
        base = normalize_name(entry['name'])
        for item in live:
            for candidate in item['keys']:
                if base and candidate and (base in candidate or candidate in base):
                    entry['near'].append({'slug': item['slug'], 'title': item['title'],
                                          'province': item['province']})
                    break
    return rows, live_only, extra_name_pages


def apply_fixes(path: str, fixes: dict[str, str]) -> dict[str, str]:
    """نوشتنِ نامکِ تازه در خط‌های همان ردیف‌ها (بدونِ دست‌زدن به بقیهٔ فایل)."""
    with open(path, encoding='utf-8') as handle:
        text = handle.read()
    applied: dict[str, str] = {}
    out_lines = []
    for line in text.split('\n'):
        match = ROW.search(line)
        if match and match.group(1) in fixes:
            old_slug = match.group(1)
            new_slug = fixes[old_slug]
            out_lines.append(line.replace("'slug' => '%s'" % old_slug, "'slug' => '%s'" % new_slug, 1))
            applied[old_slug] = new_slug
        else:
            out_lines.append(line)
    if applied:
        with open(path, 'w', encoding='utf-8') as handle:
            handle.write('\n'.join(out_lines))
    return applied


def validate(rows: list[dict]) -> list[str]:
    """بازبینیِ ساختاری پس از اصلاح: نامک‌های یکتا و معتبر."""
    problems: list[str] = []
    seen: set[str] = set()
    for row in rows:
        slug = row['slug']
        if not SLUG_RE.match(slug):
            problems.append('invalid slug: %s' % slug)
        if slug in seen:
            problems.append('duplicate slug: %s' % slug)
        seen.add(slug)
    return problems


def build_markdown(report: dict) -> str:
    rows = report['rows']
    fixes = [r for r in rows if r['action'] == 'fix']
    flags = [r for r in rows if r['action'] == 'flag']
    missing = [r for r in rows if r['reason'] == 'missing-name']
    lines = [
        '# هم‌راستاسازیِ نامک‌های رجیستری جغرافیا با صفحه‌های زنده',
        '',
        '| سنجه | شمار |',
        '|---|---:|',
        '| ردیف‌های رجیستری | %d |' % len(rows),
        '| نامکِ درست (exact) | %d |' % len([r for r in rows if r['reason'] == 'exact']),
        '| نامکِ اصلاح‌شده (fix) | %d |' % len(fixes),
        '| نیازمندِ تصمیمِ مالک (ambiguous/slug-taken) | %d |' % len(flags),
        '| بدونِ صفحهٔ هم‌نام | %d |' % len(missing),
        '| صفحهٔ منتشرشدهٔ بدونِ ردیفِ رجیستری | %d |' % len(report['live_only']),
        '| صفحهٔ زنده با نامِ هم‌نامِ یک ردیف ولی نامکِ دیگر (تصمیمِ مالک) | %d |' % len(report.get('extra_name_pages', [])),
        '| اصلاح‌های اعمال‌شده | %d |' % len(report.get('applied', {})),
        '',
    ]

    def table(items: list[dict]) -> list[str]:
        out = ['| نامکِ رجیستری | نامکِ زنده | نام | استان |', '|---|---|---|---|']
        for row in items:
            out.append('| `%s` | `%s` | %s | %s |' % (row['slug'], row['live_slug'], row['name'], row['province']))
        return out

    if fixes:
        lines += ['## ۱. اصلاحِ نامک (اعمال‌شده یا پیشنهادی)', ''] + table(fixes) + ['']
    if flags:
        lines += ['## ۲. نیازمندِ تصمیمِ مالک', '', '| نامکِ رجیستری | نام | نامزدهای زنده |', '|---|---|---|']
        for row in flags:
            candidates = '، '.join('`%s` (%s)' % (m['slug'], m['title']) for m in row['matches']) or '—'
            lines.append('| `%s` | %s | %s |' % (row['slug'], row['name'], candidates))
        lines.append('')
    near = [r for r in missing if r['near']]
    if near:
        lines += ['## ۳. نامِ نزدیک ولی ناهم‌سان (پیشنهاد، بدونِ تغییرِ خودکار)', '',
                  '| نامکِ رجیستری | نامِ رجیستری | نامزدِ زنده |', '|---|---|---|']
        for row in near:
            lines.append('| `%s` | %s | `%s` (%s) |' % (row['slug'], row['name'],
                                                         row['near'][0]['slug'], row['near'][0]['title']))
        lines.append('')
    if report['live_only']:
        lines += ['## ۴. صفحه‌های منتشرشدهٔ بدونِ ردیفِ رجیستری', '', '| نامک | عنوان | استان |', '|---|---|---|']
        lines += ['| `%s` | %s | %s |' % (i['slug'], i['title'], i['province']) for i in report['live_only']]
        lines.append('')
    extra = report.get('extra_name_pages', [])
    if extra:
        lines += ['## ۵. صفحه‌های زندهٔ هم‌نام با یک ردیفِ رجیستری ولی با نامکِ دیگر', '',
                  'این‌ها همان مواردی‌اند که در جدولِ اختلاف‌ها بحث شده‌اند (شهر در برابر شهرستان، '
                  'یا دو صفحهٔ هم‌نام). ابزار عمداً هیچ‌کدام را ادغام نمی‌کند.', '',
                  '| نامکِ صفحه | عنوان | استان | ردیفِ رجیستریِ هم‌نام | وضعیت |', '|---|---|---|---|---|']
        merged_paths = set(report.get('merged_paths') or [])
        for page in extra:
            is_merged = bool(page.get('merged')) or ('/city/%s/' % page['slug']) in merged_paths
            state = 'ادغام‌شده (۳۰۱)' if is_merged else 'بدونِ تغییر'
            lines.append('| `%s` | %s | %s | %s | %s |' % (page['slug'], page['title'], page['province'],
                                                          page['registry_slug'] or '—', state))
        lines.append('')
    lines += ['> این ابزار هیچ URL، عنوان یا وضعیتی را تغییر نمی‌دهد؛ فقط نامکِ ردیف‌های رجیستری '
              'را با نامکِ صفحهٔ منتشرشدهٔ هم‌نام یکی می‌کند و مواردِ مبهم را گزارش می‌دهد.', '']
    return '\n'.join(lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description='هم‌راستاسازیِ نامک‌های رجیستری جغرافیا با خروجی زنده')
    parser.add_argument('--registry', default='wp-content/themes/sarzaminaryan-child/data/counties.php')
    parser.add_argument('--wxr', required=True, help='خروجی شهرستان‌ها (s-aryan-shar.xml)')
    parser.add_argument('--json', dest='json_out', default=None)
    parser.add_argument('--markdown', dest='md_out', default=None)
    parser.add_argument('--apply', action='store_true', help='نوشتنِ نامکِ ردیف‌های fix در فایلِ رجیستری')
    parser.add_argument('--redirects', default='wp-content/themes/sarzaminaryan-child/data/redirects.php',
                        help='نقشهٔ ۳۰۱ برای علامت‌گذاریِ صفحه‌های ادغام‌شده')
    args = parser.parse_args(argv)

    registry, _ = read_registry(args.registry)
    live = read_live(args.wxr)
    rows, live_only, extra_pages = plan(registry, live)
    report = {'rows': rows, 'live_only': live_only, 'extra_name_pages': extra_pages, 'applied': {}}

    fixes = {r['slug']: r['live_slug'] for r in rows if r['action'] == 'fix' and r['live_slug']}
    if args.apply and fixes:
        applied = apply_fixes(args.registry, fixes)
        report['applied'] = applied
        registry, _ = read_registry(args.registry)
        rows, live_only, extra_pages = plan(registry, live)
        report = {'rows': rows, 'live_only': live_only, 'extra_name_pages': extra_pages, 'applied': applied}

    merged = read_redirects(args.redirects)
    for page in extra_pages:
        page['merged'] = ('/city/%s/' % page['slug']) in merged
    report['merged_paths'] = sorted(merged)
    report['problems'] = validate(rows)
    report['summary'] = {
        'rows': len(rows),
        'fixes': len([r for r in rows if r['action'] == 'fix']),
        'flags': len([r for r in rows if r['action'] == 'flag']),
        'missing': len([r for r in rows if r['reason'] == 'missing-name']),
        'live_only': len(live_only),
        'extra_name_pages': len(extra_pages),
        'applied': len(report.get('applied', {})),
    }

    if args.json_out:
        with open(args.json_out, 'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=2)
    markdown = build_markdown(report)
    if args.md_out:
        with open(args.md_out, 'w', encoding='utf-8') as fh:
            fh.write(markdown)
    else:
        sys.stdout.write(markdown)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
