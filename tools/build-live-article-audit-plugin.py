#!/usr/bin/env python3
"""ساخت ZIP قابل نصب افزونهٔ sa-live-article-audit خارج از درخت مخزن.

استفاده: python3 tools/build-live-article-audit-plugin.py [مسیر خروجی]
اگر data/baseline.json کنار افزونه باشد (در CI با
tools/generate-live-article-audit-baseline.php ساخته می‌شود) داخل ZIP می‌رود؛
وگرنه ZIP بدون خط مبنای همراه ساخته می‌شود و افزونه روی سایت از دادهٔ قالب
فرزند یا فایل بارگذاری‌شده استفاده می‌کند.
طبق سیاست مخزن، ZIP داخل گیت کامیت نمی‌شود و به GitHub Release می‌رود.
"""
from __future__ import annotations

import hashlib
import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN = ROOT / "wp-content" / "plugins" / "sa-live-article-audit"
SLUG = "sa-live-article-audit"
INCLUDE = [
    "sa-live-article-audit.php",
    "uninstall.php",
    "readme.txt",
    "includes/class-sa-laa-compare.php",
    "includes/class-sa-laa-baseline.php",
    "includes/class-sa-laa-extractor.php",
    "includes/class-sa-laa-export.php",
    "includes/class-sa-laa-admin.php",
    "assets/admin.css",
    "assets/admin.js",
]
OPTIONAL = ["data/baseline.json"]


def main() -> int:
    header = (PLUGIN / f"{SLUG}.php").read_text(encoding="utf-8")
    version = re.search(r"^\s*\*\s*Version:\s*(\S+)", header, re.MULTILINE).group(1)
    out = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(f"/tmp/{SLUG}-{version}.zip")
    files = list(INCLUDE)
    for rel in OPTIONAL:
        if (PLUGIN / rel).exists():
            files.append(rel)
        else:
            print(f"warning: optional file missing, skipped: {rel}")
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for rel in files:
            src = PLUGIN / rel
            if not src.exists():
                raise SystemExit(f"فایل گمشده: {src}")
            zf.write(src, f"{SLUG}/{rel}")
    digest = hashlib.sha256(out.read_bytes()).hexdigest()
    print(f"built: {out} ({out.stat().st_size} bytes)")
    print(f"sha256: {digest}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
