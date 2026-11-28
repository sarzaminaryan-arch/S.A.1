#!/usr/bin/env python3
"""کاوش فقط‌خواندنی آدرس‌های سایت با کد وضعیت واقعی.

چرا این ابزار لازم شد: در سندباکس توسعه، اتصال مستقیم به IPهای ایرانی بسته است،
پس ابزارهای محلی فقط «پاسخ خالی / TLS EOF» می‌بینند و نمی‌توانند بگویند صفحه
`500` می‌دهد یا `403` یا `404`. این ابزار روی رانر GitHub Actions اجرا می‌شود
(که اینترنت آزاد دارد) و برای هر آدرس، کد وضعیت، تغییرمسیر و نشانهٔ خطای
وردپرس/سرور را چاپ می‌کند.

فقط GET می‌زند، هیچ کوکی/رمز/نویسه‌ای نمی‌فرستد و هیچ چیزی را تغییر نمی‌دهد.

کاربرد:
    python3 tools/ops/probe_urls.py
    python3 tools/ops/probe_urls.py --base https://sarzaminaryan.ir
"""
from __future__ import annotations

import argparse
import re
import ssl
import sys
import urllib.error
import urllib.request

DEFAULT_BASE = "https://sarzaminaryan.ir"

# نمونه‌های هر نوع مسیر — تا اگر یک لایه خراب است، از بقیه جدا شود.
PATHS = [
    ("خانه", "/"),
    ("برگهٔ ساده", "/about/"),
    ("برگهٔ سادهٔ دیگر", "/contact/"),
    ("داشبورد", "/dashboard/"),
    ("آرشیو استان‌ها", "/province/"),
    ("استان تهران", "/province/tehran/"),
    ("استان فارس", "/province/fars/"),
    ("آرشیو شهرستان‌ها", "/city/"),
    ("شهرستان تبریز", "/city/tabriz/"),
    ("آرشیو دیدنی‌ها", "/attraction/"),
    ("دیدنی: آبشار بیشه", "/attraction/bisheh-waterfall-dorud/"),
    ("آرشیو آموزش سفر", "/travel-guide/"),
    ("دستهٔ آموزش سفر", "/category/camping-survival/"),
    ("نشانی ناموجود (باید ۴۰۴)", "/this-page-should-be-404-xyz/"),
    ("نقشهٔ سایت وردپرس", "/wp-sitemap.xml"),
    ("wp-login", "/wp-login.php"),
    ("REST ریشه", "/wp-json/"),
    ("REST پینگ ایجنت", "/wp-json/sa-agent/v1/ping"),
    # ---- گروه تشخیصی: آیا نوع‌محتوا ثبت شده ولی قاعدهٔ بازنویسی کهنه است؟ ----
    # اگر این‌ها ۲۰۰ بدهند ولی مسیرهای بالا ۴۰۴، یعنی CPT سالم است و فقط
    # rewrite_rules کهنه/خالی است (رفع: Settings → Permalinks → Save).
    ("؟province=tehran (بدون rewrite)", "/?province=tehran"),
    ("؟post_type=province (بدون rewrite)", "/?post_type=province"),
    ("؟p=2246 (شناسهٔ جاذبه)", "/?p=2246"),
    ("؟pagename=about", "/?pagename=about"),
    ("برگهٔ editorial-policy", "/editorial-policy/"),
    ("برگهٔ policy (ناموجود)", "/policy/"),
    ("city-contrib (ناموجود)", "/city-contrib/"),
    ("خوراک RSS", "/feed/"),
]

SIGNATURES = [
    (r"critical error|There has been a critical error", "خطای مرگبار PHP (critical error)"),
    (r"Access to this resource on the server is denied", "۴۰۳ سی‌پنل/mod_security"),
    (r"Default Web Site Page|defaultwebpage", "صفحهٔ پیش‌فرض سی‌پنل"),
    (r"Error establishing a database connection", "اتصال پایگاه‌داده قطع است"),
    (r"<title>404|صفحه پیدا نشد|Nothing Found", "قالب ۴۰۴"),
    (r"Fatal error", "Fatal error در خروجی"),
]


