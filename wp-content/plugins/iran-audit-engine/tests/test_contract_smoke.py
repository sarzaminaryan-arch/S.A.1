#!/usr/bin/env python3
"""Dependency-free smoke checks for the local audit-plugin contract artifacts.

These checks do not replace WordPress integration, REST permission, SQL, staging,
or human Golden Set tests. Run with: python3 tests/test_contract_smoke.py
"""

from __future__ import annotations

import hashlib
import json
import re
import unittest
from pathlib import Path


ENGINE = Path(__file__).resolve().parents[1]
PLUGIN_ROOT = ENGINE.parent
RULES_FILE = ENGINE / "data" / "rules.json"


class AuditPluginContractSmoke(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.rules = json.loads(RULES_FILE.read_text(encoding="utf-8"))
        cls.evaluators = (ENGINE / "includes" / "class-iaae-evaluators.php").read_text(encoding="utf-8")
        cls.rest = (ENGINE / "includes" / "class-iaae-rest.php").read_text(encoding="utf-8")
        cls.jobs = (ENGINE / "includes" / "class-iaae-jobs.php").read_text(encoding="utf-8")
        cls.auditor = (ENGINE / "includes" / "class-iaae-auditor.php").read_text(encoding="utf-8")
        cls.profile_mapper = (ENGINE / "includes" / "class-iaae-profile-mapper.php").read_text(encoding="utf-8")
        cls.admin = (ENGINE / "includes" / "class-iaae-admin.php").read_text(encoding="utf-8")
        cls.database = (ENGINE / "includes" / "class-iaae-database.php").read_text(encoding="utf-8")
        cls.dashboard = (PLUGIN_ROOT / "iran-audit-dashboard" / "assets" / "dashboard.js").read_text(encoding="utf-8")

    def test_approved_rule_snapshot_is_intact(self) -> None:
        raw = RULES_FILE.read_bytes()
        self.assertEqual(
            hashlib.sha256(raw).hexdigest(),
            "06f0310548e2984a2e83341c24a668d6fadcd262d2e61dc9e70e201f806ed02b",
        )
        self.assertEqual(self.rules["schema_version"], 1)
        self.assertEqual(self.rules["rules_version"], "2026.10.08-2")
        self.assertEqual(len(self.rules["rules"]), 41)
        self.assertEqual(len({rule["id"] for rule in self.rules["rules"]}), 41)
        self.assertEqual(sum(self.rules["category_weights"].values()), 100)

    def test_every_rule_has_an_evaluator_method(self) -> None:
        start = self.evaluators.index("$map    = array(")
        end = self.evaluators.index("\n\t\t);", start)
        mapping = self.evaluators[start:end]
        mapped_ids = set(re.findall(r"'([A-Z0-9]+(?:-[A-Z0-9]+)+)'\s*=>", mapping))
        mapped_methods = set(re.findall(r"=>\s*'([a-z][a-z0-9_]*)'", mapping))
        methods = set(re.findall(r"private static function ([a-z][a-z0-9_]*)\s*\(", self.evaluators))
        rule_ids = {rule["id"] for rule in self.rules["rules"]}
        self.assertEqual(mapped_ids, rule_ids)
        self.assertFalse(mapped_methods - methods)

    def test_rest_contract_routes_are_registered(self) -> None:
        expected = {
            "/status", "/rules", "/posts", "/posts/(?P<id>\\d+)/report",
            "/posts/(?P<id>\\d+)/history", "/posts/(?P<id>\\d+)/report/(?P<report_id>\\d+)",
            "/posts/(?P<id>\\d+)/diff", "/posts/(?P<id>\\d+)/audit", "/queue",
            "/jobs/(?P<job_id>job_\\d+)", "/stats/summary", "/stats/rankings",
            "/stats/top-issues", "/stats/trend", "/stats/distribution",
            "/stats/coverage-gaps", "/claims", "/claims/(?P<id>\\d+)", "/export",
        }
        routes = set(re.findall(r"register_rest_route\(\s*self::NAMESPACE,\s*'([^']+)'", self.rest))
        self.assertTrue(expected.issubset(routes), f"Missing routes: {sorted(expected - routes)}")
        self.assertIn("'permission_callback' => array( __CLASS__, 'can_view' )", self.rest)
        self.assertIn("'permission_callback' => array( __CLASS__, 'can_run' )", self.rest)

    def test_engine_storage_is_allowlisted_and_site_content_is_not_mutated(self) -> None:
        allowed = {
            "reports", "issues", "category_scores", "links", "similarities",
            "jobs", "claims", "settings", "settings_audit",
        }
        declared = set(re.findall(r"'([a-z_]+)'", self.database.split("$allowed = array(", 1)[1].split(")", 1)[0]))
        self.assertEqual(declared, allowed)
        forbidden_calls = re.compile(
            r"\b(?:wp_insert_post|wp_update_post|wp_delete_post|update_post_meta|add_post_meta|delete_post_meta|"
            r"wp_set_object_terms|wp_remove_object_terms|wp_update_term|wp_delete_term)\s*\("
        )
        for source in ENGINE.rglob("*.php"):
            if "tests" in source.parts:
                continue
            self.assertIsNone(forbidden_calls.search(source.read_text(encoding="utf-8")), str(source))
        self.assertIn("wp_safe_remote_get", (ENGINE / "includes" / "class-iaae-auditor.php").read_text(encoding="utf-8"))
        self.assertIn("'redirection'         => 0", (ENGINE / "includes" / "class-iaae-auditor.php").read_text(encoding="utf-8"))
        self.assertIn("get_setting( 'rendered_checks', false )", (ENGINE / "includes" / "class-iaae-auditor.php").read_text(encoding="utf-8"))
        self.assertIn("'external_link_checks_enabled' => false", self.rest)

    def test_dashboard_is_a_rest_only_safe_client(self) -> None:
        self.assertIn("X-WP-Nonce", self.dashboard)
        self.assertIn("request('status')", self.dashboard)
        self.assertIn("request('posts'", self.dashboard)
        self.assertNotRegex(self.dashboard, r"\$wpdb|iaa_(?:reports|issues|claims)")
        self.assertNotRegex(self.dashboard, r"\.innerHTML\s*=|\beval\s*\(")
        self.assertIn("status === 404", self.dashboard)

    def test_partial_audits_preserve_api_1_1_without_scope_fields(self) -> None:
        self.assertIn("self::score( $results, $selected_modules )", self.auditor)
        self.assertIn("$partial_scope", self.auditor)
        self.assertIn("if ( $stats['weight_total'] > 0 )", self.auditor)
        self.assertIn("این دسته در درخواست فعلی انتخاب نشده است", self.auditor)
        self.assertNotIn("'audit_scope' =>", self.auditor)
        self.assertNotIn("'modules' => $selected_modules", self.auditor)
        self.assertIn("IAAE_Jobs::enqueue( array( absint( $request['id'] ) ), array(), get_current_user_id() )", self.rest)
        self.assertIn("(array) $request->get_param( 'post_ids' ), (array) $request->get_param( 'modules' )", self.rest)
        self.assertNotIn("'external_link_checks_opt_in'", self.rest)

    def test_city_taxonomy_meta_mapping_uses_existing_city_entity(self) -> None:
        self.assertIn("'city' === $type && in_array( 'county', $known, true )", self.profile_mapper)
        self.assertIn("canonical entity remains City", self.profile_mapper)
        self.assertIn("get_the_terms( (int) $post->ID, 'province_tax' )", self.profile_mapper)
        self.assertIn("IAAE_Profile_Mapper::location_fields( $post, $profile )", self.auditor)
        self.assertIn("'city' => array( 'latitude' => 'sa_city_latitude', 'longitude' => 'sa_city_longitude'", self.evaluators)
        self.assertIn("self::valid_coordinate_pair( $latitude, $longitude )", self.evaluators)
        self.assertIn("$latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180", self.evaluators)
        self.assertNotRegex(self.profile_mapper, r"register_post_type\s*\(\s*['\"]county")
        self.assertIn("['county', 'شهرها']", self.dashboard)

    def test_report_retention_defaults_to_ten_engine_owned_records(self) -> None:
        self.assertIn("get_setting( 'retention_reports', 10 )", self.auditor)
        self.assertIn("'retention_reports', $retention", self.admin)
        self.assertIn("array( 'issues', 'category_scores', 'claims', 'similarities' )", self.auditor)
        self.assertIn("DELETE FROM {$reports}", self.auditor)

    def test_job_insert_formats_match_the_inserted_columns(self) -> None:
        insert_start = self.jobs.index("$inserted = $wpdb->insert(")
        insert_end = self.jobs.index("if ( false === $inserted )", insert_start)
        block = self.jobs[insert_start:insert_end]
        data = block.split("array(", 1)[1].split("),\n\t\t\t\tarray(", 1)[0]
        columns = re.findall(r"^\s*'[a-z_]+\s*'\s*=>", data, flags=re.MULTILINE)
        formats_match = re.search(r"array\(\s*((?:'%[sdf]'\s*,?\s*)+)\)", block, flags=re.DOTALL)
        self.assertIsNotNone(formats_match)
        formats = re.findall(r"'%[sdf]'", formats_match.group(1))
        self.assertEqual(len(columns), 14)
        self.assertEqual(len(formats), len(columns))


if __name__ == "__main__":
    unittest.main(verbosity=2)
