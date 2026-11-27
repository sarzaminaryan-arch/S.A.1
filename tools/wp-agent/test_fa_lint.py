#!/usr/bin/env python3
"""Offline tests for the Persian spelling/style filter (owner order 2026-11-16).

The owner saw «ظهرهای ژوئیه و اوت» on the live Ahvaz page: «ظهر های ژوئیه و اوت
در فارسی نداریم، یک فیلتر برای غلط املایی یا چنین مواردی بذار». This test pins
what the filter must catch, what it must leave alone, and that every publishing
path actually calls it.
"""
import json
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))

import fa_lint as fl  # noqa: E402
import wp_agent as wa  # noqa: E402


def kinds(text):
    return sorted({line.split(":")[0] for line in fl.scan(text)})


# 1. The exact sentence from the owner's screenshot is rejected, both names.
bad = "گرمای تابستان را دست‌کم نگیرید — ظهرهای ژوئیه و اوت در فضای باز نمانید."
hits = fl.scan(bad)
assert len([h for h in hits if h.startswith("FOREIGN")]) == 2, hits
assert "ژوئیه" in " ".join(hits) and "اوت" in " ".join(hits)

# 2. The corrected sentence is clean.
assert fl.scan("ظهرهای تیر و مرداد در فضای باز نمانید.") == []

# 3. Every Gregorian month name is caught, and never inside a Persian word
#    («مارس» used to match the middle of «بیمارستان»).
for month in ("ژانویه", "فوریه", "مارس", "آوریل", "ژوئن", "جولای", "آگوست",
              "سپتامبر", "اکتبر", "نوامبر", "دسامبر", "ژوئیه", "اوت"):
    found = fl.scan(f"در ماه {month} به این شهر رفتیم")
    assert any("FOREIGN" in f for f in found), month
assert fl.scan("بیمارستان مرکزی و دانشگاه شهر") == []
# «می» هرگز به‌عنوان نام ماه میلادی پرچم نمی‌شود (فقط به‌عنوان پیشوند فعل).
assert not any("FOREIGN" in f for f in fl.scan("می خانه و می ناب"))

# 4. ZWNJ and spacing errors.
assert any("پیشوند" in f or "نیم‌فاصله" in f for f in fl.scan("در می رود و کتاب ها را برد"))
assert fl.scan("در می‌رود و کتاب‌ها را برد") == []
assert any("به‌جامانده" in f for f in fl.scan("ضعف بجا‌ماندهٔ شهر"))
assert any("خانه‌ها" in f for f in fl.scan("خانهها و دانشگاهها"))  # glued after «ه»

# 5. Arabic letters / digits.
assert kinds("كتاب جديد") == ["AR"]
assert kinds("سال ١٣٩٥") == ["AR"]

# 6. Sanctioned reader-request box keeps «منبع معتبر» out of the banned list,
#    while a real "no source" sentence is still banned.
box = ('<p>اگر دربارهٔ تاریخ یا داده‌های این مقاله اصلاحیه و منبع معتبر دارید، '
       'آن را به Mail@sarzaminaryan.ir بفرستید.</p>')
assert fl.scan(box) == [], fl.scan(box)
assert any("BANNED" in f for f in fl.scan("این عدد بدون منبع است."))

# 7. Machine-readable meta keys are not prose: slugs, coordinates, schema types.
meta = {
    "slug": "ahvaz", "sa_city_slug": "ahvaz-city", "lat": "31.3275", "lng": "48.69389",
    "sa_cty_neighbors": "hamidiyeh - شمال‌غرب\nshadegan - جنوب",
    "sa_city_schema_type": "City + TouristDestination",
    "sa_faq": json.dumps([{"q": "کجاست؟", "a": "مرکز خوزستان."}], ensure_ascii=False),
    "sa_sources": "پل سفید | خبرگزاری صداوسیما | https://www.irib-news.ir/fa/news/2900250 | ۱۴۰۵/۰۸/۲۵",
}
assert fl.scan_meta(meta) == [], fl.scan_meta(meta)
meta["sa_faq"] = json.dumps([{"q": "کجاست؟", "a": "در ماه اوت به آن‌جا بروید."}], ensure_ascii=False)
assert any("FOREIGN" in f for f in fl.scan_meta(meta))
assert fl.scan_meta({
    "sa_sources": "پروندهٔ کاروانسراها | UNESCO World Heritage Centre | https://whc.unesco.org/en/list/1668 | ۱۴۰۵/۰۷/۰۴\n---\nFACT CHECK English private note",
}) == []
assert any("FOREIGN" in f for f in fl.scan_meta({
    "sa_sources": "گزارش سفر در ماه اوت | خبرگزاری | https://example.org/a | ۱۴۰۵/۰۷/۰۴",
}))

