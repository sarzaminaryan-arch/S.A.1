import importlib.util
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "audit_wxr_content.py"
SPEC = importlib.util.spec_from_file_location("audit_wxr_content", SCRIPT)
AUDIT = importlib.util.module_from_spec(SPEC)
assert SPEC and SPEC.loader
SPEC.loader.exec_module(AUDIT)


def wxr_item(post_id, slug, body, seo_title="", seo_description="", status="publish"):
    title_meta = f"<wp:postmeta><wp:meta_key>sa_seo_title</wp:meta_key><wp:meta_value><![CDATA[{seo_title}]]></wp:meta_value></wp:postmeta>" if seo_title else ""
    description_meta = f"<wp:postmeta><wp:meta_key>sa_seo_description</wp:meta_key><wp:meta_value><![CDATA[{seo_description}]]></wp:meta_value></wp:postmeta>" if seo_description else ""
    faq_meta = """<wp:postmeta><wp:meta_key>sa_faq</wp:meta_key><wp:meta_value><![CDATA[[{"q":"چطور برسیم؟","a":"از مسیر اصلی."}]]]></wp:meta_value></wp:postmeta>"""
    sources_meta = """<wp:postmeta><wp:meta_key>sa_sources</wp:meta_key><wp:meta_value><![CDATA[نقشه | مرجع | https://maps.example.test/ | 2026-10-01]]></wp:meta_value></wp:postmeta>"""
    return f"""
    <item>
      <title>عنوان {post_id}</title>
      <link>https://sarzaminaryan.ir/city/{slug}/</link>
      <content:encoded><![CDATA[{body}]]></content:encoded>
      <wp:post_id>{post_id}</wp:post_id>
      <wp:post_name>{slug}</wp:post_name>
      <wp:post_type>city</wp:post_type>
      <wp:status>{status}</wp:status>
      {title_meta}{description_meta}{faq_meta}{sources_meta}
    </item>
    """


class WxrAuditTest(unittest.TestCase):
    def audit(self, items):
        xml = f"""<?xml version="1.0" encoding="UTF-8"?>
        <rss xmlns:content="http://purl.org/rss/1.0/modules/content/"
             xmlns:wp="http://wordpress.org/export/1.2/">
          <channel>{''.join(items)}</channel>
        </rss>"""
        with tempfile.TemporaryDirectory() as temp_dir:
            path = Path(temp_dir) / "sample.xml"
            path.write_text(xml, encoding="utf-8")
            return AUDIT.audit_wxr(path, "https://sarzaminaryan.ir")

    def test_flags_content_hygiene_and_counts_wxr_meta(self):
        body = (
            "<h1>شهرستان نمونه</h1><p>یادداشت برای نویسنده: TODO</p>"
            "<a href=\"/city/other/\"></a>"
            "<a href=\"https://official.example.test/info\">مرجع رسمی</a>"
            "<img src=\"/photo.jpg\">"
        )
        report = self.audit([wxr_item(1, "sample", body, "راهنمای نمونه", "توضیح نمونه")])
        row = report["items"][0]
        self.assertEqual(report["summary"]["items"], 1)
        self.assertEqual(row["post_type"], "city")
        self.assertEqual(row["h1_count"], 1)
        self.assertEqual(row["faq_count"], 1)
        self.assertEqual(row["source_count"], 1)
        self.assertEqual(row["internal_links"], 1)
        self.assertEqual(row["external_links"], 1)
        self.assertEqual(row["empty_anchors"], 1)
        self.assertEqual(row["images_missing_alt"], 1)
        self.assertTrue(row["editorial_residue"])
        self.assertIn("h1_inside_editor_content", row["flags"])
        self.assertIn("possible_editorial_placeholder", row["flags"])

    def test_reports_duplicate_seo_titles_without_emitting_title_text(self):
        body = "<p>متن واقعی و مفید برای خواننده.</p>"
        report = self.audit(
            [
                wxr_item(1, "first", body, "عنوان تکراری", "توضیح اول"),
                wxr_item(2, "second", body, "عنوان تکراری", "توضیح دوم"),
            ]
        )
        duplicate = report["summary"]["duplicate_seo_titles"]
        self.assertEqual(duplicate, [{"slugs": ["first", "second"], "occurrences": 2}])
        self.assertNotIn("seo_title", report["items"][0])
        self.assertNotIn("عنوان تکراری", str(report))

    def test_allowed_transparency_markers_are_not_editorial_residue(self):
        body = "<p>ساعت بازدید [نیازمند بررسی] است؛ [منبع لازم] برای قیمت ثبت شده.</p>"
        report = self.audit([wxr_item(1, "transparent", body)])
        self.assertFalse(report["items"][0]["editorial_residue"])

    def test_duplicate_slugs_are_scoped_to_post_type(self):
        report = self.audit(
            [
                wxr_item(1, "same-slug", "<p>اول</p>"),
                wxr_item(2, "same-slug", "<p>دوم</p>"),
            ]
        )
        self.assertEqual(
            report["summary"]["duplicate_slugs"],
            [{"post_type": "city", "slug": "same-slug", "occurrences": 2}],
        )


if __name__ == "__main__":
    unittest.main()
