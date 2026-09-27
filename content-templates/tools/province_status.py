#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
province_status.py — آمار مختصر ۳۱ استان (کدام نوشته شده، هر کدام چند واژه، FAQ، منابع، تصویر، افزونه).

    python3 content-templates/tools/province_status.py            # چاپ جدول + نوشتن content/provinces/STATUS.md + به‌روزرسانی content/README.md
    python3 content-templates/tools/province_status.py --print    # فقط چاپ (بدون نوشتن فایل)

ترتیب و نام‌ها از فهرست ثابت قالب (data/provinces.php) خوانده می‌شود؛ هیچ نامکی تغییر نمی‌کند.
شمارش واژه: «متن» = بلوک ۳ بدون جدول‌ها، ارجاع‌های بالانویس، نشان‌های بررسی، توضیح‌های HTML و علامت‌های تیتر
(همان رقمی که در گزارش‌ها می‌آید)؛ «ماشینی» = split ساده‌ی کل بلوک ۳ (رقمی که Rank Math/seo_audit می‌بیند).
"""
import json
import os
import re
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
PROVINCES_PHP = os.path.join(ROOT, 'wp-content/themes/sarzaminaryan-child/data/provinces.php')
CONTENT_DIR = os.path.join(ROOT, 'content/provinces')
IMG_DIR = os.path.join(ROOT, 'assets/featured/provinces')
PLUGIN_DIR = os.path.join(ROOT, 'wp-content/plugins')
STATUS_MD = os.path.join(CONTENT_DIR, 'STATUS.md')
README_MD = os.path.join(ROOT, 'content/README.md')
START, END = '<!-- PROVINCE-STATUS:START -->', '<!-- PROVINCE-STATUS:END -->'

FA_DIGITS = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')


def fa(n):
    if isinstance(n, int):
        return format(n, ',').replace(',', '٬').translate(FA_DIGITS)
    return str(n).translate(FA_DIGITS)


def provinces():
    text = open(PROVINCES_PHP, encoding='utf-8').read()
    rows = re.findall(r"'slug'\s*=>\s*'([^']+)'.*?'name'\s*=>\s*'([^']+)'.*?'en'\s*=>\s*'([^']+)'.*?'center'\s*=>\s*'([^']+)'", text)
    return [dict(order=i + 1, slug=s, name=n, en=e, center=c) for i, (s, n, e, c) in enumerate(rows)]


def block(text, n):
    m = re.search(r'=== BLOCK %d:[^\n]*===\n(.*?)(?==== BLOCK \d+:|\Z)' % n, text, flags=re.S)
    return m.group(1) if m else ''


def prose_words(body):
    b = re.sub(r'<!--.*?-->', ' ', body, flags=re.S)
    b = re.sub(r'<sup>.*?</sup>', ' ', b, flags=re.S)
    b = re.sub(r'\[(نیازمند بررسی|منبع لازم)[^\]]*\]', ' ', b)
    b = '\n'.join(l for l in b.split('\n') if not l.lstrip().startswith('|'))
    b = re.sub(r'^#+\s*', '', b, flags=re.M)
    b = re.sub(r'[*_`>]+', ' ', b)
    return len(b.split())


def article_stats(path):
    text = open(path, encoding='utf-8').read()
    b3, b4, b5, b9 = block(text, 3), block(text, 4), block(text, 5), block(text, 9)
    m = re.search(r'تولید:\s*([۰-۹0-9/]+)', text)
    faq = len(re.findall(r'^- q:', b4, flags=re.M))
    src_lines = [l for l in re.split(r'\n---\n', b5.split('```')[1] if '```' in b5 else b5)[0].split('\n') if l.count('|') >= 3]
    return dict(
        words=prose_words(b3),
        words_machine=len(b3.split()),
        h2=len(re.findall(r'^## ', b3, flags=re.M)),
        h3=len(re.findall(r'^### ', b3, flags=re.M)),
        faq=faq,
        sources=len(src_lines),
        review=len(re.findall(r'\[نیازمند بررسی', b3)),
        source_needed=len(re.findall(r'\[منبع لازم', b3)),
        produced=m.group(1) if m else '—',
        publish='DRAFT ONLY' if 'DRAFT ONLY' in b9 else ('READY' if 'READY' in b9 else '—'),
        size_kb=os.path.getsize(path) // 1024,
    )


def plugin_state(slug, order):
    batch = min(3, (order - 1) // 10 + 1)  # b01 = 1–10, b02 = 11–20, b03 = 21–31
    pid = 'b%02d' % batch
    manifest = os.path.join(PLUGIN_DIR, 'sa-province-importer-%s' % pid, 'data', 'manifest.json')
    if not os.path.exists(manifest):
        return pid, 'هنوز ساخته نشده'
    for row in json.load(open(manifest, encoding='utf-8')).get('provinces', []):
        if row.get('slug') == slug:
            if row.get('status') == 'article':
                return pid, 'مقاله + تصویر' if row.get('has_image') else 'مقاله (بی‌تصویر)'
            return pid, 'فقط تصویر' if row.get('has_image') else 'خالی'
    return pid, '—'


def build():
    rows = []
    for p in provinces():
        path = os.path.join(CONTENT_DIR, p['slug'] + '.md')
        img = os.path.exists(os.path.join(IMG_DIR, p['slug'] + '.webp'))
        pid, pstate = plugin_state(p['slug'], p['order'])
        st = article_stats(path) if os.path.exists(path) else None
        rows.append(dict(p, image=img, plugin=pid, plugin_state=pstate, stats=st))
    return rows


def table(rows):
    written = [r for r in rows if r['stats']]
    nxt = next((r for r in rows if not r['stats']), None)
    total_words = sum(r['stats']['words'] for r in written)
    out = []
    out.append('| # | استان | نامک | مقاله | واژه (متن) | H2 | FAQ | منابع | نشان بررسی | تصویر شاخص | افزونه‌ی درون‌ریز |')
    out.append('|---|---|---|---|---|---|---|---|---|---|---|')
    for r in rows:
        s = r['stats']
        if s:
            art = '✅ %s — %s' % (s['publish'], s['produced'])
            words = fa(s['words'])
            h2 = fa(s['h2'])
            faq = fa(s['faq'])
            src = fa(s['sources'])
            mk = fa(s['review'] + s['source_needed'])
        else:
            art = '⏳ بعدی' if (nxt and nxt['slug'] == r['slug']) else '— در نوبت'
            words = h2 = faq = src = mk = '—'
        img = '✅' if r['image'] else '✗ لازم'
        out.append('| %s | %s | `%s` | %s | %s | %s | %s | %s | %s | %s | %s: %s |' % (
            fa(r['order']), r['name'], r['slug'], art, words, h2, faq, src, mk, img, r['plugin'], r['plugin_state']))
    n_img = sum(1 for r in rows if r['image'])
    summary = ('**جمع:** %s از ۳۱ استان نوشته شده (%s واژه‌ی متن در مجموع، میانگین %s واژه) · '
               'تصویر شاخص آماده: %s از ۳۱ · بعدی در نوبت: %s'
               % (fa(len(written)), fa(total_words), fa(total_words // len(written)) if written else '۰',
                  fa(n_img), ('**%s** (`%s`)' % (nxt['name'], nxt['slug'])) if nxt else '— (همه نوشته شده)'))
    return '\n'.join(out), summary


def main():
    rows = build()
    tbl, summary = table(rows)
    from datetime import date
    body = ('## آمار مختصر ۳۱ استان\n\n'
            '_تولید خودکار با `python3 content-templates/tools/province_status.py` — ترتیب = فهرست ثابت `data/provinces.php` · '
            'ستون «واژه (متن)» = بلوک ۳ بدون جدول/ارجاع/نشان · «نشان بررسی» = [نیازمند بررسی] + [منبع لازم] در متن مقاله (بلوک ۳) · '
            'به‌روزرسانی: %s_\n\n%s\n\n%s\n' % (date.today().isoformat(), tbl, summary))
    print(body)
    if '--print' in sys.argv:
        return
    open(STATUS_MD, 'w', encoding='utf-8').write('# وضعیت تولید مقاله‌های استان‌ها\n\n' + body)
    readme = open(README_MD, encoding='utf-8').read()
    chunk = '%s\n%s%s' % (START, body, END)
    if START in readme and END in readme:
        readme = readme[:readme.index(START)] + chunk + readme[readme.index(END) + len(END):]
    else:
        readme = readme.rstrip('\n') + '\n\n' + chunk + '\n'
    open(README_MD, 'w', encoding='utf-8').write(readme)


if __name__ == '__main__':
    main()
