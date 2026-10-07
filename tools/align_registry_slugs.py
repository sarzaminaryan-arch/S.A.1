#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""هم‌راستاسازیِ slugهای رجیستری جغرافیا با صفحه‌های **منتشرشدهٔ زنده**.

مسئله: URLهای `/city/{slug}/` قفل‌اند و تغییر نمی‌کنند، اما رجیستری در چند ردیف
slug دیگری دارد (مثلاً `isfahan` در برابر `isfahan-city`). نتیجه این است که
واژه‌نامهٔ لینک‌سازیِ خودکار، جعبه‌های «شهرستان‌های استان» و `sa_county_of_post()`
آن ردیف‌ها را پیدا نمی‌کنند.

این ابزار فقط ردیف‌هایی را عوض می‌کند که «یک نام، دقیقاً یک صفحهٔ منتشرشدهٔ هم‌نام
با slug متفاوت» دارند؛ هر ابهام (چند صفحهٔ هم‌نام، یا slug مقصد که قبلاً در رجیستری
است) فقط گزارش می‌شود و به تصمیم انسانی می‌ماند.

ورودی/خروجی:
  --registry  فایل `data/counties.php`
  --wxr       خروجی زندهٔ شهرستان‌ها (`s-aryan-shar.xml`)
  --json      گزارش ماشین‌خوان
  --markdown  گزارش فارسی
  --apply     نوشتنِ اصلاح‌ها در همان فایل رجیستری (پیش‌فرض: فقط گزارش)
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from collections import defaultdict

import registry_live_diff as rld  # noqa: F401  (وابستگیِ عمدی: یک منطقِ یکسان‌سازی)


def variant_names(value: str) -> set[str]:
    """نام‌های هم‌ارزِ یک عنوان برای تطبیقِ محافظه‌کارانه.

    «شهرستان بوشهر» و «بوشهر» یکی‌اند؛ «شهر بوشهر» هم به همان شهرستان اشاره دارد
    (شهرِ مرکزِ شهرستان). اگر هر دو در خروجی زنده باشند، تصمیم با انسان است و
    ابزار فقط گزارش می‌کند.
    """
    base = rld.normalize_name(value)
    variants = {base}
    without_city = rld.normalize_name(re.sub(r'^شهر\s+', '', value or '').strip())
    if without_city:
        variants.add(without_city)
    return variants


def plan(registry: list[dict], live: list[dict]) -> tuple[list[dict], list[dict]]:
    """دسته‌بندی هر ردیف رجیستری و فهرستِ صفحه‌های زندهٔ بدون ردیف."""
    published = [i for i in live if i['status'] == 'publish' and i['slug']]
    by_slug = {i['slug']: i for i in published}
    by_name: dict[str, list[dict]] = defaultdict(list)
    for item in published:
        for name in variant_names(item['title']):
            if item not in by_name[name]:
                by_name[name].append(item)

    used = {r['slug'] for r in registry}
    rows: list[dict] = []
    for row in registry:
        names = variant_names(row['name'])
        entry = {'slug': row['slug'], 'name': row['name'], 'province': row['province'],
                 'status': row['status'], 'live_slug': '', 'reason': '', 'action': 'keep',
                 'candidates': []}
        if row['slug'] in by_slug:
            entry['reason'] = 'same-slug'
            entry['live_slug'] = row['slug']
        else:
            matches = []
            for name in names:
                for item in by_name.get(name, []):
                    if item not in matches:
                        matches.append(item)
            if len(matches) == 1:
                target = matches[0]['slug']
                entry['live_slug'] = target
                if target in used:
                    entry['action'] = 'flag'
                    entry['reason'] = 'slug-taken'
                else:
                    entry['action'] = 'fix'
                    entry['reason'] = 'single-name-match'
            elif len(matches) > 1:
                entry['action'] = 'flag'
                entry['reason'] = 'ambiguous-name'
                entry['candidates'] = sorted(m['slug'] for m in matches)
            else:
                entry['reason'] = 'no-published-match'
        rows.append(entry)

    reg_slugs = {r['slug'] for r in registry}
    reg_names = {rld.normalize_name(r['name']) for r in registry}
    live_only = [
        {'slug': i['slug'], 'title': i['title'], 'province': i.get('province', '')}
        for i in published
        if i['slug'] not in reg_slugs and rld.normalize_name(i['title']) not in reg_names
    ]
    live_only.sort(key=lambda x: (x['province'], x['slug']))
    return rows, live_only


