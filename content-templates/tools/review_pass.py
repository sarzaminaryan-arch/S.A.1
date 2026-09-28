# -*- coding: utf-8 -*-
"""review_pass.py — مقایسهٔ هر استان با شرایط قانون ۱۰ (سیاست بازبینی v1.3)
خروجی: مشکلات categorized + فهرست URLهای پرریسک برای 404-check دستی.
"""
import re, sys, json, urllib.parse as up

MARKERS = ['[نیازمند بررسی]', '[منبع لازم]', '[URL لازم]', '[نیاز به بررسی]', '(به‌زودی)', 'TBD', 'TODO', '؟؟']
WORKLOG = ['اپراتور', 'در این نوبت', 'باز نشد', 'تأیید کند', 'تایید کند', 'FACT CHECK', 'ر.ک. بلوک', 'ر.ک. FACT']
GARBLES = ['arching', 'نویz', 'پولis', 'Ø', 'Û', 'Â ', '&nbsp;', ' ،', ' .', '( ', ' )', '،،', '..']
EMAIL = 'Mail@sarzaminaryan.ir'

def sections(doc):
    def cut(a, b=None):
        i = doc.find(a)
        if i < 0: return ''
        j = doc.find(b, i) if b else len(doc)
        return doc[i:j if j > 0 else len(doc)]
    return {
        'header': doc[:doc.find('=== BLOCK 1') if '=== BLOCK 1' in doc else 0],
        'b1': cut('=== BLOCK 1', '=== BLOCK 2'),
        'b2': cut('=== BLOCK 2', '=== BLOCK 3'),
        'b3': cut('=== BLOCK 3', '=== BLOCK 4'),
        'b4': cut('=== BLOCK 4', '=== BLOCK 5'),
        'b5': cut('=== BLOCK 5', '=== BLOCK 6'),
        'b6': cut('=== BLOCK 6', '=== BLOCK 7'),
        'b9': cut('=== BLOCK 9'),
    }

def risky_url(u):
    flags = []
    if u.startswith('http://'): flags.append('http')
    for m in re.finditer(r'%(?![0-9A-Fa-f]{2})', u): flags.append('bad-percent'); break
    try:
        p = up.urlparse(u)
        if not p.scheme: flags.append('no-scheme')
        if p.netloc in ('sdata.ir', 'www.sdata.ir'): flags.append('unreliable-domain')
    except Exception:
        flags.append('parse-error')
    if re.search(r'\.(html?|php|aspx)$/i' if False else r'%00|\s', u): flags.append('weird')
    return flags

def review(path):
    doc = open(path, encoding='utf-8').read()
    s = sections(doc)
    rep = {'file': path, 'markers': {}, 'worklog': [], 'garbles': [], 'community': False,
           'email': EMAIL in doc, 'faq': 0, 'h2': len(re.findall(r'^## ', s['b3'], re.M)),
           'h3': len(re.findall(r'^### ', s['b3'], re.M)), 'sup': len(re.findall(r'<sup>', s['b3'])),
           'rows': 0, 'un_cited': [], 'no_url': [], 'risky': [], 'marker_lines': [], 'flagged_lines': []}
    for mk in MARKERS:
        n = doc.count(mk)
        if n: rep['markers'][mk] = n
    for i, line in enumerate(doc.splitlines(), 1):
        if any(mk in line for mk in MARKERS): rep['marker_lines'].append(i)
        hits = [w for w in WORKLOG if w in line]
        if hits: rep['worklog'].append((i, hits, line.strip()[:110]))
        if any(g in line for g in GARBLES) and 'http' not in line:
            rep['garbles'].append((i, [g for g in GARBLES if g in line], line.strip()[:90]))
    body = s['b3']
    rep['community'] = ('از مردم عزیز' in body and 'درخواست داریم' in body)
    rep['faq'] = len(re.findall(r'^- q: ', s['b4'], re.M)) or len(re.findall(r'^- q: ', body, re.M))
    # BLOCK 5 rows
    urls = []
    for line in s['b5'].splitlines():
        line = line.strip()
        if not line or line.startswith('===') or line.startswith('```'): continue
        if line.count('|') >= 2:
            m2 = re.search(r'(https?://[^\s|]+)', line)
            if not m2:
                if 'http' not in line and len(line) > 25 and not line.startswith(('---', 'یادداشت')):
                    rep['no_url'].append(line[:80])
                continue
            u = m2.group(1)
            urls.append(u); rep['rows'] += 1
            fl = risky_url(u)
            if fl: rep['risky'].append((u, fl))
            import urllib.parse as _up
            if _up.unquote(u).rstrip('/') not in _up.unquote(body): rep['un_cited'].append(u)
            continue
        m = re.search(r'(https?://\S+)', line)
        if not m:
            if re.search(r'[|：:]', line) and len(line) > 25: rep['no_url'].append(line[:80])
            continue
        u = m.group(1).rstrip('|، ')
        urls.append(u)
        rep['rows'] += 1
        fl = risky_url(u)
        if fl: rep['risky'].append((u, fl))
        import urllib.parse as _up
        if _up.unquote(u).rstrip('/') not in _up.unquote(body): rep['un_cited'].append(u)
    rep['all_urls'] = urls
    return rep

if __name__ == '__main__':
    out = {}
    for p in sys.argv[1:]:
        r = review(p)
        out[p] = r
        print('=' * 70)
        print(p)
        print('  markers:', r['markers'] or '✓', '| marker lines:', len(r['marker_lines']))
        print('  worklog hits:', len(r['worklog']), '| garbles:', len(r['garbles']))
        print('  community:', '✓' if r['community'] else '✗ MISSING', '| email:', '✓' if r['email'] else '✗',
              '| H2:', r['h2'], '| FAQ:', r['faq'], '| sup:', r['sup'])
        print('  BLOCK5 rows:', r['rows'], '| uncited:', len(r['un_cited']), '| no-url rows:', len(r['no_url']),
              '| risky:', len(r['risky']))
        for w in r['worklog'][:6]: print('   W', w[0], w[1], '::', w[2][:95])
        for g in r['garbles'][:6]: print('   G', g[0], g[1], '::', g[2][:80])
        for u in r['no_url'][:5]: print('   N', u)
        for u, fl in r['risky'][:8]: print('   R', fl, u[:100])
        for u in r['un_cited'][:5]: print('   U', u[:100])
    json.dump(out, open('/tmp/review_report.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
