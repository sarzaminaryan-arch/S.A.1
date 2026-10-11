#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
schema_build.py — ساخت اسکیمای JSON-LD (@graph) از روی متن نهایی مقاله، تا اسکیما همیشه عیناً با متن یکی باشد.

  Article + BreadcrumbList همیشه؛ HowTo فقط اگر بخش «روش گام‌به‌گام» مرحله‌های «۱. **نام**. متن» دارد؛
  FAQPage فقط اگر بخش پرسش‌های رایج با قالب «**۱. سؤال**» دارد.

کاربرد:
  python3 schema_build.py FILE.md            چاپ JSON
  python3 schema_build.py --write FILE.md    جایگزینی/افزودن بخش «## اسکیما برای پیاده‌سازی» در خود فایل
"""
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import article_model as am  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
JALALI = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']
STEP_RE = re.compile(r'^\s*(?:[' + am.PERSIAN_DIGITS + r']+|\d+)[\.)]\s+\*\*(.+?)\*\*[\.:：]?\s*(.*)$')


def jalali_to_gregorian(jy: int, jm: int, jd: int) -> str:
    jy += 1595
    days = -355668 + 365 * jy + (jy // 33) * 8 + ((jy % 33) + 3) // 4 + jd + (((jm - 1) * 31) if jm < 7 else ((jm - 7) * 30 + 186))
    gy = 400 * (days // 146097)
    days %= 146097
    if days > 36524:
        days -= 1
        gy += 100 * (days // 36524)
        days %= 36524
        if days >= 365:
            days += 1
    gy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        gy += (days - 1) // 365
        days = (days - 1) % 365
    gd = days + 1
    sal_a = [0, 31, (29 if (gy % 4 == 0 and gy % 100 != 0) or gy % 400 == 0 else 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
    gm = 0
    while gm < 13 and gd > sal_a[gm]:
        gd -= sal_a[gm]
        gm += 1
    return f'{gy:04d}-{gm:02d}-{gd:02d}'


def date_from_byline(line: str) -> str:
    m = re.search(r'([' + am.PERSIAN_DIGITS + r'0-9]{1,2})\s+(' + '|'.join(JALALI) + r')\s+([' + am.PERSIAN_DIGITS + r'0-9]{4})', line or '')
    if not m:
        return ''
    tr = {ord(c): str(i) for i, c in enumerate(am.PERSIAN_DIGITS)}
    d, mon, y = int(m.group(1).translate(tr)), JALALI.index(m.group(2)) + 1, int(m.group(3).translate(tr))
    return jalali_to_gregorian(y, mon, d)


def clean_text(s: str) -> str:
    s = am.LINK_RE.sub(r'\1', s)
    s = re.sub(r'\*\*|__|`', '', s)
    s = am.EMOJI_RE.sub('', s)
    return re.sub(r'\s+', ' ', s).strip()


def build(art: am.Article, cfg: dict) -> dict:
    site = cfg['site_url'].rstrip('/')
    slug = art.front.get('نامک', '')
    url = f'{site}/{slug}/'
    org = {'@type': 'Organization', 'name': cfg['byline']}
    headline = am.strip_emoji(art.h1 or art.front.get('عنوان سئو', ''))
    byline = art.lines[art.byline_line] if art.byline_line >= 0 else ''
    article = {
        '@type': 'Article',
        'headline': headline,
        'description': art.front.get('توضیح متا', ''),
        'inLanguage': 'fa',
        'url': url,
        'mainEntityOfPage': url,
        'author': org,
        'publisher': org,
        'dateModified': date_from_byline(byline) or '',
    }
    if art.front.get('وضعیت') == 'منتشرشده' and art.front.get('تاریخ انتشار'):
        article['datePublished'] = art.front['تاریخ انتشار']
    graph = [article, {
        '@type': 'BreadcrumbList',
        'itemListElement': [
            {'@type': 'ListItem', 'position': 1, 'name': 'خانه', 'item': f'{site}/'},
            {'@type': 'ListItem', 'position': 2, 'name': art.front.get('کلمهٔ کلیدی اصلی') or headline, 'item': url},
        ],
    }]
    steps_sec = art.section_by_key('steps')
    if steps_sec:
        steps = []
        for line in steps_sec.text_lines(art.lines):
            m = STEP_RE.match(line)
            if m:
                steps.append({'@type': 'HowToStep', 'name': clean_text(m.group(1)), 'text': clean_text(m.group(2)) or clean_text(m.group(1))})
        if len(steps) >= 2:
            graph.append({'@type': 'HowTo', 'name': clean_text(steps_sec.title), 'description': art.front.get('چکیده', ''), 'step': steps})
    if art.faq:
        graph.append({'@type': 'FAQPage', 'mainEntity': [
            {'@type': 'Question', 'name': clean_text(q), 'acceptedAnswer': {'@type': 'Answer', 'text': clean_text(a)}}
            for q, a, _ in art.faq
        ]})
    return {'@context': 'https://schema.org', '@graph': graph}


def render_block(obj: dict) -> str:
    return '## اسکیما برای پیاده‌سازی\n\n```json\n' + json.dumps(obj, ensure_ascii=False, indent=2) + '\n```\n'


def write_into(path: str, cfg: dict) -> str:
    art = am.parse(path)
    obj = build(art, cfg)
    lines = art.lines
    if art.schema_section_line >= 0:
        new_lines = lines[:art.schema_section_line] + render_block(obj).split('\n')
    else:
        new_lines = lines + [''] + render_block(obj).split('\n')
    text = '\n'.join(new_lines)
    text = re.sub(r'\n{3,}', '\n\n', text).rstrip('\n') + '\n'
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write(text)
    types = [n['@type'] for n in obj['@graph']]
    return '، '.join(types)


def main(argv):
    cfg = json.load(open(os.path.join(HERE, 'lint-config.json'), encoding='utf-8'))
    write = False
    if argv and argv[0] == '--write':
        write, argv = True, argv[1:]
    for p in argv:
        if write:
            print(f'{p}: اسکیما نوشته شد ({write_into(p, cfg)})')
        else:
            print(json.dumps(build(am.parse(p), cfg), ensure_ascii=False, indent=2))
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
