#!/usr/bin/env python3
"""Offline regression guard for the Tehran cluster master-template audit.

Owner order (2026-11-25): county articles must follow CLUSTER-SYSTEM-MASTER.md
and internal links must follow the site's 4-layer rules. This test locks the
normaliser's invariants and checks that the shipped v6 drafts stay clean.
"""
from __future__ import annotations

import json
import re
import sys
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))
import cluster_ops as co  # noqa: E402


class ClusterLinkTests(unittest.TestCase):
    def test_normalise_tags_keeps_one_link_per_destination(self):
        html = ('<a href="/city/tehran/" target="_self">تهران</a> و '
                '<a href="/city/tehran/" target="_blank">شهر تهران</a> و '
                '<a href="/province/tehran/" target="_self">استان</a>')
        out, touched = co.normalise_tags(html)
        self.assertEqual(co.internal_hrefs(out), ["/city/tehran/", "/province/tehran/"])
        self.assertIn("شهر تهران", out, "duplicate link must be unwrapped, not deleted")
        self.assertNotIn('target="_blank"', out)
        self.assertTrue(touched)

    def test_join_links_uses_self_target(self):
        tag = co.join_links([("/city/tehran/", "تهران"), ("/attraction/milad-tower/", "برج میلاد")])
        self.assertEqual(tag.count('target="_self"'), 2)
        self.assertNotIn("_blank", tag)

    def test_zero_branch_city_targets_are_persian_and_complete(self):
        branches = co.branches_by_city(co.load_manifest())
        meta = co.read_pair(co.V6_CITY, "eslamshahr-219")[1]
        targets = co.city_targets("eslamshahr", branches,
                                  co.province_county_slugs(co.read_pair(co.V6_HUB, "tehran-43")[0]), meta)
        hrefs = [h for h, _ in targets]
        self.assertIn(co.PROVINCE, hrefs)
        self.assertGreaterEqual(len([h for h in hrefs if h.startswith("/attraction/")]), 3)
        for _, text in targets:
            self.assertFalse(re.search(r"[A-Za-z]", text), f"Latin anchor: {text}")

    def test_attraction_targets_have_city_and_siblings(self):
        branches = co.branches_by_city(co.load_manifest())
        targets = co.att_targets("milad-tower", "tehran", branches)
        hrefs = [h for h, _ in targets]
        self.assertIn("/city/tehran/", hrefs)
        self.assertIn(co.PROVINCE, hrefs)
        self.assertGreaterEqual(len([h for h in hrefs if h.startswith("/attraction/")]), 2)

    def test_hub_targets_cover_every_branch(self):
        manifest = co.load_manifest()
        hub_html = co.read_pair(co.V6_HUB, "tehran-43")[0]
        targets = co.hub_targets(manifest, co.province_county_slugs(hub_html))
        hrefs = {h for h, _ in targets}
        for row in manifest:
            self.assertIn(co.att_link(row["slug"]), hrefs)
        self.assertGreaterEqual(len([h for h in hrefs if h.startswith("/city/")]), 16)

    def test_add_missing_links_limits_links_and_rotates_wording(self):
        html = ("<!-- sa:sec:stay -->\n<!-- /sa:sec:stay -->\n"
                "<!-- sa:sec:map -->\n<!-- /sa:sec:map -->\n"
                "<!-- sa:sec:history -->\n<!-- /sa:sec:history -->\n")
        targets = [(co.att_link(f"a{i}"), f"جاذبه {i}") for i in range(9)]
        out, added = co.add_missing_links(html, targets,
                                          ["stay", "map", "history"], "sec",
                                          co.CITY_SENTENCES)
        self.assertEqual(sorted(added), sorted(h for h, _ in targets))
        blocks = re.findall(r"<p>(.*?)</p>", out, re.S)
        self.assertEqual(len(blocks), 3)
        for block in blocks:
            self.assertLessEqual(block.count("href="), 3)
        self.assertEqual(len(set(blocks)), 3, "sentence templates must rotate")

    def test_seo_fix_lengths_and_clean_prose(self):
        for slug, (title, desc) in co.SEO_FIXES.items():
            self.assertLessEqual(len(title), 60, slug)
            self.assertTrue(140 <= len(desc) <= 155, f"{slug}: {len(desc)} chars")
        for slug, paras in co.BODY_ADDITIONS.items():
            for para in paras:
                for banned in ("باید", "راستش", "در حال ساخت", "بازبینی", "کلیک کنید"):
                    self.assertNotIn(banned, para, f"{slug}: banned wording {banned}")


