#!/usr/bin/env python3
"""آزمون آفلاین «واردکردن تصویر مجاز» — سیاست مجوز، نه رابط کاربری.

قاعدهٔ مالک: تصویر فقط وقتی منتشر می‌شود که مجوزش اجازهٔ استفاده بدهد و نام
عکاس بیاید. این تست همان قاعده را در دو لایه می‌سنجد: سنجهٔ پایتون (پیش از
ارسال) و قاعدهٔ PHP (پیش از ذخیره).
"""
import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import import_photos as ip  # noqa: E402

GOOD = {
    "city_id": 71,
    "place": "آیینه‌خانه مفخم",
    "kind": "heritage",
    "url": "https://upload.wikimedia.org/wikipedia/commons/thumb/e/ee/x.jpg/640px-x.jpg",
    "author": "حسین معمری",
    "license": "cc-by-4",
    "license_url": "https://creativecommons.org/licenses/by/4.0/",
    "source_page": "https://commons.wikimedia.org/wiki/File:x.jpg",
}

assert ip.check(GOOD) == [], "a complete, reuse-friendly photo passes"
assert ip.check({**GOOD, "license": "cc-by-sa-3.0"}) == [], "the 3.0 spelling Commons returns is accepted"

# مجوزها: NC و ND «آزاد» نیستند و صفحه‌ای با آن‌ها قانونی نیست.
for bad in ("cc-by-nd-4", "cc-by-nc-4", "all-rights-reserved", ""):
    assert any("license" in p for p in ip.check({**GOOD, "license": bad})), bad

# نام عکاس اجباری است.
assert any("author" in p for p in ip.check({**GOOD, "author": ""})), "author is required"

# فقط دامنهٔ فایل مجاز، فقط https.
assert any("allow-list" in p for p in ip.check({**GOOD, "url": "https://kojaro.com/x.jpg"}))
assert any("https" in p for p in ip.check({**GOOD, "url": "http://upload.wikimedia.org/x.jpg"}))
assert any("allow-list" in p for p in ip.check({**GOOD, "url": "https://evil.test/upload.wikimedia.org/x.jpg"}))
assert ip.check({**GOOD, "url": "https://thumb.wikimedia.org/wikipedia/commons/thumb/a/ab/x.jpg/960px-x.jpg"}) == [], "the thumbnail host is allowed too" 

# نوع مکان محدود است (نوار سه‌کادره فقط kind=fun را می‌خواند).
assert any("kind" in p for p in ip.check({**GOOD, "kind": "food"}))

# قاعدهٔ PHP: همان فهرست مجوز و همان دامنه.
php = (HERE.parents[1] / "wp-content/themes/sarzaminaryan-child/inc/county-media.php").read_text(encoding="utf-8")
for token in ("'cc0'", "'pd'", "'cc-by-4'", "'cc-by-sa-4'", "'upload.wikimedia.org'", "'thumb.wikimedia.org'", "sa_photo_is_publishable"):
    assert token in php, token
assert "cc-by-nd" not in php and "cc-by-nc" not in php, "ND/NC must not be allow-listed"
assert ("update_post_meta( (int) $att_id, SA_PHOTO_META_AUTHOR" in php
        or "update_post_meta( $att_id, SA_PHOTO_META_AUTHOR" in php), "the author is stored with the file"

# قرارداد دسته: هر عکس باید کلیدهای اجباری را داشته باشد.
required = {"city_id", "place", "kind", "url", "author", "license"}
assert required <= set(GOOD), "the batch contract carries every required key"

# شکل نشانی بندانگشتی: کار ۱۱۷ سه تصویر را با نشانی یک‌بار-نام‌دار از دست داد و
# ویکی‌مدیا ۴۰۴ داد. حالا همان خطا پیش از ارسال گرفته می‌شود.
bad_thumb = "https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c9/960px-x.jpg"
ok_thumb = "https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c9/x.jpg/960px-x.jpg"
assert ip.commons_thumb_shape(ok_thumb) is True
assert ip.commons_thumb_shape(bad_thumb) is False
assert any("repeat the file name" in p for p in ip.check({**GOOD, "url": bad_thumb})), "a malformed thumb URL is refused before upload"
assert ip.commons_thumb_shape("https://upload.wikimedia.org/wikipedia/commons/e/ee/x.jpg") is True, "an original file needs no thumb shape"

