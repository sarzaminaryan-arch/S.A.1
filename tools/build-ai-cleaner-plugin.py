#!/usr/bin/env python3
"""ساخت ZIP قابل نصب افزونهٔ sa-ai-sentence-cleaner خارج از درخت مخزن.

استفاده: python3 tools/build-ai-cleaner-plugin.py [مسیر خروجی]
خروجی پیش‌فرض: /tmp/sa-ai-sentence-cleaner-<version>.zip
طبق سیاست مخزن، ZIP داخل گیت کامیت نمی‌شود و به GitHub Release می‌رود.
"""
from __future__ import annotations

import hashlib
import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN = ROOT / "wp-content" / "plugins" / "sa-ai-sentence-cleaner"
INCLUDE = [
    "sa-ai-sentence-cleaner.php",
    "uninstall.php",
    "readme.txt",
    "includes/patterns.php",
    "includes/class-sa-ai-engine.php",
    "includes/class-sa-ai-store.php",
    "includes/class-sa-ai-admin.php",
    "assets/admin.css",
    "assets/admin.js",
]


def main() -> int:
    header = (PLUGIN / "sa-ai-sentence-cleaner.php").read_text(encoding="utf-8")
    version = re.search(r"^\s*\*\s*Version:\s*(\S+)", header, re.MULTILINE).group(1)
    out = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(f"/tmp/sa-ai-sentence-cleaner-{version}.zip")
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for rel in INCLUDE:
            src = PLUGIN / rel
            if not src.exists():
                raise SystemExit(f"فایل گمشده: {src}")
            zf.write(src, f"sa-ai-sentence-cleaner/{rel}")
    digest = hashlib.sha256(out.read_bytes()).hexdigest()
    print(f"built: {out} ({out.stat().st_size} bytes)")
    print(f"sha256: {digest}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
