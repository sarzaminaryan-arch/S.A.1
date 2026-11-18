#!/usr/bin/env python3
"""واردکردن تصاویر «مجاز» مکان‌ها از راه پل ایجنت (قالب ۲.۲۲.۹۸+).

چرا این ابزار وجود دارد
-----------------------
دستور مالک: تصویر مکان‌های جذابی که در صفحه‌های شهرستان تعریف می‌کنیم باید
«کاملاً قانونی، با نام‌بردن از عکاس» در سایت بیاید. سندباکس ایجنت به
دامنه‌های تصویری (upload.wikimedia.org) دسترسی ندارد، ولی رانر گیت‌هاب دارد؛
پس فایل به‌صورت base64 از این‌جا فرستاده نمی‌شود — سایت خودش فایل را
می‌گیرد و مجوز را پیش از ذخیره بررسی می‌کند.

قرارداد فایل دسته (`tools/photos/<batch>.json`)
------------------------------------------------
    {
      "note": "چرا این عکس‌ها",
      "photos": [
        {
          "city_id": 71,
          "place": "آیینه‌خانه مفخم",
          "kind": "heritage",            # heritage | fun | nature | modern
          "url": "https://upload.wikimedia.org/…/640px-….jpg",
          "author": "حسین معمری",         # نام عکاس — اجباری
          "license": "cc-by-4",          # cc0 | pd | cc-by-4 | cc-by-sa-4 …
          "license_url": "https://creativecommons.org/licenses/by/4.0/",
          "source_page": "https://commons.wikimedia.org/wiki/File:…",
          "alt": "…",
          "note": "یک پاراگراف راهنما برای کادر «مکان در یک نگاه»",
          "points": "نکتهٔ اول|نکتهٔ دوم|نکتهٔ سوم",
          "highlight": "یک خط هایلایت برای نوار سه‌کادره",
          "tag": "تفریحی"
        }
      ]
    }

پروندهٔ ارسالی مالک (قالب ۲.۲۲.۱۱۵+)
-------------------------------------
همهٔ عکس‌ها از ویکی‌مدیا نمی‌آیند: مالک خودش هم پرونده می‌فرستد (مثلاً نمای
هوایی معبد آناهیتای بیشاپور). آن پرونده‌ها میزبان و مجوز کریتیوکامنز ندارند،
پس به‌جای `url` کلید `file` (مسیر داخل مخزن) می‌گیرند و به‌صورت base64 به
`POST /wp-json/sa-agent/v1/photos/upload` می‌روند؛ کد مجوزشان `sa-archive`
(«بایگانی سرزمین آریان — ارسالی مالک») است و نام پدیدآور همچنان اجباری است.

    {
      "city_id": 325,
      "place": "معبد آناهیتای بیشاپور (نمای هوایی)",
      "overlay": "نمای هوایی معبد آناهیتا",
      "kind": "heritage",
      "file": "tools/photos/files/kazerun-anahita-aerial.webp",
      "author": "سرزمین آریان",
      "license": "sa-archive",
      "lead": true,          # نمای اول کادر «مکان در یک نگاه»
      "order": 1             # ترتیب دستی در اسلایدر
    }

هر عکس پیش از ارسال در همین‌جا هم سنجیده می‌شود: مجوز باید در فهرست مجاز
باشد، نام عکاس خالی نباشد، و برای عکس دارای `url` نشانی https و از دامنهٔ
مجاز باشد؛ برای پروندهٔ مالک، خودِ فایل باید داخل مخزن، در سقف ۳ مگابایت و
با بایت‌های هم‌خوانِ jpeg/webp/png باشد. آن‌گاه سایت همان قواعد را دوباره
اعمال می‌کند (دفاع در دو لایه).
"""
from __future__ import annotations

import base64
import json
import os
import sys
import urllib.parse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ALLOWED_LICENSES = {
    "cc0", "pd",
    "cc-by-4", "cc-by-3", "cc-by-2",
    "cc-by-sa-4", "cc-by-sa-3", "cc-by-sa-2",
}
ALLOWED_HOSTS = {"upload.wikimedia.org", "thumb.wikimedia.org"}
RESULT_DIR = ROOT / "tools" / "photos" / "results"

# v2.22.115 — پروندهٔ ارسالی مالک: میزبان ویکی‌مدیا ندارد، پس کد مجوز داخلی
# `sa-archive` می‌گیرد (در قالب هم مجاز است) و بایت‌هایش باید با نوع اعلام‌شده بخواند.
OWNER_LICENSES = ALLOWED_LICENSES | {"sa-archive"}
OWNER_FILE_DIR = ROOT / "tools" / "photos" / "files"
MAX_BYTES = 3 * 1024 * 1024  # همان سقف مسیر واردات کامانز
MIME_BY_MAGIC = (
    (b"\xff\xd8\xff", "image/jpeg"),
    (b"\x89PNG", "image/png"),
)


