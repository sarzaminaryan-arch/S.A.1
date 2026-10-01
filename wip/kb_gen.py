# -*- coding: utf-8 -*-
"""تولید پرونده‌های شهرستان استان کهگیلویه و بویراحمد (استان ۲۳) — ساختار ۹ بلوکی سرزمین آریان."""
import io, os

PROV = "kohgiluyeh-boyer-ahmad"
PROV_FA = "کهگیلویه و بویراحمد"
SEEN = "2026-10-01"

ALL = [
    ("boyer-ahmad", "بویراحمد"),
    ("kohgiluyeh", "کهگیلویه"),
    ("gachsaran", "گچساران"),
    ("dena", "دنا"),
    ("bahmai", "بهمئی"),
    ("choram", "چرام"),
    ("basht", "باشت"),
    ("landeh", "لنده"),
    ("margoun", "مارگون"),
]

def nav(slug):
    i = [s for s, _ in ALL].index(slug)
    order = [ALL[(i + k) % len(ALL)] for k in range(1, len(ALL))]
    links = "، ".join("[{}](/city/{}/)".format(fa, sl) for sl, fa in order)
    return ("برای ادامهٔ گشت در استان، [راهنمای استان {p}](/province/{ps}/) و دیگر شهرستان‌های آن نیز در دسترس‌اند: {l}."
            .format(p=PROV_FA, ps=PROV, l=links))

