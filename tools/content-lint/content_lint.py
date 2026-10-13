#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
content_lint.py — فیلتر محتوای مقالات «آموزش کمپینگ و بقا» (جایگزین مرحلهٔ بازبینی انسانی).

چهار خانوادهٔ بررسی:
  T  انطباق با ARTICLE-STANDARD.md (ترتیب ۲۰گانه، بایلاین، FAQ، جدول، لینک‌ها، اسکیما)
  M  نشانه‌های ماشینی (نام مدل، مقدمه/مؤخرهٔ چت، جای‌خالی، مارک‌آپ باقی‌مانده، کلیشه، جملهٔ متا،
     اختلاط لحن، ایموجی در تیتر، حروف عربی/خط غیرفارسی، رقم لاتین، تکرار عینی)
  R  جمله/بندِ احتمالاً نامرتبط (هم‌پوشانی واژگانی با موضوع مقاله) + واژگان تجاری/خارج از حوزه
  S  مرزهای ایمنی (آتش، آب، امداد، نجات در آب) با تشخیص سادهٔ نفی

همهٔ یافته‌ها «هشدار» (warn) یا «اطلاع» (info) هستند؛ هیچ‌کدام انتشار را قفل نمی‌کند.
مقاله وقتی «آمادهٔ انتشار» است که هیچ warn باز نداشته باشد؛ تصمیم نهایی با مالک است.

کاربرد:
  python3 content_lint.py FILE.md [FILE2.md …]            گزارش متنی
  python3 content_lint.py --fix FILE.md                   اصلاح‌های مکانیکیِ امن و سپس گزارش
  python3 content_lint.py --format md|json DIR_OR_FILES   خروجی برای گزارش/CI
  python3 content_lint.py --check-ready …                 خروج غیرصفر فقط اگر مقالهٔ «آمادهٔ انتشار» هشدار دارد