def normalise_license(code: str) -> str:
    """ویکی‌مدیا مجوز را با نسخه می‌دهد (`cc-by-sa-3.0`); یک شکل کافی است."""
    code = code.strip().lower()
    return code[:-2] if code.endswith(".0") else code


def host(url: str) -> str:
    rest = url.split("://", 1)[-1]
    return rest.split("/", 1)[0].lower()


def sniff_mime(data: bytes) -> str:
    """نوع واقعی فایل از بایت‌های اول — نه از پسوند نام فایل."""
    if data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        return "image/webp"
    for magic, mime in MIME_BY_MAGIC:
        if data.startswith(magic):
            return mime
    return ""


def owner_file(photo: dict) -> tuple[Path, str]:
    """مسیر و خطای پروندهٔ ارسالی مالک (خالی یعنی درست)."""
    raw = str(photo.get("file") or "").strip()
    path = Path(raw if os.path.isabs(raw) else ROOT / raw)
    try:
        path = path.resolve()
    except OSError:
        return Path(raw), "file path cannot be resolved"
    if OWNER_FILE_DIR not in path.parents:
        return path, "file must live inside tools/photos/files/"
    if not path.is_file():
        return path, f"file {raw!r} is not in the repository"
    return path, ""


def check(photo: dict) -> list[str]:
    """Everything that would make the photo illegal to publish."""
    if str(photo.get("file") or "").strip():
        return check_owner_file(photo)
    problems = []
    url = str(photo.get("url") or "")
    if not url.startswith("https://"):
        problems.append("url must be https")
    if host(url) not in ALLOWED_HOSTS:
        problems.append(f"host {host(url)!r} is not on the allow-list")
    if not str(photo.get("author") or "").strip():
        problems.append("author is required (the photographer must be named)")
    lic = normalise_license(str(photo.get("license") or ""))
    if lic not in ALLOWED_LICENSES:
        problems.append(f"license {lic!r} is not reuse-friendly")
    if not commons_thumb_shape(url):
        problems.append("thumbnail URL must repeat the file name (/thumb/a/ab/<file>/<N>px-<file>)")
    if not str(photo.get("place") or "").strip():
        problems.append("place is required")
    if not int(photo.get("city_id") or 0):
        problems.append("city_id is required")
    kind = str(photo.get("kind") or "")
    if kind not in {"heritage", "fun", "nature", "modern"}:
        problems.append(f"kind {kind!r} must be heritage|fun|nature|modern")
    return problems


def check_owner_file(photo: dict) -> list[str]:
    """سنجش پرونده‌ای که مالک فرستاده: فایل واقعی، نوع هم‌خوان، سقف حجم، اعتبار."""
    problems = []
    path, err = owner_file(photo)
    if err:
        problems.append(err)
    else:
        data = path.read_bytes()
        if len(data) < 2000:
            problems.append("file is smaller than 2 KB (probably not a real photo)")
        if len(data) > MAX_BYTES:
            problems.append(f"file is {len(data)} bytes, over the {MAX_BYTES}-byte ceiling")
        mime = sniff_mime(data)
        if not mime:
            problems.append("file bytes are not jpeg, webp or png")
        elif str(photo.get("mime") or mime) != mime:
            problems.append(f"declared mime does not match the file bytes ({mime})")
    if not str(photo.get("author") or "").strip():
        problems.append("author is required (the photographer must be named)")
    lic = normalise_license(str(photo.get("license") or ""))
    if lic not in OWNER_LICENSES:
        problems.append(f"license {lic!r} is not allowed for an owner-sent file")
    if not str(photo.get("place") or "").strip():
        problems.append("place is required")
    if not int(photo.get("city_id") or 0):
        problems.append("city_id is required")
    kind = str(photo.get("kind") or "")
    if kind not in {"heritage", "fun", "nature", "modern"}:
        problems.append(f"kind {kind!r} must be heritage|fun|nature|modern")
    return problems


def commons_thumb_shape(url: str) -> bool:
    """آیا نشانی، شکل درست «بندانگشتی ویکی‌مدیا» را دارد؟

    خطای واقعی کار ۱۱۷: نشانی بندانگشتی یک بار نام فایل داشت
    (`/thumb/c/c9/960px-<file>` بدون تکرار نام) و ویکی‌مدیا ۴۰۴ داد. شکل درست:
    `/thumb/<a>/<ab>/<file>/<عرض>px-<file>` — نام فایل پیش و پس از عرض می‌آید.
    """
    path = urllib.parse.unquote(urllib.parse.urlparse(url).path)
    if "/thumb/" not in path:
        return True  # فایل اصلی: بررسی شکل لازم ندارد
    segs = [s for s in path.split("/") if s]
    if len(segs) < 5:
        return False
    width, _, tail = segs[-1].partition("px-")
    return width.isdigit() and segs[-2] == tail


