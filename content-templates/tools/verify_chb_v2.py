# -*- coding: utf-8 -*-
"""Correctness filter (فیلتر صحت مقاله) for CHB counties v2 — gate + numeric cross-check."""
import re, os, json, urllib.parse

ORDER = ["shahrekord","borujen","ben","saman","farsan","kuhrang","ardal","kiar","lordegan","khanmirza","farrokhshahr","falard"]
PROVS = {"chaharmahal-bakhtiari","isfahan","lorestan","khuzestan","kohgiluyeh-boyer-ahmad"}
SLUGS = set(ORDER) | {"chadegan","tiran-karvan","lenjan","semirom","fereydunshahr"}
POP = {"shahrekord":278912,"borujen":118381,"ben":28326,"saman":34616,"farsan":95286,"kuhrang":41535,
 "ardal":68714,"kiar":50976,"lordegan":122930,"khanmirza":53728,"farrokhshahr":37068,"falard":33023}
ROWS = {"shahrekord":"شهرکرد","kuhrang":"کوهرنگ (چلگرد)","saman":"سامان","borujen":"بروجن","lordegan":"لردگان",
 "farsan":"فارسان","ardal":"اردل","kiar":"کیار (شلمزار)","khanmirza":"خانمیرزا (آلونی)","ben":"بن",
 "farrokhshahr":"فرخ‌شهر","falard":"فلارد (مال‌خلیفه)"}
cj = json.load(open("wp-content/plugins/sa-province-importer-b01/data/counties.json", encoding="utf-8"))
ok = True
report = []
for s in ORDER:
    t = open(f"content/cities/{s}.md", encoding="utf-8").read()
    blk = {}
    for n in range(1, 10):
        m = re.search(rf"=== BLOCK {n}:(.*?)=== BLOCK {n+1}:", t, re.S) if n < 9 else re.search(r"=== BLOCK 9:(.*)$", t, re.S)
        blk[n] = m.group(1) if m else ""
        if not blk[n].strip():
            report.append(f"[{s}] BLOCK {n} MISSING"); ok = False
    b3 = blk[3]
    ntok = len(re.findall(r"\S+", b3))
    faq = t.count("- q:")
    links = re.findall(r"\]\((/city/[^)#]+?|/province/[^)#]+?)/?\)", b3)
    bad = [u for u in links if u.split("/city/")[-1].split("/province/")[-1] not in (SLUGS | PROVS)]
    srcs = re.findall(r"^\d+\. (.+)$", blk[5], re.M)
    dated = [x for x in srcs if re.search(r"20\d\d-\d\d-\d\d", x)]
    # sup[1] target == source row 1 URL
    m1 = re.search(r"\[۱\]\((https?://[^)]+)\)", b3) or re.search(r"\[1\]\((https?://[^)]+)\)", b3)
    r1 = srcs[0] if srcs else ""
    u1 = re.search(r"(https?://\S+?) \|", r1)
    sup_ok = m1 and u1 and urllib.parse.unquote(m1.group(1).split("#")[0]).rstrip("/") == urllib.parse.unquote(u1.group(1).rstrip("/"))
    img = f"assets/featured/counties/chaharmahal-bakhtiari/{s}.webp"
    has_img = os.path.exists(img)
    alt = re.search(r"^alt: (.+)$", blk[2], re.M)
    FAN={"shahrekord":"شهرکرد","borujen":"بروجن","ben":"بن","saman":"سامان","farsan":"فارسان","kuhrang":"کوهرنگ","ardal":"اردل","kiar":"کیار","lordegan":"لردگان","khanmirza":"خانمیرزا","farrokhshahr":"فرخ‌شهر","falard":"فلارد"}
    alt_ok = alt and (FAN[s] in alt.group(1) or "شهرستان" in alt.group(1))
    rel = "province: chaharmahal-bakhtiari" in blk[1]
    mail = "Mail@sarzaminaryan.ir" in b3
    dd = re.search(r"\.\.|\!<", b3)
    ai = re.search(r"هوش مصنوعی|AI-generated|as an AI", b3 + blk[4] + blk[5])
    marker = re.search(r"\[نیازمند بررسی\]|\[منبع لازم\]|\[نیازمند منبع\]", b3)
    popfield = re.search(r"population: (\d+)", blk[1])
    pop_ok = popfield and int(popfield.group(1)) == POP[s]
    row_in_cj = ROWS[s] in json.dumps(cj, ensure_ascii=False)
    facts = len(re.findall(r"^\d+\. ", re.search(r"## حقایق جالب\n\n(.*?)\n\n## ", b3, re.S).group(1), re.M)) if "## حقایق جالب" in b3 else 0
    secs = len(re.findall(r"\n## ", b3))
    checks = [
        ("tokens>=800", ntok >= 800, ntok), ("faq==11", faq == 11, faq), ("links>=10", len(links) >= 10, len(links)),
        ("sources>=5", len(srcs) >= 5, len(srcs)), ("sources-dated", len(dated) == len(srcs), f"{len(dated)}/{len(srcs)}"),
        ("sup1==row1", bool(sup_ok), ""), ("image", has_img, "yes" if has_img else "NEXT-TURN"),
        ("alt", bool(alt_ok), ""), ("relations", rel, ""), ("mail", mail, ""),
        ("no-double-dot", not dd, ""), ("no-AI-mark", not ai, ""), ("no-workshop-mark", not marker, ""),
        ("pop==census", bool(pop_ok), popfield.group(1) if popfield else "?"),
        ("row-in-counties.json", row_in_cj, ""), ("facts>=10", facts >= 10, facts), ("sections>=13", secs >= 13, secs),
        ("bad-links", not bad, str(bad)[:40]),
    ]
    fails = [c for c in checks if not c[1]]
    if fails:
        ok = False
        line = " ".join(f"{n}={v}" for n, good, v in fails)
        report.append(f"[{s:12s}] FAIL {line}")
    else:
        report.append(f"[{s:12s}] ALL-OK (tokens={ntok} faq={faq} links={len(links)} srcs={len(srcs)} facts={facts} secs={secs} img={'Y' if has_img else 'N'})")
print("\n".join(report))
print("=" * 40)
print("FILTER RESULT:", "PASS" if ok else "FAIL")