# v2.22.115 — پروندهٔ ارسالی مالک: بدون میزبان ویکی‌مدیا و بدون مجوز کامانز، ولی با
# فایل واقعی داخل مخزن، بایت‌های هم‌خوان و نام پدیدآور. مسیرش /photos/upload است.
assert ip.sniff_mime(b"\xff\xd8\xff\xe0junk") == "image/jpeg", "jpeg magic bytes are sniffed"
assert ip.sniff_mime(b"RIFFxxxxWEBPjunk") == "image/webp", "webp magic bytes are sniffed"
assert ip.sniff_mime(b"\x89PNG\r\n\x1a\njunk") == "image/png", "png magic bytes are sniffed"
assert ip.sniff_mime(b"GIF89ajunk") == "", "an unlisted format has no mime"
OWNER = {
    "city_id": 325,
    "place": "معبد آناهیتای بیشاپور (نمای هوایی)",
    "kind": "heritage",
    "file": "tools/photos/files/kazerun-anahita-aerial.webp",
    "author": "سرزمین آریان",
    "license": "sa-archive",
}
if (HERE / "files" / "kazerun-anahita-aerial.webp").is_file():
    assert ip.check(OWNER) == [], "the owner's aerial passes with a named author and the archive licence"
assert any("license" in x for x in ip.check({**OWNER, "license": "cc-by-nd-4"})), "ND stays refused even for owner files"
assert any("license" in x for x in ip.check({**OWNER, "license": ""})), "an owner file still needs a licence code"
assert any("author" in x for x in ip.check({**OWNER, "author": ""})), "an owner file still names its author"
assert any("not in the repository" in x for x in ip.check({**OWNER, "file": "tools/photos/files/nope.webp"})), "a missing file is refused before any upload"
assert any("tools/photos/files/" in x for x in ip.check({**OWNER, "file": "/etc/passwd"})), "a file outside the repo folder is refused"
assert any("kind" in x for x in ip.check({**OWNER, "kind": "food"})), "the kind vocabulary is shared by both routes"
php_src = (HERE.parents[1] / "wp-content/themes/sarzaminaryan-child/inc/county-media.php").read_text(encoding="utf-8")
assert "'sa-archive'" in php_src, "the theme's licence table carries the owner-archive code"
assert "sa_photo_bytes_match_mime" in php_src, "the theme re-checks the declared mime against the real bytes"
assert "function sa_photo_replace_with_attachment" in php_src, "the theme can swap a photo's file in place"
admin_src = (HERE.parents[1] / "wp-content/themes/sarzaminaryan-child/inc/county-photos-admin.php").read_text(encoding="utf-8")
for token in ("add_meta_box( 'sa-county-photos'", "wp_ajax_sa_photo_attach", "wp_ajax_sa_photo_swap", "wp_ajax_sa_photo_detach", "wp_ajax_sa_photo_save"):
    assert token in admin_src, token

# هر فایل دستهٔ داخل مخزن هم باید از فیلتر فارسی تمیز بگذرد (قاعدهٔ مالک: بدون «باید»،
# بدون ماه میلادی، بدون واژهٔ لاتین جاافتاده در متن فارسی).
try:
    sys.path.insert(0, str(HERE.parents[1] / "tools" / "wp-agent" / "replace_src"))
    import fa_lint  # type: ignore

    for batch_file in sorted(HERE.glob("2*.json")):
        for photo in json.loads(batch_file.read_text(encoding="utf-8")).get("photos", []):
            text = " ".join(str(photo.get(k, "")) for k in ("note", "points", "highlight", "tag", "alt"))
            hits = fa_lint.scan(text)
            assert not hits, f"{batch_file.name} / {photo.get('place')}: {hits}"
except ImportError:
    pass

print("ALL TESTS PASSED")
