#!/usr/bin/env python3
"""Owner-run, GET-only checker for the offline URL audit CSV.

This script is intentionally not run during the repository-only audit. It accepts
only sarzaminaryan.ir URLs, sends no cookies, waits 1.5 seconds between GETs,
uses a 15-second request timeout, and does not follow redirects to other hosts.
Unresolved W_* tokens and aggregate placeholders are recorded as skipped.

Dependency: install the Python package `requests` before owner-run execution.

Usage:
    python tools/check_urls.py reports/url-audit-offline.csv
    python tools/check_urls.py reports/url-audit-offline.csv --output reports/url-audit-results.csv
"""

from __future__ import annotations

import argparse
import csv
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin, urlparse

import requests

ORIGIN = "https://sarzaminaryan.ir/"
ALLOWED_HOSTS = {"sarzaminaryan.ir", "www.sarzaminaryan.ir"}
TIMEOUT_SECONDS = 15
DELAY_SECONDS = 1.5
MAX_REDIRECTS = 10
RESULT_COLUMNS = ["status_http", "final_url", "redirect_chain", "checked_at_utc", "check_error"]


def normalize_url(raw: str) -> str | None:
    """Resolve same-origin root-relative paths; reject unknown tokens/external URLs."""
    value = (raw or "").strip()
    if not value or value.startswith("W_") or value.startswith("UNKNOWN"):
        return None
    if value.startswith("/"):
        value = urljoin(ORIGIN, value)
    parsed = urlparse(value)
    if parsed.scheme not in {"http", "https"} or parsed.hostname not in ALLOWED_HOSTS:
        return None
    return value


def check_url(url: str, last_request_time: list[float]) -> dict[str, str]:
    """Perform GET requests only, manually following same-site redirects."""
    current = url
    chain: list[str] = []
    checked_at = datetime.now(timezone.utc).isoformat()

    for redirect_count in range(MAX_REDIRECTS + 1):
        elapsed = time.monotonic() - last_request_time[0]
        if last_request_time[0] and elapsed < DELAY_SECONDS:
            time.sleep(DELAY_SECONDS - elapsed)
        try:
            response = requests.get(
                current,
                headers={"User-Agent": "SarzaminAryan-OfflineURLAudit/1.0"},
                cookies={},
                timeout=TIMEOUT_SECONDS,
                allow_redirects=False,
            )
            last_request_time[0] = time.monotonic()
        except requests.RequestException as exc:
            return {
                "status_http": "ERROR",
                "final_url": current,
                "redirect_chain": " -> ".join(chain),
                "checked_at_utc": checked_at,
                "check_error": f"{type(exc).__name__}: {exc}",
            }

        status = response.status_code
        location = response.headers.get("Location")
        if status not in {301, 302, 303, 307, 308} or not location:
            return {
                "status_http": str(status),
                "final_url": current,
                "redirect_chain": " -> ".join(chain),
                "checked_at_utc": checked_at,
                "check_error": "",
            }

        next_url = urljoin(current, location)
        chain.append(f"{status} {current} -> {next_url}")
        next_host = urlparse(next_url).hostname
        if next_host not in ALLOWED_HOSTS:
            return {
                "status_http": str(status),
                "final_url": next_url,
                "redirect_chain": " -> ".join(chain),
                "checked_at_utc": checked_at,
                "check_error": "External redirect not followed; no request sent to the other host.",
            }
        if redirect_count == MAX_REDIRECTS:
            return {
                "status_http": str(status),
                "final_url": next_url,
                "redirect_chain": " -> ".join(chain),
                "checked_at_utc": checked_at,
                "check_error": f"Stopped after {MAX_REDIRECTS} redirects.",
            }
        current = next_url

    raise AssertionError("unreachable")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("input_csv", type=Path, help="Offline audit CSV; never fetched itself")
    parser.add_argument("--output", type=Path, help="Result CSV (default: <input>-checked.csv)")
    args = parser.parse_args()
    output_path = args.output or args.input_csv.with_name(f"{args.input_csv.stem}-checked.csv")

    with args.input_csv.open("r", encoding="utf-8-sig", newline="") as src:
        reader = csv.DictReader(src)
        if not reader.fieldnames or "URL" not in reader.fieldnames:
            parser.error("input CSV must contain a 'URL' column")
        fieldnames = list(reader.fieldnames)
        for column in RESULT_COLUMNS:
            if column not in fieldnames:
                fieldnames.append(column)
        rows = list(reader)

    cache: dict[str, dict[str, str]] = {}
    last_request_time = [0.0]
    for row in rows:
        url = normalize_url(row.get("URL", ""))
        if url is None:
            result = {
                "status_http": "SKIPPED",
                "final_url": "",
                "redirect_chain": "",
                "checked_at_utc": datetime.now(timezone.utc).isoformat(),
                "check_error": "URL is unresolved, non-HTTP, aggregate-only, or outside the allowed site host; no request sent.",
            }
        elif url in cache:
            result = cache[url]
        else:
            result = check_url(url, last_request_time)
            cache[url] = result
        row.update(result)

    output_path.parent.mkdir(parents=True, exist_ok=True)
    with output_path.open("w", encoding="utf-8-sig", newline="") as dst:
        writer = csv.DictWriter(dst, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)
    print(f"Wrote {len(rows)} rows to {output_path}; no cookies; GET only; timeout={TIMEOUT_SECONDS}s; delay={DELAY_SECONDS}s.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