"""
import argparse
import json
import os
import re
import sys
from collections import Counter
from dataclasses import dataclass, asdict
from typing import Dict, List, Optional, Tuple

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import article_model as am  # noqa: E402
from gdoc_normalize import normalize as gdoc_normalize  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_CONFIG = os.path.join(HERE, 'lint-config.json')

PD = am.PERSIAN_DIGITS
LATIN_DIGIT_RE = re.compile(r'[0-9]')
ARABIC_LETTERS = {'ي': 'ی', 'ك': 'ک', 'ة': 'ه', 'ۀ': 'هٔ', 'ى': 'ی'}
CJK_RE = re.compile(r'[\u4e00-\u9fff\u3040-\u30ff\uac00-\ud7af]')
LATIN_RUN_RE = re.compile(r"(?:\b[A-Za-z][A-Za-z'\-]*\b[\s,;:]*){6,}")
URL_RE = re.compile(r'https?://\S+')
INLINE_CODE_RE = re.compile(r'`[^`]*`')
SENT_SPLIT_RE = re.compile(r'(?<=[\.!\?؟؛])\s+')
TOKEN_RE = re.compile(r'[\u0600-\u06FF\u200c]+|[A-Za-z][A-Za-z\-]+')
JALALI_MONTHS = 'فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند'
DATE_FA_RE = re.compile(r'[' + PD + r']{1,2}\s+(' + JALALI_MONTHS + r')\s+[' + PD + r']{4}')
ISO_DATE_RE = re.compile(r'^\d{4}-\d{2}-\d{2}$')

CATEGORY_LABEL = {
    'T': 'انطباق با استاندارد', 'M': 'نشانه‌های ماشینی', 'R': 'ربط و حوزه', 'S': 'مرزهای ایمنی',
}


@dataclass
class Finding:
    code: str
    severity: str      # warn | info
    line: int          # ۱-مبنا؛ ۰ یعنی کل فایل
    message: str
    excerpt: str = ''

    @property
    def category(self) -> str:
        return self.code[0]


def load_config(path: str) -> dict:
    with open(path, encoding='utf-8') as fh:
        return json.load(fh)


# ----------------------------------------------------------------------------
# اصلاح‌های مکانیکی امن
# ----------------------------------------------------------------------------
def _persianize_digits_in_prose(line: str) -> str:
    """ارقام لاتین را فقط در متن آزاد به فارسی تبدیل می‌کند (نه در URL، لینک، کد درون‌خطی)."""
    parts: List[Tuple[str, bool]] = []  # (segment, convertible)
    pos = 0
    protect = sorted(
        [(m.start(), m.end()) for m in URL_RE.finditer(line)]
        + [(m.start(), m.end()) for m in INLINE_CODE_RE.finditer(line)]
        + [(m.start(2) - 1, m.end()) for m in am.LINK_RE.finditer(line)]  # بخش (href)
    )
    for a, b in protect:
        if a < pos:
            continue
        parts.append((line[pos:a], True))
        parts.append((line[a:b], False))
        pos = b
    parts.append((line[pos:], True))
    out = []
    for idx, (seg, conv) in enumerate(parts):
        if conv:
            if idx == 0:
                m0 = re.match(r'^(\s*\d+[\.)]\s+)', seg)
                if m0:  # شمارهٔ فهرست مرتب را لاتین نگه می‌داریم تا مارک‌داون فهرست بسازد
                    out.append(m0.group(1))
                    seg = seg[m0.end():]
            # اختصارهای لاتین مثل H1 یا CO2 را دست نمی‌زنیم
            seg = re.sub(r'(?<![A-Za-z])[0-9]+(?![A-Za-z])', lambda m: am.to_persian_digits(m.group(0)), seg)
        out.append(seg)
    return ''.join(out)


def apply_fixes(text: str, cfg: dict) -> Tuple[str, List[str]]:
    notes: List[str] = []
    if re.search(r'^\s*\\#|<br\s*/?>', text, re.M):
        text = gdoc_normalize(text)
        notes.append('خروجی Google Docs به مارک‌داون تمیز تبدیل شد (حذف <br> و فرارهای \\#، \\*)')
    # حروف عربی
    cnt = sum(text.count(k) for k in ARABIC_LETTERS)
    if cnt:
        for k, v in ARABIC_LETTERS.items():
            text = text.replace(k, v)
        notes.append(f'{am.to_persian_digits(str(cnt))} حرف عربی (ي/ك/ة) به فارسی تبدیل شد')
    lines = text.split('\n')
    art = am.parse('<mem>', text)
    changed_digits = 0
    changed_heads = 0
    for i, line in enumerate(lines):
        if art.in_fence(i):
            continue
        if art.front_span[0] <= i < art.front_span[1]:
            if re.match(r'^\s*(نامک|slug)\s*[:：]', line, re.I):
                continue
        m = am.HEADING_RE.match(line)
        if m:
            new_title = am.strip_emoji(m.group(2))
            new_title = re.sub(r'\s*\{#[^}]+\}\s*$', '', new_title)
            new_title = re.sub(r'\s{2,}', ' ', new_title).strip()
            if new_title != m.group(2).strip():
                changed_heads += 1
            lines[i] = f"{m.group(1)} {new_title}"
            line = lines[i]
        new_line = _persianize_digits_in_prose(line)
        if new_line != line:
            changed_digits += 1
            lines[i] = new_line
    if changed_digits:
        notes.append(f'ارقام لاتین در {am.to_persian_digits(str(changed_digits))} خط به فارسی تبدیل شد')
    if changed_heads:
        notes.append(f'ایموجی/شناسهٔ لنگر از {am.to_persian_digits(str(changed_heads))} تیتر حذف شد')
    text = '\n'.join(l.rstrip() for l in lines)
    text = re.sub(r'\n{3,}', '\n\n', text).strip('\n') + '\n'
    return text, notes


# ----------------------------------------------------------------------------
# ابزارهای متن
# ----------------------------------------------------------------------------
def words_of(text: str) -> List[str]:
    return [w for w in re.findall(r'[\u0600-\u06FF\u200c]+|[A-Za-z0-9][A-Za-z0-9\-]*', text)]


_SUFFIXES = ('\u200cهایی', '\u200cهای', '\u200cها', 'هایی', 'های', 'ها', '\u200cای', '\u200cتر', '\u200cترین')


def stem(tok: str) -> str:
    t = tok.lower().strip('\u200c')
    for suf in _SUFFIXES:
        if t.endswith(suf) and len(t) - len(suf) >= 3:
            t = t[: -len(suf)]
            break
    if len(t) > 4 and t.endswith('ی'):
        t = t[:-1]
    return t


def content_tokens(text: str, stop: set) -> List[str]:
    toks = []
    for t in TOKEN_RE.findall(text):
        if len(t) < 2:
            continue
        s = stem(t)
        if t in stop or s in stop or len(s) < 2:
            continue
        toks.append(s)
    return toks


def excerpt(s: str, n: int = 110) -> str:
    s = re.sub(r'\s+', ' ', s).strip()
    return s if len(s) <= n else s[: n - 1] + '…'


# ----------------------------------------------------------------------------
# بررسی‌ها
# ----------------------------------------------------------------------------
class Linter:
    def __init__(self, cfg: dict):
        self.cfg = cfg
        self.lim = cfg['limits']
        self.stop = set(cfg.get('stopwords', []))
        self.machine = {k: [re.compile(p, re.M) for p in v] for k, v in cfg['machine_patterns'].items()}
        self.findings: List[Finding] = []
        self._per_code: Counter = Counter()

    # ---- ثبت
    def add(self, code: str, line: int, message: str, exc: str = '', severity: str = 'warn'):
        self._per_code[code] += 1
        if self._per_code[code] > self.lim['max_findings_per_code']:
            if self._per_code[code] == self.lim['max_findings_per_code'] + 1:
                self.findings.append(Finding(code, severity, 0, f'… موارد بیشتری از همین نوع وجود دارد (فقط {am.to_persian_digits(str(self.lim["max_findings_per_code"]))} مورد اول نمایش داده شد)'))
            return
        self.findings.append(Finding(code, severity, line + 1 if line >= 0 else 0, message, excerpt(exc) if exc else ''))

    # ---- اجرای همه
    def run(self, art: am.Article) -> List[Finding]:
        self.findings = []
        self._per_code = Counter()
        self.art = art
        self.body_a, self.body_b = art.body_range()
        self.body_words = words_of(art.body_text())
        self.check_front()
        self.check_head()
        self.check_order_and_sections()
        self.check_tables_links()
        self.check_faq_and_schema()
        self.check_machine_marks()
        self.check_relevance()
        self.check_safety()
        self.check_status()
        return self.findings

    # ---- T: بلوک سئو
    def check_front(self):
        art, cfg = self.art, self.cfg
        if not art.front:
            self.add('T01', 0, 'بلوک سئوی ویراستار (کامنت HTML با کلیدهای عنوان سئو، توضیح متا، نامک…) در ابتدای فایل نیست')
            return
        for key in cfg['front_required']:
            if key not in art.front or not art.front[key]:
                alt = [k for k in art.front if am.norm_title(k) == am.norm_title(key)]
                if not alt:
                    self.add('T01', art.front_span[0], f'کلید «{key}» در بلوک سئو نیست یا خالی است')
        for key in cfg['front_outdated']:
            if key in art.front:
                self.add('T01', art.front_span[0], f'کلید قدیمی «{key}» (برگهٔ بازبینی انسانی حذف شده است)؛ به «وضعیت» تغییر بده')
        for key, lim, what in (('عنوان سئو', 60, 'عنوان سئو'), ('توضیح متا', 160, 'توضیح متا')):
            v = art.front.get(key, '')
            if v and len(v) > lim:
                self.add('T01', art.front_span[0], f'{what} {am.to_persian_digits(str(len(v)))} نویسه است؛ بیش از {am.to_persian_digits(str(lim))} در نتایج جست‌وجو بریده می‌شود', v, severity='info')
        status = art.front.get('وضعیت', '')
        if status and status not in cfg['status_values']:
            self.add('T01', art.front_span[0], f'مقدار «وضعیت» باید یکی از {"، ".join(cfg["status_values"])} باشد', status)
        slug = art.front.get('نامک', '')
        if slug and not re.fullmatch(r'[a-z0-9]+(-[a-z0-9]+)*', slug):
            self.add('T01', art.front_span[0], 'نامک باید فقط حروف کوچک لاتین، رقم و خط تیره باشد', slug)
        base = os.path.splitext(os.path.basename(art.path))[0]
        if slug and base not in ('<mem>',) and base != slug and not base.startswith('_'):
            self.add('T01', art.front_span[0], f'نام فایل «{base}.md» با نامک «{slug}» یکی نیست', severity='info')
        meta = art.front.get('توضیح متا', '')
        if meta and not (70 <= len(meta) <= 170):
            self.add('T01', art.front_span[0], f'طول توضیح متا {am.to_persian_digits(str(len(meta)))} نویسه است (محدودهٔ پیشنهادی ۷۰ تا ۱۷۰)', severity='info')

    # ---- T: H1، بایلاین، پاسخ سریع، هشدار، مقدمه
    def check_head(self):
        art, cfg = self.art, self.cfg
        h1s = [i for i in range(len(art.lines)) if not art.in_fence(i) and art.lines[i].startswith('# ')]
        if len(h1s) != 1:
            self.add('T02', h1s[1] if len(h1s) > 1 else 0, f'باید دقیقاً یک H1 باشد؛ {am.to_persian_digits(str(len(h1s)))} مورد پیدا شد')
        if art.h1 is None:
            return
        if am.EMOJI_RE.search(art.h1):
            self.add('M06', art.h1_line, 'ایموجی در H1', art.h1)
        if art.byline_line < 0:
            self.add('T03', art.h1_line, 'سطر بایلاین «**نویسنده:** تیم تحریریه آموزش‌های بقا | **آخرین به‌روزرسانی:** … | [روش تهیهٔ مقالات](/editorial-policy)» بعد از H1 نیست')
        else:
            bl = art.lines[art.byline_line]
            if cfg['byline'] not in bl:
                self.add('T03', art.byline_line, f'نام نویسنده باید «{cfg["byline"]}» باشد', bl)
            if not DATE_FA_RE.search(bl):
                self.add('T03', art.byline_line, 'تاریخ شمسی (مثل «۱۹ مهر ۱۴۰۵») در سطر بایلاین نیست', bl)
            if cfg['editorial_policy_path'] not in bl:
                self.add('T03', art.byline_line, f'پیوند روش تهیه «{cfg["editorial_policy_path"]}» در سطر بایلاین نیست', bl)
        intro = art.section_by_key('intro')
        first_h2 = min([s.start for s in art.sections], default=len(art.lines))
        head_lines = art.lines[art.h1_line + 1:first_h2]
        head_text = '\n'.join(head_lines)
        if 'پاسخ سریع' not in head_text:
            self.add('T04', art.h1_line, 'بلوک «> **پاسخ سریع:** …» (۲ تا ۴ جمله) پیش از مقدمه نیست')
        else:
            qa = next((l for l in head_lines if 'پاسخ سریع' in l), '')
            n_sent = len([s for s in SENT_SPLIT_RE.split(qa.split('پاسخ سریع', 1)[1]) if s.strip()])
            if n_sent > 5:
                self.add('T04', art.h1_line, f'پاسخ سریع {am.to_persian_digits(str(n_sent))} جمله دارد؛ ۲ تا ۴ جمله کافی است', severity='info')
        if not re.search(r'^>\s*#{2,4}\s*.*(⚠️|هشدار|محدودیت)', head_text, re.M):
            self.add('T05', art.h1_line, 'بلوک هشدار/محدودیت اصلی («> ### ⚠️ …») بالای صفحه و پیش از مقدمه نیست')
        if intro:
            paras = [l.strip() for l in intro.text_lines(art.lines) if l.strip() and not l.startswith('#')]
            if not paras:
                self.add('T06', intro.start, 'بخش مقدمه خالی است')
            else:
                if not paras[0].startswith('فرض کن'):
                    self.add('T06', intro.start + 1, 'مقدمه باید با سناریوی فرضی «فرض کن …» آغاز شود', paras[0])
                n = len(words_of(' '.join(paras)))
                if n > self.lim['intro_words_max']:
                    self.add('T06', intro.start, f'مقدمه {am.to_persian_digits(str(n))} واژه دارد؛ حداکثر {am.to_persian_digits(str(self.lim["intro_words_max"]))}')
                if re.search(r'(من|ما) (خودم|خودمون|بارها|یه بار)[^\n]{0,20}(تجربه|امتحان کردم|دیدم|رفتم)', ' '.join(paras)):
                    self.add('T06', intro.start, 'ادعای تجربهٔ شخصی در مقدمه؛ استاندارد: سناریوی فرضی بدون ادعای تجربهٔ واقعی')

    # ---- T: ترتیب بخش‌ها و بخش‌های الزامی
    def check_order_and_sections(self):
        art = self.art
        found = [(s.key, s) for s in art.sections if s.key]
        keys = [k for k, _ in found]
        required = ['intro', 'prereq', 'steps', 'safety', 'myths', 'trouble', 'prevent', 'faq', 'checklist', 'links', 'sources', 'closing', 'schema']
        for k in required:
            if k not in keys:
                self.add('T07', 0, f'بخش الزامی «{am.SECTION_LABELS[k]}» پیدا نشد (تیتر H2 با کلیدواژهٔ همین بخش لازم است)')
        order = [am.SECTION_ORDER.index(k) for k in keys]
        for i in range(1, len(order)):
            if order[i] < order[i - 1]:
                self.add('T08', found[i][1].start, f'ترتیب بخش‌ها با استاندارد نمی‌خواند: «{am.SECTION_LABELS[keys[i]]}» باید پیش از «{am.SECTION_LABELS[keys[i - 1]]}» بیاید')
                break
        unknown = [s for s in art.sections if not s.key]
        for s in unknown:
            self.add('T09', s.start, 'تیتر H2 خارج از الگوی ۲۰گانه؛ یا به یکی از بخش‌های استاندارد تبدیل شود یا H3 زیر بخش مربوط باشد', s.title, severity='info')
        # روش گام‌به‌گام: برچسب‌های کاربرد/وسایل/هشدار/تصویر
        steps = art.section_by_key('steps')
        if steps:
            txt = '\n'.join(steps.text_lines(art.lines))
            for label in ('مناسب برای', 'لازم داری', 'هشدار', 'تصویر'):
                if label not in txt:
                    self.add('T10', steps.start, f'در بخش روش گام‌به‌گام برچسب «**{label}:**» نیست')
            if not re.search(r'^\s*([' + PD + r']+|\d+)[\.)]\s+', txt, re.M):
                self.add('T10', steps.start, 'بخش روش گام‌به‌گام فهرست شماره‌دار ندارد')
            if re.search(r'جایگزین خلاقانه', art.body_text()) is None:
                self.add('T10', steps.start, 'هیچ «جایگزین خلاقانه»ای در مقاله نیست (قانون مالک: دست‌کم یک مورد)', severity='info')
        # باورهای غلط
        myths = art.section_by_key('myths')
        if myths:
            n = len(re.findall(r'باور غلط', '\n'.join(myths.text_lines(art.lines))))
            if n < self.lim['myths_min']:
                self.add('T11', myths.start, f'بخش باورهای غلط {am.to_persian_digits(str(n))} مورد دارد؛ حداقل {am.to_persian_digits(str(self.lim["myths_min"]))}')
        # ایمنی: شماره‌دار
        safety = art.section_by_key('safety')
        if safety:
            txt = '\n'.join(safety.text_lines(art.lines))
            if not re.search(r'^\s*([' + PD + r']+|\d+)[\.)]\s+', txt, re.M):
                self.add('T12', safety.start, 'نکات ایمنی باید شماره‌دار باشند', severity='info')
        # پیام پایانی و سلب مسئولیت (نسخهٔ ۱٫۲: سطر «کمک هوش مصنوعی» و «تأیید مالک» طبق دستور مالک حذف شده است)
        closing = art.section_by_key('closing')
        tail = '\n'.join(art.lines[closing.start:self.body_b]) if closing else '\n'.join(art.lines[max(self.body_a, self.body_b - 15):self.body_b])
        if not re.search(r'جایگزین[^\n]{0,60}(پزشک|توصیه|راهنما)[^\n]{0,60}نیست', tail):
            self.add('T13', closing.start if closing else self.body_b - 1, 'سلب مسئولیت («…جایگزین توصیهٔ پزشکی، راهنمای محلی یا آموزش عملی نیست») نیست')
        if closing:
            n = len(words_of('\n'.join(l for l in closing.text_lines(art.lines) if not l.strip().startswith('*'))))
            if n > 160:
                self.add('T13', closing.start, f'پیام پایانی {am.to_persian_digits(str(n))} واژه دارد؛ کوتاه و مسئولانه باشد', severity='info')
        # طول
        n = len(self.body_words)
        if n < self.lim['words_min']:
            self.add('T14', 0, f'طول بدنه {am.to_persian_digits(str(n))} واژه است؛ کمتر از {am.to_persian_digits(str(self.lim["words_min"]))} (کامل‌بودن مهم‌تر از عدد است)', severity='info')
        elif n > self.lim['words_hard_max']:
            self.add('T14', 0, f'طول بدنه {am.to_persian_digits(str(n))} واژه است؛ بسیار بیشتر از {am.to_persian_digits(str(self.lim["words_max"]))} — حشو را کم کن')
        elif n > self.lim['words_max']:
            self.add('T14', 0, f'طول بدنه {am.to_persian_digits(str(n))} واژه است؛ بیش از {am.to_persian_digits(str(self.lim["words_max"]))} (قابل قبول اگر حشو نداشته باشد)', severity='info')
        # منابع
        src = art.section_by_key('sources')
        if src and not [l for l in src.text_lines(art.lines) if l.strip().startswith(('-', '*'))]:
            self.add('T15', src.start, 'بخش منابع خالی است؛ دست‌کم نام سازمان/راهنما')

    # ---- T: جدول و لینک
    def check_tables_links(self):
        art, cfg, lim = self.art, self.cfg, self.lim
        if not art.tables:
            self.add('T16', 0, 'هیچ جدول تصمیم/مقایسه‌ای در مقاله نیست (بند ۷ استاندارد)')
        for line, ncols in art.tables:
            if ncols > lim['table_cols_max']:
                self.add('T16', line, f'جدول {am.to_persian_digits(str(ncols))} ستون دارد؛ حداکثر {am.to_persian_digits(str(lim["table_cols_max"]))}')
        allowed = set(cfg['allowed_slugs'].keys())
        fixed = set(cfg['fixed_paths'])
        links_sec = art.section_by_key('links')
        src_sec = art.section_by_key('sources')
        internal_in_links = 0
        for line, text, href in art.links:
            in_links = links_sec and links_sec.start < line < links_sec.end
            in_src = src_sec and src_sec.start < line < src_sec.end
            if href.startswith('#'):
                self.add('T17', line, 'لینک لنگر داخلی (#…) در وردپرس بدون شناسهٔ عنوان کار نمی‌کند', href)
                continue
            if href.startswith(('http://', 'https://')):
                if href.startswith(cfg['site_url']):
                    path = '/' + href[len(cfg['site_url']):].strip('/')
                    href = path if path != '/' else '/'
                elif not in_src:
                    self.add('T17', line, 'لینک خارجی فقط در بخش منابع و پس از تأیید URL مجاز است', href)
                    continue
                else:
                    continue
            if href in fixed:
                if in_links:
                    internal_in_links += 1
                continue
            m = re.fullmatch(r'/?([a-z0-9]+(?:-[a-z0-9]+)*)/?', href)
            if not m:
                self.add('T17', line, 'قالب لینک داخلی باید «/slug» باشد', href)
                continue
            slug = m.group(1)
            if slug not in allowed:
                self.add('T17', line, f'نامک «{slug}» در فهرست پیوندهای مجاز نیست (lint-config.json → allowed_slugs)', text)
            elif in_links:
                internal_in_links += 1
        if links_sec:
            if internal_in_links < lim['links_min']:
                self.add('T17', links_sec.start, f'بخش لینک‌های مرتبط {am.to_persian_digits(str(internal_in_links))} پیوند مجاز دارد؛ حداقل {am.to_persian_digits(str(lim["links_min"]))}')
            elif internal_in_links > lim['links_max']:
                self.add('T17', links_sec.start, f'بخش لینک‌های مرتبط {am.to_persian_digits(str(internal_in_links))} پیوند دارد؛ حداکثر {am.to_persian_digits(str(lim["links_max"]))}', severity='info')
        # لینک خرید
        for line, text, href in art.links:
            host = re.sub(r'^https?://', '', href).split('/')[0].lower()
            path = '/' + '/'.join(re.sub(r'^https?://', '', href).split('/')[1:]).lower()
            if re.search(r'(shop|store|digikala|amazon|basalam|torob|emalls)', host) or re.search(r'/(product|products|shop|cart|checkout|buy)(/|$|\?)', path) or re.search(r'(?<![آ-ی])خرید(?![آ-ی])', text):
                self.add('R02', line, 'لینک خرید/فروشگاه ممنوع است', href)

    # ---- T: FAQ و اسکیما
    def check_faq_and_schema(self):
        art, cfg, lim = self.art, self.cfg, self.lim
        faq_sec = art.section_by_key('faq')
        n = len(art.faq)
        if faq_sec:
            if n < lim['faq_min'] or n > lim['faq_max']:
                self.add('T18', faq_sec.start, f'{am.to_persian_digits(str(n))} پرسش شناسایی شد؛ باید ۵ تا ۷ پرسش با قالب «**۱. سؤال**» باشد')
            for q, a, line in art.faq:
                if not a:
                    self.add('T18', line, 'پرسش بدون پاسخ', q)
                elif len(words_of(a)) > 90:
                    self.add('T18', line, 'پاسخ پرسش بلند است؛ کوتاه بنویس و برای تفصیل به مقالهٔ مرتبط لینک بده', q, severity='info')
        if art.schema_section_line < 0 and art.schema_json is None:
            self.add('T19', 0, 'بخش اسکیما (## اسکیما برای پیاده‌سازی + فنس json) نیست')
            return
        if art.schema_json is None:
            self.add('T19', art.schema_section_line, 'فنس ```json اسکیما پیدا نشد')
            return
        if art.schema_error:
            self.add('T19', art.schema_span[0], f'JSON اسکیما معتبر نیست: {art.schema_error}')
            if re.search(r'^\s*//', art.schema_json, re.M):
                self.add('T19', art.schema_span[0], 'کامنت // داخل JSON مجاز نیست')
            return
        # شمارش خطوط اضافی: فنس‌های json متعدد
        json_fences = [f for f in art.fences if f[2].startswith('json')]
        if len(json_fences) > 1:
            self.add('T19', json_fences[0][0], f'{am.to_persian_digits(str(len(json_fences)))} بلوک json جدا وجود دارد؛ همه در یک @graph ادغام شوند')
        obj = art.schema_obj
        nodes = []
        if isinstance(obj, dict) and '@graph' in obj:
            nodes = [x for x in obj['@graph'] if isinstance(x, dict)]
        elif isinstance(obj, dict):
            nodes = [obj]
        elif isinstance(obj, list):
            nodes = [x for x in obj if isinstance(x, dict)]
        types = {}
        for nd in nodes:
            t = nd.get('@type')
            if isinstance(t, list):
                t = t[0]
            types.setdefault(t, nd)
        for t in ('Article', 'BreadcrumbList'):
            if t not in types:
                self.add('T19', art.schema_span[0], f'اسکیمای {t} همیشه لازم است')
        site = cfg['site_url'].rstrip('/')
        slug = art.front.get('نامک', '')
        status = art.front.get('وضعیت', '')
        a = types.get('Article')
        if a:
            if art.h1 and am.strip_emoji(a.get('headline', '')) != am.strip_emoji(art.h1):
                self.add('T19', art.schema_span[0], 'headline اسکیما با H1 یکی نیست', a.get('headline', ''))
            for role in ('author', 'publisher'):
                r = a.get(role) or {}
                if r.get('@type') != 'Organization' or r.get('name') != cfg['byline']:
                    self.add('T19', art.schema_span[0], f'{role} باید Organization با نام «{cfg["byline"]}» باشد')
            if a.get('inLanguage') != 'fa':
                self.add('T19', art.schema_span[0], 'inLanguage باید "fa" باشد', severity='info')
            dm = a.get('dateModified', '')
            if not ISO_DATE_RE.match(str(dm)):
                self.add('T19', art.schema_span[0], 'dateModified به قالب YYYY-MM-DD لازم است')
            if 'datePublished' in a and status != 'منتشرشده':
                self.add('T19', art.schema_span[0], 'datePublished فقط پس از انتشار واقعی درج می‌شود')
            for f in ('url', 'mainEntityOfPage'):
                v = a.get(f)
                if isinstance(v, dict):
                    v = v.get('@id')
                if slug and v and v.rstrip('/') != f'{site}/{slug}':
                    self.add('T19', art.schema_span[0], f'{f} باید «{site}/{slug}/» باشد', str(v))
            if 'image' in a:
                self.add('T19', art.schema_span[0], 'image در اسکیما؛ برنامهٔ فعلی بدون تصویر است — فقط اگر تصویر واقعی وجود دارد', severity='info')
        b = types.get('BreadcrumbList')
        if b:
            items = b.get('itemListElement') or []
            last = items[-1] if items else {}
            li = last.get('item')
            if isinstance(li, dict):
                li = li.get('@id')
            if slug and (not li or str(li).rstrip('/') != f'{site}/{slug}'):
                self.add('T19', art.schema_span[0], 'آخرین عنصر BreadcrumbList باید URL همین مقاله باشد')
        # FAQPage
        fp = types.get('FAQPage')
        if art.faq and not fp:
            self.add('T19', art.schema_span[0], 'مقاله FAQ دارد ولی FAQPage در اسکیما نیست', severity='info')
        if fp:
            ents = fp.get('mainEntity') or []
            sq = [(am.strip_emoji(e.get('name', '')), re.sub(r'\s+', ' ', am.strip_emoji((e.get('acceptedAnswer') or {}).get('text', ''))).strip()) for e in ents]
            tq = [(am.strip_emoji(q), re.sub(r'\s+', ' ', am.strip_emoji(re.sub(r'\*\*|\[|\]\([^)]*\)', '', a))).strip()) for q, a, _ in art.faq]
            if len(sq) != len(tq):
                self.add('T19', art.schema_span[0], f'FAQPage {am.to_persian_digits(str(len(sq)))} پرسش دارد و متن {am.to_persian_digits(str(len(tq)))}؛ باید عیناً یکی باشند')
            else:
                for (q1, a1), (q2, a2) in zip(sq, tq):
                    if q1.strip('؟? ') != q2.strip('؟? '):
                        self.add('T19', art.schema_span[0], 'پرسش FAQPage با متن یکی نیست', f'{q1} ≠ {q2}')
                    elif a1 != a2:
                        self.add('T19', art.schema_span[0], 'پاسخ FAQPage با متن صفحه عیناً یکی نیست', q1)
        # HowTo
        ht = types.get('HowTo')
        steps_sec = art.section_by_key('steps')
        if ht:
            if not steps_sec:
                self.add('T19', art.schema_span[0], 'HowTo فقط برای روش مرحله‌دار مجاز است؛ بخش روش گام‌به‌گام پیدا نشد')
            else:
                body = '\n'.join(steps_sec.text_lines(art.lines))
                for st in ht.get('step') or []:
                    nm = am.strip_emoji(st.get('name', ''))
                    if nm and nm not in am.strip_emoji(body):
                        self.add('T19', art.schema_span[0], 'نام مرحلهٔ HowTo عیناً در متن بخش روش نیست', nm)
            for f in ('totalTime', 'estimatedCost'):
                if f in ht:
                    self.add('T19', art.schema_span[0], f'{f} فقط در صورت اتکای واقعی؛ در غیر این صورت حذف شود', severity='info')
        declared = art.front.get('اسکیما', '')
        for t in types:
            if t and t not in declared:
                self.add('T19', art.front_span[0], f'نوع «{t}» در اسکیما هست ولی در بلوک سئو اعلام نشده', severity='info')

    # ---- M: نشانه‌های ماشینی
    def check_machine_marks(self):
        art = self.art
        a, b = self.body_a, self.body_b
        fm_a, fm_b = art.front_span
        body_lines = [(i, art.lines[i]) for i in range(0, len(art.lines)) if not art.in_fence(i)]
        # M01..M09 الگوهای پیکربندی
        for code, pats in self.machine.items():
            short = code.split('_')[0]
            for i, line in body_lines:
                if not line.strip():
                    continue
                in_front = fm_a <= i < fm_b
                if in_front and short not in ('M01', 'M03'):
                    continue
                if short == 'M04' and in_front:
                    continue
                for p in pats:
                    m = p.search(line)
                    if m:
                        if short == 'M09':
                            # اختلاط لحن: فقط در بدنه، و نه در نقل‌قول/هشدار رسمی
                            if not (a <= i < b) or line.lstrip().startswith('>') or 'جایگزین توصیهٔ' in line:
                                break
                            self.add('M09', i, 'اختلاط لحن «شما/کنید» در متنی که با «تو» نوشته شده', line, severity='info')
                            break
                        label = {
                            'M01': 'نام مدل یا عنوان سند تولید («مقاله شماره …»)',
                            'M02': 'مقدمه/مؤخرهٔ گفت‌وگویی مدل',
                            'M03': 'جای‌خالی یا دستور به ویراستار',
                            'M04': 'مارک‌آپ باقی‌مانده (HTML/فرار/کامنت)',
                            'M07': 'کلیشهٔ متنی مدل',
                            'M08': 'جملهٔ متا دربارهٔ خود مقاله',
                            'M12': 'سطر «کمک هوش مصنوعی» یا «تأیید مالک» (طبق دستور مالک، مقاله باید تمیز و بی‌این‌سطرها باشد)',
                        }[short]
                        if short == 'M04' and (fm_a <= i < fm_b):
                            break
                        self.add(short, i, label, line if len(line) < 160 else m.group(0))
                        break
        # ایموجی در تیترها و تراکم
        for i, line in body_lines:
            if a <= i < b and am.HEADING_RE.match(line) and am.EMOJI_RE.search(line):
                self.add('M06', i, 'ایموجی در تیتر (املای معیار در تیترها)', line)
        body_txt = art.body_text()
        n_emoji = len([c for c in am.EMOJI_RE.findall(body_txt) if c not in ('\ufe0f', '\u200d')])
        n_words = max(1, len(self.body_words))
        dens = n_emoji * 100.0 / n_words
        if dens > self.lim['emoji_per_100_words_max']:
            self.add('M06', 0, f'تراکم ایموجی {am.to_persian_digits(f"{dens:.1f}")} در هر ۱۰۰ واژه ({am.to_persian_digits(str(n_emoji))} ایموجی)؛ «زیاده‌روی نه»', severity='info')
        # خط غیرفارسی، حروف عربی، رقم لاتین
        for i, line in body_lines:
            if not (a <= i < b) or not line.strip():
                continue
            if CJK_RE.search(line):
                self.add('M05', i, 'نویسهٔ چینی/ژاپنی/کره‌ای', line)
            plain = re.sub(r'\([^)]*\)', '', URL_RE.sub('', am.LINK_RE.sub(r'\1', line)))
            src_sec = art.section_by_key('sources')
            if LATIN_RUN_RE.search(plain) and not (src_sec and src_sec.start < i < src_sec.end):
                self.add('M05', i, 'جملهٔ لاتین/انگلیسی در متن فارسی', line)
            if any(ch in line for ch in ARABIC_LETTERS):
                self.add('M05', i, 'حروف عربی (ي/ك/ة) به‌جای فارسی', line)
            prose = re.sub(r'^\s*\d+[\.)]\s+', '', INLINE_CODE_RE.sub('', am.LINK_RE.sub(r'\1', URL_RE.sub('', line))))
            if re.search(r'(?<![A-Za-z])[0-9]+(?![A-Za-z])', prose):
                self.add('M05', i, 'رقم لاتین در متن فارسی (رقم فارسی لازم است؛ --fix آن را اصلاح می‌کند)', line)
        # همان بررسی برای بلوک سئوی ویراستار (توضیح متا و چکیده هم به سایت می‌روند)
        for i in range(fm_a, fm_b):
            line = art.lines[i]
            if CJK_RE.search(line):
                self.add('M05', i, 'نویسهٔ چینی/ژاپنی/کره‌ای در بلوک سئو', line)
            if any(ch in line for ch in ARABIC_LETTERS):
                self.add('M05', i, 'حروف عربی (ي/ك/ة) در بلوک سئو', line)
        # ادعای تجربهٔ شخصی/شاهد عینی (استاندارد: تجربه و آمار ساخته نمی‌شود)
        exp = re.compile(r'((?<![آ-ی])(من|خودم|ما)(?![آ-ی])[^\n.!؟]{0,40}(دیدم|دیدیم|تجربه کردم|امتحان کردم|آزمایش کردم|شاهد بودم|رفتم و|بودم که))|(بارها دیدم|به چشم خودم|تجربهٔ شخصی|تجربه شخصی|سال‌ها(ست)? (که )?(تو|در) (طبیعت|کوه)[^\n]{0,15}(بودم|هستم|می‌رم))')
        for i, line in body_lines:
            if a <= i < b and exp.search(line):
                self.add('M11', i, 'ادعای تجربهٔ شخصی/شاهد عینی؛ استاندارد: تجربه، تست یا نتیجه ساخته نمی‌شود', line)
        # تکرار عینی بندها
        seen: Dict[str, int] = {}
        for p in art.paragraphs:
            key = re.sub(r'\W+', ' ', p.text).strip()
            if len(words_of(key)) < 8:
                continue
            if key in seen:
                self.add('M10', p.line, f'بند عیناً تکرار خط {am.to_persian_digits(str(seen[key] + 1))} است', p.text)
            else:
                seen[key] = p.line
        # فنس‌های غیر json در بدنه
        for s, e, lang in art.fences:
            if a <= s < b and not lang.startswith('json'):
                self.add('M04', s, f'بلوک کد «{lang or "بدون زبان"}» در بدنهٔ مقاله؛ فلوچارت/چک‌لیست را به فهرست تبدیل کن', severity='warn' if 'بسته‌نشده' in lang else 'info')
        # لنگر وردپرس‌ناپذیر / HTML خام
        for i, line in body_lines:
            if a <= i < b and re.search(r'<(div|span|p|img|table|iframe|style)[\s>]', line):
                self.add('M04', i, 'HTML خام در بدنه', line)

    # ---- R: ربط
    def check_relevance(self):
        art, cfg, lim = self.art, self.cfg, self.lim
        stop = self.stop
        topic_src = ' '.join([
            art.h1 or '', art.front.get('عنوان سئو', ''), art.front.get('کلمهٔ کلیدی اصلی', ''),
            art.front.get('کلمات کلیدی فرعی', ''), art.front.get('چکیده', ''), art.front.get('توضیح متا', ''),
        ] + [s.title for s in art.sections] + [l for l in art.lines[self.body_a:self.body_b] if l.startswith('###')])
        topic = set(content_tokens(topic_src, stop))
        body_toks = content_tokens(art.body_text(), stop)
        freq = Counter(body_toks)
        core = {t for t, c in freq.most_common(60) if c >= 3} | topic | {stem(w) for w in cfg.get('domain_lexicon', [])}
        a_sec = {'links', 'sources', 'schema', 'closing'}
        for p in art.paragraphs:
            if p.section in a_sec or p.kind == 'faq_q':
                continue
            text = am.LINK_RE.sub(r'\1', p.text)
            if re.match(r'^\*\*(مناسب برای|لازم داری|زمان|هشدار|تصویر)[:：]\*\*', text.strip()):
                continue  # برچسب‌های ثابت بخش گام‌به‌گام
            toks = content_tokens(text, stop)
            if len(toks) < lim['relevance_min_tokens']:
                continue
            hits = sum(1 for t in toks if t in core)
            score = hits / len(toks)
            if score < lim['relevance_threshold']:
                self.add('R01', p.line, f'بند با موضوع مقاله هم‌پوشانی واژگانی کمی دارد ({am.to_persian_digits(f"{score:.2f}")})؛ بررسی کن که نامرتبط یا حشو نباشد', text)
                continue
            # جمله‌های بلند بدون هیچ هم‌پوشانی
            for sent in SENT_SPLIT_RE.split(text):
                st = content_tokens(sent, stop)
                if len(st) >= lim['relevance_sentence_min_tokens'] and not any(t in core for t in st):
                    self.add('R01', p.line, 'جملهٔ بلند بدون هیچ واژهٔ موضوعی؛ احتمالاً نامرتبط', sent)
        # واژگان تجاری / خارج از حوزه / تعمیم
        body_lines = [(i, art.lines[i]) for i in range(self.body_a, self.body_b) if not art.in_fence(i)]
        for code, words in cfg['offtopic_lexicon'].items():
            short = code.split('_')[0]
            label = {'R02': 'واژهٔ تجاری/خارج از حوزهٔ مقاله', 'R03': 'تعمیم جنسیتی/گروهی — نامرتبط با آموزش ایمنی'}[short]
            pat = re.compile('|'.join(words))
            neg = re.compile(r'(ندار|نیست|ممنوع|بدون|نمی‌د|نمیذار|نمی‌ذار|نذاشت|نمی‌کن|نمی‌ش|نمیش|تبلیغ نمی)')
            for i, line in body_lines:
                for sent in SENT_SPLIT_RE.split(line):
                    m = pat.search(sent)
                    if m and not (short == 'R02' and neg.search(sent)):
                        self.add(short, i, f'{label}: «{m.group(0)}»', sent)
                        break
        brands = re.compile(r'\b(' + '|'.join(re.escape(b) for b in cfg['brand_lexicon']) + r')\b')
        for i, line in body_lines:
            m = brands.search(line)
            if m:
                self.add('R04', i, f'نام برند «{m.group(0)}» — تبلیغ برند ممنوع؛ فقط اگر مثال خنثی و بدون توصیهٔ خرید است بماند', line, severity='info')

    # ---- S: مرزهای ایمنی
    def check_safety(self):
        art = self.art
        full = art.body_text()
        h1 = (art.h1 or '') + ' ' + art.front.get('کلمهٔ کلیدی اصلی', '') + ' ' + art.front.get('عنوان سئو', '')
        body_lines = [(i, art.lines[i]) for i in range(self.body_a, self.body_b) if not art.in_fence(i)]
        global_neg = re.compile(self.cfg.get('global_negation', r'(نکن|نکنید|نباید|هرگز|هیچ‌وقت|هیچوقت|ممنوع|خطرناک|خطر|اشتباه|غلط|نیست|نبر|نده|نریز|نزن|نرو|نپر|نخور|نذار|نگیر|نمی‌گیر|نمی‌کن|نمیکن|نمی‌ش|نمیش|به‌جای|به جای|(?<![آ-ی])نه(?![آ-ی]))'))

        def skip_sentence(line: str, sent: str) -> bool:
            st = sent.strip()
            return (not st or line.lstrip().startswith('#') or line.lstrip().startswith('> #')
                    or 'باور غلط' in line or st.rstrip('*').endswith(('؟', '?')))

        for rule in self.cfg['safety_rules']:
            topic_any = rule.get('topic_any') or []
            sev = rule.get('severity', 'warn')
            if 'require' in rule:
                # قانون «باید ذکر شود» فقط برای مقاله‌ای که موضوعش همین است (H1/کلیدواژه)
                if any(t in h1 for t in topic_any) and not re.search(rule['require'], full):
                    self.add(rule['code'], 0, rule['message'], severity=sev)
                continue
            if topic_any and not any(t in h1 or t in full for t in topic_any):
                continue
            pat = re.compile(rule['pattern'])
            neg = re.compile(rule['negation']) if rule.get('negation') else None
            for i, line in body_lines:
                sents = SENT_SPLIT_RE.split(line)
                for k, sent in enumerate(sents):
                    if skip_sentence(line, sent) and sev != 'info':
                        continue
                    ctx = sent + ' ' + (sents[k + 1] if k + 1 < len(sents) else '')
                    if pat.search(sent) and not (neg and neg.search(ctx)) and not (sev != 'info' and global_neg.search(ctx)):
                        self.add(rule['code'], i, rule['message'], sent, severity=sev)
                        break

    # ---- وضعیت
    def check_status(self):
        art, cfg = self.art, self.cfg
        status = art.front.get('وضعیت', '')
        warns = [f for f in self.findings if f.severity == 'warn']
        if status == cfg['ready_status'] and warns:
            self.add('T00', art.front_span[0], f'وضعیت «{status}» است ولی {am.to_persian_digits(str(len(warns)))} هشدار باز دارد؛ یا هشدارها را رفع کن یا وضعیت را «پیش‌نویس» بگذار')
        if status == 'منتشرشده' and warns:
            self.add('T00', art.front_span[0], 'مقالهٔ منتشرشده هشدار باز دارد؛ نسخهٔ سایت را هم اصلاح کن', severity='info')


# ----------------------------------------------------------------------------
# گزارش
# ----------------------------------------------------------------------------
def fa(n) -> str:
    return am.to_persian_digits(str(n))


def report_text(path: str, art: am.Article, findings: List[Finding], fix_notes: List[str]) -> str:
    warns = [f for f in findings if f.severity == 'warn']
    infos = [f for f in findings if f.severity == 'info']
    status = art.front.get('وضعیت', '—')
    out = [f'■ {path}', f'  نامک: {art.front.get("نامک", "—")} | وضعیت: {status} | واژه‌های بدنه: {fa(len(words_of(art.body_text())))} | هشدار: {fa(len(warns))} | اطلاع: {fa(len(infos))}']
    for n in fix_notes:
        out.append(f'  ✎ اصلاح خودکار: {n}')
    if not findings:
        out.append('  ✔ بدون یافته — از نظر فیلتر، آمادهٔ تصمیم مالک برای انتشار است.')
    for cat in 'TMRS':
        fs = [f for f in findings if f.category == cat]
        if not fs:
            continue
        out.append(f'  ── {CATEGORY_LABEL[cat]} ({fa(len(fs))})')
        for f in fs:
            mark = '⚠' if f.severity == 'warn' else 'ℹ'
            loc = f'خط {fa(f.line)}' if f.line else 'کل فایل'
            out.append(f'  {mark} {f.code} [{loc}] {f.message}' + (f'\n      ↳ {f.excerpt}' if f.excerpt else ''))
    return '\n'.join(out)


def report_md_table(rows: List[dict]) -> str:
    out = ['| فایل | نامک | واژه | هشدار | اطلاع | وضعیت | کدهای پرتکرار |', '|---|---|---|---|---|---|---|']
    for r in rows:
        top = '، '.join(f'{c}×{fa(n)}' for c, n in Counter(f['code'] for f in r['findings'] if f['severity'] == 'warn').most_common(4)) or '—'
        out.append(f"| `{os.path.basename(r['path'])}` | `{r['slug'] or '—'}` | {fa(r['words'])} | {fa(r['warn'])} | {fa(r['info'])} | {r['status'] or '—'} | {top} |")
    return '\n'.join(out)


def collect_paths(args: List[str]) -> List[str]:
    paths: List[str] = []
    for a in args:
        if os.path.isdir(a):
            for name in sorted(os.listdir(a)):
                if name.endswith('.md') and not name.startswith('_'):
                    paths.append(os.path.join(a, name))
        else:
            paths.append(a)
    return paths


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('paths', nargs='+', help='فایل یا پوشهٔ مقالات')
    ap.add_argument('--config', default=DEFAULT_CONFIG)
    ap.add_argument('--fix', action='store_true', help='اصلاح‌های مکانیکی امن را روی فایل اعمال کن')
    ap.add_argument('--format', choices=['text', 'json', 'md'], default='text')
    ap.add_argument('--check-ready', action='store_true', help='خروج غیرصفر فقط برای مقالهٔ «آمادهٔ انتشار» با هشدار باز')
    ap.add_argument('--mark-ready', action='store_true',
                    help='اگر مقاله هیچ هشداری نداشت، «وضعیت» را به «آمادهٔ انتشار» تغییر بده (مقالهٔ «منتشرشده» دست نمی‌خورد)')
    ap.add_argument('--quiet', action='store_true', help='فقط خلاصه')
    ns = ap.parse_args(argv)

    cfg = load_config(ns.config)
    linter = Linter(cfg)
    rows = []
    any_warn = False
    ready_broken = False
    texts = []
    for path in collect_paths(ns.paths):
        with open(path, encoding='utf-8') as fh:
            text = fh.read()
        fix_notes: List[str] = []
        if ns.fix:
            new_text, fix_notes = apply_fixes(text, cfg)
            if new_text != text:
                with open(path, 'w', encoding='utf-8') as fh:
                    fh.write(new_text)
                text = new_text
        art = am.parse(path, text)
        findings = linter.run(art)
        warns = sum(1 for f in findings if f.severity == 'warn')
        if ns.mark_ready and warns == 0 and art.front.get('وضعیت', '') not in (cfg['ready_status'], 'منتشرشده'):
            new_text = re.sub(r'^(وضعیت:)[^\n]*$', lambda m: m.group(1) + ' ' + cfg['ready_status'], text, count=1, flags=re.M)
            if new_text != text:
                with open(path, 'w', encoding='utf-8') as fh:
                    fh.write(new_text)
                text = new_text
                fix_notes.append(f'وضعیت → {cfg["ready_status"]}')
                art = am.parse(path, text)
                findings = linter.run(art)
                warns = sum(1 for f in findings if f.severity == 'warn')
        infos = sum(1 for f in findings if f.severity == 'info')
        any_warn = any_warn or warns > 0
        status = art.front.get('وضعیت', '')
        if status == cfg['ready_status'] and warns:
            ready_broken = True
        rows.append({'path': path, 'slug': art.front.get('نامک', ''), 'status': status, 'words': len(words_of(art.body_text())),
                     'warn': warns, 'info': infos, 'fixes': fix_notes, 'findings': [asdict(f) for f in findings]})
        if ns.format == 'text' and not ns.quiet:
            texts.append(report_text(path, art, findings, fix_notes))
    if ns.format == 'json':
        print(json.dumps(rows, ensure_ascii=False, indent=1))
    elif ns.format == 'md':
        print(report_md_table(rows))
    else:
        if texts:
            print('\n\n'.join(texts))
        total_w = sum(r['warn'] for r in rows)
        total_i = sum(r['info'] for r in rows)
        print(f'\n═ خلاصه: {fa(len(rows))} فایل، {fa(total_w)} هشدار، {fa(total_i)} اطلاع. '
              + ('همهٔ فایل‌ها بدون هشدارند.' if not any_warn else 'هشدارها را پیش از علامت‌گذاری «آمادهٔ انتشار» رفع کن.'))
    if ns.check_ready:
        return 1 if ready_broken else 0
    return 1 if any_warn else 0


if __name__ == '__main__':
    sys.exit(main())
