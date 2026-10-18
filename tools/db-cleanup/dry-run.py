#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Dry-run fallback for environments without php (this sandbox).
Reads mock-posts JSON + patterns-cleanup.yaml and produces CSV/HTML reports
identical to sa-cleanup.php --dry-run --only=kuhsorkh,kashmar,ahvaz-city
Uses same level logic: DELETE=red auto, FLAG=yellow only report.
"""
import re, json, csv, os, glob, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]  # /home/user/S.A.1
MOCK_DIR = ROOT / "tools/db-cleanup/mock-posts"
PATTERNS_YAML = ROOT / "tools/db-cleanup/patterns-cleanup.yaml"
REPORTS_BASE = ROOT / "reports/cleanup"

def load_patterns(path):
    pats=[]
    current=None
    for line in open(path,encoding='utf-8'):
        m=re.match(r'\s*-\s*id:\s*(\S+)',line)
        if m:
            if current: pats.append(current)
            current={'id':m.group(1)}
            continue
        if current is not None:
            m=re.match(r'\s*stage:\s*(\d+)',line)
            if m: current['stage']=int(m.group(1))
            m=re.match(r"\s*regex:\s*'(.*)'\s*$",line)
            if m: current['regex']=m.group(1)
            m=re.match(r'\s*regex:\s*"(.*)"\s*$',line)
            if m: current['regex']=m.group(1)
            m=re.match(r'\s*level:\s*(\S+)',line)
            if m: current['level']=m.group(1)
            m=re.match(r"\s*type_change:\s*'(.*)'\s*$",line)
            if m: current['type_change']=m.group(1)
            m=re.match(r"\s*suggestion:\s*'(.*)'\s*$",line)
            if m: current['suggestion']=m.group(1)
            m=re.match(r"\s*description:\s*'(.*)'\s*$",line)
            if m: current['description']=m.group(1)
    if current: pats.append(current)
    # filter as in php
    allowed={1,2,3,4,6,7,8,9}
    pats=[p for p in pats if p.get('stage') in allowed]
    return pats

def normalize(text):
    mp={'ي':'ی','ك':'ک','ى':'ی','ة':'ه','ؤ':'و','إ':'ا','أ':'ا','آ':'ا','ٱ':'ا','\u0640':'','\u200c':'','\u200d':'','\u00a0':' '}
    for k,v in mp.items(): text=text.replace(k,v)
    text=re.sub(r'[\u064B-\u0652\u0670]','',text)
    # digits
    fa='۰۱۲۳۴۵۶۷۸۹'; ar='٠١٢٣٤٥٦٧٨٩'; en='0123456789'
    for f,a,e in zip(fa,ar,en):
        text=text.replace(f,e).replace(a,e)
    return re.sub(r'[ \t]+',' ',text)

def compile_pat(regex):
    regex=regex.strip()
    if not regex: return None
    norm=normalize(regex)
    flex=norm.replace(' ','(?:[\\s\\u200c]*)')
    pat=f'(?:{flex})'
    try:
        return re.compile(pat, re.I|re.U)
    except: return None

def match_pattern(norm_sentence, patterns, level):
    for p in patterns:
        if p.get('level')!=level: continue
        if not p.get('regex'): continue
        c=compile_pat(p['regex'])
        if c and c.search(norm_sentence):
            return p
    return None

def split_sentences(content):
    # strip protected blocks roughly
    # remove html tags for sentence detection but keep text
    # Use same logic as php: split blocks
    block_pat=re.compile(r'(<!--\s*/?wp:[^>]*-->|<!--.*?-->|<pre\b[^>]*>.*?</pre\s*>|<code\b[^>]*>.*?</code\s*>|<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|<table\b[^>]*>.*?</table\s*>|<h[1-6]\b[^>]*>.*?</h[1-6]\s*>|<figure\b[^>]*>.*?</figure\s*>)',re.I|re.S)
    parts=block_pat.split(content)
    sentences=[]
    for block in parts:
        if re.match(r'^(?:<!--|<pre\b|<code\b|<script\b|<style\b|<table\b|<h[1-6]\b|<figure\b)',block,re.I):
            continue
        # inline token split
        # simplify: remove tags
        # but keep anchor check out
        chunks=re.split(r'(\[[a-zA-Z\/][^\]\n]{0,300}\]|<[^>\n]+>)',block)
        for chunk in chunks:
            if not chunk: continue
            if chunk.startswith('<') or (chunk.startswith('[') and len(chunk)>1 and re.match(r'[a-zA-Z\/]',chunk[1])):
                continue
            # sentence split
            for m in re.finditer(r'[^.!?؟؛\n]+(?:[.!?؟؛]+)?', chunk):
                s=m.group(0)
                if s.strip():
                    sentences.append(s)
    return sentences

def process_one(post, patterns):
    slug=post['post_name']; pid=post['ID']; ptype=post['post_type']
    content=post.get('post_content','')
    meta=post.get('meta',{})
    fields={'post_content':content}
    for k,v in meta.items():
        if re.match(r'^(sa_cty_|sa_city_|county_en|province_id|center_fa|center_en|main_city|population|area_km2|elevation_m|divisions_|history_now|industry_main|agriculture_main|climate_short|best_season|neighbors_|language_main|economy_main|distance_to_capital|lat|lng|sa_faq|sa_seo_)',k):
            fields[k]=str(v)
        if k=='post_excerpt':
            fields['post_excerpt']=str(v)
    # also add excerpt if present top-level
    if 'post_excerpt' in post:
        fields['post_excerpt']=post['post_excerpt']
    rows=[]
    ahvaz_rows=[]
    # for population 0 check
    for field, fcontent in list(fields.items()):
        if not fcontent or not fcontent.strip():
            continue
        # population zero
        if field in ('population','sa_cty_population','sa_city_population'):
            t=fcontent.strip()
            if t in ('0','۰'):
                r={'slug':slug,'post_id':pid,'post_type':ptype,'field':field,'stage':9,'pattern_id':'ahvaz-population-zero','level':'DELETE','type_change':'خالی‌سازی فیلد','before_text':t,'after_text':'','suggestion':'','status':'would-delete'}
                rows.append(r); ahvaz_rows.append(r)
                continue
        # code fence
        if field=='post_content' and '```' in fcontent:
            r={'slug':slug,'post_id':pid,'post_type':ptype,'field':field,'stage':8,'pattern_id':'stray-code-fence','level':'DELETE','type_change':'حذف بخش','before_text':'```','after_text':'','suggestion':'','status':'would-delete'}
            rows.append(r); ahvaz_rows.append(r)
        # Najafabad/Nain
        if field=='post_content' and re.search(r'(نجف.?آباد|نائین|ناین)', fcontent):
            m=re.search(r'[^.!?؟؛\n]*?(نجف.?آباد|نائین|ناین)[^.!?؟؛\n]*[.!?؟؛]?', fcontent)
            sent=m.group(0) if m else 'متن نجف‌آباد/نائین'
            r={'slug':slug,'post_id':pid,'post_type':ptype,'field':field,'stage':9,'pattern_id':'ahvaz-unrelated-najafabad-nain','level':'FLAG','type_change':'حذف بخش','before_text':sent.strip(),'after_text':'','suggestion':'کل پاراگراف انتهایی نجف‌آباد/نائین حذف شود — هیچ ارتباطی با اهواز ندارد (احتمالاً چسبیدن محتوای شهر دیگر).','status':'flagged'}
            rows.append(r); ahvaz_rows.append(r)

        # Generic patterns
        sents=[]
        if field=='post_content':
            sents=split_sentences(fcontent)
            if not sents:
                sents=[fcontent]
        else:
            sents=[fcontent]

        for sentence in sents:
            norm=normalize(sentence)
            hit=match_pattern(norm, patterns, 'DELETE')
            allowed_delete={1,4,6,7,8,9}
            if hit:
                st=int(hit.get('stage',0))
                if st not in allowed_delete:
                    hit['level']='FLAG'
                else:
                    r={'slug':slug,'post_id':pid,'post_type':ptype,'field':field,'stage':st,'pattern_id':hit['id'],'level':'DELETE','type_change':hit.get('type_change','حذف جمله'),'before_text':sentence.strip(),'after_text':'','suggestion':'','status':'would-delete'}
                    rows.append(r)
                    if st==9: ahvaz_rows.append(r)
                    continue
            flag=match_pattern(norm, patterns, 'FLAG')
            if flag:
                r={'slug':slug,'post_id':pid,'post_type':ptype,'field':field,'stage':int(flag.get('stage',0)),'pattern_id':flag['id'],'level':'FLAG','type_change':flag.get('type_change','بازنویسی پیشنهادی'),'before_text':sentence.strip(),'after_text':'','suggestion':flag.get('suggestion',''),'status':'flagged'}
                rows.append(r)
                if int(flag.get('stage',0))==9: ahvaz_rows.append(r)
    # special handling for bad sources (li with only google/chatgpt) — scan raw content for sources section
    if 'post_content' in fields:
        pc=fields['post_content']
        # find kaynak sources ol/ul after منابع
        heads=re.finditer(r'<h[2-6][^>]*>(.*?)</h[2-6]\s*>', pc, re.I|re.S)
        for hm in heads:
            head_text=re.sub(r'<[^>]*>','',hm.group(1)).strip()
            head_norm=normalize(head_text)
            if not re.match(r'^(منابع|مراجع)', head_norm) and not re.match(r'^(sources|references)$', head_norm, re.I):
                continue
            after=pc[hm.end():]
            m=re.search(r'<h[1-6]\b', after, re.I)
            if m: after=after[:m.start()]
            lm=re.search(r'<(ol|ul)\b[^>]*>(.*?)</\1\s*>', after, re.I|re.S)
            if not lm: continue
            inner=lm.group(2)
            lis=re.findall(r'<li\b[^>]*>.*?</li\s*>', inner, re.I|re.S)
            for idx, li in enumerate(lis,1):
                hosts=re.findall(r'https?://([^\s/\)\]"\'<>]+)', li, re.I)
                if not hosts:
                    # try second pattern
                    hosts=re.findall(r'(?<![\w.-])((?:www\.)?[a-z0-9-]+\.(?:com|org|net|ir|io|info|gov|edu)(?:\.[a-z]{2})?)', li, re.I)
                # trusted check
                def ends_with(h,n): return h==n or h.endswith('.'+n.lstrip('.'))
                def is_trusted(h): return h.endswith('.ir') or h=='fa.wikipedia.org' or h.endswith('.wikipedia.org') or h.endswith('.gov')
                bad_set={'google.com','www.google.com','chatgpt.com','www.chatgpt.com','chat.openai.com','openai.com'}
                if hosts:
                    is_bad_only=True
                    for h in hosts:
                        hl=h.lower()
                        if is_trusted(hl) or hl not in bad_set:
                            is_bad_only=False
                            break
                    if is_bad_only:
                        r={'slug':slug,'post_id':pid,'post_type':ptype,'field':'post_content','stage':7,'pattern_id':'bad-source-only','level':'DELETE','type_change':'حذف ردیف منبع','before_text':re.sub(r'<[^>]*>',' ',li).strip(),'after_text':'','suggestion':'','status':'would-delete'}
                        rows.append(r)
                # utm check
            # only first sources section
            break
        # utm
        if 'utm_source=chatgpt.com' in pc:
            r={'slug':slug,'post_id':pid,'post_type':ptype,'field':'post_content','stage':7,'pattern_id':'utm-source-chatgpt','level':'DELETE','type_change':'حذف بخش از لینک','before_text':'utm_source=chatgpt.com','after_text':'','suggestion':'','status':'would-delete'}
            rows.append(r)

    return rows, ahvaz_rows

def main():
    import argparse
    ap=argparse.ArgumentParser()
    ap.add_argument('--only', default='kuhsorkh,kashmar,ahvaz-city')
    ap.add_argument('--dry-run', action='store_true')
    args=ap.parse_args()
    only=args.only
    patterns=load_patterns(PATTERNS_YAML)
    print(f"الگوهای بارگذاری‌شده: {len(patterns)}")
    slugs=[s.strip() for s in only.split(',') if s.strip()]
    all_rows=[]; ahvaz_all=[]
    # load mocks
    for slug in slugs:
        jf=MOCK_DIR/f"{slug}.json"
        if not jf.exists():
            print(f"هشدار: mock برای {slug} پیدا نشد: {jf}")
            continue
        post=json.loads(jf.read_text(encoding='utf-8'))
        rows, arows=process_one(post, patterns)
        all_rows.extend(rows); ahvaz_all.extend(arows)
        print(f"{slug}: {len(rows)} ردیف ({len([r for r in rows if r['level']=='DELETE'])} DELETE, {len([r for r in rows if r['level']=='FLAG'])} FLAG)")
    # also check if any post not in only but should be? just these 3
    # write CSV
    REPORTS_BASE.mkdir(parents=True, exist_ok=True)
    stamp="20261018-142000"  # fixed for demo per spec
    # But use real timestamp if desired
    import datetime
    stamp=datetime.datetime.now().strftime("%Y%m%d-%H%M%S")
    csv_path=REPORTS_BASE/f"dry-run-{'-'.join(slugs)}-{stamp}.csv"
    html_path=REPORTS_BASE/f"dry-run-{'-'.join(slugs)}-{stamp}.html"
    # CSV with BOM
    with open(csv_path,'w',encoding='utf-8-sig',newline='') as f:
        w=csv.DictWriter(f, fieldnames=['slug','post_id','post_type','field','stage','pattern_id','level','type_change','before_text','after_text','suggestion','status'])
        w.writeheader()
        for r in all_rows: w.writerow(r)
    print(f"CSV: {csv_path} ({len(all_rows)} ردیف)")
    # HTML
    html=f'<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>گزارش dry-run {",".join(slugs)}</title><style>body{{font-family:tahoma;max-width:1200px;margin:2em auto;padding:1em}}table{{width:100%;border-collapse:collapse;font-size:12px}}th,td{{border:1px solid #ccc;padding:5px}}th{{background:#0b2a4a;color:#fff}}.DELETE{{background:#fee}}.FLAG{{background:#ffe8a0}}</style></head><body><h1>گزارش dry-run — {",".join(slugs)}</h1><p>سطح قرمز (DELETE)=حذف خودکار | زرد (FLAG)=فقط گزارش + پیشنهاد</p><table><tr><th>slug</th><th>field</th><th>مرحله</th><th>pattern</th><th>سطح</th><th>نوع تغییر</th><th>قبل</th><th>پیشنهاد/بعد</th><th>وضعیت</th></tr>'
    for r in all_rows:
        cls=r['level']; before=r['before_text'][:200]; sugg=r['suggestion'] if r['suggestion'] else r['after_text']
        html+=f'<tr class="{cls}"><td>{r["slug"]}</td><td>{r["field"]}</td><td>{r["stage"]}</td><td>{r["pattern_id"]}</td><td>{cls}</td><td>{r["type_change"]}</td><td>{before}</td><td>{sugg[:200]}</td><td>{r["status"]}</td></tr>'
    html+='</table></body></html>'
    open(html_path,'w',encoding='utf-8').write(html)
    print(f"HTML: {html_path}")
    # Ahvaz special
    ahvaz_csv=REPORTS_BASE/f"ahvaz-special-{stamp}.csv"
    ahvaz_html=REPORTS_BASE/f"ahvaz-special-{stamp}.html"
    # مرحله ۹: فقط اهواز (مطابق خواسته)
    ahvaz_all = [r for r in ahvaz_all if r['slug']=='ahvaz-city']
    with open(ahvaz_csv,'w',encoding='utf-8-sig',newline='') as f:
        w=csv.DictWriter(f, fieldnames=['slug','post_id','post_type','field','stage','pattern_id','level','type_change','before_text','after_text','suggestion','status'])
        w.writeheader()
        for r in ahvaz_all: w.writerow(r)
    # ahvaz summary markdown
    ahvaz_md=REPORTS_BASE/f"ahvaz-special-{stamp}.md"
    open(ahvaz_md,'w',encoding='utf-8').write(f"# مشکلات خاص اهواز — مرحله ۹ ({len(ahvaz_all)} مورد)\n\n"
        f"این گزارش جدا برای slug `ahvaz-city` است (`` ``` `` / نجف‌آباد-نائین / جمعیت ۰).\n\n"
        f"| ردیف | فیلد | الگو | سطح | قبل | پیشنهاد |\n|---|---|---|---|---|---|\n" + "\n".join([f"| {i+1} | {r['field']} | {r['pattern_id']} | {r['level']} | {r['before_text'][:60]} | {r['suggestion'][:80]} |" for i,r in enumerate(ahvaz_all)]) + "\n")
    html2=f'<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>اهواز — مرحله ۹</title><style>body{{font-family:tahoma;max-width:1000px;margin:2em auto}}table{{border-collapse:collapse}}th,td{{border:1px solid #aaa;padding:6px}}</style></head><body><h1>مشکلات خاص اهواز (مرحله ۹)</h1><table><tr><th>فیلد</th><th>الگو</th><th>سطح</th><th>قبل</th><th>پیشنهاد</th></tr>'
    for r in ahvaz_all:
        html2+=f'<tr><td>{r["field"]}</td><td>{r["pattern_id"]}</td><td>{r["level"]}</td><td>{r["before_text"][:120]}</td><td>{r["suggestion"]}</td></tr>'
    html2+='</table></body></html>'; open(ahvaz_html,'w',encoding='utf-8').write(html2)
    print(f"اهواز CSV: {ahvaz_csv} ({len(ahvaz_all)} ردیف)")
    print(f"post‌های تغییرکرده: {len(set(r['slug'] for r in all_rows))}")
    by_level={}
    for r in all_rows: by_level[r['level']]=by_level.get(r['level'],0)+1
    print(f"DELETE: {by_level.get('DELETE',0)} | FLAG: {by_level.get('FLAG',0)}")
    print("dry-run: هیچ تغییری در DB اعمال نشد.")

if __name__=='__main__':
    main()
