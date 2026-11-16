#!/usr/bin/env python3
"""گارد یونیک بودن متن در برابر ویکی‌پدیا — دستور مالک، ۲۶ آبان ۱۴۰۵.

«متن‌های ویکی‌پدیا را هم بخوان، و جمع مطالب دیگر، بدون نیاز به اشاره به منبع، برداشت
شخصی گفته بشه، که در نهایت این مقاله برای گوگل یک متن یونیک ثبت بشه نه کپی از ویکی‌پدیا.»

این ابزار همان سنجش است: بدنهٔ ساخته‌شده را با متن زندهٔ صفحهٔ ویکی‌پدیای همان شهرستان
مقایسه می‌کند و هر **دنبالهٔ هشت‌واژه‌ای مشترک** را گزارش می‌دهد. عددهای جمعیت و مساحت
و نام‌های خاص از مقایسه کنار گذاشته می‌شوند (واژه‌های عددی و لاتین و نام‌های کوتاه)،
چون تطابقشان اجتناب‌ناپذیر است و کپی محسوب نمی‌شود.

استفاده:
    python3 tools/wp-agent/replace_src/wiki_overlap.py tools/wp-agent/outbox/<dir> \
        --url https://fa.wikipedia.org/wiki/شهرستان_کرج

خروجی: صفر یعنی هیچ جملهٔ هشت‌واژه‌ای مشترک نیست. هر یافته، جمله باید بازنویسی شود.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import urllib.parse
import urllib.request
from pathlib import Path

ZWNJ = "\u200c"
WORD_RE = re.compile(r"[\u0600-\u06FF" + ZWNJ + r"]+")
N = 8


def words(text: str) -> list[str]:
    """واژه‌های فارسی/عربی؛ عدد، لاتین و نشانه حذف می‌شوند."""
    clean = re.sub(r"<[^>]+>", " ", text)
    clean = re.sub(r"<!--.*?-->", " ", clean, flags=re.S)
    return [w.replace(ZWNJ, "") for w in WORD_RE.findall(clean)]


def shingles(ws: list[str]) -> set[str]:
    return {" ".join(ws[i:i + N]) for i in range(len(ws) - N + 1)}


def fetch_wiki(url: str) -> str:
    """متن صفحهٔ ویکی از API (فقط متن نمایشی، بی‌الگوی ویکی)."""
    parts = urllib.parse.urlparse(url)
    title = urllib.parse.unquote(parts.path.rsplit("/", 1)[-1]).replace("_", " ")
    api = (f"https://{parts.netloc}/w/api.php?action=parse&prop=text&format=json"
           f"&page={urllib.parse.quote(title)}")
    req = urllib.request.Request(api, headers={"User-Agent": "sar-agent-wiki-guard/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            data = json.loads(resp.read().decode("utf-8"))
    except Exception as err:      # sandbox has no direct network to wikipedia
        raise SystemExit(
            f"cannot reach wikipedia from here ({err}).\n"
            f"Fetch the article with the browser tool, save the text as a snapshot under\n"
            f"docs/live-site/briefs/wiki/<slug>.txt and run again with --wiki-file <path>.")
    html = data.get("parse", {}).get("text", {}).get("*", "")
    html = re.sub(r"<(script|style)\b.*?</\1>", " ", html, flags=re.S | re.I)
    html = re.sub(r"<sup.*?</sup>", " ", html, flags=re.S)
    return html


def scan(page: str, wiki_text: str) -> list[str]:
    mine, theirs = shingles(words(page)), shingles(words(wiki_text))
    shared = sorted(mine & theirs)
    return ["…" + s + "…" for s in shared]


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("target", help="فایل html یا پوشهٔ outbox")
    ap.add_argument("--url", required=True, help="نشانی صفحهٔ ویکی‌پدیای همان شهرستان")
    ap.add_argument("--wiki-file", help="متن ویکی از پیش ذخیره‌شده (وقتی شبکه نیست)")
    a = ap.parse_args(argv[1:])

    target = Path(a.target)
    pages = [target] if target.is_file() else sorted(target.glob("*.html"))
    if not pages:
        print(f"no html in {target}", file=sys.stderr)
        return 2
    wiki_text = Path(a.wiki_file).read_text(encoding="utf-8") if a.wiki_file else fetch_wiki(a.url)

    bad = 0
    for page in pages:
        hits = scan(page.read_text(encoding="utf-8"), wiki_text)
        if hits:
            bad += 1
            print(f"FAIL {page.name}: {len(hits)} shared {N}-word run(s)")
            for h in hits[:10]:
                print("   ", h)
        else:
            print(f"OK   {page.name}: no shared {N}-word run")
    print(f"\n{len(pages) - bad}/{len(pages)} page(s) unique against wikipedia")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
