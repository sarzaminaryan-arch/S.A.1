#!/usr/bin/env python3
"""Package the one-shot SA SEO Inspector plugin into an installable zip.

    python3 tools/seo-audit/build_inspector_zip.py

Output: .tmp/dist/sa-seo-inspector-<version>.zip  (git-ignored, upload via
wp-admin → افزونه‌ها → افزودن → بارگذاری افزونه).

The zip contains a single folder `sa-seo-inspector/` with the plugin file, so
WordPress recognises it as a normal plugin. Nothing else is added — the plugin
is read-only and meant to be deleted after one run.
"""

import os
import re
import sys
import zipfile

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
SRC_DIR = os.path.join(REPO, "tools/seo-audit/sa-seo-inspector")
OUT_DIR = os.path.join(REPO, ".tmp/dist")


def version_of(main_file):
    with open(main_file, encoding="utf-8") as fh:
        head = fh.read(2000)
    m = re.search(r"^ \* Version:\s*([\d.]+)", head, re.M)
    return m.group(1) if m else "1.0.0"


def main():
    main_file = os.path.join(SRC_DIR, "sa-seo-inspector.php")
    if not os.path.isfile(main_file):
        sys.exit(f"missing {main_file}")
    version = version_of(main_file)
    os.makedirs(OUT_DIR, exist_ok=True)
    out = os.path.join(OUT_DIR, f"sa-seo-inspector-{version}.zip")

    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for name in sorted(os.listdir(SRC_DIR)):
            if name.endswith((".php", ".txt", ".md")) and not name.startswith("."):
                zf.write(os.path.join(SRC_DIR, name), f"sa-seo-inspector/{name}")

    size = os.path.getsize(out)
    print(f"[built] {out}  ({size} bytes, version {version})")
    with zipfile.ZipFile(out) as zf:
        for n in zf.namelist():
            print(f"  - {n}")


if __name__ == "__main__":
    main()
