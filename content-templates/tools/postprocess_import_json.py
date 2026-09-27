#!/usr/bin/env python3
"""Post-process a freshly-built b0x article JSON to the shipped v1.0.4 conventions.

The repo build_import_package.py keeps editorial markers and converts <sup>
citations to sa-cite spans. The accepted/shipped article JSONs instead:
  * remove every `<sup>[n](url)</sup>` citation entirely from content_html and faq
  * remove every `[نیازمند بررسی: …]`, `[منبع لازم: …]`, `[URL لازم…]` marker
  * keep literal «(به‌زودی)» spans untouched
  * set seo.sa_focus_keyword to the two-part form «استان X، X»
  * recompute post.word_count from the stripped HTML
Marker/sup counts are still reported in the top-level `markers` field by the
builder; this script only touches the final rendered text.

Usage: python3 content-templates/tools/postprocess_import_json.py <json-path>
"""
import json
import re
import sys

SUP_RE = re.compile(r'<sup>\[[^\]]*\]\([^)]*\)</sup>')
MARKER_RE = re.compile(r'[ \t‌]*\[(?:نیازمند بررسی|منبع لازم|URL لازم)[^\]]*\]')
TAG_RE = re.compile(r'<[^>]+>')


def clean_text(s: str) -> str:
    s = SUP_RE.sub('', s)
    s = MARKER_RE.sub('', s)
    s = re.sub(r'[ \t]{2,}', ' ', s)
    s = re.sub(r'\(\s*\)', '', s)          # drop parens left empty by stripping
    s = re.sub(r' +([،؛:.!؟\)])', r'\1', s)  # no space before punctuation
    s = re.sub(r'\(\s+', '(', s)
    return s


def word_count(html: str) -> int:
    return len([w for w in TAG_RE.sub(' ', html).split() if w.strip()])


def main(path: str) -> None:
    with open(path, encoding='utf-8') as f:
        data = json.load(f)

    html = data['post']['content_html']
    data['post']['content_html'] = clean_text(html)
    data['post']['word_count'] = word_count(data['post']['content_html'])

    for item in data.get('faq', []):
        for key in ('q', 'a', 'question', 'answer'):
            if key in item and isinstance(item[key], str):
                item[key] = clean_text(item[key])

    name = data.get('term_name') or data.get('name_fa', '').replace('استان ', '')
    data['seo']['sa_focus_keyword'] = f'استان {name}، {name}'

    with open(path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
        f.write('\n')

    left = SUP_RE.findall(data['post']['content_html']) + MARKER_RE.findall(data['post']['content_html'])
    print(f"{path}: word_count={data['post']['word_count']} focus={data['seo']['sa_focus_keyword']} leftovers={len(left)}")


if __name__ == '__main__':
    main(sys.argv[1])
