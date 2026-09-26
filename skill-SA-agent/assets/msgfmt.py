#!/usr/bin/env python3
"""Minimal msgfmt: compile a GNU gettext .po file into a .mo file (no external deps).

Usage: python3 msgfmt.py path/to/fa_IR.po [path/to/fa_IR.mo]
Supports msgctxt, msgid_plural/msgstr[n], multi-line strings, fuzzy skipping.
"""
import ast
import struct
import sys


def parse_po(text):
    entries = {}
    cur = {}
    key = None
    fuzzy = False

    def flush():
        nonlocal cur, fuzzy
        if 'msgid' in cur and not fuzzy:
            mid = cur['msgid']
            ctx = cur.get('msgctxt')
            k = (ctx + '\x04' if ctx is not None else '') + mid
            if 'msgid_plural' in cur:
                forms = [cur.get('msgstr[%d]' % i, '') for i in range(10) if 'msgstr[%d]' % i in cur]
                if any(forms):
                    entries[k + '\x00' + cur['msgid_plural']] = '\x00'.join(forms)
            else:
                if cur.get('msgstr', '') or mid == '':
                    entries[k] = cur.get('msgstr', '')
        cur = {}
        fuzzy = False

    for raw in text.splitlines():
        line = raw.strip()
        if not line:
            flush()
            key = None
            continue
        if line.startswith('#'):
            if line.startswith('#,') and 'fuzzy' in line:
                fuzzy = True
            continue
        if line.startswith('"'):
            if key is not None:
                cur[key] += ast.literal_eval(line)
            continue
        kw, _, rest = line.partition(' ')
        key = kw
        cur[key] = ast.literal_eval(rest.strip()) if rest.strip() else ''
    flush()
    return entries


def build_mo(entries):
    keys = sorted(entries.keys())
    ids = b''
    strs = b''
    offsets = []
    for k in keys:
        kb = k.encode('utf-8')
        vb = entries[k].encode('utf-8')
        offsets.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b'\0'
        strs += vb + b'\0'
    n = len(keys)
    keystart = 7 * 4 + 16 * n
    valuestart = keystart + len(ids)
    koffsets = []
    voffsets = []
    for o1, l1, o2, l2 in offsets:
        koffsets += [l1, o1 + keystart]
        voffsets += [l2, o2 + valuestart]
    out = struct.pack('Iiiiiii', 0x950412de, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    out += struct.pack('%di' % len(koffsets), *koffsets)
    out += struct.pack('%di' % len(voffsets), *voffsets)
    return out + ids + strs


if __name__ == '__main__':
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    src = sys.argv[1]
    dst = sys.argv[2] if len(sys.argv) > 2 else src[:-3] + '.mo'
    with open(src, encoding='utf-8') as fh:
        entries = parse_po(fh.read())
    with open(dst, 'wb') as fh:
        fh.write(build_mo(entries))
    print('%s: %d entries -> %s' % (src, len(entries) - (1 if '' in entries else 0), dst))