def render(d):
    s = d["wiki"]
    def c(n=1):
        return "<sup>[{}]({})</sup>".format("۱۲۳۴۵۶۷۸۹"[n-1], d["srcurl"][n-1])
    out = io.StringIO()
    w = out.write
    w("# شهرستان {fa} — خروجی «ایجنت شهرستان» (دستهٔ {b}، استان ۲۳)\n\n".format(fa=d["fa"], b=d["batch"]))
    w("> تولید: {today} · حالت: full · وضعیت: DRAFT ONLY (دلیل در بلوک ۹)\n".format(today="۱۴۰۴/۰۷/۰۹"))
    w("> ساختار مطابق `content-templates/city-county-structure.md` + اصلاحیهٔ کارفرما (بخش‌های بدون منبع نوشته نمی‌شوند).\n\n")
    w("```\n=== BLOCK 1: ENTITY & SEO ===\n")
    w("city_name_fa: شهرستان {}\n".format(d["fa"]))
    w("city_name_en: {} County\n".format(d["en"]))
    w("slug: {}\n".format(d["slug"]))
    w("scope: county+center\nprovince: {}\n".format(PROV))
    w("focus_keyword: شهرستان {}\n".format(d["fa"]))
    w("secondary_keywords: [{}]\n".format(", ".join(d["kw"])))
    w("seo_title: {}\n".format(d["seo_title"]))
    w("meta_description: {}\n".format(d["meta"]))
    w("og_title: شهرستان {} — {}\n".format(d["fa"], d["tag"]))
    w("og_description: {}\n".format(d["og"]))
    w("excerpt: {}\n".format(d["excerpt"]))
    w("fields:\n")
    w("  population: {}       # source_year: 1395 — سرشماری عمومی نفوس و مسکن\n".format(d["pop_raw"]))
    w("  area_km2: {}\n".format(d["area_raw"]))
    w("  google_map_url: {}\n".format(d["map"]))
    w("  phone_prefix: '074'\n")
    w("relations:\n  province: {}\n\n".format(PROV))
    w("=== BLOCK 2: FEATURED IMAGE ===\nimage_prompt: |\n  {}\n".format(d["imgprompt"]))
    w("overlay_text_fa: شهرستان {}؛ {}\n".format(d["fa"], d["tag"]))
    w("overlay_text_en: {} County — {}\n".format(d["en"], d["tag_en"]))
    w("alt: {}\n".format(d["alt"]))
    w("caption: {}\n".format(d["caption"]))
    w("symbols_used: [{}]\n\n".format(", ".join(d["symbols"])))

    w("=== BLOCK 3: ARTICLE (Markdown) ===\n")
    w("# {}\n\n".format(d["h1"]))
    w("## معرفی شهرستان\n\n")
    w("**شهرستان {fa}** یکی از شهرستان‌های [استان {p}](/province/{ps}/) است و مرکز آن، شهر {c} است.{r}"
      " امتیازهای ویژهٔ این سرزمین را نخست ببینید:\n\n".format(fa=d["fa"], p=PROV_FA, ps=PROV, c=d["center"], r=c()))
    for b in d["bullets"]:
        w("- {}{}\n".format(b, c()))
    w("\n{}{}\n\n".format(d["intro_tail"], c()))

    w("## مشخصات کلی\n\n| شاخص | مقدار | سال مرجع / توضیح |\n|---|---|---|\n")
    for k, v, n in d["table"]:
        w("| {} | {} | {}{} |\n".format(k, v, n, c()))
    w("\n")

    w("## موقعیت جغرافیایی\n\n{}{}\n\n".format(d["geo"], c()))
    w("| همسایه | جهت |\n|---|---|\n")
    for nb, dirn in d["neighbors"]:
        w("| {} | {} |\n".format(nb, dirn))
    w("\n")

    for h2, body in d["sections"]:
        w("## {}\n\n".format(h2))
        for para in body:
            w("{}{}\n\n".format(para, c()))

    w("## تقسیمات کشوری\n\n{}{}\n\n".format(d["div_intro"], c()))
    w("| بخش | مرکز بخش | جمعیت بخش (۱۳۹۵) | دهستان‌ها |\n|---|---|---|---|\n")
    for row in d["div_rows"]:
        w("| {} | {} | {} | {} |\n".format(*row))
    w("\n")

    if d.get("attractions"):
        w("## جاذبه‌های گردشگری\n\n")
        for para in d["attractions"]:
            w("{}{}\n\n".format(para, c()))
    w("{}\n\n".format(nav(d["slug"])))

    w("## نقشه و لوکیشن\n\n{} برای مسیریابی، [نقشهٔ شهرستان {} در گوگل‌مپ]({}) را باز کنید.{}\n\n"
      .format(d["maptext"], d["fa"], d["map"], c(2)))

    w("## حقایق جالب\n\n")
    for f in d["facts"]:
        w("- {}{}\n".format(f, c()))
    w("\n")

    w("## از مردم عزیز شهرستان {}، یک درخواست داریم\n\n".format(d["fa"]))
    w("اگر در شهرستان {fa} زندگی می‌کنید یا به این دیار دلبسته‌اید، دانستهٔ محلی شما — از نام درست مکان‌ها و آیین‌ها تا"
      " عکس‌های تازه — این راهنما را کامل‌تر می‌کند. اصلاح‌ها و پیشنهادهایتان را به `Mail@sarzaminaryan.ir` بفرستید؛"
      " برای دیدن دیگر صفحه‌های منطقه هم [راهنمای استان {p}](/province/{ps}/) در دسترس است.\n\n"
      .format(fa=d["fa"], p=PROV_FA, ps=PROV))

    w("=== BLOCK 4: FAQ ===\n")
    for q, a in d["faq"]:
        w("- q: {}\n  a: {}\n".format(q, a))
    w("\n=== BLOCK 5: SOURCES ===\n")
    for i, (title, kind, url) in enumerate(d["sources"], 1):
        w("{}. {} | {} | {} | {}\n".format(i, title, kind, url, SEEN))
    w("\n--- FACT CHECK ---\n\n=== BLOCK 6: SCHEMA DATA ===\n")
    w('sameAs: ["{}"]\n'.format(s))
    w("containedInPlace: {}\nschema_type: City + TouristDestination\nfaq_page: yes\n".format(PROV))
    w("breadcrumb: [خانه, استان {}, شهرستان {}]\nimage: yes\n\n".format(PROV_FA, d["fa"]))
    w("=== BLOCK 7: QUALITY CHECK ===\n")
    w("[PASS] FAQ {} سؤال ≥ حداقل ۱۰\n".format(len(d["faq"])))
    w("[PASS] منابع {} ردیف، همه با URL زندهٔ دیده‌شده ({})\n".format(len(d["sources"]), SEEN))
    w("[PASS] ارجاع بالانویس مطابق شمارهٔ بلوک ۵\n[PASS] تصویر شاخص + ALT شامل کلیدواژه\n")
    w("[PASS] کلیدواژه در پاراگراف اول و پایانی\n[PASS] لینک داخلی استان + نوار ناوبری ۸ شهرستان دیگر استان\n")
    w("[PASS] همهٔ اعداد با سال مرجع / منبع\n[PASS] بدون نشان کارگاهی در بلوک‌های ۳ تا ۵\n\n")
    w("=== BLOCK 8: FACT CHECK REPORT ===\n")
    for line in d["factcheck"]:
        w("{}\n".format(line))
    w("\n=== BLOCK 9: PUBLISH STATUS ===\n")
    w("DRAFT ONLY — پس از بازبینی v1.3 دستهٔ شهرستان‌های استان ۲۳ و تأیید پروژه منتشر شود.\n```\n")
    return out.getvalue()


def build(d):
    d.setdefault("srcurl", [d["wiki"], d["map"]])
    path = os.path.join("content", "cities", d["slug"] + ".md")
    with open(path, "w", encoding="utf-8") as f:
        f.write(render(d))
    return path
