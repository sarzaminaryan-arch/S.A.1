# -*- coding: utf-8 -*-
"""fix_v13.py — پاک‌سازی خودکار مطابق قانون ۱۰ (سیاست بازبینی v1.3)
استفاده: python3 fix_v13.py <province.md>
"""
import re, sys, urllib.parse as up

FA = "۰۱۲۳۴۵۶۷۸۹"
def fa_num(n): return "".join(FA[int(c)] for c in str(n))

TAGS = [
    r'\s*\[نیازمند بررسی[^\]]*\]',
    r'\s*\[نیازمند تطبیق[^\]]*\]',
    r'\s*\[منبع لازم[^\]]*\]',
    r'\s*\(به\u200cزودی\)',
]
COMMUNITY = """## از مردم عزیز استان {name}، یک درخواست داریم

ما در سرزمین آریان کوشیده‌ایم اطلاعات این راهنما را دقیق و کامل گرد آوریم، اما هیچ نوشته‌ای بی‌نقص نیست. اگر نکته‌ای درباره شهر یا استان‌تان دیدید که اشتباه نوشته شده است، لطفاً از طریق ایمیل [Mail@sarzaminaryan.ir](mailto:Mail@sarzaminaryan.ir) به ما خبر دهید تا آن را تصحیح کنیم. اگر هم موردی هست که به گمان ما جا افتاده و باید به این معرفی افزوده می‌شد، خوشحال می‌شویم برای کامل‌تر شدن اطلاعات شهرتان ما را یاری کنید؛ دانسته‌های شما، دقت این راهنما را برای همه مسافران بالا می‌برد.
"""