class Client:
    def __init__(self, site: str, user: str, password: str):
        self.base = site.rstrip("/") + "/wp-json/sa-agent/v1"
        token = base64.b64encode(f"{user}:{password}".encode()).decode()
        self.token = token

    def post(self, path: str, body: dict) -> dict:
        import urllib.error
        import urllib.request

        req = urllib.request.Request(
            self.base + path,
            data=json.dumps(body).encode(),
            headers={
                "Authorization": f"Basic {self.token}",
                "X-SA-Authorization": f"Basic {self.token}",  # اگر میزبان Authorization را بردارد
                "Content-Type": "application/json; charset=utf-8",
                "Accept": "application/json",
                "User-Agent": "sa-wp-agent/1.0 (photo import)",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=120) as resp:
                return json.loads(resp.read().decode())
        except urllib.error.HTTPError as err:
            detail = err.read().decode()[:400]
            raise SystemExit(f"HTTP {err.code} on {path}: {detail}") from err

    def get(self, path: str) -> dict:
        import urllib.request

        req = urllib.request.Request(
            self.base + path,
            headers={
                "Authorization": f"Basic {self.token}",
                "X-SA-Authorization": f"Basic {self.token}",
                "Accept": "application/json",
                "User-Agent": "sa-wp-agent/1.0 (photo import)",
            },
        )
        with urllib.request.urlopen(req, timeout=60) as resp:
            return json.loads(resp.read().decode())


def main(argv: list[str]) -> int:
    if len(argv) != 2:
        print("usage: import_photos.py tools/photos/<batch>.json", file=sys.stderr)
        return 2
    batch_path = Path(argv[1] if os.path.isabs(argv[1]) else ROOT / argv[1])
    batch = json.loads(batch_path.read_text(encoding="utf-8"))
    name = batch_path.stem

    site = os.environ.get("SA_SITE_URL", "")
    user = os.environ.get("SA_USER", "")
    password = (os.environ.get("SA_APP_PASSWORD", "")).replace(" ", "")
    if not (site and user and password):
        print("SA_SITE_URL, SA_USER and SA_APP_PASSWORD are required", file=sys.stderr)
        return 2

    report: dict = {
        "batch": name,
        "note": batch.get("note", ""),
        "imported": [],
        "reused": [],
        "refused": [],
        "failed": [],
    }

    # ۱) سنجش محلی همهٔ عکس‌ها پیش از هر تماس با سایت.
    for photo in batch.get("photos", []):
        problems = check(photo)
        if problems:
            report["refused"].append({"place": photo.get("place"), "problems": problems})
    if report["refused"]:
        RESULT_DIR.mkdir(parents=True, exist_ok=True)
        (RESULT_DIR / f"{name}.json").write_text(
            json.dumps(report, ensure_ascii=False, indent=1) + "\n", encoding="utf-8"
        )
        print(f"{len(report['refused'])} photo(s) refused locally; nothing sent.", file=sys.stderr)
        return 1

    client = Client(site, user, password)
    ping = client.get("/ping")
    if not ping.get("can_write"):
        print("the WordPress account cannot write", file=sys.stderr)
        return 1

    for photo in batch.get("photos", []):
        shared = ("city_id", "place", "kind", "alt", "note", "points",
                  "highlight", "tag", "overlay", "area", "author", "license",
                  "license_url", "source_page")
        if str(photo.get("file") or "").strip():
            path, _ = owner_file(photo)
            data = path.read_bytes()
            body = {k: photo.get(k) for k in shared + ("order", "lead")}
            body["file_base64"] = base64.b64encode(data).decode()
            body["filename"] = path.name
            body["mime"] = sniff_mime(data)
            endpoint = "/photos/upload"
        else:
            body = {k: photo.get(k) for k in shared + ("url",)}
            endpoint = "/photos/import"
        body = {k: v for k, v in body.items() if v is not None}
        try:
            out = client.post(endpoint, body)
        except SystemExit as err:  # پیام دقیق سرور در خروجی می‌ماند
            report["failed"].append({"place": photo.get("place"), "error": str(err)[:400]})
            continue
        row = {"place": photo.get("place"), "id": out.get("id"), "url": out.get("url")}
        if endpoint == "/photos/upload":
            row["owner_file"] = str(photo.get("file"))
        (report["reused"] if out.get("reused") else report["imported"]).append(row)

    RESULT_DIR.mkdir(parents=True, exist_ok=True)
    (RESULT_DIR / f"{name}.json").write_text(
        json.dumps(report, ensure_ascii=False, indent=1) + "\n", encoding="utf-8"
    )
    print(
        f"imported {len(report['imported'])}, reused {len(report['reused'])}, "
        f"failed {len(report['failed'])}, refused {len(report['refused'])}"
    )
    return 0 if not report["failed"] else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
