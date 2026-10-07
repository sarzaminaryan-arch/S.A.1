#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ممیزیِ لینک‌سازی داخلیِ خودکار روی محتوای واقعی (شبیه‌سازی رندر).

لینک‌های داخلیِ این قالب در زمانِ نمایش ساخته می‌شوند (`inc/internal-links.php`)،
پس فایل WXR آن‌ها را نشان نمی‌دهد. این ابزار بدنهٔ واقعیِ صفحه‌های استان را به موتورِ
خودِ قالب می‌دهد (از طریق `tools/sa-tests/run-autolink-audit.php`) و لینک‌های
تولیدشده را می‌شمارد.

خروجی: markdown و/یا JSON. متن مقاله چاپ نمی‌شود؛ فقط slug/عنوان/شمارش و
واژهٔ لینک‌شده (که همان متنِ نمایشیِ لینک است) می‌آید.
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import tempfile
import unicodedata
import xml.etree.ElementTree as ET
from collections import Counter

NS = '{http://wordpress.org/export/1.2/}'
CONTENT = '{http://purl.org/rss/1.0/modules/content/}encoded'
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RUNNER = 'tools/sa-tests/run-autolink-audit.php'


def _local(tag: str) -> str:
    return tag.split('}')[-1]


def normalize(value: str) -> str:
    text = unicodedata.normalize('NFC', value or '')
    text = text.replace('\u064a', '\u06cc').replace('\u0649', '\u06cc').replace('\u0643', '\u06a9')
    text = text.replace('\u200c', ' ')
    for prefix in ('شهرستان ', 'شهر ', 'استان '):
        if text.startswith(prefix):
            text = text[len(prefix):]
    return re.sub(r'\s+', ' ', text).strip()


def read_items(path: str, types: set[str]) -> list[dict]:
    items: list[dict] = []
    for _, el in ET.iterparse(path, events=('end',)):
        if _local(el.tag) == 'item':
            post_type = el.findtext(NS + 'post_type') or ''
            if post_type in types:
                terms = {}
                for cat in el.findall('category'):
                    if cat.get('domain') == 'province_tax':
                        terms['province'] = cat.get('nicename') or ''
                items.append({
                    'type': post_type,
                    'slug': el.findtext(NS + 'post_name') or '',
                    'title': (el.findtext('title') or '').strip(),
                    'status': el.findtext(NS + 'status') or '',
                    'province': terms.get('province', ''),
                    'content': el.findtext(CONTENT) or '',
                })
            el.clear()
    return items


def build_job(cities: list[dict], provinces: list[dict],
              subjects: list[dict] | None = None) -> dict:
    roster = [{'type': i['type'], 'slug': i['slug'], 'title': i['title']}
              for i in cities + provinces if i['status'] == 'publish' and i['slug']]
    if subjects is None:
        subjects = [p for p in provinces if p['type'] == 'province']
    picked = [{'type': p['type'], 'slug': p['slug'], 'title': p['title'], 'content': p['content']}
              for p in subjects if p['status'] == 'publish' and p['slug'] and p['content'].strip()]
    return {'roster': roster, 'subjects': picked}


def pick_subjects(mode: str, cities: list[dict], provinces: list[dict],
                  registry_path: str, provinces_path: str) -> tuple[list[dict], dict]:
    """انتخاب صفحه‌های بررسی‌شده بر اساس حالت (province|both|ambiguous)."""
    live = cities + provinces
    if mode == 'province':
        return [p for p in provinces if p['type'] == 'province'], {}
    if mode == 'both':
        return [i for i in live if i['type'] in ('city', 'province') and i['status'] == 'publish'], {}
    if mode == 'ambiguous':
        needles = ambiguous_needles(read_provinces(provinces_path), read_registry_rows(registry_path), live)
        picked = [i for i in live if i['status'] == 'publish' and i['type'] in ('city', 'province')
                  and normalize(i['title']) in needles]
        return picked, needles
    raise SystemExit('حالتِ نامعتبر: ' + mode)


