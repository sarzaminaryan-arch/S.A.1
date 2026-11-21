#!/usr/bin/env python3
"""Offline regression tests for deterministic sa_sources URL recovery & remediation payloads."""
import json
import sys
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))

import build_audit_remediation as bar  # noqa: E402
import fa_lint as fl  # noqa: E402


class AuditRemediationTests(unittest.TestCase):
    def test_skeleton_wiki_and_news_recovery_never_guesses(self):
        local_pool = {
            "https://fa.wikipedia.org/wiki/_": {
                "https://fa.wikipedia.org/wiki/%D8%A7%D8%B3%D8%AA%D8%A7%D9%86_%D8%A7%D9%84%D8%A8%D8%B1%D8%B2",
                "https://fa.wikipedia.org/wiki/%D8%B3%D8%AF_%D8%A7%D9%85%DB%8C%D8%B1%DA%A9%D8%A8%DB%8C%D8%B1",
            }
        }
        raw = (
            "سد امیرکبیر — سال ساخت | ویکی‌پدیای فارسی | https://fa.wikipedia.org/wiki/_ | ۱۴۰۵/۰۷/۰۴\n"
            "تپه ازبکی | خبرگزاری مهر | https://www.mehrnews.com/news/4558602/------------ | ۱۴۰۵/۰۷/۰۴\n"
            "برج گنبد قابوس؛ ثبت جهانی | ویکی‌پدیای فارسی | https://fa.wikipedia.org/wiki/__ | ۱۴۰۵/۰۷/۰۴\n"
            "راهنمای خرید | وبلاگ متفرقه | https://example.ir/blog/---/ | ۱۴۰۵/۰۷/۰۴"
        )
        repaired, pairs, unrecovered = bar.repair_sources_text(raw, local_pool, {})
        self.assertEqual(len(pairs), 3)
        self.assertIn("https://fa.wikipedia.org/wiki/%D8%B3%D8%AF_%D8%A7%D9%85%DB%8C%D8%B1%DA%A9%D8%A8%DB%8C%D8%B1", repaired)
        self.assertIn("https://www.mehrnews.com/news/4558602", repaired)
        self.assertIn(
            "https://fa.wikipedia.org/wiki/%D8%A8%D8%B1%D8%AC_%DA%AF%D9%86%D8%A8%D8%AF_%DA%AF%D8%A7%D8%A8%D9%88%D8%B3"
            if False
            else "https://fa.wikipedia.org/wiki/%D8%A8%D8%B1%D8%AC_%DA%AF%D9%86%D8%A8%D8%AF_%D9%82%D8%A7%D8%A8%D9%88%D8%B3",
            repaired,
        )
        # Third-party blog URL without exact match must NOT be guessed:
        self.assertIn("https://example.ir/blog/---/", repaired)
        self.assertEqual(len(unrecovered), 1)
        self.assertEqual(fl.scan_meta({"sa_sources": repaired}), [])

    def test_generated_remediation_outbox_passes_fa_lint(self):
        for meta_file in sorted((REPO / "tools/wp-agent/outbox/20261121-coords-dorud").glob("*.meta.json")):
            data = json.loads(meta_file.read_text(encoding="utf-8"))
            self.assertEqual(fl.scan_meta(data), [], f"fa_lint failed on {meta_file.name}")
        for meta_file in sorted((REPO / "tools/wp-agent/outbox/20261121-sources-repair").glob("*.meta.json")):
            data = json.loads(meta_file.read_text(encoding="utf-8"))
            self.assertEqual(fl.scan_meta(data), [], f"fa_lint failed on {meta_file.name}")
        for html_file in sorted((REPO / "tools/wp-agent/outbox/20261121-editorial-pilot").glob("*.html")):
            html = html_file.read_text(encoding="utf-8")
            self.assertEqual(fl.scan(html), [], f"fa_lint failed on {html_file.name}")
            self.assertTrue(bar.html_tags_balanced(html), f"unbalanced tags in {html_file.name}")


if __name__ == "__main__":
    unittest.main()