# 8. Fixer repairs the safe cases and leaves the rest reported.
fixed, applied = fl.fix_text("ظهرهای ژوئیه و اوت؛ خانهها و بجا‌مانده. در می رود")
assert "ژوئیه" not in fixed and "اوت " not in fixed, fixed
assert "خانه‌ها" in fixed and "به‌جامانده" in fixed and "می‌رود" in fixed, fixed
assert applied

# 9. Every publishing path calls the filter (source-level pin, like the other
#    template tests: a new path that forgets it fails here, not on the site).
for rel, needles in {
    "replace_src/check_template.py": ("fa_scan", "fa_scan_meta"),
    "build_section_batch.py": ("fa_scan", "fa_scan_meta"),
    "build_replace_batch.py": ("fa_scan",),
    "wp_agent.py": ("fa_lint", "scan_meta"),
}.items():
    src = (HERE / rel).read_text(encoding="utf-8")
    for needle in needles:
        assert needle in src, f"{rel} does not run the Persian spelling filter ({needle})"

# 10. The live-write path refuses a bad text even when the banned-word list is clean.
try:
    wa.check_banned("ظهرهای ژوئیه و اوت")
    raise AssertionError("check_banned should reject a Gregorian month name")
except wa.AgentError as err:
    assert "فیلتر املا" in str(err)
wa.check_banned("ظهرهای تیر و مرداد")

# Photo attribution is a legitimate Latin name/licence, not foreign prose.
credit = '<figcaption>عکس: <span class="sa-photo-credit-latin">Diba Tensile Architecture</span> · <span class="sa-photo-credit-latin">CC BY-SA 4.0</span></figcaption>'
assert fl.scan(credit) == [], fl.scan(credit)
assert any("LATIN" in x for x in fl.scan('<p>Diba Tensile Architecture</p>'))
assert any("FOREIGN" in x for x in fl.scan('<span class="sa-photo-credit-latin">Diba در ماه اوت</span>'))

# 11. CLI: a directory run fails on a bad file and passes after --fix.
with tempfile.TemporaryDirectory() as d:
    page = Path(d) / "x-1.html"
    page.write_text("<p>در ماه اوت هوا گرم است.</p>\n", encoding="utf-8")
    assert fl.main(["fa_lint", str(page), "--quiet"]) == 1
    assert fl.main(["fa_lint", str(page), "--fix", "--quiet"]) == 0  # fixed in place
    assert "اوت" not in page.read_text(encoding="utf-8")
    assert fl.main(["fa_lint", str(page), "--quiet"]) == 0

print("OK: Persian spelling filter (months, ZWNJ, Arabic letters, sanctioned box, meta keys, every publish path)")


# 8. فرمول‌های دیوانی هوش مصنوعی و جملهٔ تکراری داخل یک فایل (دستور مالک ۱۴۰۵/۰۹/۰۷).
for banned in ("استعلام شود", "بازبینی گردد", "نکتهٔ صداقت", "در این مقاله", "امیدوارم"):
    hits = fl.scan(f"اطلاعات این بخش {banned} پیش از هر اقدامی.")
    assert any("BANNED" in h for h in hits), banned
dup = "باغ فین در سال ۱۳۹۰ خورشیدی در فهرست میراث جهانی یونسکو ثبت شد.\n" * 2
hits = fl.scan_duplicates(dup, "t: ")
assert any("DUP" in h for h in hits)
assert fl.scan_duplicates("باغ فین در سال ۱۳۹۰ خورشیدی در فهرست میراث جهانی یونسکو ثبت شد.", "t: ") == []
print("fa_lint new owner gates OK")
