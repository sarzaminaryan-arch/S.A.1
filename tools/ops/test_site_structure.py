"""تست‌های آفلاین ابزار تحلیل ساختار دامپ (`tools/ops/site_structure.py`).

هیچ اتصال شبکه‌ای و هیچ پایگاه‌دادهٔ واقعی لازم نیست: یک دامپ کوچک نمونه
در حافظه/فایل موقت ساخته می‌شود و صحت تجزیه و شمارش بررسی می‌شود.
"""
from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TOOL = ROOT / "tools/ops/site_structure.py"

DUMP = """-- MySQL dump نمونه
CREATE TABLE `IR_posts` (`ID` bigint, `post_content` longtext, `post_type` varchar(20), `post_status` varchar(20));
INSERT INTO `IR_posts` (`ID`,`post_content`,`post_type`,`post_status`) VALUES (1,'<p>یک دو سه چهار پنج شش هفت</p>','city','publish');
INSERT INTO `IR_posts` (`ID`,`post_content`,`post_type`,`post_status`) VALUES (2,'<p>کوتاه</p>','city','draft');
INSERT INTO `IR_posts` (`ID`,`post_content`,`post_type`,`post_status`) VALUES (3,'<p>a, b (c) d\\'e</p>','province','publish');
INSERT INTO `IR_posts` (`ID`,`post_content`,`post_type`,`post_status`) VALUES (4,'<p>x</p>','province','publish');
CREATE TABLE `IR_postmeta` (`meta_id` bigint, `post_id` bigint, `meta_key` varchar(50), `meta_value` longtext);
INSERT INTO `IR_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (1,1,'sa_seo_title','عنوان');
INSERT INTO `IR_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (2,1,'_thumbnail_id','att-99');
INSERT INTO `IR_postmeta` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES (3,3,'sa_seo_title','عنوان استان');
CREATE TABLE `IR_terms` (`term_id` bigint, `name` varchar(50), `slug` varchar(50));
INSERT INTO `IR_terms` (`term_id`,`name`,`slug`) VALUES (1,'فارس','fars');
CREATE TABLE `IR_term_taxonomy` (`term_taxonomy_id` bigint, `term_id` bigint, `taxonomy` varchar(32), `count` bigint);
INSERT INTO `IR_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`count`) VALUES (7,1,'province_tax',2);
CREATE TABLE `IR_term_relationships` (`object_id` bigint, `term_taxonomy_id` bigint);
INSERT INTO `IR_term_relationships` (`object_id`,`term_taxonomy_id`) VALUES (1,7);
CREATE TABLE `IR_options` (`option_id` bigint, `option_name` varchar(100), `option_value` longtext);
INSERT INTO `IR_options` (`option_id`,`option_name`,`option_value`) VALUES (1,'siteurl','https://example.test');
INSERT INTO `IR_options` (`option_id`,`option_name`,`option_value`) VALUES (2,'sa_child_version','9.9.9');
"""


def _run(tmp_path: Path) -> dict:
    dump = tmp_path / "sample.sql"
    dump.write_text(DUMP, encoding="utf-8")
    out = tmp_path / "out"
    proc = subprocess.run(
        [sys.executable, str(TOOL), "--sql", str(dump), "--out-dir", str(out),
         "--workdir", str(tmp_path / "work")],
        capture_output=True, text=True, timeout=120,
    )
    assert proc.returncode == 0, proc.stderr
    assert (out / "SITE-STRUCTURE.md").exists(), "خروجی Markdown ساخته نشد"
    return json.loads((out / "SITE-STRUCTURE.json").read_text(encoding="utf-8"))


def test_counts_and_budgets(tmp_path):
    data = _run(tmp_path)
    assert data["posts_by_type_status"]["city|publish"] == 1
    assert data["posts_by_type_status"]["city|draft"] == 1
    assert data["budgets"]["city"]["count"] == 1
    assert data["budgets"]["city"]["avg_words"] == 7
    assert data["budgets"]["city"]["meta_coverage"]["_thumbnail_id"] == 1


def test_quoted_values_with_commas_and_parens(tmp_path):
    """مقدارهای شامل `,` و `(` نباید ردیف‌ها را بشکنند."""
    data = _run(tmp_path)
    assert data["budgets"]["province"]["count"] == 2


def test_options_and_province_distribution(tmp_path):
    data = _run(tmp_path)
    assert data["key_options"]["siteurl"] == "https://example.test"
    assert data["key_options"]["sa_child_version"] == "9.9.9"
    assert data["cities_per_province"] == [{"province": "فارس", "slug": "fars", "cities": 1}]


def test_markdown_is_persian_and_mentions_source(tmp_path):
    _run(tmp_path)
    md = (tmp_path / "out" / "SITE-STRUCTURE.md").read_text(encoding="utf-8")
    assert "ساختار فعلی سایت" in md
    assert "sample.sql" in md
