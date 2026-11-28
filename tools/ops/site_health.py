#!/usr/bin/env python3
"""سنجش سلامت دامنه و وب‌سرور سایت — بدون وابستگی بیرونی.

کاربرد:
    python3 tools/ops/site_health.py                 # دامنهٔ تولید
    python3 tools/ops/site_health.py sarzaminaryan.ir
    python3 tools/ops/site_health.py --json

چه چیزی می‌سنجد؟
  1) رکوردهای DNS (A / AAAA / NS / MX / TXT) از راه DNS-over-HTTPS گوگل.
  2) باز بودن درگاه‌های 80 و 443 روی میزبان.
  3) پاسخ HTTP پورت ۸۰ (کد، سرور، نشانهٔ «Default Web Site Page» سی‌پنل).
  4) دست‌دادن TLS روی ۴۴۳ با SNI درست (گواهی، نام میزبان، سرآمد).
  5) پاسخ /wp-json/ (آیا وردپرس واقعاً بالاست؟).

این ابزار فقط می‌خواند و هیچ تغییری در سایت نمی‌دهد. برای تشخیص قطعیِ
«دامنه روی سرور هاست مجازی (vhost) ندارد» از نشانهٔ `default_vhost` استفاده کن:
پاسخ ۸۰ صفحهٔ پیش‌فرض cPanel و ۴۴۳ بی‌گواهی/EOF = دامنه در Apache تعریف نشده است.
"""
from __future__ import annotations

import argparse
import json
import socket
import ssl
import sys
import urllib.error
import urllib.request

DEFAULT_HOST = "sarzaminaryan.ir"
DOH = "https://dns.google/resolve?name={name}&type={qtype}"
UA = "Mozilla/5.0 (compatible; sa-site-health/1.0)"


def _get(url: str, timeout: float = 8.0):
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "*/*"})
    with urllib.request.urlopen(req, timeout=timeout) as resp:  # noqa: S310 (فقط https ثابت)
        return resp.status, resp.read(20000), dict(resp.headers)


def dns(name: str, qtype: str) -> list[str] | None:
    """رکورد DNS را از DoH می‌خواند؛ در نبود شبکه، None برمی‌گرداند."""
    for _ in range(3):
        try:
            status, body, _h = _get(DOH.format(name=name, qtype=qtype), timeout=6)
            data = json.loads(body.decode("utf-8"))
        except (urllib.error.URLError, TimeoutError, OSError, ValueError):
            continue
        answers = [a.get("data") for a in data.get("Answer", []) if a.get("data")]
        return answers
    return None


def tcp(host: str, port: int, timeout: float = 6.0) -> tuple[bool, str]:
    try:
        with socket.create_connection((host, port), timeout=timeout):
            return True, "open"
    except OSError as exc:
        return False, f"{type(exc).__name__}: {exc}"


def http_probe(host: str, path: str = "/", timeout: float = 8.0) -> dict:
    """درخواست HTTP ساده روی پورت ۸۰ و برگرداندن کد و سرصفحه‌ها."""
    out: dict = {"url": f"http://{host}{path}"}
    try:
        conn = socket.create_connection((host, 80), timeout=timeout)
    except OSError as exc:
        out["error"] = f"{type(exc).__name__}: {exc}"
        return out
    try:
        conn.sendall(
            f"GET {path} HTTP/1.1\r\nHost: {host}\r\nUser-Agent: {UA}\r\n"
            f"Accept: */*\r\nConnection: close\r\n\r\n".encode()
        )
        chunks = []
        while True:
            data = conn.recv(65536)
            if not data:
                break
            chunks.append(data)
            if sum(len(c) for c in chunks) > 400000:
                break
    except OSError as exc:
        out["error"] = f"{type(exc).__name__}: {exc}"
        return out
    finally:
        conn.close()

    raw = b"".join(chunks)
    if not raw:
        out["error"] = "empty reply (پاسخی نیامد)"
        return out
    head, _, body = raw.partition(b"\r\n\r\n")
    lines = head.decode("latin-1", "replace").split("\r\n")
    out["status_line"] = lines[0] if lines else ""
    headers = {}
    for line in lines[1:]:
        if ":" in line:
            k, v = line.split(":", 1)
            headers[k.strip().lower()] = v.strip()
    out["server"] = headers.get("server", "")
    out["location"] = headers.get("location", "")
    text = body.decode("utf-8", "replace")
    out["bytes"] = len(body)
    out["title"] = _title(text)
    out["cpanel_default_page"] = (
        "defaultwebpage.cgi" in text
        or "Default Web Site Page" in text
        or "img-sys/IP_changed.png" in text
    )
    # نشانهٔ «پارک‌شده»: صفحهٔ فروش/تبلیغ دامنه به‌جای سایت واقعی.
    out["parked"] = any(k in text.lower() for k in ("domain is for sale", "buy this domain"))
    return out