def _title(html: str) -> str:
    """عنوان صفحه — برای اینکه بفهمیم یک آدرس واقعاً همان صفحه را داده یا صفحهٔ اصلی/۴۰۴."""
    m = re.search(r"<title[^>]*>(.*?)</title>", html, re.I | re.S)
    if not m:
        return ""
    return re.sub(r"\s+", " ", m.group(1)).strip()[:90]


def probe(url: str) -> dict:
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE

    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            return None

    opener = urllib.request.build_opener(
        urllib.request.HTTPSHandler(context=ctx), NoRedirect
    )
    req = urllib.request.Request(url, headers={"User-Agent": "SarzaminProbe/1.0 (+read-only)"})
    out = {"url": url, "status": None, "location": "", "bytes": 0, "error": "", "sign": [], "title": ""}
    try:
        with opener.open(req, timeout=45) as resp:
            body = resp.read(400_000).decode("utf-8", "replace")
            out["status"] = resp.status
            out["bytes"] = len(body)
            out["sign"] = [label for pat, label in SIGNATURES if re.search(pat, body, re.I)]
            out["title"] = _title(body)
    except urllib.error.HTTPError as exc:
        body = exc.read(400_000).decode("utf-8", "replace") if exc.fp else ""
        out["status"] = exc.code
        out["location"] = exc.headers.get("Location", "") or ""
        out["bytes"] = len(body)
        out["sign"] = [label for pat, label in SIGNATURES if re.search(pat, body, re.I)]
        out["title"] = _title(body)
    except Exception as exc:  # noqa: BLE001 - گزارش هر نوع خطای شبکه کافی است
        out["error"] = f"{type(exc).__name__}: {exc}"
    return out


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="کاوش فقط‌خواندنی آدرس‌های سایت")
    ap.add_argument("--base", default=DEFAULT_BASE)
    ap.add_argument("--annotations", action="store_true", help="چاپ annotationهای GitHub برای خواندن از API")
    args = ap.parse_args(argv)
    base = args.base.rstrip("/")

    print(f"{'نوع':<26} {'کد':<6} {'حجم':>8}  نشانی")
    print("-" * 96)
    rows = []
    for label, path in PATHS:
        r = probe(base + path)
        rows.append(r)
        code = r["status"] if r["status"] is not None else "ERR"
        size = f"{r['bytes']:,}" if r["bytes"] else "-"
        print(f"{label:<26} {str(code):<6} {size:>8}  {path}")
        if r["location"]:
            print(f"{'':<26} {'→':<6} {'':>8}  {r['location']}")
        if r["error"]:
            print(f"{'':<26} {'!':<6} {'':>8}  {r['error'][:110]}")
        if r["title"]:
            print(f"{'':<26} {'»':<6} {'':>8}  {r['title']}")
        for s in r["sign"]:
            print(f"{'':<26} {'⚠':<6} {'':>8}  {s}")

    print("\nخلاصه:")
    ok = [r for r in rows if r["status"] == 200]
    bad = [r for r in rows if r["status"] not in (200, 404)]
    print(f"  ۲۰۰: {len(ok)}   خطادار: {len(bad)}")
    for r in bad:
        code = r["status"] if r["status"] is not None else "ERR"
        print(f"    {code:<5} {r['url']}  {' / '.join(r['sign']) or r['error'][:70]}")

    # وقتی این ابزار روی رانر GitHub اجرا می‌شود، لاگ و artifact ممکن است از
    # سندباکس توسعه خواندنی نباشند؛ annotationها از API خواندنی‌اند. GitHub
    # تعداد annotation هر step را محدود می‌کند، پس نتایج را در چند خط فشرده
    # می‌کنیم (هر خط چند آدرس).
    if args.annotations:
        pairs = list(zip(PATHS, rows))
        chunk = 3
        for i in range(0, len(pairs), chunk):
            part = pairs[i : i + chunk]
            bits = []
            for (label, path), r in part:
                code = r["status"] if r["status"] is not None else "ERR"
                extra = ("/" + ",".join(s.split(" ")[0] for s in r["sign"])) if r["sign"] else ""
                ttl = f"«{r['title'][:34]}»" if r["title"] else ""
                bits.append(f"{path}={code}{extra}{ttl}")
            body = " | ".join(bits)
            print(f"::notice title=probe {i // chunk + 1}::{body}")
        print(f"::notice title=probe summary::200:{len(ok)} bad:{len(bad)} of {len(rows)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
