import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "wxr_audit_rollup.py"
SPEC = importlib.util.spec_from_file_location("wxr_audit_rollup", SCRIPT)
ROLLUP = importlib.util.module_from_spec(SPEC)
assert SPEC and SPEC.loader
SPEC.loader.exec_module(ROLLUP)


def report(file_name, items, duplicates=None):
    """یک گزارش ممیزی ساختگی با همان شکل خروجی audit_wxr_content.py."""
    summary = {
        "file": file_name,
        "items": len(items),
        "duplicate_seo_titles": duplicates or [],
        "duplicate_seo_descriptions": duplicates or [],
        "duplicate_slugs": [],
        "external_domain_histogram": {},
        "posts_over_35pct_single_domain": 0,
    }
    return {"summary": summary, "items": items}


def row(**overrides):
    base = {
        "post_id": "1",
        "post_type": "city",
        "status": "publish",
        "slug": "sample-city",
        "url": "https://sarzaminaryan.ir/city/sample-city/",
        "word_count": 1200,
        "h1_count": 0,
        "faq_count": 8,
        "source_count": 5,
        "internal_links": 20,
        "external_links": 12,
        "image_count": 0,
        "images_missing_alt": 0,
        "external_domains": 2,
        "top_external_domain": "en.wikipedia.org",
        "top_external_domain_links": 4,
        "empty_anchors": 0,
        "editorial_residue": False,
        "flags": [],
    }
    base.update(overrides)
    return base


class RollupTest(unittest.TestCase):
    def build(self, rows):
        return ROLLUP.build_markdown([report("sample.xml", rows)], None, ROLLUP.issue_rows([report("sample.xml", rows)]))

    def test_clean_row_has_no_issues(self):
        self.assertEqual(ROLLUP.issue_rows([report("sample.xml", [row()])]), [])

    def test_body_h1_and_residue_are_p0_for_published(self):
        issues = ROLLUP.issue_rows(
            [report("sample.xml", [row(h1_count=1, editorial_residue=True)])]
        )
        self.assertEqual(len(issues), 1)
        self.assertEqual(issues[0]["risk"], "P0")
        self.assertEqual(issues[0]["issues"], "body_h1;editorial_residue")

    def test_draft_rows_are_downgraded_to_p2(self):
        issues = ROLLUP.issue_rows([report("sample.xml", [row(status="draft", h1_count=1)])])
        self.assertEqual(issues[0]["risk"], "P2")

    def test_duplicate_seo_and_thin_city_and_links(self):
        duplicates = [{"slugs": ["a-city", "b-city"], "occurrences": 2}]
        issues = ROLLUP.issue_rows(
            [
                report(
                    "sample.xml",
                    [
                        row(slug="a-city", word_count=500, internal_links=2),
                        row(slug="b-city", word_count=500, internal_links=2),
                    ],
                    duplicates=duplicates,
                )
            ]
        )
        issue_set = {i["issues"] for i in issues}
        self.assertEqual(len(issues), 2)
        self.assertTrue(any("duplicate_seo" in s and "thin_content" in s and "few_internal_links" in s for s in issue_set))

    def test_markdown_summarises_and_never_contains_seo_slots(self):
        markdown = self.build([row(h1_count=1, slug="sample-city")])
        self.assertIn("ممیزی corpus سه خروجی WXR", markdown)
        self.assertIn("P0", markdown)
        self.assertIn("`sample-city`", markdown)
        self.assertNotIn("seo_title", markdown)

    def test_csv_columns_and_risks(self):
        issues = ROLLUP.issue_rows([report("sample.xml", [row(h1_count=1)])])
        with tempfile.TemporaryDirectory() as temp_dir:
            path = Path(temp_dir) / "issues.csv"
            ROLLUP.write_issues_csv(issues, path)
            content = path.read_text(encoding="utf-8-sig")
        self.assertIn("risk", content.splitlines()[0])
        self.assertIn("P0", content)
        self.assertIn("body_h1", content)

    def test_aggregate_groups_by_file_type_and_status(self):
        rows = [row(), row(slug="second"), row(post_type="page", status="draft", word_count=10)]
        table = ROLLUP.aggregate([report("sample.xml", rows)])
        keys = {(t["post_type"], t["status"]) for t in table}
        self.assertEqual(keys, {("city", "publish"), ("page", "draft")})
        cities = next(t for t in table if t["post_type"] == "city")
        self.assertEqual(cities["items"], 2)
        self.assertEqual(cities["internal_links"], 40)


if __name__ == "__main__":
    unittest.main()
