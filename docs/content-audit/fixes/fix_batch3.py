# -*- coding: utf-8 -*-
"""batch 3 — پاک‌سازی علامت‌های تکراری و یکدست‌سازی نامک‌های شناسه‌دار (🟠۱۰ ممیزی)."""
import io, re, sys

BASE = '/home/user/S.A.1/wp-content/themes/sarzaminaryan-child/'
AD, GD, RD = BASE + 'data/ready-articles.php', BASE + 'data/ready-guides.php', BASE + 'data/redirects.php'
A = io.open(AD, encoding='utf-8').read()
G = io.open(GD, encoding='utf-8').read()
R = io.open(RD, encoding='utf-8').read()
log = []


def rx(name, pat, repl, count=1, scope='A'):
    global A, G, R
    txt = {'A': A, 'G': G, 'R': R}[scope]
    new, n = re.subn(pat, repl, txt)
    if n != count:
        print('!! %-50s انتظار %d، یافت %d' % (name, count, n)); sys.exit(1)
    if scope == 'A': A = new
    elif scope == 'G': G = new
    else: R = new
    log.append((name, n))


# ── ۱) تنوین تکراری (حاصل جمع «ا» + علامت موجود در بعضی نامک‌ها و واژه‌ها)
d_a = len(re.findall('\u064b{2,}', A))
d_g = len(re.findall('\u064b{2,}', G))
A = re.sub('\u064b{2,}', '\u064b', A)
G = re.sub('\u064b{2,}', '\u064b', G)
log.append(('تنوین تکراری (مقالات)', d_a))
log.append(('تنوین تکراری (راهنماها)', d_g))

# ── ۲) نامک «تنگ صیاد»: 9235-… → tang-e-sayad-national-park
rx('tang-sayad: کلید رکورد', r"\t'9235-tang-e-sayad-national-park' => array\(",
   "\t'tang-e-sayad-national-park' => array(")
rx('tang-sayad: فیلد slug', r"'slug'\s+=>\s+'9235-tang-e-sayad-national-park',",
   "'slug'         => 'tang-e-sayad-national-park',")

# ── ۳) نامک «سرآقا سید»: 23328-… → sar-agha-seyed-village (نشانی منبع بیرونی دست‌نخورده می‌ماند)
rx('sar-agha: کلید رکورد', r"\t'23328-sar-agha-seyed-village' => array\(",
   "\t'sar-agha-seyed-village' => array(")
rx('sar-agha: فیلد slug', r"'slug'\s+=>\s+'23328-sar-agha-seyed-village',",
   "'slug'         => 'sar-agha-seyed-village',")
rx('sar-agha: پیوند داخلی', r'href="/attraction/23328-sar-agha-seyed-village/"',
   'href="/attraction/sar-agha-seyed-village/"')
rx('sar-agha: مسیر تصاویر همراه', r"'theme_img'\s+=>\s+'23328-sar-agha-seyed-village/",
   "'theme_img'   => 'sar-agha-seyed-village/", 3)
if 'kojaro.com/attraction/23328-sar-agha-seyed-village/' not in A:
    print('!! نشانی منبع بیرونی باید دست‌نخورده بماند'); sys.exit(1)

# ── ۴) تغییر مسیرهای ۳۰۱ برای دو نامک تازه
add = ("\t/*\n"
       "\t * نامک‌های تازهٔ دو صفحهٔ شناسه‌دار (۱۴۰۵-۰۷-۲۱): شناسهٔ خروجیِ وردپرس از نشانی\n"
       "\t * برداشته شد و مسیرهای قدیمی با ۳۰۱ به نامک‌های معنادار می‌روند.\n"
       "\t */\n"
       "\t'/attraction/9235-tang-e-sayad-national-park/' => '/attraction/tang-e-sayad-national-park/',\n"
       "\t'/attraction/23328-sar-agha-seyed-village/' => '/attraction/sar-agha-seyed-village/',\n")
if '/attraction/9235-tang-e-sayad-national-park/' not in R:
    rx('redirects: ۳۰۱ تنگ صیاد/سرآقا سید', r"\t'/attraction/308149-attraction-tabas/' => '/attraction/tabas-attractions/',\n",
       "\t'/attraction/308149-attraction-tabas/' => '/attraction/tabas-attractions/',\n\n" + add, 1, 'R')

io.open(AD, 'w', encoding='utf-8').write(A)
io.open(GD, 'w', encoding='utf-8').write(G)
io.open(RD, 'w', encoding='utf-8').write(R)
print('== batch 3 ==')
for n, c in log:
    print('  %-50s %d' % (n, c))
print('\nA=%d G=%d R=%d' % (len(A), len(G), len(R)))
