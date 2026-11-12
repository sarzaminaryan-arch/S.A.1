#!/usr/bin/env python3
"""ساخت ZIP قابل نصب افزونهٔ یک‌بارمصرف sa-faq-restore خارج از درخت مخزن.

استفاده: python3 tools/build-faq-restore-plugin.py [مسیر خروجی]
طبق سیاست مخزن، ZIP داخل گیت کامیت نمی‌شود و به GitHub Release می‌رود.
"""
from __future__ import annotations

import hashlib
import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN = ROOT / "wp-content" / "plugins" / "sa-faq-restore"
SLUG = "sa-faq-restore"
INCLUDE = [
    "sa-faq-restore.php",
    "uninstall.php",
    "readme.txt",
]


def main() -> int:
    header = (PLUGIN / f"{SLUG}.php").read_text(encoding="utf-8")
    version_match = re.search(r"^\s*\*\s*Version:\s*(\S+)", header, re.MULTILINE)
    if not version_match:
        raise SystemExit("نسخهٔ افزونه در هدر پیدا نشد")
    version = version_match.group(1)
    out = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(f"/tmp/{SLUG}-{version}.zip")
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for rel in INCLUDE:
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
