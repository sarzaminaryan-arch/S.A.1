#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
gdoc_convert.py — تبدیل پیش‌نویسِ ۹بخشی (تمپلیت Drive مالک؛ خروجی Qwen/Google Docs) به قالب ۲۰بندی ARTICLE-STANDARD v1.1.

ورودی:  پیش‌نویس (خام یا نرمال‌شده) + فایل مکمل (supplement) با بخش‌های «## key».
خروجی: مقالهٔ کامل با بلوک سئو، بایلاین، پاسخ سریع، هشدار، مقدمه، پیش‌نیاز، جدول، روش گام‌به‌گام،
        بومی‌سازی، ایمنی، باورهای غلط، عیب‌یابی، پیشگیری، FAQ، چک‌لیست، لینک‌ها، منابع، پیام پایانی،
        شفافیت و اسکیمای تولیدشده از متن.

کلیدهای مکمل (همه اختیاری مگر آن‌که گفته شود):
  h1, title (عنوان سئو جایگزین)، meta (توضیح متای جایگزین)، risk*, quick*, warn_title*, warn*, intro*, prereq*, table_title, table*, steps_title,
  steps_meta* (سطرهای «مناسب برای:/لازم داری:/زمان:/هشدار:/تصویر:»), howto (yes|no),
  iran*, safety (موارد شماره‌دار اضافه), myths (جفت‌های باور غلط/واقعیت اضافه), trouble*, prevent*,
  faq_extra, links* (هر سطر یک نامک، اختیاری « — توضیح»), sources*, closing, drop (الگوهای regex؛ هر سطر یکی،
  برای حذف جمله/بند نامرتبط), replace (سطرهای «قدیم => جدید»)

کاربرد:
  python3 gdoc_convert.py --supplement SUPP.md IN.md OUT.md
"""
import json
import os
import re
import sys
from typing import Dict, List, Optional, Tuple

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import article_model as am  # noqa: E402
import schema_build as sb  # noqa: E402
from gdoc_normalize import normalize as gdoc_normalize  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
TODAY_FA = '۱۹ مهر ۱۴۰۵'

SEO_KEYS = [
    ('عنوان سئو', 'عنوان سئو'), ('متا توضیحات', 'توضیح متا'), ('کلمه کلیدی اصلی', 'کلمهٔ کلیدی اصلی'),
    ('کلمات کلیدی فرعی', 'کلمات کلیدی فرعی'), ('نامک', 'نامک'), ('اسکیما', 'اسکیما'), ('چکیده', 'چکیده'),
]


def read_supplement(path: str) -> Dict[str, str]:
    sup: Dict[str, str] = {}
    key = None
    buf: List[str] = []
    for line in open(path, encoding='utf-8').read().split('\n'):
        m = re.match(r'^## ([a-z_0-9]+)\s*$', line)
        if m:
            if key:
                sup[key] = '\n'.join(buf).strip('\n')
            key, buf = m.group(1), []
        elif key:
            buf.append(line)
    if key:
        sup[key] = '\n'.join(buf).strip('\n')
    return sup


def split_h2(lines: List[str], start: int, end: int) -> List[Tuple[str, int, int]]:
    heads = [i for i in range(start, end) if lines[i].startswith('## ')]
    out = []
    for k, i in enumerate(heads):
        j = heads[k + 1] if k + 1 < len(heads) else end
        out.append((lines[i][3:].strip(), i, j))
    return out


def kind_of(title: str) -> str:
    t = am.norm_title(title)
    if any(k in t for k in ('اسکیما', 'schema')):
        return 'schema'
    if any(k in t for k in ('سوال', 'سؤال', 'پرسش')):
        return 'faq'
    if any(k in t for k in ('جمع‌بندی', 'چک‌لیست', 'چک لیست', 'جمع بندی')):
        return 'summary'
    if any(k in t for k in ('لینک', 'پیوند')):
        return 'links'
    if any(k in t for k in ('پیام پایانی', 'حرف آخر', 'کلام آخر')):
        return 'closing'
    if any(k in t for k in ('نکات مهم', 'هشدار')):
        return 'warnings'
    return 'content'


def strip_blank_edges(block: List[str]) -> List[str]:
    while block and not block[0].strip():
        block = block[1:]
    while block and not block[-1].strip():
        block = block[:-1]
    return block


def tidy(block: List[str]) -> List[str]:
    """حذف خطوط «---» و خطوط خالی تکراری."""
    out: List[str] = []
    for l in block:
        if l.strip() == '---':
            continue
        if not l.strip() and out and not out[-1].strip():
            continue
        out.append(l.rstrip())
    return strip_blank_edges(out)


def demote(block: List[str]) -> List[str]:
    out = []
    for l in block:
        m = am.HEADING_RE.match(l)
        if m and len(m.group(1)) <= 5:
            out.append('#' * (len(m.group(1)) + 1) + ' ' + m.group(2))
        else:
            out.append(l)
    return out


def numbered_step(line: str) -> str:
    """«### ۱. عنوان» (زیرتیتر مرحله در پیش‌نویس) → «۱. **عنوان**» (قالب مرحلهٔ استاندارد)."""
    m = re.match(r'^#{3,4}\s+(\d+|[۰-۹]+)[\.)]\s*(.+?)\s*$', line)
    if m:
        return f"{am.to_persian_digits(m.group(1))}. **{m.group(2).strip()}**"
    return line


