#!/usr/bin/env python3
"""آماده‌سازی پیش‌نویس ۲۶ (نجات غریق؛ قالب RankMath) به ساختار استاندارد پیش‌نویس‌ها برای gdoc_convert.py.

این پیش‌نویس تنها فایلی است که بلوک سئو را در کامنت HTML و پرسش‌ها را به‌شکل «### س:» دارد؛
این اسکریپت یک‌بارمصرف آن را به همان ساختاری می‌برد که بقیهٔ پیش‌نویس‌ها دارند.
"""
import re
import sys

sys.path.insert(0, __import__('os').path.dirname(__import__('os').path.abspath(__file__)))
from gdoc_normalize import normalize  # noqa: E402

src = normalize(open(sys.argv[1], encoding='utf-8').read()).split('\n')
out = []

# ---- بلوک سئو از کامنت
def grab(label):
    for i, l in enumerate(src[:300]):
        if label in l and l.strip().endswith(':'):
            vals = []
            for m in src[i + 1:i + 20]:
                s = m.strip()
                if not s or s == '<br>':
                    if vals and (s == '<br>' or not s) and len(vals) >= 1 and any(x in src[i + 1 + len(vals)] for x in ('<br>',)):
                        pass
                    continue
                if re.match(r'^(🔤|📝|🔑|📎|📂|🏷|🖼|📊|\\=|=)', s):
                    break
                vals.append(s)
            return vals
    return []

title = ' '.join(grab('عنوان سئو'))
meta = ' '.join(grab('متا توضیحات'))
focus = ' '.join(grab('کلمه کلیدی اصلی'))
secondary = '، '.join(grab('کلمات کلیدی فرعی'))
slug = ' '.join(grab('نامک'))
excerpt = ' '.join(grab('چکیده مقاله'))
out += [
    f'- **عنوان سئو (Title Tag):** {title}',
    f'- **متا توضیحات (Meta Description):** {meta}',
    f'- **کلمه کلیدی اصلی (Focus Keyword):** {focus}',
    f'- **کلمات کلیدی فرعی:** {secondary}',
    f'- **نامک (Slug):** {slug}',
    '- **اسکیما:** Article، BreadcrumbList، FAQPage، HowTo',
    f'- **چکیده مقاله:** {excerpt}',
    '', '---', '',
]

# ---- بدنه از H1 به بعد
h1 = next(i for i, l in enumerate(src) if l.startswith('# '))
body = src[h1:]
text = '\n'.join(body)

# بخش‌های قدم‌به‌قدم → یک H2 با زیرتیترهای H3 شماره‌دار
text = text.replace('## ⛔ اول از همه: نپر توی آب!', '## مراحل نجات غریق از ساحل (قدم به قدم)\n\n### ۱. اول از همه: نپر توی آب')
text = re.sub(r'^## ✅ قدم اول: (.+)$', r'### ۲. \1', text, flags=re.M)
text = re.sub(r'^## ✅ قدم دوم: (.+)$', r'### ۳. \1', text, flags=re.M)
text = re.sub(r'^## ✅ قدم سوم: (.+)$', r'### ۴. \1', text, flags=re.M)
text = re.sub(r'^## ✅ قدم چهارم: (.+)$', r'### ۵. \1', text, flags=re.M)
text = re.sub(r'^## ❤️ آموزش CPR ساده برای بزرگسال$', '### ۶. آموزش CPR ساده برای بزرگسال', text, flags=re.M)
text = re.sub(r'^## ✅ قدم پنجم: (.+)$', r'### ۷. \1', text, flags=re.M)
# زیرتیترهای H3 داخل قدم‌ها → خط پررنگ
text = re.sub(r'^### (?!\d|[۰-۹])(?:🔹\s*|⚠️\s*)?(.+?)\s*$', lambda m: f"**{m.group(1).strip()}**", text, flags=re.M)
# جدول «نباید»ها → بخش هشدارها
def table_to_warnings(m):
    rows = [r for r in re.findall(r'^\|\s*(.+?)\s*\|\s*(.+?)\s*\|\s*$', m.group(0), flags=re.M) if not r[0].startswith(('❌', '-'))]
    items = [f'- ⚠️ هشدار: {a.strip()}؛ به‌جایش: {b.strip()}.' for a, b in rows if not set(a.strip()) <= set('-')]
    return '## ⚠️ نکات مهم و هشدارها\n\n' + '\n\n'.join(items) + '\n'
