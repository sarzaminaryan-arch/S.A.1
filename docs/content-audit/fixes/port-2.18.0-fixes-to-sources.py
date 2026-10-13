#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""پورت اصلاحات نسخهٔ ۲٫۱۸٫۰ از فایل ساختهٔ‌شده به منابع مارک‌داون.

چرا: `data/ready-guides.php` از `docs/camping-education/articles/*.md` ساخته می‌شود و
مرحلهٔ CI با `build_theme_data.py --check` برابری این دو را بررسی می‌کند. پس هر اصلاح
متن در راهنماها باید در منبع مارک‌داون انجام شود، نه فقط در فایل خروجی.

روش: هر «جفت خط» (قدیم/تازه) از فایل خروجی گرفته می‌شود؛ خطِ متناظر در منبع پیدا
می‌شود و تغییرهای کاراکتری همان جفت روی خط منبع «تصویر» می‌شود (سه‌راهه‌سازی ساده).
"""
import difflib
import glob
import io
import os
import re
import sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
MD_DIR = os.path.join(REPO, 'docs', 'camping-education', 'articles')
PAIRS = os.environ.get('SA_PAIRS', '/tmp/pairs.txt')


def load(path):
    return io.open(path, encoding='utf-8').read()


def project(md_line, old_gen, new_gen):
    """تغییرهای old_gen→new_gen را روی md_line اعمال می‌کند؛ None اگر تطبیق نشد."""
    sm = difflib.SequenceMatcher(None, old_gen, new_gen, autojunk=False)
    out, pos = md_line, 0
    for tag, i1, i2, j1, j2 in sm.get_opcodes():
        if tag == 'equal':
            continue
        old_seg, new_seg = old_gen[i1:i2], new_gen[j1:j2]
        if old_seg:
            idx = out.find(old_seg, pos)
            if idx < 0:
                return None
            out = out[:idx] + new_seg + out[idx + len(old_seg):]
            pos = idx + len(new_seg)
        else:
            anchor = old_gen[max(0, i1 - 12):i1]
            idx = out.rfind(anchor, 0, pos + 40) if anchor else pos
            if idx < 0:
                return None
            at = idx + len(anchor)
            out = out[:at] + new_seg + out[at:]
            pos = at + len(new_seg)
    return out


def best_line(text, needle):
    """بهترین خط متناظر با needle را برمی‌گرداند: (خط، امتیاز)."""
    best, score = None, 0
    for line in text.split('\n'):
        if len(line) < 20:
            continue
        s = difflib.SequenceMatcher(None, line, needle, autojunk=False).find_longest_match(
            0, len(line), 0, len(needle))
        if s.size > score:
            best, score = line, s.size
    return best, score


def main():
    rows = [l.rstrip('\n').split('\t', 2) for l in io.open(PAIRS, encoding='utf-8')]
    files = sorted(glob.glob(os.path.join(MD_DIR, '*.md')))
    texts = {f: load(f) for f in files}
    applied = skipped = 0
    for idx, old, new in rows:
        if not old or old == new:
            skipped += 1
            continue
        # خط منبع را از میان همهٔ فایل‌ها پیدا کن
        cand = []
        for f, t in texts.items():
            line, score = best_line(t, old)
            if line:
                cand.append((score, f, line))
        if not cand:
            print('!! [%s] خط منبع پیدا نشد' % idx)
            skipped += 1
            continue
        cand.sort(key=lambda x: -x[0])
        score, f, line = cand[0]
        if score < 25:
            print('!! [%s] تطبیق ضعیف (%d) — دستی بررسی شود' % (idx, score))
            skipped += 1
            continue
        projected = project(line, old, new)
        if not projected:
            print('!! [%s] تصویر تغییر ناموفق — دستی بررسی شود' % idx)
            skipped += 1
            continue
        texts[f] = texts[f].replace(line, projected, 1)
        applied += 1
        # نسخهٔ تکراری در همان فایل (مثل همان متن در بلوک اسکیمای JSON)
        if texts[f].count(line) >= 1:
            texts[f] = texts[f].replace(line, projected)
    for f, t in texts.items():
        io.open(f, 'w', encoding='utf-8').write(t)
    print('اعمال‌شده: %d | رهاشده: %d' % (applied, skipped))


if __name__ == '__main__':
    main()
