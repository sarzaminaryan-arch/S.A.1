import json, glob, re, sys

spec=json.load(open('tools/wp-agent/topic_clusters_100.json'))
provs=spec['province_execution_order']
clusters={c['rank']:c for c in spec['clusters']}

EXTRA = {
  'docs/live-site/attraction-drafts/live-replacements/chaharmahal-bakhtiari/kuhrang-fritillaria-plain-guide.md': ('chaharmahal-bakhtiari', None, None),
  'docs/live-site/attraction-drafts/live-replacements/chaharmahal-bakhtiari/sar-agha-seyed-village.md': ('chaharmahal-bakhtiari', 'کوهرنگ', 'روستای سرآقا سید'),
  'docs/live-site/attraction-drafts/live-replacements/chaharmahal-bakhtiari/tang-e-sayad-national-park.md': ('chaharmahal-bakhtiari', 'شهرکرد', 'پارک ملی تنگ صیاد'),
  'docs/live-site/attraction-drafts/live-replacements/fars/bishapur.md': ('fars', None, None),
  'docs/live-site/attraction-drafts/live-replacements/fars/eram-garden.md': ('fars', None, None),
  'docs/live-site/attraction-drafts/live-replacements/fars/margoon-falls.md': ('fars', None, None),
  'docs/live-site/attraction-drafts/live-replacements/fars/naqsh-e-rostam.md': ('fars', None, None),
  'docs/live-site/attraction-drafts/live-replacements/fars/pasargadae.md': ('fars', 'پاسارگاد', 'پاسارگاد'),
  'docs/live-site/attraction-drafts/live-replacements/fars/persepolis-takht-jamshid.md': ('fars', None, None),
  'docs/live-site/attraction-drafts/live-replacements/fars/shah-cheragh.md': ('fars', 'شیراز', 'حرم شاه‌چراغ'),
  'docs/live-site/attraction-drafts/live-replacements/fars/vakil-complex.md': ('fars', None, None),
  'docs/live-site/attraction-drafts/live-replacements/gilan/anzali-international-wetland-guide.md': ('gilan', None, None),
  'docs/live-site/attraction-drafts/live-replacements/gilan/masouleh.md': ('gilan', None, None),
  'docs/live-site/attraction-drafts/live-replacements/golestan/golestan-national-park-guide.md': ('golestan', 'گالیکش', 'پارک ملی گلستان'),
  'docs/live-site/attraction-drafts/live-replacements/hamadan/ali-sadr-cave-hamedan-guide.md': ('hamadan', None, None),
  'docs/live-site/attraction-drafts/live-replacements/hormozgan/qeshm-stars-valley-geopark.md': ('hormozgan', None, None),
  'docs/live-site/attraction-drafts/live-replacements/kerman/shahdad-kaluts-lut-desert.md': ('kerman', None, None),
  'docs/live-site/attraction-drafts/live-replacements/khuzestan/shevi-waterfall-tele-zang.md': ('khuzestan', 'دزفول', 'آبشار شوی (تله زنگ)'),
  'docs/live-site/attraction-drafts/live-replacements/khuzestan/shushtar-historical-hydraulic-system.md': ('khuzestan', 'شوشتر', 'سازه‌های آبی تاریخی شوشتر'),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/azgan-wetland-dorud.md': ('lorestan', 'دورود', 'تالاب ازگن (قارون)'),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/aznadar-waterfall-doroud.md': ('lorestan', 'دورود', 'آبشار ازنادر (دره اسپر)'),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/babahur-forest-park-dorud.md': ('lorestan', 'دورود', 'تفرجگاه باباهور (بام دورود)'),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/bisheh-waterfall-dorud.md': ('lorestan', None, None),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/falak-ol-aflak-castle-khorramabad.md': ('lorestan', None, None),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/gahar-lake-dorud.md': ('lorestan', None, None),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/nigah-valley-dorud.md': ('lorestan', 'دورود', 'درهٔ نیگاه'),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/nozhian-waterfall-khorramabad.md': ('lorestan', 'خرم‌آباد', 'آبشار نوژیان'),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/tangeh-lili-gorge-doroud.md': ('lorestan', 'دورود', 'تنگهٔ لی لی'),
  'docs/live-site/attraction-drafts/live-replacements/mazandaran/mount-damavand-guide.md': ('mazandaran', None, None),
  'docs/live-site/attraction-drafts/live-replacements/razavi-khorasan/qasem-anvar-shrine.md': ('razavi-khorasan', 'تربت جام', 'آرامگاه شاه قاسم انوار'),
  'docs/live-site/attraction-drafts/live-replacements/semnan/rig-e-jenn-desert.md': ('semnan', 'گرمسار', 'کویر ریگ جن (دشت کویر)'),
  'docs/live-site/attraction-drafts/live-replacements/south-khorasan/bagh-golshan-tabas.md': ('south-khorasan', None, None),
  'docs/live-site/attraction-drafts/live-replacements/south-khorasan/ghaleh-dokhtar-boshruyeh.md': ('south-khorasan', 'بشرویه', 'قلعهٔ دختر بشرویه'),
  'docs/live-site/attraction-drafts/live-replacements/south-khorasan/tabas-attractions.md': ('south-khorasan', 'طبس', 'جاهای دیدنی طبس'),
}
PLANNED_LIVE = {
  'docs/live-site/attraction-drafts/live-replacements/chaharmahal-bakhtiari/kuhrang-fritillaria-plain-guide.md': (40, 2),
  'docs/live-site/attraction-drafts/live-replacements/fars/bishapur.md': (84, 1),
  'docs/live-site/attraction-drafts/live-replacements/fars/eram-garden.md': (5, 3),
  'docs/live-site/attraction-drafts/live-replacements/fars/margoon-falls.md': (87, 1),
  'docs/live-site/attraction-drafts/live-replacements/fars/naqsh-e-rostam.md': (83, 2),
  'docs/live-site/attraction-drafts/live-replacements/fars/persepolis-takht-jamshid.md': (83, 1),
  'docs/live-site/attraction-drafts/live-replacements/fars/vakil-complex.md': (5, 5),
  'docs/live-site/attraction-drafts/live-replacements/gilan/anzali-international-wetland-guide.md': (45, 1),
  'docs/live-site/attraction-drafts/live-replacements/gilan/masouleh.md': (49, 1),
  'docs/live-site/attraction-drafts/live-replacements/hamadan/ali-sadr-cave-hamedan-guide.md': (16, 1),
  'docs/live-site/attraction-drafts/live-replacements/hormozgan/qeshm-stars-valley-geopark.md': (14, 1),
  'docs/live-site/attraction-drafts/live-replacements/kerman/shahdad-kaluts-lut-desert.md': (17, 1),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/bisheh-waterfall-dorud.md': (77, 2),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/falak-ol-aflak-castle-khorramabad.md': (26, 1),
  'docs/live-site/attraction-drafts/live-replacements/lorestan/gahar-lake-dorud.md': (77, 1),
  'docs/live-site/attraction-drafts/live-replacements/mazandaran/mount-damavand-guide.md': (32, 1),
  'docs/live-site/attraction-drafts/live-replacements/south-khorasan/bagh-golshan-tabas.md': (100, 2),
}

