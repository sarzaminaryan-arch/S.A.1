#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
build_theme_data.py — تولید دادهٔ قالب برای مقالات آموزشی کمپینگ.

ورودی:  docs/camping-education/articles/*.md (فقط مقاله‌های با وضعیت «آمادهٔ انتشار» یا «منتشرشده»)
خروجی: wp-content/themes/sarzaminaryan-child/data/ready-guides.php

هر مقاله به یک رکورد ابزار «مقالات آماده» تبدیل می‌شود (نوع «نوشته/post»، دستهٔ آموزش‌های کمپینگ):
  - بلوک سئو → sa_seo_title / sa_seo_description / sa_focus_keyword / چکیده
  - بدنه → HTML سازگار با wpautop (بلوک‌ها با خط نو از هم جدا می‌شوند)
  - بخش «پرسش‌های رایج» → ردیف‌های faq (متای sa_faq؛ قالب خودش آکاردئون را می‌سازد)
  - بخش «منابع» → متای sa_sources (هر سطر «عنوان | URL»)
  - بخش «اسکیما» حذف می‌شود (قالب اسکیمای Article را خودش چاپ می‌کند؛ FAQPage طبق سیاست قالب چاپ نمی‌شود)
  - پیوندهای داخلی https://SITE/slug → /slug/
  - کلیدهای «تصویر شاخص» (مسیر نسبی به assets/img/ قالب) و «متن جایگزین تصویر» → رکورد cover
    (ابزار «مقالات آماده» فایل را از قالب به کتابخانهٔ رسانه می‌برد و با همین متن جایگزین تصویر شاخص می‌کند)

کاربرد:
  python3 tools/content-lint/build_theme_data.py [--articles DIR] [--out FILE] [--check]
"""
import argparse
import html
import json
import os
import re
import sys
from typing import Dict, List, Optional, Tuple

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import article_model as am  # noqa: E402

ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
DEFAULT_ARTICLES = os.path.join(ROOT, 'docs', 'camping-education', 'articles')
DEFAULT_OUT = os.path.join(ROOT, 'wp-content', 'themes', 'sarzaminaryan-child', 'data', 'ready-guides.php')
THEME_IMG = os.path.join(ROOT, 'wp-content', 'themes', 'sarzaminaryan-child', 'assets', 'img')
COVER_RE = re.compile(r'^[a-z0-9][a-z0-9/_-]*\.(webp|jpe?g|png)$')
CONFIG = os.path.join(HERE, 'lint-config.json')

CATEGORY = ('camping-survival', 'آموزش‌های ضروری کمپینگ و بقا')

BRANCHES: Dict[str, Tuple[str, str, List[str]]] = {
    'camping-gear': ('تجهیزات کمپینگ', 'Camping gear', [
        'camping-essential-checklist', 'essential-camping-equipment', 'how-to-choose-camping-tent', 'sleeping-bag-guide',
        'outdoor-clothing-guide', 'first-aid-kit-checklist', 'outdoor-cooking-equipment', 'camping-lighting-guide',
        'build-shelter-branches']),
    'pro-gear': ('تجهیزات حرفه‌ای', 'Professional gear', [
        'portable-water-filter-review', 'camping-stove-review', 'multi-tool-knife-review', 'gps-navigation-tools',
        'rope-carabiner-guide', 'professional-rescue-kit']),
    'diy-outdoor': ('کاردستی و جایگزین‌های DIY', 'DIY', [
        'diy-water-filter', 'diy-can-stove', 'rope-alternatives-outdoor', 'diy-compass', 'diy-emergency-torch',
        'trash-bag-emergency-raincoat', 'newspaper-insulation-bed', 'can-cookware-diy', 'diy-bottle-lantern',
        'pull-tab-tools-diy', 'plastic-bottle-shovel-funnel', 'diy-water-container']),
    'campfire': ('آتش در طبیعت', 'Campfire', [
        'how-to-start-campfire', 'fire-without-lighter', 'maintain-fire-in-rain', 'how-to-extinguish-campfire']),
    'drinking-water': ('آب آشامیدنی', 'Drinking water', [
        'camping-water-planning', 'drinking-water-in-nature', 'emergency-water-purification']),
    'water-rescue': ('نجات غریق و ایمنی آبی', 'Water rescue', [
        'drowning-rescue-guide', 'self-rescue-drowning', 'unconscious-drowning-cpr', 'water-safety-children']),
    'first-aid': ('کمک‌های اولیه در طبیعت', 'First aid', [
        'cpr-complete-guide', 'ankle-sprain-first-aid', 'burn-injury-first-aid', 'overdose-rescue-guide',
        'bleeding-wound-first-aid', 'heatstroke-first-aid', 'hypothermia-first-aid', 'snake-insect-bite-first-aid',
        'old-clothes-bandage-wick', 'leg-fracture-first-aid']),
    'campsite-shelter': ('کمپ‌سازی و سرپناه', 'Campsite and shelter', [
        'diy-hammock-trees', 'choosing-campsite', 'ground-insulation-sleeping']),
    'wildlife': ('برخورد با حیوانات', 'Wildlife encounters', [
        'bear-encounter-guide', 'wild-boar-encounter', 'snake-encounter-guide', 'stray-dog-encounter',
        'night-wildlife-defense', 'safe-food-storage-outdoor']),
    'navigation': ('مسیریابی و سیگنال', 'Navigation and signaling', [
        'navigation-by-sun', 'navigation-by-stars', 'lost-in-nature-guide', 'paper-map-reading',
        'rescue-signaling-guide', 'international-distress-signals']),
    'field-skills': ('مهارت‌های میدانی', 'Field skills', [
        'knot-tying-guide', 'outdoor-cooking-minimal', 'personal-hygiene-outdoor', 'children-outdoor-safety']),
}

ENGLISH: Dict[str, str] = {
    'camping-essential-checklist': 'Camping Essentials Checklist',
    'essential-camping-equipment': 'Essential Camping Equipment',
    'how-to-choose-camping-tent': 'How to Choose a Camping Tent',
    'sleeping-bag-guide': 'Sleeping Bag Guide',
    'outdoor-clothing-guide': 'Outdoor Clothing Guide',
    'first-aid-kit-checklist': 'First Aid Kit Checklist',
    'outdoor-cooking-equipment': 'Outdoor Cooking Equipment',
    'camping-lighting-guide': 'Camping Lighting Guide',
    'trash-bag-emergency-raincoat': 'Trash Bag Emergency Raincoat',
    'newspaper-insulation-bed': 'Newspaper Insulation Bed',
    'can-cookware-diy': 'DIY Can Cookware',
    'diy-bottle-lantern': 'DIY Water Bottle Lantern',
    'pull-tab-tools-diy': 'Pull Tab Tools',
    'plastic-bottle-shovel-funnel': 'Plastic Bottle Shovel and Funnel',
    'old-clothes-bandage-wick': 'Old Clothes Bandage and Fire Wick',
    'build-shelter-branches': 'Build a Branch Shelter',
    'portable-water-filter-review': 'Portable Water Filters',
    'camping-stove-review': 'Camping Stoves',
    'multi-tool-knife-review': 'Multi-tools and Knives',
    'gps-navigation-tools': 'GPS and Navigation Tools',
    'rope-carabiner-guide': 'Ropes and Carabiners',
    'professional-rescue-kit': 'Professional Rescue Kit',
    'diy-water-filter': 'DIY Water Filter',
    'diy-can-stove': 'DIY Can Stove',
    'rope-alternatives-outdoor': 'Rope Alternatives Outdoors',
    'diy-compass': 'DIY Compass',
    'diy-emergency-torch': 'DIY Emergency Torch',
    'how-to-start-campfire': 'How to Start a Campfire',
    'fire-without-lighter': 'Fire Without a Lighter',
    'maintain-fire-in-rain': 'Keeping a Fire in the Rain',
    'how-to-extinguish-campfire': 'How to Extinguish a Campfire',
    'camping-water-planning': 'Camping Water Planning',
    'drinking-water-in-nature': 'Drinking Water in Nature',
    'emergency-water-purification': 'Emergency Water Purification',
    'drowning-rescue-guide': 'Drowning Rescue Guide',
    'self-rescue-drowning': 'Self-Rescue from Drowning',
    'unconscious-drowning-cpr': 'Unconscious Drowning Victim and CPR',
    'cpr-complete-guide': 'Complete CPR Guide',
    'ankle-sprain-first-aid': 'Ankle Sprain First Aid',
    'burn-injury-first-aid': 'Burn Injury First Aid',
    'overdose-rescue-guide': 'Overdose Rescue Guide',
    'bleeding-wound-first-aid': 'Severe Bleeding First Aid',
    'heatstroke-first-aid': 'Heatstroke First Aid',
    'hypothermia-first-aid': 'Hypothermia First Aid',
    'snake-insect-bite-first-aid': 'Snake and Insect Bite First Aid',
    'diy-water-container': 'DIY Water Container',
    'water-safety-children': 'Water Safety for Children',
    'leg-fracture-first-aid': 'Leg Fracture First Aid',
    'diy-hammock-trees': 'DIY Hammock Between Trees',
    'choosing-campsite': 'How to Choose a Campsite',
    'ground-insulation-sleeping': 'Ground Insulation for Sleeping',
    'bear-encounter-guide': 'Bear Encounter Guide',
    'wild-boar-encounter': 'Wild Boar Encounter',
    'snake-encounter-guide': 'Snake Encounter Guide',
    'stray-dog-encounter': 'Stray Dog Encounter',
    'night-wildlife-defense': 'Night Wildlife Safety',
    'safe-food-storage-outdoor': 'Safe Food Storage Outdoors',
    'navigation-by-sun': 'Navigation by the Sun',
    'navigation-by-stars': 'Navigation by the Stars',
    'lost-in-nature-guide': 'Lost in Nature Guide',
    'paper-map-reading': 'Paper Map Reading',
    'rescue-signaling-guide': 'Rescue Signaling Guide',
    'international-distress-signals': 'International Distress Signals',
    'knot-tying-guide': 'Knot Tying Guide',
    'outdoor-cooking-minimal': 'Minimalist Outdoor Cooking',
    'personal-hygiene-outdoor': 'Personal Hygiene Outdoors',
    'children-outdoor-safety': 'Children Outdoor Safety',
}

READY_STATUSES = ('آمادهٔ انتشار', 'منتشرشده')

# ---------------------------------------------------------------------------
# inline markdown → HTML
# ---------------------------------------------------------------------------
LINK_RE = re.compile(r'\[([^\]]+)\]\(([^)\s]+)\)')
IMAGE_LINE_RE = re.compile(r'^!\[([^\]]+)\]\(([^)\s]+)\)$')
GUIDE_IMAGE_RE = re.compile(
    r'^\.\./\.\./\.\./wp-content/themes/sarzaminaryan-child/assets/img/guides/'
    r'([a-z0-9-]+\.(?:webp|jpe?g|png))$'
)
TOPIC_CARD_IMAGE_RE = re.compile(r'^topic-cards/[a-z0-9][a-z0-9-]*\.webp$')
BOLD_RE = re.compile(r'\*\*(.+?)\*\*')
ITAL_RE = re.compile(r'(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])')
CODE_RE = re.compile(r'`([^`]+)`')


class Inline:
    def __init__(self, site_url: str):
        self.site = site_url.rstrip('/')
        self.internal: List[str] = []

    def href(self, url: str) -> Tuple[str, bool]:
        """پیوند داخلی مطلق → نسبی؛ خروجی: (href, external?)"""
        if url.startswith(self.site + '/'):
            path = url[len(self.site):]
            if not path.endswith('/'):
                path += '/'
            self.internal.append(path.strip('/'))
            return path, False
        if url.startswith('/'):
            if not url.endswith('/') and '#' not in url and '?' not in url:
                url += '/'
            self.internal.append(url.strip('/'))
            return url, False
        return url, True

    def image(self, alt: str, url: str) -> str:
        """Render a GitHub-relative guide image as a checked local theme asset."""
        match = GUIDE_IMAGE_RE.fullmatch(url)
        if not match or not alt.strip():
            raise ValueError('تصویر بدنه باید مسیر محلی مجاز و متن جایگزین داشته باشد: ' + url)
        filename = match.group(1)
        local_file = os.path.join(THEME_IMG, 'guides', filename)
        if not os.path.isfile(local_file):
            raise ValueError('فایل تصویر بدنه پیدا نشد: ' + local_file)
        src = self.site + '/wp-content/themes/sarzaminaryan-child/assets/img/guides/' + filename
        return (
            '<figure class="sa-guide-infographic"><img src="' + html.escape(src, quote=True)
            + '" alt="' + html.escape(alt.strip(), quote=True) + '" loading="lazy" decoding="async" /></figure>'
        )

    def topic_card(self, image: str, alt: str, title: str, description: str) -> str:
        """Render a validated, uniform guide topic card using a bundled WebP asset."""
        if not TOPIC_CARD_IMAGE_RE.fullmatch(image):
            raise ValueError('مسیر تصویر کارت باید topic-cards/{name}.webp باشد: ' + image)
        if not alt.strip() or not title.strip() or not description.strip():
            raise ValueError('کارت موضوعی باید متن جایگزین، عنوان و توضیح داشته باشد.')
        local_file = os.path.join(THEME_IMG, 'guides', image)
        if not os.path.isfile(local_file):
            raise ValueError('فایل تصویر کارت پیدا نشد: ' + local_file)
        src = self.site + '/wp-content/themes/sarzaminaryan-child/assets/img/guides/' + image
        return (
            '<figure class="sa-guide-topic-card">'
            '<div class="sa-guide-topic-card__media"><img src="' + html.escape(src, quote=True)
            + '" alt="' + html.escape(alt.strip(), quote=True)
            + '" width="720" height="540" loading="lazy" decoding="async" /></div>'
            '<figcaption class="sa-guide-topic-card__body">'
            '<strong class="sa-guide-topic-card__title">' + html.escape(title.strip(), quote=False) + '</strong>'
            '<span class="sa-guide-topic-card__description">' + html.escape(description.strip(), quote=False) + '</span>'
            '</figcaption></figure>'
        )

    def render(self, text: str) -> str:
        text = html.escape(text, quote=False)
        # پیوندها را قبل از تأکیدها بسازیم تا * داخل URL آسیب نبیند
        out: List[str] = []
        pos = 0
        for m in LINK_RE.finditer(text):
            out.append(self._emph(text[pos:m.start()]))
            label, url = m.group(1), html.unescape(m.group(2))
            href, external = self.href(url)
            attrs = ' target="_blank" rel="noopener noreferrer"' if external else ''
            out.append('<a href="' + html.escape(href, quote=True) + '"' + attrs + '>' + self._emph(label) + '</a>')
            pos = m.end()
        out.append(self._emph(text[pos:]))
        return ''.join(out)

    @staticmethod
    def _emph(s: str) -> str:
        s = CODE_RE.sub(lambda m: '<code>' + m.group(1) + '</code>', s)
        s = BOLD_RE.sub(lambda m: '<strong>' + m.group(1) + '</strong>', s)
        s = ITAL_RE.sub(lambda m: '<em>' + m.group(1) + '</em>', s)
        return s


# ---------------------------------------------------------------------------
# block renderer
# ---------------------------------------------------------------------------
STEP_RE = re.compile(r'^([۰-۹0-9]+)\.\s+\*\*([^*]+)\*\*\s*$')
OL_RE = re.compile(r'^([۰-۹0-9]+)\.\s+(.*)$')
UL_RE = re.compile(r'^(\s*)[-*]\s+(.*)$')
CHECK_RE = re.compile(r'^[-*]\s+\[[ xX]\]\s+(.*)$')
TABLE_SEP_RE = re.compile(r'^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$')
HEADING_RE = re.compile(r'^(#{2,6})\s+(.*)$')
NOTE_LINE_RE = re.compile(r'^\*(?!\*)(.+)\*$')
LABEL_LINE_RE = re.compile(r'^\*\*([^*]+):\*\*\s*(.*)$')
STEP_META_LABELS = ('مناسب برای', 'لازم داری', 'زمان', 'هشدار', 'تصویر')
CARD_START = ':::sa-topic-card'


def split_cells(line: str) -> List[str]:
    s = line.strip()
    if s.startswith('|'):
        s = s[1:]
    if s.endswith('|'):
        s = s[:-1]
    return [c.strip() for c in s.split('|')]


class Renderer:
    def __init__(self, inline: Inline):
        self.inl = inline
        self.out: List[str] = []
        self.last = ''  # نوع آخرین بلوک (برای تشخیص عنوان مرحله از آیتم فهرست شماره‌دار)

    @staticmethod
    def num(s: str) -> int:
        return int(am.to_persian_digits(s).translate(str.maketrans('۰۱۲۳۴۵۶۷۸۹', '0123456789')))

    def is_step_heading(self, lines: List[str], i: int, m) -> bool:
        """«۱. **عنوان**» وقتی عنوان مرحله است که ادامهٔ یک فهرست شماره‌دار نباشد."""
        if self.last == 'ol':
            return False
        nxt = next((lines[k].strip() for k in range(i + 1, len(lines)) if lines[k].strip()), '')
        mo = OL_RE.match(nxt)
        if not mo:
            return True
        return self.num(mo.group(1)) == 1  # فهرست بعدی از ۱ شروع می‌شود → این سطر عنوان مرحله است

    # -- helpers
    def p(self, text: str, cls: str = '') -> None:
        attr = ' class="' + cls + '"' if cls else ''
        self.out.append('<p' + attr + '>' + self.inl.render(text) + '</p>')

    def render_lines(self, lines: List[str]) -> str:
        i = 0
        n = len(lines)
        while i < n:
            line = lines[i]
            s = line.strip()
            if not s or s == '---':
                i += 1
                continue
            # Uniform, locally bundled topic card; fields stay plain text and are escaped at render time.
            if s == CARD_START:
                j = i + 1
                fields: Dict[str, str] = {}
                allowed = ('image', 'alt', 'title', 'description')
                while j < n and lines[j].strip() != ':::':
                    raw = lines[j].strip()
                    if raw:
                        key, sep, value = raw.partition(':')
                        if not sep or key not in allowed or key in fields:
                            raise ValueError('فیلد نامعتبر یا تکراری در کارت موضوعی: ' + raw)
                        fields[key] = value.strip()
                    j += 1
                if j >= n:
                    raise ValueError('پایان کارت موضوعی ::: پیدا نشد.')
                missing = [key for key in allowed if not fields.get(key, '').strip()]
                if missing:
                    raise ValueError('فیلدهای ضروری کارت موضوعی خالی هستند: ' + ', '.join(missing))
                self.out.append(self.inl.topic_card(fields['image'], fields['alt'], fields['title'], fields['description']))
                self.last = ''
                i = j + 1
                continue
            # GitHub-relative image in the source article → validated theme image in WordPress.
            m = IMAGE_LINE_RE.fullmatch(s)
            if m:
                self.out.append(self.inl.image(m.group(1), m.group(2)))
                self.last = ''
                i += 1
                continue
            # تیترها
            m = HEADING_RE.match(s)
            if m:
                lvl = len(m.group(1))
                self.last = ''
                self.out.append('<h%d>%s</h%d>' % (lvl, self.inl.render(am.strip_emoji(m.group(2)).strip()), lvl))
                i += 1
                continue
            # نقل‌قول / جعبه
            if s.startswith('>'):
                j = i
                block: List[str] = []
                while j < n and lines[j].strip().startswith('>'):
                    block.append(re.sub(r'^\s*>\s?', '', lines[j]))
                    j += 1
                self.last = ''
                self.quote(block)
                i = j
                continue
            # جدول
            if s.startswith('|') and i + 1 < n and TABLE_SEP_RE.match(lines[i + 1].strip()):
                j = i + 2
                rows: List[str] = []
                while j < n and lines[j].strip().startswith('|'):
                    rows.append(lines[j])
                    j += 1
                self.last = ''
                self.table(lines[i], rows)
                i = j
                continue
            # چک‌لیست
            if CHECK_RE.match(s):
                j = i
                items: List[str] = []
                while j < n:
                    if not lines[j].strip():
                        k = self.next_nonblank(lines, j)
                        if k < n and CHECK_RE.match(lines[k].strip()):
                            j = k
                            continue
                        break
                    if not CHECK_RE.match(lines[j].strip()):
                        break
                    items.append(CHECK_RE.match(lines[j].strip()).group(1))
                    j += 1
                self.out.append('<ul class="sa-checklist">')
                for it in items:
                    self.out.append('<li>' + self.inl.render(it) + '</li>')
                self.out.append('</ul>')
                self.last = ''
                i = j
                continue
            # فهرست نقطه‌ای (با تورفتگی یک سطح)
            if UL_RE.match(line) and not line.startswith((' ', '\t')):
                j = i
                items: List[Tuple[str, List[str]]] = []
                while j < n:
                    if not lines[j].strip():
                        k = self.next_nonblank(lines, j)
                        if k < n and UL_RE.match(lines[k]) and not CHECK_RE.match(lines[k].strip()):
                            j = k
                            continue
                        break
                    mm = UL_RE.match(lines[j])
                    if not mm or CHECK_RE.match(lines[j].strip()):
                        break
                    if mm.group(1):
                        if items:
                            items[-1][1].append(mm.group(2))
                    else:
                        items.append((mm.group(2), []))
                    j += 1
                self.out.append('<ul>')
                for text, subs in items:
                    if subs:
                        self.out.append('<li>' + self.inl.render(text) + '\n<ul>\n' + '\n'.join('<li>' + self.inl.render(x) + '</li>' for x in subs) + '\n</ul>\n</li>')
                    else:
                        self.out.append('<li>' + self.inl.render(text) + '</li>')
                self.out.append('</ul>')
                self.last = ''
                i = j
                continue
            # عنوان مرحله: «۱. **عنوان**» که بعدش بند می‌آید
            m = STEP_RE.match(s)
            if m and self.is_step_heading(lines, i, m):
                self.out.append('<h3 class="sa-step"><span class="sa-step__n">%s</span> %s</h3>' % (am.to_persian_digits(m.group(1)), self.inl.render(m.group(2).strip())))
                self.last = 'step'
                i += 1
                continue
            # فهرست شماره‌دار
            if OL_RE.match(s):
                j = i
                items2: List[str] = []
                last_no = 0
                while j < n:
                    if not lines[j].strip():
                        k = self.next_nonblank(lines, j)
                        mk = OL_RE.match(lines[k].strip()) if k < n else None
                        if mk and not lines[k].startswith((' ', '\t')) and (not STEP_RE.match(lines[k].strip()) or self.num(mk.group(1)) == last_no + 1):
                            j = k
                            continue
                        break
                    mj = OL_RE.match(lines[j].strip())
                    if not mj or lines[j].startswith((' ', '\t')):
                        break
                    if STEP_RE.match(lines[j].strip()) and items2 and self.num(mj.group(1)) != last_no + 1:
                        break  # عنوان مرحلهٔ بعدی، نه ادامهٔ فهرست
                    items2.append(mj.group(2))
                    last_no = self.num(mj.group(1))
                    j += 1
                    # زیرفهرست تورفته
                    while j < n and lines[j].startswith((' ', '\t')) and UL_RE.match(lines[j]):
                        items2[-1] += '\n<ul>\n<li>' + self.inl.render(UL_RE.match(lines[j]).group(2)) + '</li>\n</ul>'
                        j += 1
                self.out.append('<ol>')
                for it in items2:
                    if '\n<ul>' in it:
                        head, rest = it.split('\n<ul>', 1)
                        self.out.append('<li>' + self.inl.render(head) + '\n<ul>' + rest + '</li>')
                    else:
                        self.out.append('<li>' + self.inl.render(it) + '</li>')
                self.out.append('</ol>')
                self.last = 'ol'
                i = j
                continue
            # سطر یادداشت ایتالیک (شفافیت / سلب مسئولیت)
            m = NOTE_LINE_RE.match(s)
            if m and '*' not in m.group(1):
                self.last = ''
                self.p(m.group(1), 'sa-article__note')
                i += 1
                continue
            # سطرهای برچسب‌دار بخش گام‌به‌گام («مناسب برای:» …) → فهرست مشخصات
            m = LABEL_LINE_RE.match(s)
            if m and m.group(1).strip() in STEP_META_LABELS:
                j = i
                metas: List[Tuple[str, str]] = []
                while j < n:
                    t = lines[j].strip()
                    if not t:
                        j += 1
                        continue
                    mm = LABEL_LINE_RE.match(t)
                    if mm and mm.group(1).strip() in STEP_META_LABELS:
                        metas.append((mm.group(1).strip(), mm.group(2).strip()))
                        j += 1
                        continue
                    break
                metas = [(k, v) for k, v in metas if k != 'تصویر']  # یادداشت ویراستاری؛ در سایت نمایش داده نمی‌شود
                if metas:
                    self.out.append('<ul class="sa-steps-meta">')
                    for k, v in metas:
                        self.out.append('<li><strong>' + html.escape(k) + ':</strong> ' + self.inl.render(v) + '</li>')
                    self.out.append('</ul>')
                self.last = ''
                i = j
                continue
            # بند معمولی (سطرهای پیاپی غیرخالی یک بند هستند)
            j = i
            buf: List[str] = []
            while j < n and lines[j].strip() and not self.is_block_start(lines[j]):
                buf.append(lines[j].strip())
                j += 1
            if not buf:  # خط شروع بلوک ناشناخته؛ به‌عنوان بند
                buf = [s]
                j = i + 1
            self.last = ''
            self.p(' '.join(buf))
            i = j
        return '\n'.join(self.out)

    @staticmethod
    def next_nonblank(lines: List[str], j: int) -> int:
        while j < len(lines) and not lines[j].strip():
            j += 1
        return j

    @staticmethod
    def is_block_start(line: str) -> bool:
        s = line.strip()
        return bool(HEADING_RE.match(s) or s.startswith('>') or s.startswith('|') or UL_RE.match(line) or OL_RE.match(s) or s == '---' or NOTE_LINE_RE.match(s) or LABEL_LINE_RE.match(s) or s == CARD_START)


    def quote(self, block: List[str]) -> None:
        first = next((b for b in block if b.strip()), '')
        body = [b for b in block if b.strip()]
        if first.startswith('### '):
            title = first[4:].strip()
            rest = body[1:]
            self.out.append('<div class="sa-callout sa-callout--warning" role="note">')
            self.out.append('<p class="sa-callout__title">' + self.inl.render(title) + '</p>')
            for r in rest:
                self.p(r)
            self.out.append('</div>')
            return
        if first.startswith('**پاسخ سریع'):
            self.out.append('<div class="sa-callout sa-callout--answer">')
            for r in body:
                self.p(r)
            self.out.append('</div>')
            return
        self.out.append('<blockquote>')
        for r in body:
            self.p(r)
        self.out.append('</blockquote>')

    def table(self, header: str, rows: List[str]) -> None:
        heads = split_cells(header)
        self.out.append('<figure class="wp-block-table sa-table-wrap"><table class="sa-table">')
        self.out.append('<thead><tr>' + ''.join('<th>' + self.inl.render(h) + '</th>' for h in heads) + '</tr></thead>')
        self.out.append('<tbody>')
        for r in rows:
            cells = split_cells(r)
            cells += [''] * (len(heads) - len(cells))
            self.out.append('<tr>' + ''.join('<td>' + self.inl.render(c) + '</td>' for c in cells[:len(heads)]) + '</tr>')
        self.out.append('</tbody></table></figure>')


# ---------------------------------------------------------------------------
# article → record
# ---------------------------------------------------------------------------
def php_str(s: str) -> str:
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def sources_lines(art: am.Article, inline: Inline) -> List[str]:
    sec = art.section_by_key('sources')
    if not sec:
        return []
    out: List[str] = []
    for l in art.lines[sec.start + 1:sec.end]:
        s = l.strip()
        if not s or s.startswith('#'):
            continue
        s = re.sub(r'^[-*]\s+', '', s)
        if re.search(r'پس از تأیید|اضافه می‌شود|درج شده است$', s) and 'http' not in s:
            continue  # یادداشت ویراستاری دربارهٔ منابع، نه خودِ منبع
        links = LINK_RE.findall(s)
        if links:
            # «CDC — [متن](url) و [متن۲](url۲)» → یک سطر برای هر پیوند
            prefix = s.split('[', 1)[0].strip(' —-:،')
            for label, url in links:
                title = (prefix + ' — ' if prefix else '') + label
                out.append(title.replace('|', '/') + ' | ' + url)
            continue
        m = re.search(r'https?://\S+', s)
        if m:
            url = m.group(0).rstrip('.،)')
            title = s.replace(m.group(0), '').strip(' :—-')
            out.append(title.replace('|', '/') + ' | ' + url)
        else:
            out.append(s.replace('|', '/').rstrip('.'))
    return out


def faq_rows(art: am.Article, inline: Inline) -> List[Dict[str, str]]:
    rows = []
    for q, a, _ in art.faq:
        rows.append({'q': am.strip_emoji(q).strip(), 'a': inline.render(a.strip())})
    return rows


def body_html(art: am.Article, inline: Inline) -> str:
    skip_keys = {'faq', 'sources', 'schema'}
    lines = art.lines
    keep: List[str] = []
    # از بعد از H1 تا قبل از اولین بخش حذف‌شده‌ها، بخش‌به‌بخش
    h1 = next((i for i, l in enumerate(lines) if l.startswith('# ')), -1)
    secs = art.sections
    # محدودهٔ پیش از اولین H2
    first_h2 = min((s.start for s in secs if s.level == 2), default=len(lines))
    pre = lines[h1 + 1:first_h2]
    for l in pre:
        if l.strip().startswith('**نویسنده:**'):
            continue  # بایلاین را قالب از متا و تاریخ می‌سازد
        keep.append(l)
    for s in secs:
        if s.level != 2:
            continue
        if s.key in skip_keys:
            continue
        keep.extend(lines[s.start:s.end])
    # فنس‌های کد (اسکیما) هرگز نباید در بدنه بمانند
    text = '\n'.join(keep)
    text = re.sub(r'```.*?```', '', text, flags=re.S)
    r = Renderer(inline)
    return r.render_lines(text.split('\n'))


def build_record(path: str, cfg: dict) -> Optional[Dict[str, object]]:
    art = am.parse(path)
    status = art.front.get('وضعیت', '')
    if status not in READY_STATUSES:
        return None
    slug = art.front.get('نامک', '').strip()
    if not slug:
        raise SystemExit('نامک ندارد: ' + path)
    inline = Inline(cfg['site_url'])
    content = body_html(art, inline)
    branch = next((k for k, (_, _, slugs) in BRANCHES.items() if slug in slugs), None)
    tags = {branch: BRANCHES[branch][0]} if branch else {}
    sources = sources_lines(art, inline)
    rec = {
        'key': slug,
        'title': am.strip_emoji(art.h1 or art.front.get('عنوان سئو', '')).strip(),
        'type': 'post',
        'slug': slug,
        'excerpt': art.front.get('چکیده', '').strip(),
        'english': ENGLISH.get(slug, slug.replace('-', ' ').title()),
        'kind': 'tent',
        'category': {CATEGORY[0]: CATEGORY[1]},
        'tags': tags,
        'meta': {
            'sa_seo_title': art.front.get('عنوان سئو', '').strip(),
            'sa_seo_description': art.front.get('توضیح متا', '').strip(),
            'sa_focus_keyword': art.front.get('کلمهٔ کلیدی اصلی', art.front.get('کلمه کلیدی اصلی', '')).strip(),
            'sa_byline': cfg.get('byline', 'تیم تحریریه آموزش‌های بقا'),
            'sa_risk_level': art.front.get('سطح ریسک', '').strip(),
            'sa_sources': '\n'.join(sources),
        },
        'faq': faq_rows(art, inline),
        'cover': cover_record(art, path),
        'remove_featured_image': art.front.get('تصویر شاخص', '').strip() == 'حذف',
        'content': content,
        'internal_links': sorted(set(inline.internal)),
    }
    return rec


def cover_record(art: am.Article, path: str) -> Dict[str, str]:
    """تصویر شاخص همراه قالب: «تصویر شاخص: guides/<slug>.webp» + «متن جایگزین تصویر: …» در بلوک سئو."""
    raw = art.front.get('تصویر شاخص', '').strip()
    if raw == 'حذف':
        return {}
    file = raw.lstrip('/')
    if not file or file in ('بدون تصویر', '—', '-'):
        return {}
    if not COVER_RE.match(file) or '..' in file:
        raise SystemExit('مسیر «تصویر شاخص» نامعتبر است (فقط حروف کوچک لاتین، رقم، خط تیره و پسوند webp/jpg/png): %s در %s' % (file, path))
    if not os.path.isfile(os.path.join(THEME_IMG, file)):
        raise SystemExit('فایل «تصویر شاخص» در قالب نیست: assets/img/%s (%s)' % (file, path))
    kw = art.front.get('کلمهٔ کلیدی اصلی', art.front.get('کلمه کلیدی اصلی', '')).strip()
    alt = art.front.get('متن جایگزین تصویر', '').strip()
    if not alt:
        raise SystemExit('«متن جایگزین تصویر» برای تصویر شاخص خالی است: ' + path)
    if kw and kw not in alt:
        raise SystemExit('«متن جایگزین تصویر» باید کلمهٔ کلیدی اصلی («%s») را داشته باشد: %s' % (kw, path))
    return {
        'file': file,
        'alt': alt,
        'title': ('اینفوگرافیک ' + kw) if kw else alt,
    }


def php_array(d: Dict[str, str], indent: str) -> str:
    if not d:
        return 'array()'
    lines = ['array(']
    for k, v in d.items():
        lines.append(indent + '\t' + php_str(k) + ' => ' + php_str(v) + ',')
    lines.append(indent + ')')
    return '\n'.join(lines)


def emit_php(records: List[Dict[str, object]], version_note: str) -> str:
    out: List[str] = []
    out.append('<?php')
    out.append('/**')
    out.append(' * «مقالات آمادهٔ آموزش کمپینگ و بقا» — data source for the in-theme import tool (type: post).')
    out.append(' *')
    out.append(' * GENERATED by tools/content-lint/build_theme_data.py from docs/camping-education/articles/*.md')
    out.append(' * (only articles whose «وضعیت» is «آمادهٔ انتشار»/«منتشرشده»). Do not edit by hand; edit the')
    out.append(' * Markdown article, run the content filter, then rebuild this file.')
    out.append(' *')
    out.append(' * Importing a record creates a draft post in the «' + CATEGORY[1] + '» category; the owner\'s')
    out.append(' * approval tick publishes it. FAQ rows go to the sa_faq meta (visible accordion, no FAQPage')
    out.append(' * JSON-LD), sources to sa_sources; the theme prints the Article schema itself.')
    out.append(' *')
    out.append(' * ' + version_note)
    out.append(' *')
    out.append(' * @package Sarzaminaryan_Child')
    out.append(' */')
    out.append('')
    out.append("if ( ! defined( 'ABSPATH' ) ) {")
    out.append('\texit;')
    out.append('}')
    out.append('')
    out.append('return array(')
    for r in records:
        out.append('\t' + php_str(r['key']) + ' => array(')
        for k in ('title', 'type', 'slug', 'excerpt', 'english', 'kind'):
            out.append('\t\t' + php_str(k).ljust(16) + ' => ' + php_str(str(r[k])) + ',')
        out.append("\t\t'category'      => " + php_array(r['category'], '\t\t') + ',')
        out.append("\t\t'tags'          => " + php_array(r['tags'], '\t\t') + ',')
        out.append("\t\t'meta'          => " + php_array(r['meta'], '\t\t') + ',')
        if r.get('cover'):
            out.append("\t\t'cover'         => " + php_array(r['cover'], '\t\t') + ',')
        if r.get('remove_featured_image'):
            out.append("\t\t'remove_featured_image' => true,")
        out.append("\t\t'faq'           => array(")
        for row in r['faq']:
            out.append('\t\t\tarray(')
            out.append("\t\t\t\t'q' => " + php_str(row['q']) + ',')
            out.append("\t\t\t\t'a' => " + php_str(row['a']) + ',')
            out.append('\t\t\t),')
        out.append('\t\t),')
        out.append("\t\t'content'       => <<<'SA_ART'")
        out.append(str(r['content']))
        out.append('SA_ART,')
        out.append('\t),')
    out.append(');')
    return '\n'.join(out) + '\n'


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--articles', default=DEFAULT_ARTICLES)
    ap.add_argument('--out', default=DEFAULT_OUT)
    ap.add_argument('--check', action='store_true', help='فقط بررسی کن که فایل خروجی با نتیجهٔ تولید یکی است (برای CI)')
    ns = ap.parse_args(argv)

    cfg = json.load(open(CONFIG, encoding='utf-8'))
    allowed = set(cfg.get('allowed_slugs', [])) | {x.strip('/') for x in cfg.get('fixed_paths', [])}
    records: List[Dict[str, object]] = []
    problems: List[str] = []
    for name in sorted(os.listdir(ns.articles)):
        if not name.endswith('.md'):
            continue
        rec = build_record(os.path.join(ns.articles, name), cfg)
        if rec is None:
            continue
        c = str(rec['content'])
        for bad, label in (('**', 'نشانهٔ ** باقی مانده'), ('](', 'پیوند مارک‌داون باقی مانده'), ('\n## ', 'تیتر مارک‌داون باقی مانده'), ('{{', 'جای‌خالی قالب'), ('```', 'فنس کد')):
            if bad in c:
                problems.append('%s: %s' % (rec['slug'], label))
        for l in rec['internal_links']:
            base = l.split('#')[0]
            if base and base not in allowed:
                problems.append('%s: پیوند داخلی غیرمجاز /%s/' % (rec['slug'], base))
        if not rec['faq']:
            problems.append('%s: FAQ خالی' % rec['slug'])
        records.append(rec)
    if problems:
        print('\n'.join('✗ ' + p for p in problems))
        return 1
    note = 'Records: %d. Category: %s (%s).' % (len(records), CATEGORY[1], CATEGORY[0])
    php = emit_php(records, note)
    if ns.check:
        cur = open(ns.out, encoding='utf-8').read() if os.path.exists(ns.out) else ''
        if cur != php:
            print('✗ ' + ns.out + ' با مقالات هم‌خوان نیست؛ build_theme_data.py را اجرا کن.')
            return 1
        print('✓ ' + ns.out + ' به‌روز است (%d رکورد).' % len(records))
        return 0
    os.makedirs(os.path.dirname(ns.out), exist_ok=True)
    with open(ns.out, 'w', encoding='utf-8') as fh:
        fh.write(php)
    print('نوشته شد: %s (%d رکورد، %d کیلوبایت)' % (ns.out, len(records), len(php.encode('utf-8')) // 1024))
    return 0


if __name__ == '__main__':
    sys.exit(main())
