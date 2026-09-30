#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
audit_city_fenced.py — runs content-templates/tools/seo_audit.py on a county article
(content/cities/<slug>.md) after fencing the public BLOCK 5 rows, so the auditor can
number them the way the theme's <ol> does.

Why: the theme prints BLOCK 5 as an ordered list, so `<sup>[n]>` must match row n.
seo_audit.py reads those rows from a fenced block inside BLOCK 5; county articles keep
BLOCK 5 unfenced (the importer parses it as plain lines), so this helper wraps them in a
temporary copy under /tmp/audit_ready/ — the repository file is never modified.

Usage:
  python3 content-templates/tools/audit_city_fenced.py <slug> [<slug> ...]
  python3 content-templates/tools/audit_city_fenced.py --all-tehran
Exit code: 0 only if every audited file PASSes.
"""
import argparse
import os
import re
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
TMP = '/tmp/audit_ready'
KEEP = re.compile(r"(focus keyword|words \(machine|keyword count|in first|headings with|citations|"
                  r"internal links|paragraphs|markers|secondary|RESULT|   row|WARN|uncited)")


def fence_block5(text):
    """Wrap the public BLOCK 5 rows in a ``` fence (temporary copy only)."""
    m = re.search(r"(=== BLOCK 5: SOURCES ===\n)(.*?)(\n+---)", text, flags=re.S)
    if not m:
        sys.exit('BLOCK 5 not found (the file may already be fenced?)')
    return text[:m.start(2)] + "```\n" + m.group(2).strip("\n") + "\n```" + text[m.end(2):]


def audit(slug, verbose=False):
    src = os.path.join(ROOT, 'content', 'cities', slug + '.md')
    if not os.path.isfile(src):
        sys.exit('missing article: %s' % src)
    os.makedirs(TMP, exist_ok=True)
    out = os.path.join(TMP, slug + '.md')
    open(out, 'w', encoding='utf-8').write(fence_block5(open(src, encoding='utf-8').read()))
    r = subprocess.run([sys.executable, os.path.join(ROOT, 'content-templates', 'tools', 'seo_audit.py'), out],
                       capture_output=True, text=True)
    lines = r.stdout.splitlines()
    print(lines[1] if len(lines) > 1 else slug)          # focus keyword
    if verbose:
        print('\n'.join(lines[2:]))
    else:
        print('\n'.join(l for l in lines if KEEP.search(l)))
    return 'RESULT           : PASS' in r.stdout


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('slugs', nargs='*')
    ap.add_argument('--all-tehran', action='store_true',
                    help='audit the 16 Tehran county articles from counties.json')
    ap.add_argument('-v', '--verbose', action='store_true')
    args = ap.parse_args()

    slugs = list(args.slugs)
    if args.all_tehran:
        import json
        data = json.load(open(os.path.join(ROOT, 'wp-content', 'plugins', 'sa-province-importer-b01',
                                           'data', 'counties.json'), encoding='utf-8'))
        slugs += [r['slug'] for r in data['counties'] if r.get('province') == 'tehran']
    if not slugs:
        ap.error('no slug given')
    ok = True
    for s in slugs:
        print('=' * 60)
        ok = audit(s, args.verbose) and ok
    print('=' * 60)
    print('ALL PASS' if ok else 'SOME FAILED')
    sys.exit(0 if ok else 1)


if __name__ == '__main__':
    main()
