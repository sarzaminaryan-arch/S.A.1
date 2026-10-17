#!/usr/bin/env python3
"""
sync_county_meta.py — نوشتن «کلیدهای شناسنامهٔ شهرستان» روی وردپرس از راه REST API.

این اسکریپت بخشی از فاز ۲ و ۳ نقشهٔ راه شناسنامهٔ شهرستان است
(docs/county-identity-roadmap.md). برای هر شهرستان:

  1. پست را با نامک (slug) از `/wp-json/wp/v2/city` پیدا می‌کند؛
  2. پیش از هر تغییر، پشتیبان کامل (محتوا، متا، تصویر شاخص، تاریخ‌ها، وضعیت)
     را در `backup/{id}.json` می‌نویسد؛
  3. کلیدهای کانونی شناسنامه را با POST روی همان پست به‌روزرسانی می‌کند؛
  4. دوباره می‌خواند و نتیجه را گزارش می‌کند (چه کلیدی نشست، چه کلیدی خالی ماند).

قواعد ایمنی که در خودِ اسکریپت اجرا می‌شوند:
  * هر کلیدی که در payload آمده باید در `sources` همان payload منبع داشته باشد؛
    کلید بی‌منبع = توقف اجرا (دادهٔ بی‌منبع روی سایت نوشته نمی‌شود).
  * جمعیت بدون سال سرشماری نوشته نمی‌شود (`population` ↔ `population_year`).
  * مختصات نامعتبر (غیرعددی یا بیرون بازهٔ ±۹۰/±۱۸۰) نوشته نمی‌شود.
  * نامک (slug) و تاریخ انتشار (`date`) هرگز تغییر نمی‌کنند؛ `date_modified` فقط
    با پرچم صریح `--modified-date` نوشته می‌شود.
  * اعتبارنامه‌ها فقط از `.env` خوانده می‌شوند و هیچ‌وقت چاپ نمی‌شوند.

استفاده:
    python3 tools/county-sync/sync_county_meta.py payload.json --dry-run
    python3 tools/county-sync/sync_county_meta.py payload.json
    python3 tools/county-sync/sync_county_meta.py payload.json --only county_en,center_fa

متغیرهای `.env` (در ریشهٔ مخزن؛ هرگز کامیت نمی‌شود):
    SA_SITE_URL=https://sarzaminaryan.ir
    SA_USER=<نام کاربری مدیر/ویرایشگر>
    SA_APP_PASSWORD=<Application Password>

نکته: `inc/security.php` فعلاً Application Password را خاموش کرده است
(`wp_is_application_passwords_available` → false). تا وقتی آن فیلتر موقتاً
برداشته نشود (یا از WP-CLI استفاده نشود)، نوشتن از راه REST با ۴۰۱ شکست می‌خورد.
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
THEME_META_LAYER = REPO_ROOT / "wp-content/themes/sarzaminaryan-child/inc/county-profile.php"

NUMERIC_KEYS = {
    "province_id",
    "population",
    "population_year",
    "elevation_m",
    "divisions_bakhsh",
    "divisions_shahr",
    "divisions_dehestan",
    "divisions_rosta",
    "distance_to_capital_km",
}
FLOAT_KEYS = {"area_km2", "lat", "lng"}


# --------------------------------------------------------------------------- #
# تنظیمات و کلیدهای مجاز
# --------------------------------------------------------------------------- #
def load_env(path: Path) -> dict[str, str]:
    """خواندن .env بدون چاپ هیچ مقداری."""
    env: dict[str, str] = {}
    if path.is_file():
        for raw in path.read_text(encoding="utf-8").splitlines():
            line = raw.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            env[key.strip()] = value.strip().strip('"').strip("'")
    for key, value in os.environ.items():
        if key.startswith("SA_"):
            env.setdefault(key, value)
    return env


def canonical_keys() -> list[str]:
    """فهرست کلیدهای کانونی، مستقیم از لایهٔ متای قالب (تا هرگز واگرا نشود)."""
    src = THEME_META_LAYER.read_text(encoding="utf-8")
    block = re.search(r"function sa_county_profile_fields\(\)\s*\{\s*return array\((.*?)\n\t\);", src, re.S)
    if not block:
        raise SystemExit(f"cannot parse canonical keys from {THEME_META_LAYER}")
    return re.findall(r"^\t\t'([a-z0-9_]+)'\s+=>", block.group(1), re.M)


def mask(text: str, secret: str) -> str:
    return text.replace(secret, "***") if secret else text


# --------------------------------------------------------------------------- #
# REST
# --------------------------------------------------------------------------- #
class WPRest:
    def __init__(self, base_url: str, user: str, password: str, timeout: int = 60) -> None:
        self.base = base_url.rstrip("/")
        self.api = f"{self.base}/wp-json/wp/v2"
        self.timeout = timeout
        token = base64.b64encode(f"{user}:{password}".encode("utf-8")).decode("ascii")
        self.headers = {
            "Authorization": f"Basic {token}",
            "Accept": "application/json",
            "User-Agent": "sarzaminaryan-county-sync/1.0",
        }
        self._secret = password

    def _request(self, method: str, url: str, payload: dict | None = None) -> dict | list:
        data = json.dumps(payload, ensure_ascii=False).encode("utf-8") if payload is not None else None
        req = urllib.request.Request(url, data=data, method=method)
        for key, value in self.headers.items():
            req.add_header(key, value)
        if data is not None:
            req.add_header("Content-Type", "application/json; charset=utf-8")
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:  # noqa: PERF203
            body = exc.read().decode("utf-8", "replace")[:400]
            raise SystemExit(mask(f"HTTP {exc.code} برای {url}\n{body}", self._secret)) from exc
        except urllib.error.URLError as exc:
            raise SystemExit(mask(f"اتصال به {url} ممکن نشد: {exc.reason}", self._secret)) from exc

    def find_city(self, slug: str) -> dict | None:
        query = urllib.parse.urlencode({"slug": slug, "context": "edit"})
        found = self._request("GET", f"{self.api}/city?{query}")
        if isinstance(found, list) and found:
            return found[0]
        return None

    def read_city(self, post_id: int) -> dict:
        query = urllib.parse.urlencode({"context": "edit"})
        result = self._request("GET", f"{self.api}/city/{post_id}?{query}")
        if not isinstance(result, dict):
            raise SystemExit(f"پاسخ غیرمنتظره برای پست {post_id}")
        return result

    def update_city(self, post_id: int, payload: dict) -> dict:
        result = self._request("POST", f"{self.api}/city/{post_id}", payload)
        if not isinstance(result, dict):
            raise SystemExit(f"پاسخ غیرمنتظره در به‌روزرسانی پست {post_id}")
        return result


# --------------------------------------------------------------------------- #
# اعتبارسنجی payload
# --------------------------------------------------------------------------- #
def validate_entry(entry: dict, allowed: list[str], errors: list[str]) -> dict:
    slug = str(entry.get("slug", "")).strip()
    if not slug:
        errors.append("یک رکورد بدون slug است.")
        return {}
    meta = dict(entry.get("meta") or {})
    sources = dict(entry.get("sources") or {})

    for key in list(meta):
        if key not in allowed:
            errors.append(f"{slug}: کلید «{key}» در فهرست کلیدهای کانونی قالب نیست.")
            meta.pop(key)
            continue
        value = meta[key]
        if value is None or (isinstance(value, str) and not value.strip()):
            meta.pop(key)  # فیلد خالی یعنی همان خانه در کارت نیست؛ چیزی نوشته نمی‌شود.
            continue
        if not str(sources.get(key, "")).strip():
            errors.append(f"{slug}: کلید «{key}» منبع ندارد (sources) — نوشته نمی‌شود.")
            meta.pop(key)
            continue
        if key in NUMERIC_KEYS:
            digits = re.sub(r"[^\d]", "", str(value))
            if not digits:
                errors.append(f"{slug}: مقدار «{key}» عددی نیست: {value!r}")
                meta.pop(key)
            else:
                meta[key] = int(digits)
        elif key in FLOAT_KEYS:
            try:
                meta[key] = float(re.sub(r"[^\d.\-]", "", str(value)))
            except ValueError:
                errors.append(f"{slug}: مقدار «{key}» عددی نیست: {value!r}")
                meta.pop(key)
        else:
            meta[key] = str(value).strip()

    if "population" in meta and "population_year" not in meta:
        errors.append(f"{slug}: جمعیت بدون سال سرشماری نوشته نمی‌شود (population_year خالی است).")
        meta.pop("population")
    if "population_year" in meta and "population" not in meta:
        meta.pop("population_year")
    lat, lng = meta.get("lat"), meta.get("lng")
    if (lat is None) != (lng is None):
        errors.append(f"{slug}: lat و lng باید با هم باشند.")
        meta.pop("lat", None)
        meta.pop("lng", None)
    elif lat is not None and not (-90 <= float(lat) <= 90 and -180 <= float(lng) <= 180):
        errors.append(f"{slug}: مختصات بیرون بازهٔ معتبر است ({lat}, {lng}).")
        meta.pop("lat", None)
        meta.pop("lng", None)

    return {"slug": slug, "meta": meta, "sources": sources, "raw": entry}


# --------------------------------------------------------------------------- #
# پشتیبان و نوشتن
# --------------------------------------------------------------------------- #
def write_backup(post: dict, backup_dir: Path) -> Path:
    backup_dir.mkdir(parents=True, exist_ok=True)
    path = backup_dir / f"{post['id']}.json"
    path.write_text(json.dumps(post, ensure_ascii=False, indent=2), encoding="utf-8")
    return path


def main() -> int:
    parser = argparse.ArgumentParser(description="نوشتن کلیدهای شناسنامهٔ شهرستان روی وردپرس")
    parser.add_argument("payload", nargs="+", help="فایل(های) JSON دادهٔ شهرستان‌ها")
    parser.add_argument("--env", default=str(REPO_ROOT / ".env"), help="مسیر .env (پیش‌فرض: ریشهٔ مخزن)")
    parser.add_argument("--backup-dir", default=str(REPO_ROOT / "backup"), help="مسیر پشتیبان‌ها (پیش‌فرض: backup/)")
    parser.add_argument("--progress", default="", help="فایل progress.json برای ثبت پیشرفت دسته")
    parser.add_argument("--dry-run", action="store_true", help="فقط گزارش؛ هیچ نوشتنی انجام نمی‌شود")
    parser.add_argument("--no-backup", action="store_true", help="بدون پشتیبان (توصیه نمی‌شود)")
    parser.add_argument("--only", default="", help="فقط این کلیدها (جداشده با کاما)")
    parser.add_argument("--modified-date", default="", help="date_modified صریح برای این دسته (ISO-8601)")
    args = parser.parse_args()

    allowed = canonical_keys()
    only = {k.strip() for k in args.only.split(",") if k.strip()}
    errors: list[str] = []
    entries: list[dict] = []
    for payload_path in args.payload:
        data = json.loads(Path(payload_path).read_text(encoding="utf-8"))
        for raw_entry in data.get("counties", []):
            entry = validate_entry(raw_entry, allowed, errors)
            if not entry:
                continue
            if only:
                entry["meta"] = {k: v for k, v in entry["meta"].items() if k in only}
            if entry["meta"]:
                entries.append(entry)

    if errors:
        print("✗ خطاهای payload (هیچ‌چیز نوشته نمی‌شود):", file=sys.stderr)
        for line in errors:
            print(f"  - {line}", file=sys.stderr)
        return 2
    if not entries:
        print("هیچ کلید دارای منبعی برای نوشتن پیدا نشد.")
        return 0

    env = load_env(Path(args.env))
    site = env.get("SA_SITE_URL", "").strip()
    user = env.get("SA_USER", "").strip()
    password = env.get("SA_APP_PASSWORD", "").strip()
    if not site or not user or not password:
        print(
            "✗ SA_SITE_URL / SA_USER / SA_APP_PASSWORD در .env نیست.\n"
            f"  مسیر مورد انتظار: {args.env}",
            file=sys.stderr,
        )
        return 2

    rest = WPRest(site, user, password)
    backup_dir = Path(args.backup_dir)
    report: list[dict] = []
    skipped: list[dict] = []

    for entry in entries:
        slug = entry["slug"]
        post = rest.find_city(slug)
        if not post:
            skipped.append({"slug": slug, "reason": "پستی با این نامک پیدا نشد"})
            print(f"• {slug}: پستی با این نامک پیدا نشد — رد شد.")
            continue
        post_id = int(post["id"])
        link = post.get("link", "")
        full = rest.read_city(post_id)
        backup_path = None
        if not args.dry_run and not args.no_backup:
            backup_path = write_backup(full, backup_dir)

        before = dict(full.get("meta") or {})
        payload: dict = {"meta": entry["meta"]}
        if args.modified_date:
            payload["date_modified"] = args.modified_date
        written = {}
        if not args.dry_run:
            rest.update_city(post_id, payload)
            after = dict(rest.read_city(post_id).get("meta") or {})
            for key, value in entry["meta"].items():
                got = after.get(key, "")
                ok = str(got).strip() == str(value).strip()
                written[key] = {"value": value, "verified": ok, "source": entry["sources"].get(key, "")}
                if not ok:
                    print(f"  ⚠ {slug}: کلید {key} بعد از نوشتن {got!r} خوانده شد (مقدار درخواستی {value!r}).")
        else:
            for key, value in entry["meta"].items():
                written[key] = {
                    "value": value,
                    "verified": None,
                    "before": before.get(key, ""),
                    "source": entry["sources"].get(key, ""),
                }

        empty = [k for k in allowed if str((before.get(k) or "")).strip() == "" and k not in entry["meta"]]
        report.append(
            {
                "slug": slug,
                "post_id": post_id,
                "link": link,
                "dry_run": args.dry_run,
                "backup": str(backup_path) if backup_path else None,
                "written": written,
                "still_empty": empty,
            }
        )
        state = "dry-run" if args.dry_run else "نوشته شد"
        print(f"✓ {slug} (پست {post_id}) — {len(written)} کلید {state}: {', '.join(sorted(written))}")
        if empty:
            print(f"   خانهٔ خالی باقی‌مانده ({len(empty)}): {', '.join(empty)}")

    out = {
        "generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "site": site,
        "dry_run": args.dry_run,
        "counties": report,
        "skipped": skipped,
    }
    print("\nگزارش JSON:")
    print(json.dumps(out, ensure_ascii=False, indent=2)[:4000])

    if args.progress:
        progress_path = Path(args.progress)
        progress = json.loads(progress_path.read_text(encoding="utf-8")) if progress_path.is_file() else {"batches": []}
        progress["batches"].append(
            {
                "at": out["generated_at"],
                "dry_run": args.dry_run,
                "slugs": [r["slug"] for r in report],
                "skipped": skipped,
            }
        )
        if not args.dry_run:
            progress_path.parent.mkdir(parents=True, exist_ok=True)
            progress_path.write_text(json.dumps(progress, ensure_ascii=False, indent=2), encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