def _title(html: str) -> str:
    low = html.lower()
    start = low.find("<title")
    if start == -1:
        return ""
    open_end = low.find(">", start)
    end = low.find("</title>", open_end)
    if open_end == -1 or end == -1:
        return ""
    return " ".join(html[open_end + 1:end].split())[:120]


def tls_probe(host: str, timeout: float = 10.0) -> dict:
    out: dict = {"host": host, "port": 443}
    ctx = ssl.create_default_context()
    try:
        with socket.create_connection((host, 443), timeout=timeout) as sock:
            with ctx.wrap_socket(sock, server_hostname=host) as tls:
                cert = tls.getpeercert()
                out["ok"] = True
                out["tls_version"] = tls.version()
                out["subject"] = [v for rdn in cert.get("subject", ()) for v in rdn]
                out["issuer"] = [v for rdn in cert.get("issuer", ()) for v in rdn]
                out["not_after"] = cert.get("notAfter")
                out["sans"] = cert.get("subjectAltName", ())
    except ssl.SSLError as exc:
        out["ok"] = False
        out["error"] = f"SSLError: {exc}"
    except OSError as exc:
        out["ok"] = False
        out["error"] = f"{type(exc).__name__}: {exc}"
    return out


def https_probe(host: str, path: str = "/wp-json/", timeout: float = 10.0) -> dict:
    url = f"https://{host}{path}"
    out: dict = {"url": url}
    try:
        status, body, headers = _get(url, timeout=timeout)
        out["status"] = status
        out["content_type"] = headers.get("content-type") or headers.get("Content-Type") or ""
        text = body.decode("utf-8", "replace")
        out["looks_like_wp_rest"] = '"namespaces"' in text or '"routes"' in text
    except urllib.error.HTTPError as exc:
        out["status"] = exc.code
    except (urllib.error.URLError, TimeoutError, OSError) as exc:
        out["error"] = f"{type(exc).__name__}: {exc}"
    return out


def collect(host: str) -> dict:
    report: dict = {"host": host}
    report["dns"] = {
        "A": dns(host, "A"),
        "AAAA": dns(host, "AAAA"),
        "NS": dns(host, "NS"),
        "MX": dns(host, "MX"),
        "TXT": dns(host, "TXT"),
        "www_A": dns(f"www.{host}", "A"),
    }
    report["ports"] = {str(p): tcp(host, p)[1] for p in (80, 443)}
    report["http80"] = http_probe(host)
    report["tls443"] = tls_probe(host)
    report["https_wpjson"] = https_probe(host)
    report["verdict"] = verdict(report)
    return report


