#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
build_import_package.py — turns content/provinces/{slug}.md (Production Prompt output, 9 blocks)
into JSON packages for the WordPress importer plugins (wp-content/plugins/sa-province-importer-bNN).

One importer plugin = one batch of 10 provinces in the fixed order of
wp-content/themes/sarzaminaryan-child/data/provinces.php:
  batch 1 → provinces 1–10, batch 2 → 11–20, batch 3 → 21–31.

For every slug in the batch:
  * article exists  → full package (status "article"): Gutenberg HTML, excerpt, sa_* meta, SEO, FAQ, sources
  * no article yet  → stub package (status "stub"): title + taxonomy + featured image only
  * featured image  → copied from assets/featured/provinces/{slug}.webp with alt/caption from manifest.json

Usage:
  python3 content-templates/tools/build_import_package.py --batch 1
  python3 content-templates/tools/build_import_package.py --batch 1 --out wp-content/plugins/sa-province-importer-b01/data

No third-party modules (no PyYAML): the YAML subset used by the templates is parsed by hand.
"""
import argparse
import datetime
import hashlib
import html
import json
import os
import re
import shutil
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
PROVINCES_PHP = os.path.join(ROOT, 'wp-content', 'themes', 'sarzaminaryan-child', 'data', 'provinces.php')
CONTENT_DIR = os.path.join(ROOT, 'content', 'provinces')
IMG_DIR = os.path.join(ROOT, 'assets', 'featured', 'provinces')
IMG_MANIFEST = os.path.join(IMG_DIR, 'manifest.json')

BATCH_SIZE = 10
PACKAGE_FORMAT = '1.0'


# --------------------------------------------------------------------------- helpers

def load_provinces():
    txt = open(PROVINCES_PHP, encoding='utf-8').read()
    rx = re.compile(r"'slug'\s*=>\s*'([^']+)'.*?'name'\s*=>\s*'([^']+)'.*?'en'\s*=>\s*'([^']+)'.*?'center'\s*=>\s*'([^']+)'")
    return [dict(slug=m.group(1), name=m.group(2), en=m.group(3), center=m.group(4)) for m in rx.finditer(txt)]


def block(text, n):
    """Return the text of BLOCK n (between its header and the next '=== BLOCK' header / end)."""
    m = re.search(r'^=== BLOCK %d:[^\n]*\n(.*?)(?=^=== BLOCK \d|\Z)' % n, text, re.S | re.M)
    return m.group(1) if m else ''


def fenced(text):
    """First fenced code block inside text (``` or ```yaml)."""
    m = re.search(r'```[a-z]*\n(.*?)\n```', text, re.S)
    return m.group(1) if m else ''


def strip_comment(v):
    # inline YAML comments in the templates are written as "value   # note"
    v = re.sub(r'\s+#\s.*$', '', v)
    v = re.sub(r'\s+#[^\s].*$', '', v) if re.search(r'\s+#[^\s]', v) else v
    return v.strip().strip('"').strip("'")


def parse_block1(text):
    """Hand-rolled parser for the BLOCK 1 YAML subset."""
    out, lst, sub = {}, None, None
    for raw in fenced(text).split('\n'):
        if not raw.strip() or raw.lstrip().startswith('#'):
            continue
        indent = len(raw) - len(raw.lstrip(' '))
        line = raw.strip()
        if indent == 0:
            lst = sub = None
            k, _, v = line.partition(':')
            k, v = k.strip(), strip_comment(v)
            if v == '':
                out[k] = {} if k in ('fields', 'taxonomy') else []
                if isinstance(out[k], dict):
                    sub = k
                else:
                    lst = k
            else:
                out[k] = v
        else:
            if line.startswith('- ') and lst:
                out[lst].append(strip_comment(line[2:]))
            elif sub and ':' in line:
                k, _, v = line.partition(':')
                out[sub][k.strip()] = strip_comment(v)
    # typed fields
    f = out.get('fields', {})
    if 'travel_season' in f:
        f['travel_season'] = [s.strip() for s in f['travel_season'].strip('[]').split(',') if s.strip()]
    for k in ('population', 'area_km2'):
        if k in f:
            f[k] = re.sub(r'[^\d.]', '', f[k])
    return out


def parse_faq(text):
    faq, q = [], None
    for raw in fenced(text).split('\n'):
        line = raw.rstrip()
        if line.startswith('- q:'):
            q = line[4:].strip()
        elif line.strip().startswith('a:') and q is not None:
            faq.append({'q': q, 'a': line.strip()[2:].strip()})
            q = None
    return faq


def parse_sources(text):
    src = fenced(text).strip()
    # the private editor note may mention markup literally; keep it readable once tags are stripped in WP
    return src.replace('<sup>[n]</sup>', '[n]').replace('<ol>', '«ol»')


def parse_block9(text):
    body = fenced(text) or text
    m = re.search(r'^(DRAFT ONLY|READY TO PUBLISH|PUBLISH)', body.strip(), re.M)
    return m.group(1) if m else 'DRAFT ONLY'


# --------------------------------------------------------------------------- markdown → gutenberg

def esc_attr(url):
    return html.escape(url, quote=True)


def inline(text):
    """Escape, then convert the inline subset: <sup>[n](url)</sup>, [t](url), **bold**."""
    t = html.escape(text, quote=False)
    # citations written as <sup>[۱](https://…)</sup> (escaped above)
    t = re.sub(r'&lt;sup&gt;\[([^\]]+)\]\(([^)\s]+)\)&lt;/sup&gt;',
               lambda m: '<sup class="sa-cite"><a href="%s">%s</a></sup>' % (esc_attr(html.unescape(m.group(2))), m.group(1)), t)
    # generic links
    t = re.sub(r'\[([^\]]+)\]\(([^)\s]+)\)',
               lambda m: '<a href="%s">%s</a>' % (esc_attr(html.unescape(m.group(2))), m.group(1)), t)
    # bold
    t = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', t)
    return t


def table_block(rows):
    cells = [[c.strip() for c in r.strip().strip('|').split('|')] for r in rows]
    if len(cells) >= 2 and all(re.match(r'^:?-{2,}:?$', c) for c in cells[1]):
        head, body = cells[0], cells[2:]
    else:
        head, body = None, cells
    out = ['<!-- wp:table --><figure class="wp-block-table"><table>']
    if head:
        out.append('<thead><tr>' + ''.join('<th>%s</th>' % inline(c) for c in head) + '</tr></thead>')
    out.append('<tbody>')
    for r in body:
        out.append('<tr>' + ''.join('<td>%s</td>' % inline(c) for c in r) + '</tr>')
    out.append('</tbody></table></figure><!-- /wp:table -->')
    return ''.join(out)


def list_block(items, ordered):
    tag = 'ol' if ordered else 'ul'
    attrs = ' {"ordered":true}' if ordered else ''
    li = ''.join('<!-- wp:list-item --><li>%s</li><!-- /wp:list-item -->' % inline(i) for i in items)
    return '<!-- wp:list%s --><%s class="wp-block-list">%s</%s><!-- /wp:list -->' % (attrs, tag, li, tag)


def md_to_blocks(md):
    lines = md.split('\n')
    out, i, n = [], 0, len(lines)
    para = []

    def flush_para():
        if para:
            out.append('<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->' % inline(' '.join(s.strip() for s in para)))
            para.clear()

    while i < n:
        line = lines[i].rstrip()
        s = line.strip()
        if not s:
            flush_para(); i += 1; continue
        m = re.match(r'^(#{1,6})\s+(.*)$', s)
        if m:
            flush_para()
            level = len(m.group(1))
            attrs = '' if level == 2 else ' {"level":%d}' % level
            out.append('<!-- wp:heading%s --><h%d class="wp-block-heading">%s</h%d><!-- /wp:heading -->' % (attrs, level, inline(m.group(2).strip()), level))
            i += 1; continue
        if s.startswith('|'):
            flush_para()
            rows = []
            while i < n and lines[i].strip().startswith('|'):
                rows.append(lines[i]); i += 1
            out.append(table_block(rows)); continue
        if re.match(r'^[-*]\s+', s):
            flush_para()
            items = []
            while i < n and re.match(r'^\s*[-*]\s+', lines[i]):
                item = re.sub(r'^\s*[-*]\s+', '', lines[i]).rstrip()
                i += 1
                # continuation lines (indented, not a new item)
                while i < n and lines[i].startswith('  ') and not re.match(r'^\s*[-*]\s+|^\s*\d+[.)]\s+', lines[i]) and lines[i].strip():
                    item += ' ' + lines[i].strip(); i += 1
                items.append(item)
            out.append(list_block(items, False)); continue
        if re.match(r'^\d+[.)]\s+', s):
            flush_para()
            items = []
            while i < n and re.match(r'^\s*\d+[.)]\s+', lines[i]):
                item = re.sub(r'^\s*\d+[.)]\s+', '', lines[i]).rstrip()
                i += 1
                while i < n and lines[i].startswith('  ') and not re.match(r'^\s*[-*]\s+|^\s*\d+[.)]\s+', lines[i]) and lines[i].strip():
                    item += ' ' + lines[i].strip(); i += 1
                items.append(item)
            out.append(list_block(items, True)); continue
        if s.startswith('>'):
            flush_para()
            q = []
            while i < n and lines[i].strip().startswith('>'):
                q.append(lines[i].strip()[1:].strip()); i += 1
            out.append('<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->' % inline(' '.join(q)))
            continue
        if s in ('---', '***'):
            flush_para()
            out.append('<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->')
            i += 1; continue
        para.append(line); i += 1
    flush_para()
    return '\n\n'.join(out)


# --------------------------------------------------------------------------- packages

def count_markers(text):
    return {
        'review': text.count('[نیازمند بررسی'),
        'source_needed': text.count('[منبع لازم'),
        'url_needed': text.count('[URL لازم'),
        'coming_soon': text.count('(به‌زودی)'),
    }


# --------------------------------------------------------------------------- copy cleaning
# The site copy must be clean: no review markers, no inline citation numbers. The .md sources
# keep markers/citations as the research trail; the importer packages drop them.

SUP_CITE_RE = re.compile(r'<sup>\s*(?:\[[^\]]*\]\([^)]*\)|\[[^\]]*\])\s*</sup>')
# markers start with these phrases and run to the first closing bracket, whatever the separator
# after the phrase (":", "؛", "برای …" or nothing).
MARKER_RE = re.compile(r'\[(?:نیازمند بررسی|منبع لازم)[^\]]*\]')


def clean_copy(text):
    """Strip inline <sup>[n](url)</sup> citation numbers and review markers from ship-to-site copy."""
    t = SUP_CITE_RE.sub('', text)
    t = MARKER_RE.sub('', t)
    t = re.sub(r'[ \t]+([،؛.؟!…])', r'\1', t)  # no space left before punctuation
    t = re.sub(r'[ \t]{2,}', ' ', t)
    t = re.sub(r'[ \t]+\n', '\n', t)
    t = re.sub(r'\n[ \t]+', '\n', t)
    t = re.sub(r'\n{3,}', '\n\n', t)
    return t


# --------------------------------------------------------------------------- counties
# County (شهرستان) entities are `city` CPT posts per the data model. Slugs come from each
# article's appendix link map (type=city rows); alborz/zanjan (no appendix) use manual lists.

MANUAL_COUNTIES = {
    'alborz': [
        ('کرج', 'karaj'), ('فردیس', 'ferdows'), ('ساوجبلاغ', 'sojablogh'), ('نظرآباد', 'nazarabad'),
        ('چهارباغ', 'chaharbagh'), ('اشتهارد', 'eshtehard'), ('طالقان', 'talqan'),
    ],
    'zanjan': [
        ('زنجان', 'zanjan-city'), ('خدابنده', 'khodabandeh'), ('ابهر', 'abhar'), ('خرمدره', 'kharadere'),
        ('طارم', 'tarom'), ('ماهنشان', 'mahneshan'), ('ایجرود', 'ejrud'), ('سلطانیه', 'soltaniyeh'),
    ],
}

CITY_ROW_RE = re.compile(r'^\|\s*[^|\n]*\|\s*([^|\n]+?)\s*\|\s*city\s*\|\s*([a-z0-9\-]+)\s*\|', re.M)


def parse_counties(md_text):
    out, seen = [], set()
    for m in CITY_ROW_RE.finditer(md_text):
        name = m.group(1).strip().strip('*').strip()
        name = re.sub(r'\s*\(شهر\)\s*$', '', name).strip()
        slug = m.group(2)
        if not name or slug in seen:
            continue
        seen.add(slug)
        out.append((name, slug))
    return out


def build_article_package(prov, md_path, images, built):
    text = open(md_path, encoding='utf-8').read()
    b1 = parse_block1(block(text, 1))
    body_md = block(text, 3).strip()
    faq = parse_faq(block(text, 4))
    sources = parse_sources(block(text, 5))
    status9 = parse_block9(block(text, 9))
    # markers are counted on the ORIGINAL text (research trail); ship-copy is cleaned.
    markers = count_markers(body_md + ' ' + ' '.join(x['a'] for x in faq))
    body_md = clean_copy(body_md)
    faq = [{'q': clean_copy(q['q']), 'a': clean_copy(q['a'])} for q in faq]
    f = b1.get('fields', {})
    content_html = md_to_blocks(body_md)
    words = len(re.sub(r'<[^>]+>', ' ', content_html).split())
    # two focus keywords per owner request: «استان X» + «X»
    focus = b1.get('focus_keyword', '')
    parts = [x.strip() for x in focus.split('،') if x.strip()]
    if prov['name'] and prov['name'] not in parts:
        parts.append(prov['name'])
    focus = '، '.join(parts)
    pkg = {
        'package_format': PACKAGE_FORMAT,
        'built': built,
        'source_file': os.path.relpath(md_path, ROOT).replace(os.sep, '/'),
        'source_sha1': hashlib.sha1(text.encode('utf-8')).hexdigest(),
        'slug': prov['slug'],
        'status': 'article',
        'name_fa': b1.get('province_name_fa', 'استان ' + prov['name']),
        'name_en': b1.get('province_name_en', prov['en'] + ' Province'),
        'term_name': prov['name'],
        'center_city': f.get('center_city', prov['center']),
        'post': {
            'title': b1.get('province_name_fa', 'استان ' + prov['name']),
            'excerpt': b1.get('excerpt', ''),
            'content_html': content_html,
            'word_count': words,
        },
        'meta': {
            'sa_province_population': f.get('population', ''),
            'sa_province_area': f.get('area_km2', ''),
            'sa_province_latitude': f.get('latitude', ''),
            'sa_province_longitude': f.get('longitude', ''),
            'sa_province_climate': f.get('climate', ''),
        },
        'seo': {
            'sa_seo_title': b1.get('seo_title', ''),
            'sa_seo_description': b1.get('meta_description', ''),
            'sa_focus_keyword': focus,
            'sa_og_title': b1.get('og_title', b1.get('seo_title', '')),
            'sa_og_description': b1.get('og_description', b1.get('meta_description', '')),
        },
        'secondary_keywords': b1.get('secondary_keywords', []),
        'travel_season': f.get('travel_season', []),
        'google_map_url': f.get('google_map_url', ''),
        'faq': faq,
        'sources': sources,
        'publish_status': status9,
        'markers': markers,
        'image': images.get(prov['slug']),
    }
    return pkg


def build_stub_package(prov, images, built):
    return {
        'package_format': PACKAGE_FORMAT,
        'built': built,
        'source_file': None,
        'source_sha1': None,
        'slug': prov['slug'],
        'status': 'stub',
        'name_fa': 'استان ' + prov['name'],
        'name_en': prov['en'] + ' Province',
        'term_name': prov['name'],
        'center_city': prov['center'],
        'post': {'title': 'استان ' + prov['name'], 'excerpt': '', 'content_html': '', 'word_count': 0},
        'meta': {}, 'seo': {}, 'secondary_keywords': [], 'travel_season': [], 'google_map_url': '',
        'faq': [], 'sources': '', 'publish_status': 'DRAFT ONLY',
        'markers': {'review': 0, 'source_needed': 0, 'url_needed': 0, 'coming_soon': 0},
        'image': images.get(prov['slug']),
    }


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--batch', type=int, required=True, help='1 → provinces 1–10, 2 → 11–20, 3 → 21–31')
    ap.add_argument('--out', default=None, help='data/ directory of the importer plugin')
    ap.add_argument('--version', default='1.0.0', help='batch data version written to manifest')
    args = ap.parse_args()

    provinces = load_provinces()
    start = (args.batch - 1) * BATCH_SIZE
    batch = provinces[start:start + BATCH_SIZE]
    if not batch:
        sys.exit('empty batch')
    out_dir = args.out or os.path.join(ROOT, 'wp-content', 'plugins', 'sa-province-importer-b%02d' % args.batch, 'data')
    img_out = os.path.join(out_dir, 'images')
    os.makedirs(img_out, exist_ok=True)

    manifest_images = {}
    if os.path.exists(IMG_MANIFEST):
        manifest_images = json.load(open(IMG_MANIFEST, encoding='utf-8')).get('images', {})

    built = datetime.date.today().isoformat()
    images = {}
    for prov in batch:
        src = os.path.join(IMG_DIR, prov['slug'] + '.webp')
        if os.path.exists(src):
            shutil.copyfile(src, os.path.join(img_out, prov['slug'] + '.webp'))
            mi = manifest_images.get(prov['slug'], {})
            images[prov['slug']] = {
                'file': 'images/%s.webp' % prov['slug'],
                'mime': 'image/webp',
                'width': mi.get('width'), 'height': mi.get('height'), 'bytes': os.path.getsize(src),
                'alt': mi.get('alt', 'تصویر شاخص استان ' + prov['name']),
                'title': mi.get('title', 'استان ' + prov['name']),
                'caption': mi.get('caption', ''),
                'description': mi.get('description', ''),
                'sha1': hashlib.sha1(open(src, 'rb').read()).hexdigest(),
            }

    summary = []
    for idx, prov in enumerate(batch, start=start + 1):
        md_path = os.path.join(CONTENT_DIR, prov['slug'] + '.md')
        if os.path.exists(md_path):
            pkg = build_article_package(prov, md_path, images, built)
        else:
            pkg = build_stub_package(prov, images, built)
        with open(os.path.join(out_dir, prov['slug'] + '.json'), 'w', encoding='utf-8') as fh:
            json.dump(pkg, fh, ensure_ascii=False, indent=1)
        summary.append({
            'order': idx, 'slug': prov['slug'], 'name_fa': pkg['name_fa'], 'term_name': prov['name'],
            'status': pkg['status'], 'has_image': bool(pkg['image']), 'word_count': pkg['post']['word_count'],
            'faq': len(pkg['faq']), 'sources_lines': len([l for l in pkg['sources'].split('---')[0].splitlines() if 'http' in l]) if pkg['sources'] else 0,
            'publish_status': pkg['publish_status'], 'markers': pkg['markers'], 'file': prov['slug'] + '.json',
        })
        print('%-2d %-24s %-7s image=%s words=%d faq=%d' % (idx, prov['slug'], pkg['status'], 'yes' if pkg['image'] else 'no ', pkg['post']['word_count'], len(pkg['faq'])))

    manifest = {
        'batch': args.batch,
        'batch_id': 'b%02d' % args.batch,
        'label': 'دسته‌ی %d — استان‌های %d تا %d فهرست ثابت' % (args.batch, start + 1, start + len(batch)),
        'data_version': args.version,
        'package_format': PACKAGE_FORMAT,
        'built': built,
        'provinces': summary,
    }
    with open(os.path.join(out_dir, 'manifest.json'), 'w', encoding='utf-8') as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=1)

    # counties.json — title + slug lists for the importer's «create county drafts» feature.
    counties = []
    for prov in batch:
        pairs = []
        p_md = os.path.join(CONTENT_DIR, prov['slug'] + '.md')
        if os.path.exists(p_md):
            pairs = parse_counties(open(p_md, encoding='utf-8').read())
        if not pairs:
            pairs = MANUAL_COUNTIES.get(prov['slug'], [])
        for name, slug in pairs:
            counties.append({'title': name, 'slug': slug, 'province': prov['slug'], 'province_name': prov['name']})
    with open(os.path.join(out_dir, 'counties.json'), 'w', encoding='utf-8') as fh:
        json.dump({'format': '1.0', 'built': built, 'counties': counties}, fh, ensure_ascii=False, indent=1)
    print('counties: %d written → counties.json' % len(counties))
    print('written →', os.path.relpath(out_dir, ROOT))


if __name__ == '__main__':
    main()
