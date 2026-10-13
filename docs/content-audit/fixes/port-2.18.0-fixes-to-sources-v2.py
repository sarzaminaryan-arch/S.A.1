# -*- coding: utf-8 -*-
"""پورت باقی‌ماندهٔ اصلاحات (پاسخ‌های FAQ چندخطی) به منابع مارک‌داون.

تفاوت با نسخهٔ قبل: در متن متعارف، «خط نو» هم مانند تگ حذف می‌شود تا متن‌های
چندخطی مارک‌داون با متن یک‌خطی فایل خروجی تطبیق داده شوند.
"""
import difflib, glob, io, os, re, sys

REPO = '/home/user/S.A.1'
MD_DIR = os.path.join(REPO, 'docs', 'camping-education', 'articles')
BASE_GEN = '/tmp/gen_guides.php'   # ساختهٔ‌شده از منابع دست‌نخورده
CUR_PHP = os.path.join(REPO, 'wp-content/themes/sarzaminaryan-child/data/ready-guides.php')


def strip_tags(s):
    s = re.sub(r'<[^>]+>', '', s)
    for a, b in (('&gt;', '>'), ('&lt;', '<'), ('&amp;', '&'), ('&quot;', '"'), ('&#39;', "'")):
        s = s.replace(a, b)
    return s


def canon(text, skip_newlines=True):
    out, pos, i, n = [], [], 0, len(text)
    while i < n:
        ch = text[i]
        if ch == '<':
            j = text.find('>', i)
            if j != -1:
                i = j + 1
                continue
        if text.startswith('**', i):
            i += 2
            continue
        if text.startswith('\\"', i):
            out.append('"'); pos.append(i + 1); i += 2
            continue
        if skip_newlines and ch == '\n':
            i += 1
            continue
        out.append(ch); pos.append(i); i += 1
    return ''.join(out), pos


def main():
    base = io.open(BASE_GEN, encoding='utf-8').read().splitlines()
    cur = io.open(CUR_PHP, encoding='utf-8').read().splitlines()
    sm = difflib.SequenceMatcher(None, base, cur, autojunk=False)
    pairs = []
    for tag, i1, i2, j1, j2 in sm.get_opcodes():
        if tag == 'replace' and (i2 - i1) == (j2 - j1):
            for k in range(i2 - i1):
                pairs.append((base[i1 + k], cur[j1 + k]))
        elif tag != 'equal':
            pairs.append(('@@' + tag, base[i1:i2], cur[j1:j2]))
    files = sorted(glob.glob(os.path.join(MD_DIR, '*.md')))
    texts = {f: io.open(f, encoding='utf-8').read() for f in files}
    done = []
    for idx, (old, new) in enumerate(pairs):
        if not isinstance(old, str) or not old or old == new:
            continue
        old_c, new_p = strip_tags(old), strip_tags(new)
        # اگر خط PHP است، فقط «مقدار رشته» را بردار (چون منبع مارک‌داون، متن خالی دارد)
        m = re.search(r"=>\s*'(.*)',?\s*$", old_c)
        if m:
            old_c = m.group(1)
        m = re.search(r"=>\s*'(.*)',?\s*$", new_p)
        if m:
            new_p = m.group(1)
        for f in files:
            ct, pos = canon(texts[f])
            k = ct.find(old_c)
            if k < 0:
                continue
            a, b = pos[k], pos[k + len(old_c) - 1] + 1
            texts[f] = texts[f][:a] + new_p + texts[f][b:]
            # نسخهٔ تکراری در بلوک اسکیمای JSON همان فایل
            ct, pos = canon(texts[f])
            k2 = ct.find(old_c, max(0, k - 200))
            while k2 >= 0:
                a, b = pos[k2], pos[k2 + len(old_c) - 1] + 1
                texts[f] = texts[f][:a] + new_p + texts[f][b:]
                ct, pos = canon(texts[f])
                k2 = ct.find(old_c, max(0, k2 - 200))
            done.append((idx, os.path.basename(f), old_c[:60]))
    for f, t in texts.items():
        io.open(f, 'w', encoding='utf-8').write(t)
    print('اعمال‌شده: %d' % len(done))
    for idx, f, o in done:
        print('  [%d] %-34s %s' % (idx, f, o))


if __name__ == '__main__':
    main()