def verdict(report: dict) -> dict:
    """تشخیص کوتاه فارسی از روی نشانه‌های سنجیده‌شده."""
    http = report.get("http80", {})
    tls = report.get("tls443", {})
    wp = report.get("https_wpjson", {})
    if wp.get("looks_like_wp_rest"):
        return {"state": "ok", "fa": "سایت سالم پاسخ می‌دهد (wp-json در دسترس است)."}
    if http.get("cpanel_default_page") and not tls.get("ok"):
        return {
            "state": "default_vhost",
            "fa": "دامنه در وب‌سرور (vhost) تعریف نشده یا به پوشهٔ خالی اشاره می‌کند؛ "
                  "پاسخ ۸۰ «Default Web Site Page» سی‌پنل است و ۴۴۳ گواهی ندارد.",
        }
    if http.get("error") and tls.get("error"):
        # اگر هر دو لایه «بی‌صدا» بسته شوند (بدون هیچ پاسخ HTTP و بدون هشدار TLS)،
        # احتمال زیاد مسیر شبکه بسته است، نه سایت. سرور سالم همیشه یا پاسخ می‌دهد
        # یا هشدار TLS می‌فرستد؛ قطعِ کاملِ هر دو نشانهٔ فیلتر مسیر است.
        silent = ("empty reply" in str(http.get("error", ""))
                  and "closed" in str(tls.get("error", "")).lower())
        if silent:
            return {
                "state": "network_blocked",
                "fa": "هر دو لایه بی‌صدا بسته شدند (بدون پاسخ HTTP و بدون هشدار TLS)؛ "
                      "این نشانهٔ بسته‌بودن مسیر شبکه از این محیط است، نه خرابی سایت. "
                      "با یک شبکهٔ دیگر یا از مرورگر خودتان تأیید کنید: "
                      f"https://{report.get('host')}/",
            }
        return {
            "state": "down",
            "fa": "نه وب‌سرور روی ۸۰ پاسخ داد و نه TLS روی ۴۴۳ برقرار شد؛ "
                  "دامنه به سرور اشتباه/خاموش اشاره می‌کند یا پاسخ‌ها در مسیر شبکه بسته می‌شوند.",
        }
    if http.get("status_line", "").startswith("HTTP/") and not wp.get("looks_like_wp_rest"):
        return {
            "state": "no_wp",
            "fa": "وب‌سرور پاسخ می‌دهد ولی وردپرس/مسیر /wp-json/ در دسترس نیست.",
        }
    return {"state": "unknown", "fa": "نشانه‌ها یکدست نیستند؛ خروجی کامل را بررسی کن."}


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="سنجش سلامت دامنه و وب‌سرور (فقط‌خواندنی)")
    parser.add_argument("host", nargs="?", default=DEFAULT_HOST)
    parser.add_argument("--json", action="store_true", help="خروجی JSON کامل")
    args = parser.parse_args(argv)

    report = collect(args.host)
    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
        return 0

    print(f"دامنه: {report['host']}")
    print(f"A:    {report['dns']['A']}")
    print(f"AAAA: {report['dns']['AAAA']}")
    print(f"NS:   {report['dns']['NS']}")
    print(f"MX:   {report['dns']['MX']}")
    print(f"www:  {report['dns']['www_A']}")
    print(f"درگاه‌ها: {report['ports']}")
    http = report["http80"]
    if http.get("error"):
        print(f"HTTP :80 → خطا: {http['error']}")
    else:
        print(f"HTTP :80 → {http.get('status_line')} | server={http.get('server')} "
              f"| title={http.get('title')!r} | default_page={http.get('cpanel_default_page')}")
    tls = report["tls443"]
    if tls.get("ok"):
        print(f"TLS  :443 → {tls['tls_version']} | صادرکننده: {tls['issuer']} "
              f"| تا: {tls['not_after']} | SAN: {[v for v in tls['sans']][:4]}")
    else:
        print(f"TLS  :443 → خطا: {tls.get('error')}")
    wp = report["https_wpjson"]
    print(f"/wp-json/ → {wp.get('status') or wp.get('error')} "
          f"| rest={wp.get('looks_like_wp_rest', False)}")
    print(f"تشخیص: {report['verdict']['state']} — {report['verdict']['fa']}")
    if report["verdict"]["state"] == "network_blocked":
        print("یادآوری: کد خروج ۲ = «از این شبکه قابل سنجش نیست»؛ سایت ممکن است سالم باشد.")
    # کد خروج برای اتوماسیون: ۰ سالم، ۱ ناسالم، ۲ از این شبکه سنجش‌ناپذیر
    state = report["verdict"]["state"]
    if state == "ok":
        return 0
    return 2 if state == "network_blocked" else 1


if __name__ == "__main__":
    sys.exit(main())