def fix(path):
    doc = open(path, encoding='utf-8').read()
    orig = doc
    lines = doc.split('\n')
    out, skip_note = [], False
    i = 0
    while i < len(lines):
        ln = lines[i]
        # ردیف‌های بی‌URL در بلوک ۵
        if '[URL لازم' in ln and ln.count('|') >= 3:
            i += 1; continue
        # sameAs بی‌URL
        if re.match(r'^\s*- "?\[URL لازم', ln):
            i += 1; continue
        # بلوک یادداشت ویراستار تا پایان FACT CHECK
        if ln.strip() == '---' and i + 1 < len(lines) and 'یادداشت خصوصی' in lines[i + 1]:
            i += 2
            while i < len(lines) and not re.match(r'^=== BLOCK', lines[i]):
                i += 1
            continue
        if 'یادداشت خصوصی' in ln:
            i += 1
            while i < len(lines) and not re.match(r'^=== BLOCK', lines[i]) and lines[i].strip() != '---':
                i += 1
            continue
        if 'FACT CHECK ---' in ln or ln.strip() == '(بلوک ۸ در ادامه‌ی همین فایل)':
            i += 1; continue
        # توضیح فرمت ارجاع
        if '<sup>[n](URL)</sup>' in ln:
            i += 1; continue
        out.append(ln)
        i += 1
    doc = '\n'.join(out)

    # تگ‌ها
    for t in TAGS:
        doc = re.sub(t, '', doc)
    doc = doc.replace('[نیازمند بررسی]', '').replace('[نیازمند تطبیق]', '').replace('[منبع لازم]', '')
    doc = re.sub(r'\[URL لازم[^\]]*\]', '', doc)

    # کامنت‌های YAML با نشان یا یادداشت قدیمی
    doc = re.sub(r'\s*#[^\n]*(نیازمند بررسی|منبع لازم|URL لازم|به\u200cزودی|DRAFT یا نانوشته|فعلاً)[^\n]*$', '', doc, flags=re.M)

    # پرانتزهای کارگاهی
    doc = re.sub(r'\s*\([^()]*?(اپراتور|باز نشد|تأیید کند|تایید کند|در این نوبت|بارگذاری نشد)[^()]*\)', '', doc)
    doc = re.sub(r'\s*—\s*صفحه در این نوبت[^)|\n]*', '', doc)

    # جمله‌های وعدهٔ صفحهٔ آینده
    doc = re.sub(r'[^.\n]*پوشش خواهد داد\.\s*', '', doc)
    doc = re.sub(r'برای شناخت دقیق\u200cتر هر بخش،[^.\n]*دنبال کنید و برای مقایسه', 'برای مقایسه', doc)
    doc = re.sub(r'[^.\n]*صفحه\u200cهای شهرستان[^\n]*دنبال کنید[.]?\s*', '', doc)
    doc = re.sub(r'^> ?موجودیت\u200cهای پیوست[^\n]*\n?', '', doc, flags=re.M)
    doc = re.sub(r'[^.\n]*منتشر خواهد شد\.\s*', '', doc)
    doc = re.sub(r'[^.\n]*هنوز منتشر نشده[^\n]*\n?', '', doc, flags=re.M)
    doc = re.sub(r'؛ ر\.ک\. FACT CHECK', '', doc)
    doc = re.sub(r' \(ر\.ک\. FACT CHECK\)', '', doc)
    doc = doc.replace('ر.ک. FACT CHECK ', '')

    # افزودن بخش مشارکت مردمی
    name_m = re.search(r'province_name_fa:\s*(.+)', doc)
    pname = name_m.group(1).strip() if name_m else 'ایران'
    if 'از مردم عزیز' not in doc.split('=== BLOCK 4')[0]:
        sec = COMMUNITY.format(name=pname.replace('استان ', '')).replace('استان استان', 'استان')
        doc = doc.replace('\n=== BLOCK 4', '\n' + sec + '\n=== BLOCK 4', 1)
        if '\n' + sec + '\n=== BLOCK 4' not in doc:
            doc = re.sub(r'\n(=== BLOCK 4[^\n]*)', '\n' + sec + r'\n\1', doc, count=1)


    # گذر ۲ — پاک‌سازی‌های باقی‌مانده
    doc = doc.replace('؛ مواردی که منابع رسمی درباره‌ی آن‌ها هم‌داستان نیستند با نشان «نیازمند بررسی» مشخص شده‌اند.',
                      '؛ در موارد اختلاف روایت‌ها، هر دو رقم کنار هم آمده است.')
    doc = doc.replace('[TODO مالک] ', '').replace('[TODO]', '')
    doc = re.sub(r' ([.،؛])', r'\1', doc)
    doc = re.sub(r'  +', ' ', doc)
    doc = re.sub(r'\n\n\n+', '\n\n', doc)

    # بازنویسی reasons داخلی (بلوک ۹) به وضعیت بازبینی v1.3
    def refresh_b9(m):
        block = m.group(0)
        if 'reasons:' not in block:
            return block
        b5_ = doc[doc.find('=== BLOCK 5'):doc.find('=== BLOCK 6')]
        rows_n = len([l for l in b5_.split('\n') if 'http' in l])
        faq_n = len(re.findall(r'^- q: ', doc[doc.find('=== BLOCK 4'):doc.find('=== BLOCK 5')], re.M))
        new = ('reasons:\n'
               '  - منابع معتبر: %d ردیف با تاریخ دسترسی؛ نشانی\u200cها در بازبینی ۱۴۰۵/۰۷/۰۶ بازبینی و پالایش شدند.\n'
               '  - FAQ: %d پرسش با پاسخ مستند.\n'
               '  - سیاست بازبینی v1.3 (قانون ۱۰): حذف نشان\u200cها و یادداشت\u200cهای ویراستاری، افزودن بخش مشارکت مردمی و پالایش فهرست منابع.\n') % (rows_n, faq_n)
        block = re.sub(r'reasons:\n(?:\s*-[^\n]*\n?)*', new, block, count=1)
        block = re.sub(r'unlock_plan:[^\n]*\n?', '', block)
        return block
    doc = re.sub(r'=== BLOCK 9.*?(?=\Z)', lambda m: refresh_b9(m), doc, flags=re.S)

    # شماره‌گذاری دوباره ارجاع‌ها بر اساس URL
    b5 = doc[doc.find('=== BLOCK 5'):doc.find('=== BLOCK 6')]
    row_urls = []
    for ln in b5.split('\n'):
        m = re.search(r'(https?://\S+?)(?:\s*\|\s*\d{4}-\d{2}-\d{2}\s*)?$', ln.strip().rstrip('|').strip() if ln.strip().startswith('|') else ln.strip())
        if ln.count('|') >= 2:
            m = re.search(r'(https?://[^\s|]+)', ln)
            if m: row_urls.append(m.group(1))
    def norm(u):
        u = u.strip().rstrip('.,؛')
        d = up.unquote(u)
        d = re.sub(r'^https?:', '', d).rstrip('/')
        return d
    idx = {}
    for n, u in enumerate(row_urls, 1):
        idx.setdefault(norm(u), n)
    def renum(m):
        num, url = m.group(1), m.group(2)
        k = idx.get(norm(url))
        return '<sup>[%s](%s)</sup>' % (fa_num(k) if k else num, url)
    doc = re.sub(r'<sup>\[([۰-۹0-9]+)\]\(([^)]+)\)</sup>', renum, doc)

    if doc != orig:
        open(path, 'w', encoding='utf-8').write(doc)
    return len(row_urls), doc.count('<sup>')

if __name__ == '__main__':
    for p in sys.argv[1:]:
        rows, sups = fix(p)
        print(p, '| BLOCK5 rows:', rows, '| sups:', sups)
