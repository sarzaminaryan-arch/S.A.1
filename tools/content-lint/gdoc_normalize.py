#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
gdoc_normalize.py — تبدیل خروجی «Google Docs → Markdown» به مارک‌داون تمیز.

خروجی Docs همهٔ نشانه‌های مارک‌داون را با بک‌اسلش فرار می‌دهد (\\#، \\*\\*، \\-، 1\\.)،
بین پاراگراف‌ها خط <br> می‌گذارد و عنوان سند را به‌صورت «## …» در خط اول می‌آورد.
این اسکریپت همهٔ این‌ها را برمی‌گرداند تا ابزار lint روی مارک‌داون واقعی کار کند.

کاربرد:
    python3 gdoc_normalize.py IN.md [OUT.md]     (بدون OUT → stdout)
    python3 gdoc_normalize.py --in-place FILE...
"""
import re
import sys
from pathlib import Path

ESCAPABLE = r'\\#*_`\-\.\|\[\]\(\)>+~!{}='
_UNESCAPE_RE = re.compile(r'\\([' + ESCAPABLE + r'])')
_BR_RE = re.compile(r'^\s*(<br\s*/?>\s*)+$', re.IGNORECASE)
_INLINE_BR_RE = re.compile(r'\s*<br\s*/?>\s*', re.IGNORECASE)
_NBSP = '\u00a0'


def normalize(text: str) -> str:
    text = text.replace('\r\n', '\n').replace('\r', '\n').replace(_NBSP, ' ')
    lines = text.split('\n')

    # عنوان سند (خط اول «## …») وقتی خط بعدیِ معنادار خودش تیتر/بلوک است، حذف می‌شود.
    first = next((i for i, l in enumerate(lines) if l.strip()), None)
    if first is not None and lines[first].startswith('## ') and not lines[first].startswith('## \\#'):
        nxt = next((l for l in lines[first + 1:] if l.strip() and not _BR_RE.match(l)), '')
        if nxt.lstrip().startswith(('\\#', '#', '## \\#')):
            lines = lines[:first] + lines[first + 1:]

    out = []
    for line in lines:
        if _BR_RE.match(line):
            continue
        line = _INLINE_BR_RE.sub(' ', line)
        # «## \### عنوان» (تیتر داخل تیتر) → «### عنوان»
        m = re.match(r'^(#{1,6}) (\\#)+', line)
        if m:
            line = line[len(m.group(1)) + 1:]
        line = _UNESCAPE_RE.sub(r'\1', line)
        out.append(line.rstrip())

    text = '\n'.join(out)
    text = re.sub(r'\n{3,}', '\n\n', text).strip() + '\n'
    return text


def main(argv):
    if not argv or argv[0] in ('-h', '--help'):
        print(__doc__)
        return 0
    if argv[0] == '--in-place':
        for p in argv[1:]:
            path = Path(p)
            path.write_text(normalize(path.read_text(encoding='utf-8')), encoding='utf-8')
            print(f'normalized: {path}')
        return 0
    src = Path(argv[0]).read_text(encoding='utf-8')
    result = normalize(src)
    if len(argv) > 1:
        Path(argv[1]).write_text(result, encoding='utf-8')
    else:
        sys.stdout.write(result)
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