def run_simulation(job: dict, tmp_dir: str, runner: str = RUNNER,
                   node: str = 'node', timeout: int = 900, batch: int = 5) -> dict:
    """اجرای موتورِ PHP روی job (در دسته‌های کوچک) و برگرداندن JSON یکپارچه.

    دسته‌بندی لازم است چون خروجیِ php-wasm از حدود ۶۴ کیلوبایت بریده می‌شود.
    """
    subjects = job.get('subjects', [])
    if batch and len(subjects) > batch:
        merged = {'max_links': None, 'subjects': []}
        for start in range(0, len(subjects), batch):
            chunk = dict(job)
            chunk['subjects'] = subjects[start:start + batch]
            part = run_simulation(chunk, tmp_dir, runner=runner, node=node,
                                  timeout=timeout, batch=0)
            merged['max_links'] = part.get('max_links', merged['max_links'])
            merged['subjects'] += part.get('subjects', [])
        return merged
    return _run_once(job, tmp_dir, runner, node, timeout)


def _run_once(job: dict, tmp_dir: str, runner: str, node: str, timeout: int) -> dict:
    os.makedirs(tmp_dir, exist_ok=True)
    job_path = os.path.join(tmp_dir, 'job.json')
    with open(job_path, 'w', encoding='utf-8') as fh:
        json.dump(job, fh, ensure_ascii=False)
    # مسیر داخلِ فایل‌سیستمِ مجازیِ php-wasm: exec.js پوشهٔ اسکریپت را در /ws کپی می‌کند.
    rel = os.path.relpath(job_path, os.path.dirname(os.path.join(REPO_ROOT, runner)))
    env = dict(os.environ)
    env['SA_AUTOLINK_JOB'] = '/ws/' + rel.replace(os.sep, '/')
    proc = subprocess.run([node, os.path.join(REPO_ROOT, 'tools/phpwasm/exec.js'), runner],
                          cwd=REPO_ROOT, env=env, capture_output=True, text=True, timeout=timeout)
    if proc.returncode != 0:
        raise RuntimeError('شبیه‌سازی شکست خورد (%s): %s' % (proc.returncode, (proc.stderr or proc.stdout)[-2000:]))
    start = proc.stdout.find('{')
    if start < 0:
        raise RuntimeError('خروجی JSON پیدا نشد: ' + proc.stdout[:500])
    return json.loads(proc.stdout[start:])