def clean_heading_line(l: str) -> str:
    m = am.HEADING_RE.match(l)
    if not m:
        return l
    title = am.strip_emoji(m.group(2))
    title = re.sub(r'\s*\{#[^}]+\}\s*$', '', title)
    title = re.sub(r'\s*\((قدم به قدم|قدم‌به‌قدم|گام به گام|FAQ|چک‌لیست ذهنی)\)\s*', '', title).strip()
    return f"{m.group(1)} {title}"


def convert(src_text: str, sup: Dict[str, str], cfg: dict) -> str:
    text = gdoc_normalize(src_text) if re.search(r'^\s*\\#|<br\s*/?>', src_text, re.M) else src_text
    lines = text.split('\n')

    # ---- بلوک سئوی Qwen
    front: Dict[str, str] = {}
    h1_idx = next((i for i, l in enumerate(lines) if l.startswith('# ')), None)
    if h1_idx is None:
        raise SystemExit('H1 پیدا نشد')
    for l in lines[:h1_idx]:
        m = re.match(r'^-?\s*\*\*(.+?)\*\*\s*(.*)$', l.strip())
        if not m:
            continue
        label, value = m.group(1), m.group(2).strip()
        for needle, key in SEO_KEYS:
            if needle in label:
                front[key] = value
    if sup.get('title'):
        front['عنوان سئو'] = sup['title'].strip()
    if sup.get('meta'):
        front['توضیح متا'] = sup['meta'].strip()
    h1 = sup.get('h1') or am.strip_emoji(lines[h1_idx][2:])
    slug = front.get('نامک', '').strip()
    if not slug:
        raise SystemExit('نامک در بلوک سئو نیست')

    # ---- بخش‌های Qwen
    secs = split_h2(lines, h1_idx + 1, len(lines))
    first_h2 = secs[0][1] if secs else len(lines)
    qwen_intro = tidy(lines[h1_idx + 1:first_h2])
    content_blocks: List[Tuple[str, List[str]]] = []
    warnings_block: List[str] = []
    faq_block: List[str] = []
    summary_block: List[str] = []
    closing_block: List[str] = []
    for title, s, e in secs:
        k = kind_of(title)
        body = tidy(lines[s + 1:e])
        # اسکیمای H3 داخل پیام پایانی
        cut = next((i for i, l in enumerate(body) if re.match(r'^#{3,6}\s', l) and kind_of(l.lstrip('# ')) == 'schema'), None)
        if cut is not None:
            body = tidy(body[:cut])
        if k == 'content':
            content_blocks.append((am.strip_emoji(title), body))
        elif k == 'warnings':
            warnings_block = body
        elif k == 'faq':
            faq_block = body
        elif k == 'summary':
            summary_block = body
        elif k == 'closing':
            closing_block = body
        # links و schema بازسازی می‌شوند

    # ---- هشدارها و باورهای غلط را جدا کن
    myths: List[Tuple[str, str]] = []
    warns: List[str] = []
    i = 0
    wb = [l for l in warnings_block if l.strip()]
    while i < len(wb):
        l = wb[i].strip()
        if 'باور غلط' in l:
            myth = re.sub(r'^[-*]\s*', '', l)
            myth = re.sub(r'[❌⚠️💡]\s*', '', myth)
            myth = re.sub(r'^\**\s*باور غلط\s*[:：]?\s*\**\s*', '', myth).strip().strip('"«»“”').strip()
            fact = ''
            if i + 1 < len(wb) and 'واقعیت' in wb[i + 1]:
                fact = re.sub(r'^\s*[-*]?\s*\**\s*واقعیت\s*[:：]?\s*\**\s*', '', wb[i + 1]).strip()
                i += 1
            myth = f'«{myth}»' if myth and not myth.startswith('«') else myth
            myths.append((myth, fact))
        else:
            w = re.sub(r'^[-*]\s*', '', l)
            w = re.sub(r'[❌⚠️💡]\s*', '', w)
            w = re.sub(r'^\*\*(هشدار|نکته|توجه)[^*]*:\*\*\s*', '', w).strip()
            if w:
                warns.append(w)
        i += 1
    for pair in re.findall(r'\*\*باور غلط:\*\*\s*(.+?)\n+\*\*واقعیت:\*\*\s*(.+?)(?:\n|$)', sup.get('myths', '') + '\n', re.S):
        myths.append((pair[0].strip(), pair[1].strip()))
    extra_safety = [re.sub(r'^\s*(\d+|[۰-۹]+)[\.)]\s*', '', l).strip() for l in sup.get('safety', '').split('\n') if l.strip()]
    safety_items = warns + extra_safety

    # ---- روش گام‌به‌گام
    steps_lines: List[str] = []
    if content_blocks:
        first_title, first_body = content_blocks[0]
        steps_title = sup.get('steps_title') or first_title
        steps_title = re.sub(r'\s*\((قدم به قدم|قدم‌به‌قدم|گام به گام)\)\s*', '', steps_title).strip()
        steps_keys = dict(am.SECTION_KEYS)['steps']
        if not any(k in am.norm_title(steps_title) for k in steps_keys):
            steps_title = 'روش گام‌به‌گام: ' + steps_title
        steps_lines.append(f'## {steps_title}')
        steps_lines.append('')
        meta = [l.strip() for l in sup.get('steps_meta', '').split('\n') if l.strip()]
        for m in meta:
            mm = re.match(r'^([^:：]+)[:：]\s*(.*)$', m)
            if mm:
                steps_lines.append(f'**{mm.group(1).strip()}:** {mm.group(2).strip()}')
                steps_lines.append('')
        n = 0
        for l in first_body:
            cl = clean_heading_line(l)
            m3 = re.match(r'^#{3,4}\s+(.+?)\s*$', cl)
            if m3:
                n += 1
                title3 = re.sub(r'^(\d+|[۰-۹]+)[\.)]\s*', '', m3.group(1)).strip()
                steps_lines.append(f"{am.to_persian_digits(str(n))}. **{title3}**")
            else:
                steps_lines.append(cl)
        for title, body in content_blocks[1:]:
            steps_lines += ['', f'### {title}', ''] + [clean_heading_line(l) for l in demote(body)]
    # برچسب تصویر در انتها اگر در meta نبود
    if 'تصویر' not in '\n'.join(steps_lines):
        steps_lines += ['', '**تصویر:** طبق دستور فعلی، برای این مقاله تصویری لازم نیست.']

    # ---- FAQ (با امکان انتخاب و شماره‌گذاری دوباره)
    faq_lines = [clean_heading_line(l) for l in faq_block]
    keep = [int(x) for x in re.findall(r'\d+', am.to_persian_digits(sup.get('faq_keep', '')).translate({ord(c): str(i) for i, c in enumerate(am.PERSIAN_DIGITS)}))]
    if keep:
        groups: List[List[str]] = []
        for l in faq_lines:
            if am.BOLD_Q_RE.match(l.strip()):
                groups.append([l])
            elif groups:
                groups[-1].append(l)
        chosen = [g for n, g in enumerate(groups, 1) if n in keep]
        faq_lines = []
        for n, g in enumerate(chosen, 1):
            q = am.BOLD_Q_RE.match(g[0].strip())
            faq_lines.append(f'**{am.to_persian_digits(str(n))}. {q.group(2)}**')
            faq_lines += g[1:]
        faq_lines = tidy(faq_lines)
    if sup.get('faq_extra'):
        faq_lines += ['', sup['faq_extra'].strip()]

    # ---- چک‌لیست
    check_lines: List[str] = []
    for l in summary_block:
        s = l.rstrip()
        if not s.strip():
            check_lines.append('')
            continue
        if re.match(r'^(\d+|[۰-۹]+)[\.)]\s+', s) or re.match(r'^[-*]\s+', s):
            item = re.sub(r'^((\d+|[۰-۹]+)[\.)]|[-*])\s+', '', s)
            item = re.sub(r'^\[[ x]\]\s*', '', item)
            check_lines.append(f'- [ ] {item}')
        elif s.startswith('```'):
            continue
        else:
            check_lines.append(clean_heading_line(s))
    check_lines = tidy(check_lines)

    # ---- لینک‌ها
    link_lines: List[str] = []
    for l in sup.get('links', '').split('\n'):
        l = l.strip()
        if not l:
            continue
        m = re.match(r'^([a-z0-9-]+)(?:\s*[—-]+\s*(.+))?$', l)
        if not m:
            continue
        s_, note = m.group(1), m.group(2)
        title = cfg['allowed_slugs'].get(s_, s_)
        link_lines.append(f'- [{title}](/{s_})' + (f' — {note}' if note else ''))
    link_lines.append('- [هاب آموزش‌های کمپینگ](/)')

    # ---- پیام پایانی
    closing = tidy(sup['closing'].split('\n')) if sup.get('closing') else closing_block

    # ---- سرهم‌بندی
    status = 'پیش‌نویس'
    front_out = [
        '<!-- بلوک سئو — فقط برای ویراستار؛ در صفحه نمایش داده نشود',
        f'عنوان سئو: {front.get("عنوان سئو", h1)}',
        f'توضیح متا: {front.get("توضیح متا", "")}',
        f'کلمهٔ کلیدی اصلی: {front.get("کلمهٔ کلیدی اصلی", "")}',
        f'کلمات کلیدی فرعی: {front.get("کلمات کلیدی فرعی", "")}',
        f'نامک: {slug}',
        f'سطح ریسک: {sup.get("risk", "متوسط")}',
        'اسکیما: __SCHEMA_TYPES__',
        f'چکیده: {front.get("چکیده", "")}',
        f'وضعیت: {status}',
        '-->',
    ]
    out: List[str] = front_out + ['', f'# {h1}', '',
                                   f'**نویسنده:** {cfg["byline"]} | **آخرین به‌روزرسانی:** {TODAY_FA} | [روش تهیهٔ مقالات]({cfg["editorial_policy_path"]})', '',
                                   f'> **پاسخ سریع:** {sup["quick"].strip()}', '',
                                   f'> ### ⚠️ {sup["warn_title"].strip()}', f'> {sup["warn"].strip()}', '',
                                   '## مقدمه', '', sup['intro'].strip(), '',
                                   '## پیش‌نیازها و شرایط', '', sup['prereq'].strip(), '',
                                   f'## {sup.get("table_title", "جدول تصمیم سریع").strip()}', '', sup['table'].strip(), '']
    out += steps_lines + ['', '## بومی‌سازی برای ایران', '', sup['iran'].strip(), '', '## نکات ایمنی', '']
    for n, item in enumerate(safety_items, 1):
        out.append(f'{am.to_persian_digits(str(n))}. {item}')
        out.append('')
    out += ['## باورهای غلط', '']
    for myth, fact in myths:
        out += [f'**باور غلط:** {myth}', '', f'**واقعیت:** {fact}', '']
    out += ['## عیب‌یابی و علائم خطر', '', sup['trouble'].strip(), '',
            '## پیشگیری و کیت', '', sup['prevent'].strip(), '',
            '## پرسش‌های رایج', ''] + faq_lines + ['',
            '## چک‌لیست جمع‌بندی', ''] + check_lines + ['',
            '## لینک‌های مرتبط', ''] + link_lines + ['',
            '## منابع', '', sup['sources'].strip(), '',
            '## پیام پایانی', ''] + closing + ['', '---', '',
            '*این مقاله با کمک هوش مصنوعی تهیه و با فیلتر محتوای تحریریه بررسی شده است؛ تصمیم انتشار با مالک سایت است. [روش تهیهٔ مقالات](/editorial-policy)*', '',
            '*این مقاله جنبهٔ آموزشی دارد و جایگزین توصیهٔ پزشکی، راهنمای محلی، آموزش عملی یا دستور سازندهٔ محصولات نیست.*', '']
    result = '\n'.join(out)

    # ---- حذف/جایگزینی جمله‌های نامرتبط
    # drop: هر خطی از بدنه که با یکی از الگوها (regex) مطابقت کند، کامل حذف می‌شود
    for pat in [l.strip() for l in sup.get('drop', '').split('\n') if l.strip()]:
        rx = re.compile(pat)
        result = '\n'.join(l for l in result.split('\n') if not rx.search(l))
    for rl in [l for l in sup.get('replace', '').split('\n') if '=>' in l]:
        old, new = [x.strip() for x in rl.split('=>', 1)]
        if old:
            result = result.replace(old, new)
    result = re.sub(r'[ \t]+\n', '\n', result)
    result = re.sub(r'\n{3,}', '\n\n', result).strip('\n') + '\n'

    # ---- اسکیما از متن نهایی
    art = am.parse(slug + '.md', result)
    obj = sb.build(art, cfg)
    if sup.get('howto', 'no').strip().lower() != 'yes':
        obj['@graph'] = [n for n in obj['@graph'] if n.get('@type') != 'HowTo']
    types = '، '.join(n['@type'] for n in obj['@graph'])
    result = result.replace('__SCHEMA_TYPES__', types) + '\n' + sb.render_block(obj)
    return result


def main(argv):
    if len(argv) < 4 or argv[0] != '--supplement':
        print(__doc__)
        return 2
    cfg = json.load(open(os.path.join(HERE, 'lint-config.json'), encoding='utf-8'))
    sup = read_supplement(argv[1])
    src = open(argv[2], encoding='utf-8').read()
    out = convert(src, sup, cfg)
    with open(argv[3], 'w', encoding='utf-8') as fh:
        fh.write(out)
    print(f'نوشته شد: {argv[3]}')
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