roadmap_map = {}
for f in glob.glob('docs/live-site/attraction-drafts/**/*.md', recursive=True):
    if f.endswith('INDEX.md'): continue
    t=open(f,encoding='utf-8').read()
    m=re.match(r'^---\n(.*?)\n---\n', t, re.S)
    if not m: continue
    fm=m.group(1)
    cr=re.search(r'^\s*cluster_rank:\s*(\d+)', fm, re.M)
    bi=re.search(r'^\s*branch_index:\s*(\d+)', fm, re.M)
    if cr and bi:
        roadmap_map.setdefault((int(cr.group(1)), int(bi.group(1))), []).append(f)

# validate
for dct,label in ((roadmap_map,'roadmap'),({k:[f] for k,(r,i) in []}.items(),'')): pass
for k in roadmap_map:
    rank, idx = k
    assert rank in clusters and any(b['index']==idx for b in clusters[rank]['branches']), k
for f,(r,i) in PLANNED_LIVE.items():
    assert any(b['index']==i for b in clusters[r]['branches']), (f,r,i)

extra_city_of = {}
for f,(p,city,att) in EXTRA.items():
    if city:
        extra_city_of.setdefault((p,city), []).append((att, f))

jobs=[]
stats={'prov':0,'city':0,'city_extra':0,'attr':0,'attr_extra':0,'filled':set()}
for p in provs:
    pslug=p['province_slug']; ptitle=p['province_title']
    stats['prov']+=1
    jobs.append({'type':'province','path':[ptitle]})
    city_entries=[]
    for rank in p['cluster_ranks']:
        c=clusters[rank]
        if c['branch_count']>=1:
            city_entries.append((c['name'], c))
    existing_city_names={c['name'] for c in [clusters[r] for r in p['cluster_ranks']]}
    for (ps, city) in extra_city_of:
        if ps==pslug and city not in existing_city_names:
            city_entries.append((city, None))
    for city, c in city_entries:
        planned = c is not None
        stats['city']+=1
        if not planned: stats['city_extra']+=1
        cfolder=f'شهرستان {city}'
        jobs.append({'type':'city','path':[ptitle, cfolder]})
        attractions=[]
        if planned:
            attr_rank=[r for r in spec['province_execution_order'] ]  # unused
            attractions += [(b['title'], b['index'], c['rank']) for b in c['branches']]
        for att, f in extra_city_of.get((pslug, city), []):
            attractions.append((att, None, None))
        for att, idx, rank_ in attractions:
            stats['attr']+=1
            if idx is None: stats['attr_extra']+=1
            jobs.append({'type':'attr','path':[ptitle, cfolder, att]})
            uploads=[]
            if idx is not None:
                uploads += roadmap_map.get((rank_, idx), [])
                uploads += [f for f,(rr,ii) in PLANNED_LIVE.items() if (rr,ii)==(rank_,idx)]
            else:
                uploads += [f for f,(p2,c2,a2) in EXTRA.items() if p2==pslug and c2==city and a2==att]
            for f in uploads:
                name=att+'.md'
                jobs.append({'type':'upload','path':[ptitle, cfolder, att], 'file':f, 'name': name})
                if idx is not None: stats['filled'].add((rank_, idx))

for j in jobs:
    if j['type']=='upload' and j['path'][-1]=='سی‌وسه‌پل و پل خواجو':
        base=j['file'].split('/')[-1][:-3]
        rename={'si-o-se-pol':'سی‌وسه‌پل','khaju-bridge':'پل خواجو'}[base]
        j['name']=rename+'.md'

json.dump(jobs, open('/tmp/drive_manifest.json','w'), ensure_ascii=False, indent=1)
print("province folders:", stats['prov'])
print("city folders:", stats['city'], "(extra unplanned:", stats['city_extra'], ")")
print("attraction folders:", stats['attr'], "(extra unplanned:", stats['attr_extra'], ")")
upl=[j for j in jobs if j['type']=='upload']
print("uploads:", len(upl))
print("planned branches receiving >=1 file:", len(stats['filled']))
from collections import Counter
cnt=Counter(j['file'] for j in upl)
print("files assigned !=1:", {k:v for k,v in cnt.items() if v!=1} or "none")
print("total jobs:", len(jobs))