class RazaviKhorasanTests(unittest.TestCase):
    """Province 2 (`#2 مشهد` 7 + `#34 نیشابور` 3 + hub 50) — owner order 2026-11-25."""

    def setUp(self):
        self.manifest = co.load_rx_manifest()
        self.branches = co.rx_branches(self.manifest)

    def test_manifest_maps_every_branch_to_a_cluster(self):
        self.assertEqual(len(self.manifest), 10)
        self.assertEqual(len(self.branches["mashhad"]), 7)
        self.assertEqual(len(self.branches["neyshabur"]), 3)
        for row in self.manifest:
            self.assertIn(row["city_slug"], ("mashhad", "neyshabur"))

    def test_every_attraction_carries_city_and_province_ids(self):
        for row in self.manifest:
            meta = co.read_pair(co.RX_V6 / "attractions", row["slug"])[1]
            self.assertEqual(meta["sa_city_id"], str(row["county_id"]), row["slug"])
            self.assertEqual(meta["sa_province_id"], "50", row["slug"])

    def test_attraction_targets_have_mother_city_province_and_siblings(self):
        targets = co.rx_att_targets("khayyam-tomb", "neyshabur", self.branches)
        hrefs = [h for h, _ in targets]
        self.assertIn("/city/neyshabur/", hrefs)
        self.assertIn(co.RX_PROVINCE, hrefs)
        self.assertGreaterEqual(len([h for h in hrefs if h.startswith("/attraction/")]), 2)
        for _, text in targets:
            self.assertFalse(re.search(r"[A-Za-z]", text), f"Latin anchor: {text}")

    def test_city_targets_cover_branches_and_neighbour_hubs(self):
        meta = co.read_pair(co.RX_V6 / "county-hubs", "mashhad-63")[1]
        hub_html = co.read_pair(co.RX_V6 / "province-hub", "razavi-khorasan-50")[0]
        counties = co.province_county_slugs(hub_html)
        targets = co.rx_city_targets("mashhad", self.branches, counties, meta)
        hrefs = [h for h, _ in targets]
        self.assertIn(co.RX_PROVINCE, hrefs)
        for slug in self.branches["mashhad"]:
            self.assertIn(co.att_link(slug), hrefs)
        self.assertIn("/city/neyshabur/", hrefs)

    def test_hub_targets_cover_both_counties_and_all_branches(self):
        hub_html = co.read_pair(co.RX_V6 / "province-hub", "razavi-khorasan-50")[0]
        hrefs = {h for h, _ in co.rx_hub_targets(self.manifest,
                                                 co.province_county_slugs(hub_html))}
        self.assertIn("/city/mashhad/", hrefs)
        self.assertIn("/city/neyshabur/", hrefs)
        for row in self.manifest:
            self.assertIn(co.att_link(row["slug"]), hrefs)

    def test_shipped_razavi_v6_is_clean_and_complete(self):
        manifest = json.loads((co.RX_V6 / "manifest.json").read_text(encoding="utf-8"))
        self.assertEqual(len(manifest["county_hubs"]), 2)
        self.assertEqual(len(manifest["attractions"]), 10)
        self.assertEqual(manifest["province_hub"]["wp_id"], 50)
        report = co.audit(co.rx_config("v6"))
        self.assertEqual(report["summary"]["files"], 13)
        self.assertEqual(report["summary"]["clean"], 13,
                         str([(r["stem"], r["problems"]) for r in report["rows"] if r["problems"]]))
        self.assertEqual(report["summary"]["warnings"], 0,
                         str([(r["stem"], r["warnings"]) for r in report["rows"] if r["warnings"]]))

    def test_razavi_baseline_had_issues_so_the_audit_is_not_vacuous(self):
        report = co.audit(co.rx_config("baseline"))
        self.assertGreater(report["summary"]["with_issues"], 0)

    def test_neighbours_exist_in_the_theme_county_table(self):
        import check_template as ct  # noqa: E402  (replace_src on sys.path)
        slugs = ct.county_slugs()
        for stem in ("mashhad-63", "neyshabur-64"):
            meta = co.read_pair(co.RX_V6 / "county-hubs", stem)[1]
            for line in str(meta["sa_cty_neighbors"]).splitlines():
                slug = line.split(" - ")[0].strip()
                self.assertIn(slug, slugs, f"{stem}: unknown neighbour {slug}")


class ShippedV6DraftTests(unittest.TestCase):
    def test_tehran_v6_manifest_is_complete(self):
        manifest = json.loads((co.V6 / "manifest.json").read_text(encoding="utf-8"))
        self.assertEqual(len(manifest["county_hubs"]), 8)
        self.assertEqual(len(manifest["attractions"]), 18)
        self.assertIn("province_hub", manifest)

    def test_every_v6_file_passes_the_master_audit(self):
        report = co.audit(co.config_for("v6", co.load_manifest()))
        self.assertEqual(report["summary"]["files"], 27)
        self.assertEqual(report["summary"]["clean"], 27,
                         str([(r["stem"], r["problems"]) for r in report["rows"] if r["problems"]]))

    def test_baseline_had_issues_so_the_audit_is_not_vacuous(self):
        report = co.audit(co.config_for("baseline", co.load_manifest()))
        self.assertGreater(report["summary"]["with_issues"], 0)
        self.assertGreater(report["summary"]["issues"], 0)


if __name__ == "__main__":
    unittest.main()
