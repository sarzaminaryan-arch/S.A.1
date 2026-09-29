#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
build_city_import_package.py — turns content/cities/{slug}.md (agreed county template, 9 blocks)
into ONE combined JSON package (data/{province}.json, key "counties") for the province-named
city importer plugin (wp-content/plugins/sa-city-importer-<province>/).

Usage:
  python3 content-templates/tools/build_city_import_package.py --province east-azerbaijan --label "آذربایجان شرقی"
  python3 content-templates/tools/build_city_import_package.py --province east-azerbaijan --out wp-content/plugins/sa-city-importer-east-azerbaijan/data

No third-party modules: the YAML subset used by the templates is parsed by hand.
md→Gutenberg conversion is reused from build_import_package.py (same inline subset).
"""
import argparse
import datetime
import hashlib
import json
import os
import re
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
CITY_DIR = os.path.join(ROOT, 'content', 'cities')
B01_COUNTIES = os.path.join(ROOT, 'wp-content', 'plugins', 'sa-province-importer-b01', 'data', 'counties.json')
FEATURED_DIR = os.path.join(ROOT, 'assets', 'featured', 'counties')

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from build_import_package import (  # noqa: E402
    PACKAGE_FORMAT, block, fenced, parse_faq, parse_sources, parse_block9,
    md_to_blocks, count_markers,
)

SEASON_SLUGS = {'بهار': 'spring', 'تابستان': 'summer', 'پاییز': 'autumn', 'زمستان': 'winter'}


def fb(block_text):
    """Fenced content of a block; batch 1–3 county files are raw (no ``` fence) → fall back to raw text."""
    inner = fenced(block_text)
    return inner if inner.strip() else block_text


def parse_faq_fb(text):
    """parse_faq on already-unfenced text (parse_faq re-fences internally)."""
    faq, q = [], None
    for raw in fb(text).split('\n'):
        line = raw.rstrip()
        if line.startswith('- q:'):
            q = line[4:].strip()
        elif line.strip().startswith('a:') and q is not None:
            faq.append({'q': q, 'a': line.strip()[2:].strip()})
            q = None
    return faq


def parse_sources_fb(text):
    """parse_sources on already-unfenced text."""
    return fb(text).strip().replace('<sup>[n]</sup>', '[n]').replace('<ol>', '«ol»')


def parse_list_value(v):
    v = v.strip()
    if v.startswith('[') and v.endswith(']'):
        return [x.strip() for x in v[1:-1].split(',') if x.strip()]
    return [v] if v else []


def parse_city_block1(text):
    """Hand-rolled parser for the county BLOCK 1 YAML subset."""
    out, lst, sub = {}, None, None
    for raw in fb(text).split('\n'):
        if not raw.strip() or raw.lstrip().startswith('#'):
            continue
        indent = len(raw) - len(raw.lstrip(' '))
        line = raw.strip()
        if indent == 0:
            lst = sub = None
            k, _, v = line.partition(':')
            k, v = k.strip(), v.strip()
            # strip inline comments only after " # "
            v = re.sub(r'\s+#\s.*$', '', v).strip()
            if v == '':
                out[k] = {}
                sub = k
            else:
                out[k] = v
        else:
            if line.startswith('- ') and lst:
                out[lst].append(line[2:].strip())
            elif sub and ':' in line:
                k, _, v = line.partition(':')
                v = re.sub(r'\s+#\s.*$', '', v).strip()
                out[sub][k.strip()] = v
    f = out.get('fields', {})
    if 'travel_season' in f:
        f['travel_season'] = [s.strip() for s in f['travel_season'].strip('[]').split(',') if s.strip()]
    if 'population' in f:
        f['population'] = re.sub(r'[^\d.]', '', f['population'])
    if 'secondary_keywords' in out:
        out['secondary_keywords'] = parse_list_value(out['secondary_keywords'])
    return out


def parse_schema_data(text):
    """BLOCK 6 → sameAs list, geo, containedInPlace, schema_type."""
    raw = fb(text)
    sameas = re.search(r'sameAs:\s*(\[.*?\])', raw, re.S)
    geo = re.search(r'geo:\s*\{([^}]*)\}', raw)
    contained = re.search(r'containedInPlace:\s*(\S+)', raw)
    stype = re.search(r'schema_type:\s*(.+)', raw)
    out = {
        'sameAs': json.loads(sameas.group(1)) if sameas else [],
        'geo': {},
        'contained_in': contained.group(1) if contained else '',
        'schema_type': stype.group(1).strip() if stype else 'City+TouristDestination',
    }
    if geo:
        for k, v in re.findall(r'(\w+):\s*([-\d.]+)', geo.group(1)):
            out['geo'][k] = float(v)
    return out


def build_county_package(md_path, province_row, built, featured_path=None):
    text = open(md_path, encoding='utf-8').read()
    b1 = parse_city_block1(block(text, 1))
    body_md = block(text, 3).strip()
    faq = parse_faq_fb(block(text, 4))
    sources = parse_sources_fb(block(text, 5))
    schema = parse_schema_data(block(text, 6))
    status9 = parse_block9(block(text, 9))
    f = b1.get('fields', {})
    content_html = md_to_blocks(body_md)
    words = len(re.sub(r'<[^>]+>', ' ', content_html).split())
    slug = b1.get('slug', os.path.basename(md_path)[:-3])
    lat = f.get('latitude', '')
    lon = f.get('longitude', '')
    if not lat and 'latitude' in schema['geo']:
        lat = str(schema['geo']['latitude'])
    if not lon and 'longitude' in schema['geo']:
        lon = str(schema['geo']['longitude'])
    seasons = [SEASON_SLUGS.get(s.strip(), s.strip()) for s in f.get('travel_season', [])]
    sameas = schema['sameAs']
    pkg = {
        'package_format': PACKAGE_FORMAT,
        'built': built,
        'source_file': os.path.relpath(md_path, ROOT).replace(os.sep, '/'),
        'source_sha1': hashlib.sha1(text.encode('utf-8')).hexdigest(),
        'slug': slug,
        'status': 'article',
        'title': b1.get('city_name_fa', ''),
        'name_fa': b1.get('city_name_fa', ''),
        'name_en': b1.get('city_name_en', ''),
        'province': province_row,
        'post': {
            'title': b1.get('city_name_fa', ''),
            'excerpt': b1.get('excerpt', ''),
            'content_html': content_html,
            'word_count': words,
        },
        'meta': {
            'sa_city_slug': slug,
            'sa_city_population': f.get('population', ''),
            'sa_city_latitude': lat,
            'sa_city_longitude': lon,
            'sa_access_air': f.get('access_air', ''),
            'sa_access_rail': f.get('access_rail', ''),
            'sa_access_road': f.get('access_road', ''),
            'sa_google_map_url': f.get('google_map_url', ''),
            'sa_city_schema_type': schema['schema_type'],
            'sa_schema_sameas': json.dumps(sameas, ensure_ascii=False) if sameas else '',
            'sa_schema_contained_in': schema['contained_in'],
        },
        'seo': {
            'sa_seo_title': b1.get('seo_title', ''),
            'sa_seo_description': b1.get('meta_description', ''),
            'sa_focus_keyword': b1.get('focus_keyword', ''),
            'sa_og_title': b1.get('og_title', ''),
            'sa_og_description': b1.get('og_description', ''),
        },
        'secondary_keywords': b1.get('secondary_keywords', []),
        'travel_season': seasons,
        'google_map_url': f.get('google_map_url', ''),
        'faq': faq,
        'sources': sources,
        'publish_status': status9,
        'markers': count_markers(body_md + ' ' + ' '.join(x['a'] for x in faq)),
    }
    if featured_path and os.path.exists(featured_path):
        pkg['image'] = {
            'file': 'assets/counties/%s.webp' % pkg['slug'],
            'source': os.path.relpath(featured_path, ROOT).replace(os.sep, '/'),
            'mime': 'image/webp',
            'alt': pkg['seo']['sa_focus_keyword'] or pkg['title'],
            'sha1': hashlib.sha1(open(featured_path, 'rb').read()).hexdigest(),
        }
    else:
        pkg['image'] = None
    return pkg


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--province', required=True, help='province slug, e.g. east-azerbaijan')
    ap.add_argument('--label', required=True, help='province Persian term name, e.g. آذربایجان شرقی')
    ap.add_argument('--out', default=None, help='data/ directory of the importer plugin')
    ap.add_argument('--version', default='1.0.0', help='data version written to manifest')
    ap.add_argument('--skip-missing', action='store_true',
                    help='skip counties whose article md is not written yet (partial provinces) instead of aborting')
    args = ap.parse_args()

    rows = json.load(open(B01_COUNTIES, encoding='utf-8'))['counties']
    mine = [r for r in rows if r.get('province') == args.province]
    if not mine:
        sys.exit('no counties for %s in b01 counties.json' % args.province)
    by_slug = {r['slug']: r for r in mine}

    out_dir = args.out or os.path.join(ROOT, 'wp-content', 'plugins',
                                       'sa-city-importer-%s' % args.province, 'data')
    os.makedirs(out_dir, exist_ok=True)
    built = datetime.date.today().isoformat()
    province_row = {
        'slug': args.province,
        'name_fa': 'استان ' + args.label,
        'term_name': args.label,
    }

    packages, summary = [], []
    skipped = []
    for r in mine:
        md_path = os.path.join(CITY_DIR, r['slug'] + '.md')
        if not os.path.exists(md_path):
            if args.skip_missing:
                skipped.append(r['slug'])
                continue
            sys.exit('missing article: %s' % md_path)
        feat = os.path.join(FEATURED_DIR, args.province, r['slug'] + '.webp') if os.path.isdir(os.path.join(FEATURED_DIR, args.province)) else None
        pkg = build_county_package(md_path, province_row, built, feat)
        if pkg['slug'] != r['slug'] or pkg['title'].replace('شهرستان ', '') != r['title']:
            print('WARN: title/slug drift %s: pkg=%r row=%r' % (r['slug'], pkg['title'], r['title']))
        packages.append(pkg)
        summary.append({
            'slug': pkg['slug'], 'title': pkg['title'], 'status': pkg['status'],
            'word_count': pkg['post']['word_count'], 'faq': len(pkg['faq']),
            'sources_lines': len([l for l in pkg['sources'].split('---')[0].splitlines() if 'http' in l]) if pkg['sources'] else 0,
            'publish_status': pkg['publish_status'], 'markers': pkg['markers'],
            'featured_image': bool(pkg['image']),
        })
        print('%-16s words=%5d faq=%2d img=%s %s' % (pkg['slug'], pkg['post']['word_count'], len(pkg['faq']), 'Y' if pkg['image'] else '-', pkg['publish_status']))

    if skipped:
        print('skipped (no article yet): %s' % ', '.join(skipped))

    data = {
        'batch': args.province,
        'batch_id': args.province,
        'label': 'درون‌ریز شهرستان‌های استان ' + args.label,
        'data_version': args.version,
        'package_format': PACKAGE_FORMAT,
        'built': built,
        'province': province_row,
        'counties': packages,
    }
    with open(os.path.join(out_dir, args.province + '.json'), 'w', encoding='utf-8') as fh:
        json.dump(data, fh, ensure_ascii=False, indent=1)

    manifest = {
        'batch': args.province,
        'batch_id': args.province,
        'label': 'درون‌ریز شهرستان‌های استان ' + args.label,
        'data_version': args.version,
        'package_format': PACKAGE_FORMAT,
        'built': built,
        'province': province_row,
        'file': args.province + '.json',
        'counties_count': len(packages),
        'counties': summary,
    }
    with open(os.path.join(out_dir, 'manifest.json'), 'w', encoding='utf-8') as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=1)

    counties_list = {'format': '1.0', 'built': built, 'counties': mine}
    with open(os.path.join(out_dir, 'counties.json'), 'w', encoding='utf-8') as fh:
        json.dump(counties_list, fh, ensure_ascii=False, indent=1)

    print('written →', os.path.relpath(out_dir, ROOT), '(%d counties)' % len(packages))


if __name__ == '__main__':
    main()