def analyse(result: dict, subjects_all: list[dict], registry: dict[str, int],
            live_province: dict[str, str] | None = None,
            needles: dict | None = None) -> dict:
    """تحلیل خروجیِ شبیه‌سازی: شمارش‌ها، لینکِ خودی و برخوردِ نامِ خودی.

    «لینکِ نامِ خودی به استانِ دیگر» پرخطرترین یافته است: واژه‌ای که نامِ خودِ استان
    است، به شهرستانی هم‌نام در استانی دیگر لینک می‌شود (مثلاً «البرز» در صفحهٔ استان
    البرز → شهرستان البرز در قزوین).
    """
    live_province = live_province or {}
    by_slug = {(p['type'], p['slug']): p for p in subjects_all}
    rows = []
    issues = {'self_links': [], 'own_name_collisions': [], 'own_name_same_province': [],
              'authored_self_links': []}
    for subject in result.get('subjects', []):
        slug = subject['slug']
        kind = subject.get('type', '')
        page = by_slug.get((kind, slug), by_slug.get(('province', slug), {}))
        own = normalize(page.get('title', ''))
        row = {
            'slug': slug,
            'type': kind,
            'title': page.get('title', ''),
            'city': subject['counts'].get('city', 0),
            'province': subject['counts'].get('province', 0),
            'attraction': subject['counts'].get('attraction', 0),
            'other': subject['counts'].get('other', 0),
            'total': subject['total'],
            'registry_counties': registry.get(slug, 0),
            'cross_province': 0,
            'bare_anchors': int(subject.get('bare_anchors', 0) or 0),
            'city_targets': [],
        }
        subject_province = page.get('province', '') or (slug if kind == 'province' else '')
        for link in subject.get('links', []):
            link_kind = link.get('kind')
            if link_kind not in ('city', 'province'):
                continue
            path = link.get('path', '')
            label = link.get('label', '')
            parts = path.strip('/').split('/')
            target_slug = parts[-1] if parts else ''
            target_kind = parts[0] if len(parts) >= 2 else ''
            if target_kind == 'city':
                target_province = live_province.get(target_slug, '')
                if target_province and target_province != subject_province:
                    row['cross_province'] += 1
            else:
                target_province = target_slug
            generated = bool(link.get('generated', True))
            row['city_targets'].append({'label': label, 'path': path, 'province': target_province,
                                        'generated': generated})
            if not generated:
                # پیوندِ از پیش موجود در متنِ ذخیره‌شده (کارِ تحریریه، نه موتور)
                if path == '/%s/%s/' % (kind, slug):
                    issues['authored_self_links'].append({'slug': slug, 'type': kind, 'path': path})
                continue
            # لینک به صفحهٔ خودِ همین موضوع (نباید رخ دهد)
            if path == '/%s/%s/' % (kind, slug):
                issues['self_links'].append({'slug': slug, 'type': kind, 'path': path})
            # واژه‌ای که نامِ خودِ همین صفحه است، اما به موجودیتِ دیگری لینک شده
            if own and normalize(label) == own and not (target_kind == kind and target_slug == slug):
                item = {'slug': slug, 'type': kind, 'title': page.get('title', ''), 'label': label,
                        'path': path, 'target_province': target_province,
                        'subject_province': subject_province}
                if target_province and subject_province and target_province != subject_province:
                    issues['own_name_collisions'].append(item)
                else:
                    issues['own_name_same_province'].append(item)
        rows.append(row)

    cities = [r['city'] for r in rows]
    distribution = Counter(cities)
    summary = {
        'provinces': len(rows),
        'total_city_links': sum(cities),
        'median_city_links': sorted(cities)[len(cities) // 2] if cities else 0,
        'provinces_without_city_links': sorted(r['slug'] for r in rows if r['city'] == 0),
        'distribution': dict(sorted(distribution.items())),
        'max_links_cap': result.get('max_links'),
        # قاعدهٔ موتور: متنِ لینکِ تولیدشده نامِ کامل است («شهرستان X» / «استان X»).
        'bare_anchors': sum(r['bare_anchors'] for r in rows),
        'generated_city_links': sum(r['city'] + r['province'] for r in rows),
    }
    report = {'summary': summary, 'rows': rows, 'issues': issues}
    if needles:
        report['ambiguous_needles'] = {k: [list(v) for v in values] for k, values in needles.items()}
    return report


def build_markdown(report: dict, provenance: dict | None = None) -> str:
    summary = report['summary']
    head = [
        '# ممیزیِ لینک‌سازیِ داخلیِ خودکار (شبیه‌سازی رندر) — سرزمین آریان',
        '',
        '**نوع:** فقط‌خواندنی · **منبع محتوا:** خروجی WXR · **موتور:** `inc/internal-links.php` نسخهٔ همین شاخه',
        '',
        '## ۱. روش',
        '',
        '- لینک‌های داخلیِ قالب در زمانِ نمایش ساخته می‌شوند؛ شمارشِ لینک در فایل WXR معتبر نیست.',
        '- ابزار `tools/sa-tests/run-autolink-audit.php` واژه‌نامهٔ واقعی (۳۱ استان، شهرستان‌های رجیستری، نماهای منتشرشده) را می‌سازد و بدنهٔ هر صفحهٔ بررسی‌شده را از موتورِ خودِ قالب می‌گذراند.',
        '- سقفِ لینکِ هر صفحه: `%s` (فیلتر `sa_autolink_max_links`).' % summary.get('max_links_cap'),
        '- حالتِ انتخابِ صفحه: **%s**.' % report.get('subject_mode', 'province'),
        '',
        '## ۲. خلاصه',
        '',
        '| سنجه | مقدار |',
        '|---|---|',
        '| صفحه‌های بررسی‌شده | %d |' % summary['provinces'],
        '| کل لینکِ شهرستانیِ تولیدشده | %d |' % summary['total_city_links'],
        '| میانهٔ لینکِ شهرستانی در هر صفحه | %d |' % summary['median_city_links'],
        '| صفحه‌های بدون لینکِ شهرستانی | %d |' % len(summary['provinces_without_city_links']),
        '| لینکِ تولیدشده با متنِ کامل («شهرستان/استان X») | %d از %d |' % (
            summary.get('generated_city_links', 0) - summary.get('bare_anchors', 0),
            summary.get('generated_city_links', 0)),
        '| لینکِ تولیدشده با متنِ بدونِ پیشوند (نباید رخ دهد) | %d |' % summary.get('bare_anchors', 0),
        '| پیوندِ خودارجاع در متنِ ذخیره‌شده | %d |' % len(report['issues'].get('authored_self_links', [])),
        '| لینکِ تولیدشدهٔ خودی (نباید رخ دهد) | %d |' % len(report['issues'].get('self_links', [])),
        '',
    ]
    if provenance:
        head += ['> محتوا: %s · رجیستری: %s' % (provenance.get('wxr', ''), provenance.get('registry', '')), '']

    table = ['| صفحه | لینک شهرستان | لینک استان | لینک نما | سایر | کل | شهرستان‌های رجیستری |',
             '|---|---|---|---|---|---|---|']
    for row in sorted(report['rows'], key=lambda r: -r['city']):
        table.append('| %s | %d | %d | %d | %d | %d | %d |' % (
            row['title'] or row['slug'], row['city'], row['province'], row['attraction'],
            row['other'], row['total'], row['registry_counties']))
    head += ['## ۳. جدولِ صفحه‌ها', ''] + table + ['']

    issues = report['issues']
    head += ['## ۴. یافته‌های خطا', '',
             'قواعدِ موتور (نسخهٔ ۲.۱۱.۳۷): (۱) متنِ لینک همیشه نامِ کامل است — «شهرستان نطنز»، نه «نطنز»؛' +
             ' (۲) نام‌های نامبهم و هر نامی که به پدیدهٔ دیگری چسبیده باشد («بافت شهری»، «رود شاهرود»)' +
             ' بدون قرینهٔ صریح لینک نمی‌شوند؛ (۳) نامِ خودِ صفحه در همان صفحه لینک نمی‌شود و به کاندیدِ هم‌نامِ' +
             ' استانِ دیگر هم نمی‌رود (به‌جز شهرستانِ هم‌استان با قرینهٔ صریح). خروجیِ زیر تخلف‌ها را فهرست می‌کند:', '']
    if issues['own_name_collisions']:
        head += ['| صفحه | واژهٔ لینک‌شده | مقصد | استانِ مقصد | استانِ صفحه |', '|---|---|---|---|---|']
        for item in issues['own_name_collisions'][:30]:
            head.append('| %s | %s | `%s` | %s | %s |' % (item['title'] or item['slug'], item['label'],
                                                          item['path'], item['target_province'],
                                                          item.get('subject_province', '')))
        head.append('')
    else:
        head += ['- لینکِ هم‌نامِ بین‌استانی یافت نشد.', '']
    if issues['own_name_same_province']:
        head += ['**هم‌نام در استانِ خودش (نیازمند بازبینی، معمولاً درست):** %s' %
                 '، '.join('%s → `%s`' % (i['label'], i['path']) for i in issues['own_name_same_province'][:15]), '']
    if issues['self_links']:
        head += ['**لینک به صفحهٔ خودِ استان (نباید رخ دهد):**', '']
        head += ['- `%s` → `%s`' % (i['slug'], i['path']) for i in issues['self_links'][:20]]
        head.append('')
    if issues.get('authored_self_links'):
        head += ['**پیوندِ خودارجاع در متنِ ذخیره‌شده (کارِ تحریریه، نه موتور):** %d مورد — صفحه‌هایی که در متنِ خودشان به خودشان لینک داده‌اند:' %
                 len(issues['authored_self_links']), '']
        head += ['- `%s` (`%s`) → `%s`' % (i['slug'], i['type'], i['path'])
                 for i in issues['authored_self_links'][:20]]
        head.append('')
    if summary.get('bare_anchors', 0):
        head += ['**لینک با متنِ بدونِ پیشوند (تخلفِ قاعدهٔ ۱):** %d مورد — ' % summary['bare_anchors'] +
                 '، '.join('%s → `%s`' % (t['label'], t['path'])
                           for r in report['rows'] for t in r.get('city_targets', [])
                           if t.get('generated') and t.get('label')
                           and not _has_type_prefix(t['label']))[:600], '']
    cross = sum(r['cross_province'] for r in report['rows'])
    head += ['**لینک به شهرستانی در استانِ دیگر (اطلاعی):** %d لینک از %d؛ این‌ها لزوماً خطا نیستند (مثلاً نامِ شهرستانِ همسایه در متن می‌آید) و فقط برای بازبینی فهرست می‌شوند.' %
             (cross, report['summary']['total_city_links']), '']

    head += [
        '## ۵. محدودیت‌ها',
        '',
        '- این «شبیه‌سازی» است، نه صفحهٔ زنده: ترم‌های تاکسونومی، صفحه‌بندی، افزونه‌ها، کش و کوکی مدل نمی‌شوند.',
        '- لینک‌های موجودِ متن دست‌نخورده می‌مانند و در شمارشِ «تولیدشده» نمی‌آیند (قاعدهٔ خودِ موتور).',
        '- شمارشِ لینک سنجهٔ کیفیتِ محتوا نیست؛ فقط نشان می‌دهد مسیرِ داخلی ساخته می‌شود یا نه.',
    ]
    return '\n'.join(head) + '\n'


def write_self_links_csv(report: dict, path: str) -> None:
    """نوشتنِ فهرستِ پیوندهای خودارجاعِ متنِ ذخیره‌شده (فقط slug/مسیر، بدون متن)."""
    import csv
    with open(path, 'w', encoding='utf-8-sig', newline='') as fh:
        writer = csv.writer(fh)
        writer.writerow(['page_slug', 'page_type', 'self_path'])
        for item in report['issues'].get('authored_self_links', []):
            writer.writerow([item['slug'], item['type'], item['path']])


def _has_type_prefix(label: str) -> bool:
    """آیا متنِ لینک با پیشوندِ نوعِ موجودیت آغاز می‌شود؟ («شهرستان/شهر/استان/بخش/دهستان X»)"""
    import re
    return bool(re.match(r'^(?:استان|شهرستان|شهر|بخش|دهستان)[\s\u200c]', (label or '').strip()))


def _path(url: str) -> str:
    return re.sub(r'^https?://[^/]+', '', url or '')


def _php_field(row: str, key: str) -> str:
    match = re.search(r"'" + key + r"'\s*=>\s*'([^']*)'", row)
    return match.group(1) if match else ''


def read_registry_rows(path: str) -> list[dict]:
    source = open(path, encoding='utf-8').read()
    rows = []
    for row in re.findall(r'array\((.*?)\)\s*,', source, re.S):
        if "'slug'" not in row:
            continue
        rows.append({'slug': _php_field(row, 'slug'), 'name': _php_field(row, 'name'),
                     'province': _php_field(row, 'province'), 'status': _php_field(row, 'status')})
    return rows


def read_provinces(path: str) -> list[dict]:
    source = open(path, encoding='utf-8').read()
    rows = []
    for row in re.findall(r'array\((.*?)\)\s*,', source, re.S):
        if "'slug'" not in row:
            continue
        rows.append({'slug': _php_field(row, 'slug'), 'name': _php_field(row, 'name')})
    return rows


def ambiguous_needles(provinces_data: list[dict], registry: list[dict], live: list[dict]) -> dict[str, list]:
    """نام‌هایی که بیش از یک موجودیت با آن‌ها هم‌نام است (کاندیدِ تداخل در واژه‌نامه)."""
    needles: dict[str, list] = {}
    published_provinces = {i['slug'] for i in live if i['type'] == 'province' and i['status'] == 'publish'}
    live_cities = {i['slug']: i for i in live if i['type'] == 'city' and i['status'] == 'publish'}

    def add(name, entity):
        key = normalize(name)
        if key:
            needles.setdefault(key, []).append(entity)

    for province in provinces_data:
        if province['slug'] in published_provinces:
            add(province['name'], ('province', province['slug'], province['name']))
    for row in registry:
        item = live_cities.get(row['slug'])
        if not item:
            continue
        add(row['name'], ('city', row['slug'], row['name']))
        title = normalize(item['title'])
        if title and not re.search(r'(?:استان|شهرستان)\s', item['title']) and len(title.replace(' ', '')) >= 2:
            add(item['title'], ('city', row['slug'], item['title']))
    return {key: value for key, value in needles.items() if len(value) > 1}


def read_registry_counties(path: str) -> dict[str, int]:
    """شمارِ شهرستان‌های هر استان از رجیستری."""
    source = open(path, encoding='utf-8').read()
    counts: dict[str, int] = {}
    for slug, _name, province, _status in re.findall(
            r"array\(\s*'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']+)'\s*,\s*"
            r"'province'\s*=>\s*'([^']+)'\s*,\s*'status'\s*=>\s*'([^']*)'\s*\)", source):
        counts[province] = counts.get(province, 0) + 1
    return counts


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description='ممیزی رندرِ لینک‌سازی داخلی روی صفحه‌های استان')
    parser.add_argument('--wxr-shar', required=True, help='خروجی شهرستان‌ها/نماها')
    parser.add_argument('--wxr-provinces', required=True, help='خروجی استان‌ها')
    parser.add_argument('--registry', default='wp-content/themes/sarzaminaryan-child/data/counties.php')
    parser.add_argument('--runner', default=RUNNER)
    parser.add_argument('--tmp-dir', default='tools/sa-tests/.tmp-autolink')
    parser.add_argument('--batch', type=int, default=5, help='شمارِ صفحه در هر اجرای php-wasm (سقفِ خروجی)')
    parser.add_argument('--subjects', default='province', choices=['province', 'both', 'ambiguous'],
                        help='کدام صفحه‌ها شبیه‌سازی شوند')
    parser.add_argument('--provinces-php',
                        default='wp-content/themes/sarzaminaryan-child/data/provinces.php')
    parser.add_argument('--json', dest='json_out', default=None)
    parser.add_argument('--self-links-csv', dest='self_links_csv', default=None,
                        help='CSV پیوندهای خودارجاعِ متنِ ذخیره‌شده')
    parser.add_argument('--markdown', dest='md_out', default=None)
    args = parser.parse_args(argv)

    cities = read_items(args.wxr_shar, {'city', 'attraction'})
    provinces = read_items(args.wxr_provinces, {'province'})
    picked, needles = pick_subjects(args.subjects, cities, provinces, args.registry, args.provinces_php)
    job = build_job(cities, provinces, picked)
    result = run_simulation(job, args.tmp_dir, runner=args.runner, batch=args.batch)
    live_province = {i['slug']: i.get('province', '') for i in cities if i['type'] == 'city'}
    report = analyse(result, cities + provinces, read_registry_counties(args.registry), live_province, needles)

    report['subject_mode'] = args.subjects
    markdown = build_markdown(report, {'wxr': os.path.basename(args.wxr_shar),
                                       'registry': args.registry})
    if args.json_out:
        with open(args.json_out, 'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=2)
    if args.self_links_csv:
        write_self_links_csv(report, args.self_links_csv)
    if args.md_out:
        with open(args.md_out, 'w', encoding='utf-8') as fh:
            fh.write(markdown)
    else:
        sys.stdout.write(markdown)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
