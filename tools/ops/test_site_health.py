#!/usr/bin/env python3
"""Offline tests for tools/ops/site_health.py (verdict logic — no network).

The live site can be down or unreachable from CI, so the *decision* logic is
what gets locked here: a cPanel "Default Web Site Page" plus a TLS handshake
that dies must be classified as a missing vhost, never as "unknown".
"""
from __future__ import annotations

import importlib.util
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("site_health", ROOT / "tools/ops/site_health.py")
site_health = importlib.util.module_from_spec(SPEC)  # type: ignore[arg-type]
assert SPEC and SPEC.loader
SPEC.loader.exec_module(site_health)  # type: ignore[union-attr]


class VerdictTests(unittest.TestCase):
    def test_cpanel_default_page_without_certificate_is_missing_vhost(self):
        report = {
            "http80": {"status_line": "HTTP/1.1 200 OK", "cpanel_default_page": True},
            "tls443": {"ok": False, "error": "SSLError: TLS/SSL connection has been closed (EOF)"},
            "https_wpjson": {"error": "URLError: EOF"},
        }
        self.assertEqual(site_health.verdict(report)["state"], "default_vhost")

    def test_healthy_wordpress_rest_wins(self):
        report = {
            "http80": {"status_line": "HTTP/1.1 200 OK"},
            "tls443": {"ok": True},
            "https_wpjson": {"status": 200, "looks_like_wp_rest": True},
        }
        self.assertEqual(site_health.verdict(report)["state"], "ok")

    def test_silent_host_is_down_not_unknown(self):
        report = {
            "http80": {"error": "empty reply (پاسخی نیامد)"},
            "tls443": {"ok": False, "error": "SSLError: EOF"},
            "https_wpjson": {"error": "URLError: EOF"},
        }
        self.assertEqual(site_health.verdict(report)["state"], "down")

    def test_web_server_without_wordpress(self):
        report = {
            "http80": {"status_line": "HTTP/1.1 404 Not Found"},
            "tls443": {"ok": True},
            "https_wpjson": {"status": 404},
        }
        self.assertEqual(site_health.verdict(report)["state"], "no_wp")

    def test_title_extraction_tolerates_attributes_and_whitespace(self):
        html = "<html><head><title lang='fa'>\n  سرزمین  آریان \n</title></head>"
        self.assertEqual(site_health._title(html), "سرزمین آریان")

    def test_title_missing_returns_empty(self):
        self.assertEqual(site_health._title("<html><body>no head</body></html>"), "")


if __name__ == "__main__":
    unittest.main()