text = re.sub(r'## ❌ کارهایی که نباید بکنی.*?(?=\n## )', table_to_warnings, text, flags=re.S)
text = text.replace('آب قند یا نوشیدنی گرم بده', 'نوشیدنی گرم و شیرین بده، فقط اگر کاملاً هوشیار است')
text = text.replace('**آب قند** یا یه **نوشیدنی گرم** بده (نه الکل!)', 'اگر کاملاً هوشیاره، یه **نوشیدنی گرم و شیرین** بده (نه الکل!)')
text = text.replace('هوشیاره → بشونش + آب قند + زیر نظر', 'هوشیاره → بشونش + نوشیدنی گرم + زیر نظر')
# FAQ
text = text.replace('## ❓ سوالات رایج', '## ❓ سوالات رایج (FAQ)')
n = 0
def faq_q(m):
    global n
    n += 1
    pd = '۰۱۲۳۴۵۶۷۸۹'
    num = ''.join(pd[int(c)] for c in str(n))
    return f'**{num}. {m.group(1).strip()}**'
text = re.sub(r'^\*\*س: (.+?)\*\*$', faq_q, text, flags=re.M)
text = re.sub(r'^ج: ', '', text, flags=re.M)
# جمع‌بندی: فلوچارت → چک‌لیست
text = re.sub(r'## 📌 جمع‌بندی سریع.*?(?=\n🌿|\n## )', '## 🧠 جمع‌بندی سریع (چک‌لیست ذهنی)\n\n' + '\n'.join([
    '1. داد بزن و ۱۱۵ را خبر کن؛ خودت وارد آب نشو.',
    '2. چیزی بلند (طناب، شاخه، شال، تی‌شرت گره‌زده) به سمتش دراز کن یا پرت کن.',
    '3. اگر دور است، چند چیز شناور (بطری دربسته، دبه، تخته) پرت کن.',
    '4. از آب که درآمد: هوشیار → بنشان، خشک کن، زیر نظر بگیر؛ بیهوش با تنفس → وضعیت ریکاوری؛ بدون تنفس → CPR.',
    '5. حتماً به بیمارستان؛ حتی اگر حالش خوب به نظر می‌رسد.',
]) + '\n', text, flags=re.S)
text = text.replace('🌿 یه حرف آخر، رفیق', '## 💌 پیام پایانی')
# پیوندهای خالی و خطوط جنسیتی
text = re.sub(r'\s*\[اینجا بخون ← آموزش کامل CPR\]\(\)', ' در مقالهٔ جداگانهٔ CPR (به‌زودی) می‌آید', text)
text = re.sub(r'> 📎 نحوه گره زدن محکم و سریع رو \[اینجا بخون ← آموزش گره‌زدن\]\(\)\n?', '', text)
text = re.sub(r'- 📎 \[آموزش کامل: خودم دارم غرق می‌شم چیکار کنم ← لینک\]\(\)\n?', '', text)
text = text.replace('👩 خانم‌ها اگه شنا بلد نیستن لب آب بشینن، نه توی آب. حتی اگه بقیه اصرار کنن.', '🧍 اگه شنا بلد نیستی، لب آب بشین نه توی آب؛ حتی اگه بقیه اصرار کنن.')
text = text.replace('🧔 آقایون: مردونگی یعنی مراقبت. یعنی قبل از رفتن، یه طناب ۱۰ متری بنداز توی ماشین.', '🪢 مراقبت یعنی آمادگی: قبل از رفتن، یه طناب ۱۰ متری بنداز توی ماشین.')
text = text.replace('صفحه اصلی آموزش‌های ضروری کمپینگ و بقا در طبیعت\n', '')
# پیوند صفحه اصلی که به‌صورت متن مانده
text = re.sub(r'^---\s*$', '', text, flags=re.M)
out.append(text)
open(sys.argv[2], 'w', encoding='utf-8').write('\n'.join(out))
print('آماده شد:', sys.argv[2])
