#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ابزار مالک — تعیین تکلیف «پیوندهای داخلی با مقصد نامعلوم» (ایراد 🔴۱ ممیزی).

زمینه: ممیزی محتوا ۲۲ نشانی یکتا (۳۱ رخداد) را در بدنهٔ مقاله‌های دیدنی پیدا کرد که
در هیچ‌یک از چهار فهرست مرجع قالب نیستند (آموزش‌های آماده، استان‌ها، شهرستان‌ها،
دیدنی‌های آماده) و در نقشهٔ تغییر مسیر هم نمی‌آیند. ممکن است همان صفحه روی سایت با
همین نامک منتشر شده باشد؛ این نکته فقط با دیدن وردپرس یا خزیدن سایت قطعی می‌شود.

روش کار:
  ۱) بدون هیچ گزینه‌ای اجرا کنید تا فهرست وضعیت را ببینید (فقط‌خواندنی، بدون تغییر).
  ۲) برای هر نشانی، وضعیت واقعی را در وردپرس (فهرست جاذبه‌ها) بررسی کنید.
  ۳) اگر صفحه‌شان منتشر نشده است، پیوند باید به متن ساده تبدیل شود:
         python3 unlink-unknown-links.py --apply
     اگر صفحه‌شان منتشر شده است، همان پیوند می‌ماند و نامک درست را در فایل داده
     تأیید/اصلاح می‌کنید (یا نشانی قدیم را در data/redirects.php به نامک تازه می‌رسانید).

نکته: پس از هر تغییر، تست رگرسیون قالب را اجرا کنید:
    php wp-content/themes/sarzaminaryan-child/tests/test-internal-links.php
برخی از این پیوندها در همین تست، انتظار «وجود» دارند؛ اگر پیوندی را برمی‌دارید،
انتظار متناظر در تست را هم به‌روز کنید.
"""

import io, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
DATA = os.path.join(ROOT, 'wp-content', 'themes', 'sarzaminaryan-child', 'data')
FILES = {
    'articles': os.path.join(DATA, 'ready-articles.php'),
    'guides': os.path.join(DATA, 'ready-guides.php'),
}
REGISTRY = {
    'province': ('provinces.php', r"'slug'\s*=>\s*'([a-z0-9\-]+)'"),
    'city': ('counties.php', r"'slug'\s*=>\s*'([a-z0-9\-]+)'"),
}
RECORDS = r"\n\t'([a-zA-Z0-9\-]+)' => array\("
LINK = re.compile(r'<a\s+[^>]*href="/(attraction|city|province)/([^/"]+)/"[^>]*>(.*?)</a>', re.S)


def load(path):
    return io.open(path, encoding='utf-8').read()


def known():
    prov = set(re.findall(REGISTRY['province'][1], load(os.path.join(DATA, REGISTRY['province'][0]))))
    city = set(re.findall(REGISTRY['city'][1], load(os.path.join(DATA, REGISTRY['city'][0]))))
    attr = set(re.findall(RECORDS, load(FILES['articles'])))
    guide = set(re.findall(RECORDS, load(FILES['guides'])))
    return {'attraction': attr | guide, 'city': city, 'province': prov}


def scan():
    pools = known()
    found = {}
    for name, path in FILES.items():
        text = load(path)
        for m in LINK.finditer(text):
            kind, slug, _text = m.group(1), m.group(2), m.group(3)
            if slug not in pools[kind]:
                key = '/%s/%s/' % (kind, slug)
                found.setdefault(key, {'count': 0, 'files': set(), 'kinds': kind})
                found[key]['count'] += 1
                found[key]['files'].add(name)
    return found


def apply_unlink():
    pools = known()
    changed = {}
    for name, path in FILES.items():
        text = load(path)
        hits = []

        def substitute(m):
            kind, slug, inner = m.group(1), m.group(2), m.group(3)
            if slug in pools[kind]:
                return m.group(0)
            hits.append('/%s/%s/' % (kind, slug))
            return inner

        new = LINK.sub(substitute, text)
        if hits:
            io.open(path, 'w', encoding='utf-8').write(new)
            changed[name] = len(hits)
    return changed


def main():
    if '--apply' in sys.argv:
        changed = apply_unlink()
        if not changed:
            print('پیوند نامعلومی برای تبدیل پیدا نشد.')
        for name, n in changed.items():
            print('%s: %d پیوند به متن ساده تبدیل شد.' % (name, n))
        print('یادآوری: انتظارهای tests/test-internal-links.php را هم به‌روز کنید.')
        return
    found = scan()
    print('پیوندهای داخلی با مقصد نامعلوم: %d نشانی یکتا، %d رخداد\n' % (len(found), sum(v['count'] for v in found.values())))
    for key in sorted(found):
        info = found[key]
        print('  %-44s ×%d  (%s)' % (key, info['count'], '، '.join(sorted(info['files']))))
    print('\nبرای بررسی در وردپرس، هر نشانی را باز کنید؛ اگر صفحه‌اش منتشر نشده است:')
    print('  python3 unlink-unknown-links.py --apply')


if __name__ == '__main__':
    main()