def apply_fixes(path: str, rows: list[dict]) -> dict:
    """جای‌گزینیِ slug ردیف‌های `fix` در فایل رجیستری (خط‌به‌خط، بدون بازنویسی قالب)."""
    fixes = {r['slug']: r['live_slug'] for r in rows if r['action'] == 'fix' and r['live_slug']}
    with open(path, encoding='utf-8') as fh:
        lines = fh.readlines()
    applied: dict[str, str] = {}
    out: list[str] = []
    for line in lines:
        match = rld.REGISTRY_ROW.search(line)
        if match and match.group(1) in fixes:
            old, new = match.group(1), fixes[match.group(1)]
            out.append(line.replace("'slug' => '%s'" % old, "'slug' => '%s'" % new, 1))
            applied[old] = new
        else:
            out.append(line)
    if applied:
        with open(path, 'w', encoding='utf-8') as fh:
            fh.writelines(out)
    return applied


def build_markdown(report: dict) -> str:
    rows, live_only = report['rows'], report['live_only']
    fixes = [r for r in rows if r['action'] == 'fix']
    flags = [r for r in rows if r['action'] == 'flag']
    unchanged = [r for r in rows if r['action'] == 'keep']
    lines = [
        '# هم‌راستاسازیِ slugهای رجیستری جغرافیا با صفحه‌های زنده',
        '',
        '| سنجه | شمار |',
        '|---|---:|',
        '| ردیف‌های رجیستری | %d |' % len(rows),
        '| اصلاحِ slug (یک نام، یک صفحهٔ هم‌نام) | %d |' % len(fixes),
        '| نیازمندِ تصمیم (ابهام) | %d |' % len(flags),
        '| دست‌نخورده | %d |' % len(unchanged),
        '| صفحهٔ زندهٔ بدونِ ردیفِ رجیستری | %d |' % len(live_only),
        '| اصلاح‌های اعمال‌شده | %d |' % len(report.get('applied', {})),
        '',
    ]
    if fixes:
        lines += ['## ۱. اصلاحِ slug', '', '| slug رجیستری | slug زنده | نام | استان |', '|---|---|---|---|']
        lines += ['| `%s` | `%s` | %s | %s |' % (r['slug'], r['live_slug'], r['name'], r['province'])
                  for r in sorted(fixes, key=lambda x: x['province'])]
        lines.append('')
    if flags:
        lines += ['## ۲. نیازمندِ تصمیم', '', '| slug رجیستری | نام | دلیل | کاندیدهای زنده |', '|---|---|---|---|']
        lines += ['| `%s` | %s | %s | %s |' % (r['slug'], r['name'], r['reason'],
                                                '، '.join('`%s`' % c for c in r['candidates']))
                  for r in flags]
        lines.append('')
    if live_only:
        lines += ['## ۳. صفحه‌های زندهٔ بدونِ ردیفِ رجیستری', '',
                  '| slug | عنوان | استان |', '|---|---|---|']
        lines += ['| `%s` | %s | %s |' % (i['slug'], i['title'], i['province']) for i in live_only]
        lines.append('')
    lines += ['> این ابزار فقط فیلد `slug` را در ردیف‌های بی‌ابهام عوض می‌کند؛ نام، استان، وضعیت، عنوان صفحه‌ها '
              'و URLهای زنده دست‌نخورده می‌مانند. ابهام‌ها (چند صفحهٔ هم‌نام) به تصمیم انسانی می‌ماند.', '']
    return '\n'.join(lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description='هم‌راستاسازیِ slugهای رجیستری با خروجی زنده')
    parser.add_argument('--registry', default='wp-content/themes/sarzaminaryan-child/data/counties.php')
    parser.add_argument('--wxr', required=True, help='خروجی شهرستان‌ها (s-aryan-shar.xml)')
    parser.add_argument('--json', dest='json_out', default=None)
    parser.add_argument('--markdown', dest='md_out', default=None)
    parser.add_argument('--apply', action='store_true', help='اعمالِ اصلاح‌های بی‌ابهام در فایل رجیستری')
    args = parser.parse_args(argv)

    registry = rld.read_registry(args.registry)
    live = rld.read_items(args.wxr, 'city')
    rows, live_only = plan(registry, live)
    report = {'rows': rows, 'live_only': live_only, 'applied': {}}

    if args.apply:
        applied = apply_fixes(args.registry, rows)
        report['applied'] = applied
        # پس از اعمال، رجیستری را دوباره می‌خوانیم تا گزارش با فایل هم‌خوان باشد.
        registry = rld.read_registry(args.registry)
        rows, live_only = plan(registry, live)
        report = {'rows': rows, 'live_only': live_only, 'applied': applied}

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
