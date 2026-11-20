"""Offline, reproducible link baseline. Never fetch or mutate WordPress."""
import csv
import json
from pathlib import Path
from urllib.parse import urljoin, urlsplit

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/live-site/province-links'


def load(path):
    return json.loads((ROOT / path).read_text())


def route(url):
    u = urlsplit(url)
    if u.scheme in ('https', 'http') and u.hostname == 'sarzaminaryan.ir' and not u.query:
        return u.path.rstrip('/') + '/'
    return None


def main():
    OUT.mkdir(exist_ok=True, parents=True)
    records = load('docs/live-site/registry/live-articles.json')['records']
    work = load('tools/wp-agent/template_worklist.json')
    inventory = load('tools/wp-agent/results/20261119-02-inventory-city.json')['steps'][0]['result']['city']['items']
    live = {r['id']: r for r in inventory if r['status'] == 'publish'}
    records = [r for r in records if r['status'] == 'publish']
    provinces = [r for r in records if r['post_type'] == 'province']
    cities = {r['id']: live[r['id']] for r in work if r['id'] in live}
    edges = {r['wp_id']: {route(urljoin(r['permalink'], link)) for link in r['internal_links']} - {None} for r in records}
    places = [r for r in records if r['post_type'] == 'attraction']
    lines = ['# بازبینی پیوندهای ۳۱ استان | ۲۰ نوامبر ۲۰۲۶', '',
             'گزارش فقط‌خواندنی است. مقصدهای یکتای ثبت‌شده در `docs/live-site/registry/live-articles.json` (۱۱ نوامبر) با شناسه‌های شهرستان در `template_worklist.json` و موجودی شهرستان‌های ۱۷ نوامبر تطبیق داده شدند. جدول، پیوندِ مشاهده‌شده در آن خروجی را می‌شمارد، نه وضعیت امروز یا همهٔ پیوندهای تولیدشده توسط قالب. لینک‌های دارای پارامتر، ایمیل و لینک به آرشیو در شمارش رابطه‌های مستقیم نیستند.', '',
             'هر کسر = مشاهده‌شده / مورد انتظار. جهت مهم است: شیراز ← مرودشت جای مرودشت ← شیراز را نمی‌گیرد. ستون «بدنهٔ موجود» پوشش منبع در رجیستری ۱۱ نوامبر است. ستون دیدنی فقط مقصد دارای صفحه و والد شهرستانِ منطبق در همان داده را می‌سنجد؛ ۰/۰ یعنی داده‌ای برای سنجش نیست.', '',
             '| استان | بدنهٔ موجود / شهرستان | استان به شهرستان | شهرستان به استان | شهرستان به هم‌استانی | شهرستان به دیدنی |', '|---|---:|---:|---:|---:|---:|']
    totals = [0, 0, 0]
    with (OUT / 'fars-links.csv').open('w', newline='', encoding='utf-8-sig') as file:
        w = csv.writer(file, lineterminator="\n")
        w.writerow(['مبدأ', 'مقصد', 'رابطه', 'وضعیت در خروجی ۱۱ نوامبر', 'اقدام پیشنهادی'])
        for p in sorted(provinces, key=lambda r: r['title']):
            group = {r['id']: cities[r['id']] for r in work if r['province'] == p['slug'] and r['id'] in cities}
            n = len(group)
            counts = []
            for kind, pairs in [
                ('استان به شهرستان', [(p['wp_id'], c['link']) for c in group.values()]),
                ('شهرستان به استان', [(i, p['permalink']) for i in group]),
                ('هم‌استانی', [(i, c['link']) for i in group for j, c in group.items() if i != j]),
                ('دیدنی', [(a['relations']['city_id'], a['permalink']) for a in places if a['relations'].get('city_id') in group]),
            ]:
                found = 0
                for i, url in pairs:
                    seen = route(url) in edges.get(i, set())
                    found += seen
                    if p['slug'] == 'fars':
                        w.writerow([group[i]['link'] if i in group else p['permalink'], url, kind,
                                    'ثبت‌شده' if seen else ('ثبت‌نشده' if i in edges else 'بدنه ناموجود'),
                                    'حفظ پس از تأیید مقصد' if seen else 'اول بررسی در صفحهٔ رندرشده؛ افزودن فقط در صورت نبودن'])
                counts.append(f'{found}/{len(pairs)}')
                if kind == 'هم‌استانی':
                    totals[0] += found
                    totals[1] += len(pairs)
                    totals[2] += n > 13
            lines.append('| ' + ' | '.join([p['title'], f'{len(group.keys() & edges.keys())}/{n}', *counts]) + ' |')
    lines += ['', f'**نتیجهٔ سراسری:** برای {len(cities)} شهرستانِ فهرست کار، {totals[1]} پیوند جهت‌دار هم‌استانی انتظار می‌رود؛ {totals[0]} مورد در رجیستری ثبت است. در {totals[2]} استان، سقف پیش‌فرض ۱۲ مقصد در `sa_county_profile_sibling_cities()` برای پوشش همهٔ هم‌استانی‌ها کافی نیست. این محدودیتِ یک بخش است؛ نبودن لینک در کل صفحه را اثبات نمی‌کند.', '',
              'مرز موجودی: «شهر بوشهر» (۲۰۱) در موجودی منتشرشده هست ولی شهرستان جداگانه در فهرست کار نیست. چهار پیش‌نویس نیز وارد شبکه نشده‌اند. دو رابطهٔ قدیمی دیدنی‌های اصفهان به شناسه‌های خارج از فهرست کار وصل‌اند (میدان نقش جهان و سی‌وسه‌پل)؛ خروجی‌های بعدی پل، والد ۳۴۲۹ را نشان می‌دهند. این دو در مخرج جدول نیامده‌اند؛ پیش از هر اصلاح دوباره خوانده شوند.', '',
              '## نمونهٔ فارس — پیشنهاد برای بازبینی، نه تغییر منتشرشده', '',
              'در [جدول مبدأ و مقصد فارس](fars-links.csv) هر دو جهت را می‌توان با فیلتر ستون مبدأ یا مقصد دید. پیوند به صفحهٔ استان جای پیوند مستقیم به شهرستان دیگر محسوب نشده است.', '',
              'برای [شهرستان شیراز](https://sarzaminaryan.ir/city/shiraz/)، سه مقصد ثبت‌شده برای بخش «جاهای دیدنی» عبارت‌اند از: ' + '، '.join(f"[{a['title']}]({a['permalink']})" for a in places if a['relations'].get('city_id') == 323) + '. هر کدام فقط یک‌بار، در جای نام همان دیدنی؛ اگر قالب از قبل لینک ساخته باشد به متن اضافه نشود.', '',
              'ترتیب بدنهٔ بازنویسی ثبت‌شدهٔ شیراز (`outbox/20261117-replace-shiraz/shiraz-323.html`): معرفی، نقشه، تاریخ، مردم، اقتصاد، راه رسیدن و اقامت، نام‌آوران. این ترتیب با تمپلیت شهرستان سازگار است؛ جابه‌جایی بدنه پیشنهاد نمی‌شود. دیدنی‌ها در «تجربهٔ مسافرها» و فهرست الفبایی هم‌استانی‌ها بالای منابع بمانند.', '',
              'نمونهٔ خروجی خارجی: در همان فایل شیراز نشانی `https://shiraz.farsp.ir/RContent/000C7X5-` شانزده بار در ارجاعات آمده است. این تکرار دلیل خراب‌بودن یا حذف منبع نیست؛ ابتدا اعتبار مقصد و ادعای متصل به هر ارجاع بررسی شود و در صورت یکی‌بودن منبع، ارجاعات به یک مدخل منابع وصل شوند.', '',
              'گام بعد: خواندن HTML نهایی استان، شهرستان‌ها و دیدنی‌های فارس؛ ثبت لینک‌های تولیدشده توسط قالب، مقصد نهایی پس از انتقال، کد پاسخ لینک‌های داخلی و خارجی، ترتیب واقعی بخش‌ها و لینک‌های تکراری. ۴۰۳ یا وقفهٔ شبکه به معنی لینک مرده نیست. پس از این بررسی، نسخهٔ نمونه با پشتیبان و فقط در پیش‌نویس بازنویسی آماده شود.']
    (OUT / 'baseline.md').write_text('\n'.join(lines) + '\n')


if __name__ == '__main__':
    main()
