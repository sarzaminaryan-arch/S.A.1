#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
article_model.py — مدل سادهٔ یک مقالهٔ مارک‌داون این مجموعه (بدون وابستگی خارجی).

ساختار موردانتظار (ARTICLE-STANDARD.md نسخهٔ ۱٫۱):
  <!-- بلوک سئو … key: value … -->   ← فقط برای ویراستار
  # عنوان
  **نویسنده:** … | **آخرین به‌روزرسانی:** … | [روش تهیهٔ مقالات](/editorial-policy)
  > **پاسخ سریع:** …
  > ### ⚠️ هشدار اصلی
  ## مقدمه … (بخش‌های H2 به ترتیب ثابت)
  ## اسکیما برای پیاده‌سازی  + ```json … ```
"""
import json
import re
from dataclasses import dataclass, field
from typing import Dict, List, Optional, Tuple

PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹'
ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩'
_TO_PERSIAN = {ord(c): PERSIAN_DIGITS[i] for i, c in enumerate('0123456789')}
_TO_PERSIAN.update({ord(c): PERSIAN_DIGITS[i] for i, c in enumerate(ARABIC_DIGITS)})

EMOJI_RE = re.compile(
    '[\U0001F000-\U0001FAFF\U00002600-\U000027BF\U0001F900-\U0001F9FF'
    '\U00002B00-\U00002BFF\U0001FA70-\U0001FAFF\u2300-\u23FF\u2B50\u2B55\u203C\u2049'
    '\u2122\u2139\u2194-\u21AA\u231A-\u231B\u25AA-\u25FE\u2934\u2935\u3030\u303D\u3297\u3299'
    '\uFE0F\u200D\U0001F1E6-\U0001F1FF]'
)
HEADING_RE = re.compile(r'^(#{1,6})\s+(.*?)\s*#*\s*$')
FENCE_RE = re.compile(r'^\s*(```|~~~)')
TABLE_SEP_RE = re.compile(r'^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$')
LINK_RE = re.compile(r'(?<!!)\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)')
BOLD_Q_RE = re.compile(r'^\*\*\s*([' + PERSIAN_DIGITS + r'0-9]+)[\.\-–)]\s*(.+?)\s*\*\*\s*$')
HEADING_Q_RE = re.compile(r'^#{3,4}\s+(?:س\s*[:：]\s*)?(.+?)\s*$')


def to_persian_digits(s: str) -> str:
    return s.translate(_TO_PERSIAN)


def strip_emoji(s: str) -> str:
    return EMOJI_RE.sub('', s).strip()


def norm_title(s: str) -> str:
    """عنوان بدون ایموجی، نشانه و ارقام؛ برای تطبیق کلیدواژه‌ای."""
    s = strip_emoji(s)
    s = re.sub(r'[\{\}\(\)\[\]«»"\'“”:：؛;,،\.!؟\?\-–—/|*_#]+', ' ', s)
    s = re.sub(r'[' + PERSIAN_DIGITS + ARABIC_DIGITS + r'0-9]+', ' ', s)
    return re.sub(r'\s+', ' ', s).strip()


@dataclass
class Section:
    level: int
    title: str
    start: int           # شمارهٔ خط تیتر (۰-مبنا)
    end: int             # خط پایان (exclusive)
    key: Optional[str] = None  # شناسهٔ استاندارد (intro, steps, faq, …)

    def text_lines(self, lines: List[str]) -> List[str]:
        return lines[self.start + 1:self.end]


@dataclass
class Paragraph:
    line: int            # شمارهٔ خط (۰-مبنا)
    text: str
    kind: str            # para | list | quote | faq_a | faq_q
    section: Optional[str] = None


@dataclass
class Article:
    path: str
    text: str
    lines: List[str] = field(default_factory=list)
    front: Dict[str, str] = field(default_factory=dict)
    front_span: Tuple[int, int] = (0, 0)      # [start, end) خطوط بلوک سئو
    h1: Optional[str] = None
    h1_line: int = -1
    byline_line: int = -1
    sections: List[Section] = field(default_factory=list)
    schema_section_line: int = -1
    schema_json: Optional[str] = None
    schema_span: Tuple[int, int] = (-1, -1)   # خطوط داخل فنس json
    schema_obj: object = None
    schema_error: Optional[str] = None
    faq: List[Tuple[str, str, int]] = field(default_factory=list)   # (q, a, line)
    tables: List[Tuple[int, int]] = field(default_factory=list)     # (line, ncols)
    links: List[Tuple[int, str, str]] = field(default_factory=list) # (line, text, href)
    paragraphs: List[Paragraph] = field(default_factory=list)
    fences: List[Tuple[int, int, str]] = field(default_factory=list) # (start, end, lang)

    # ----- کمکی‌ها -----
    def body_range(self) -> Tuple[int, int]:
        """خطوط بدنهٔ قابل‌نمایش: از H1 تا پیش از بخش اسکیما."""
        start = self.h1_line if self.h1_line >= 0 else self.front_span[1]
        end = self.schema_section_line if self.schema_section_line >= 0 else len(self.lines)
        return start, end

    def in_fence(self, i: int) -> bool:
        return any(s <= i <= e for s, e, _ in self.fences)

    def section_by_key(self, key: str) -> Optional[Section]:
        for s in self.sections:
            if s.key == key:
                return s
        return None

    def body_text(self) -> str:
        a, b = self.body_range()
        out = []
        for i in range(a, b):
            if self.in_fence(i):
                continue
            out.append(self.lines[i])
        return '\n'.join(out)


# شناسهٔ بخش‌ها بر پایهٔ کلیدواژهٔ تیتر H2 (ترتیب = ترتیب استاندارد)
SECTION_KEYS: List[Tuple[str, List[str]]] = [
    ('intro',    ['مقدمه']),
    ('prereq',   ['پیش‌نیاز', 'پیش نیاز', 'پیشنیاز']),
    ('table',    ['جدول', 'مقایسه', 'ارزیابی', 'کدام', 'انتخاب سریع']),
    ('steps',    ['گام‌به‌گام', 'گام به گام', 'قدم‌به‌قدم', 'قدم به قدم', 'مرحله', 'مراحل', 'روش', 'آموزش', 'چطور', 'چگونه', 'دسته‌بندی']),
    ('iran',     ['ایران']),
    ('safety',   ['نکات ایمنی', 'ایمنی']),
    ('myths',    ['باور']),
    ('trouble',  ['عیب‌یابی', 'عیب یابی', 'به مشکل', 'مشکل', 'علائم خطر', 'نشانه‌های خطر', 'اشتباه پیش', 'خراب شد', 'جواب نداد']),
    ('prevent',  ['پیشگیری', 'کیت']),
    ('faq',      ['پرسش', 'سوال', 'سؤال']),
    ('checklist', ['چک‌لیست', 'چک لیست', 'جمع‌بندی', 'جمع بندی']),
    ('links',    ['لینک', 'پیوند']),
    ('sources',  ['منابع', 'منبع']),
    ('closing',  ['پیام پایانی', 'حرف آخر', 'کلام آخر']),
    ('schema',   ['اسکیما', 'schema']),
]
SECTION_ORDER = [k for k, _ in SECTION_KEYS]
SECTION_LABELS = {
    'intro': 'مقدمه', 'prereq': 'پیش‌نیازها', 'table': 'جدول تصمیم/مقایسه', 'steps': 'روش گام‌به‌گام',
    'iran': 'بومی‌سازی برای ایران', 'safety': 'نکات ایمنی', 'myths': 'باور غلط / واقعیت',
    'trouble': 'عیب‌یابی و علائم خطر', 'prevent': 'پیشگیری و کیت', 'faq': 'پرسش‌های رایج',
    'checklist': 'چک‌لیست جمع‌بندی', 'links': 'لینک‌های مرتبط', 'sources': 'منابع',
    'closing': 'پیام پایانی', 'schema': 'اسکیما',
}


def classify_heading(title: str, taken: set) -> Optional[str]:
    t = norm_title(title)
    for key, kws in SECTION_KEYS:
        if key in taken:
            continue
        for kw in kws:
            if kw in t:
                # «ایمنی» نباید بخش «نکات ایمنی» را با «عیب‌یابی ایمنی» اشتباه بگیرد؛ ترتیب فهرست این را حل می‌کند.
                return key
    return None


def parse(path: str, text: Optional[str] = None) -> Article:
    if text is None:
        with open(path, encoding='utf-8') as fh:
            text = fh.read()
    art = Article(path=path, text=text)
    lines = text.replace('\r\n', '\n').split('\n')
    art.lines = lines

    # ---- بلوک سئو (کامنت HTML در ابتدای فایل)
    i = 0
    while i < len(lines) and not lines[i].strip():
        i += 1
    if i < len(lines) and lines[i].lstrip().startswith('<!--'):
        start = i
        j = i
        while j < len(lines) and '-->' not in lines[j]:
            j += 1
        end = min(j + 1, len(lines))
        art.front_span = (start, end)
        for raw in lines[start:end]:
            raw = raw.replace('<!--', '').replace('-->', '').strip()
            m = re.match(r'^([^:：]{2,40})[:：]\s*(.*)$', raw)
            if m:
                art.front[m.group(1).strip()] = m.group(2).strip()
        i = end

    # ---- فنس‌ها
    in_fence = False
    f_start = -1
    f_lang = ''
    for n, line in enumerate(lines):
        m = FENCE_RE.match(line)
        if m:
            if not in_fence:
                in_fence, f_start = True, n
                f_lang = line.strip()[3:].strip().lower()
            else:
                art.fences.append((f_start, n, f_lang))
                in_fence = False
    if in_fence:
        art.fences.append((f_start, len(lines) - 1, f_lang + ' (بسته‌نشده)'))

    # ---- تیترها و بخش‌ها
    heads: List[Tuple[int, int, str]] = []
    for n, line in enumerate(lines):
        if art.in_fence(n) or n < art.front_span[1]:
            continue
        m = HEADING_RE.match(line)
        if m:
            heads.append((n, len(m.group(1)), m.group(2).strip()))
    for n, lvl, title in heads:
        if lvl == 1 and art.h1 is None:
            art.h1, art.h1_line = title, n
    taken: set = set()
    h2s = [(n, lvl, t) for n, lvl, t in heads if lvl == 2]
    for idx, (n, lvl, title) in enumerate(h2s):
        end = h2s[idx + 1][0] if idx + 1 < len(h2s) else len(lines)
        sec = Section(level=lvl, title=title, start=n, end=end)
        key = classify_heading(title, taken)
        if key:
            sec.key = key
            taken.add(key)
            if key == 'schema':
                art.schema_section_line = n
        art.sections.append(sec)

    if art.schema_section_line < 0:
        for n, lvl, title in heads:
            if lvl >= 3 and any(k in norm_title(title).lower() for k in ('اسکیما', 'schema')):
                art.schema_section_line = n
                for sec in art.sections:
                    if sec.start < n < sec.end:
                        sec.end = n
                break

    # ---- بایلاین
    if art.h1_line >= 0:
        for n in range(art.h1_line + 1, min(art.h1_line + 6, len(lines))):
            if 'نویسنده' in lines[n]:
                art.byline_line = n
                break

    # ---- اسکیما (آخرین فنس json پس از تیتر اسکیما، یا آخرین فنس json فایل)
    json_fences = [(s, e, l) for s, e, l in art.fences if l.startswith('json')]
    if art.schema_section_line >= 0:
        json_fences = [f for f in json_fences if f[0] > art.schema_section_line] or json_fences
    if json_fences:
        s, e, _ = json_fences[-1]
        art.schema_span = (s + 1, e)
        art.schema_json = '\n'.join(lines[s + 1:e])
        try:
            art.schema_obj = json.loads(art.schema_json)
        except Exception as exc:  # noqa: BLE001
            art.schema_error = str(exc)

    # ---- جدول‌ها، لینک‌ها، پرسش‌ها، بندها
    body_a, body_b = art.body_range()
    faq_sec = art.section_by_key('faq')
    cur_q: Optional[Tuple[str, int]] = None
    cur_a: List[str] = []

    def flush_q():
        nonlocal cur_q, cur_a
        if cur_q:
            art.faq.append((cur_q[0], ' '.join(x.strip() for x in cur_a if x.strip()), cur_q[1]))
        cur_q, cur_a = None, []

    sec_of_line: Dict[int, str] = {}
    for sec in art.sections:
        for n in range(sec.start, sec.end):
            sec_of_line[n] = sec.key or ''

    n = body_a
    while n < body_b:
        line = lines[n]
        if art.in_fence(n):
            n += 1
            continue
        if line.strip().startswith('|') and n + 1 < len(lines) and TABLE_SEP_RE.match(lines[n + 1] or ''):
            ncols = len([c for c in line.strip().strip('|').split('|')])
            art.tables.append((n, ncols))
            n += 2
            while n < body_b and lines[n].strip().startswith('|'):
                n += 1
            continue
        for m in LINK_RE.finditer(line):
            art.links.append((n, m.group(1), m.group(2)))
        if HEADING_RE.match(line):
            if faq_sec and faq_sec.start < n < faq_sec.end:
                mq = HEADING_Q_RE.match(line)
                if mq:
                    flush_q()
                    cur_q = (mq.group(1).strip(), n)
            n += 1
            continue
        if faq_sec and faq_sec.start < n < faq_sec.end:
            mq = BOLD_Q_RE.match(line.strip())
            if mq:
                flush_q()
                cur_q = (mq.group(2).strip(), n)
                n += 1
                continue
            if cur_q and line.strip():
                cur_a.append(line)
                art.paragraphs.append(Paragraph(n, line.strip(), 'faq_a', 'faq'))
                n += 1
                continue
        s = line.strip()
        if s:
            kind = 'para'
            if re.match(r'^([-*+]|\d+[\.)]|[' + PERSIAN_DIGITS + r']+[\.)])\s+', s):
                kind = 'list'
            elif s.startswith('>'):
                kind = 'quote'
            art.paragraphs.append(Paragraph(n, s, kind, sec_of_line.get(n)))
        n += 1
    flush_q()
    return art
