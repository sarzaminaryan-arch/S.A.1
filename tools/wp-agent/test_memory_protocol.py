#!/usr/bin/env python3
"""Offline regression guard for `.memory/` progress rules & the 100-cluster roadmap.

Owner order (۳ آذر ۱۴۰۵ / 2026-11-24):
  1) Every session must record work progress in `.memory/` at session end
     (`.memory/wiki/hot.md`, `.memory/wiki/roadmap.md`, `.memory/wiki/log.md`).
  2) The Topic Cluster Strategy (100 top-searched counties + 239 attraction
     branches) in `tools/wp-agent/topic_clusters_100.json` and
     `docs/live-site/topic-cluster-roadmap.md` must remain intact and in sync.
"""
from __future__ import annotations

import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class MemoryAndClusterRoadmapTests(unittest.TestCase):
    def test_memory_files_exist_and_non_empty(self):
        required = (
            ".memory/wiki/hot.md",
            ".memory/wiki/roadmap.md",
            ".memory/wiki/stack.md",
            ".memory/wiki/patterns.md",
            ".memory/wiki/decisions.md",
            ".memory/wiki/bugs.md",
            ".memory/wiki/learnings.md",
            ".memory/wiki/github-refs.md",
            ".memory/wiki/log.md",
            ".memory/session/current.md",
        )
        for rel in required:
            p = ROOT / rel
            self.assertTrue(p.is_file(), f"missing memory file: {rel}")
            text = p.read_text(encoding="utf-8").strip()
            self.assertGreater(len(text), 40, f"memory file unexpectedly empty: {rel}")

    def test_rule_entrypoints_enforce_end_of_session_memory_update(self):
        entrypoints = (
            "AGENTS.md",
            ".agent/RULES.md",
            "AGENT_RULES.md",
            "CLAUDE.md",
        )
        required_refs = (
            ".memory/wiki/hot.md",
            ".memory/wiki/roadmap.md",
            ".memory/wiki/log.md",
        )
        for rel in entrypoints:
            text = (ROOT / rel).read_text(encoding="utf-8")
            for ref in required_refs:
                self.assertIn(ref, text, f"{rel} must reference {ref} in end-of-session memory rules")

    def test_topic_clusters_100_json_and_doc_are_in_sync(self):
        json_path = ROOT / "tools/wp-agent/topic_clusters_100.json"
        doc_path = ROOT / "docs/live-site/topic-cluster-roadmap.md"
        self.assertTrue(json_path.is_file())
        self.assertTrue(doc_path.is_file())

        data = json.loads(json_path.read_text(encoding="utf-8"))
        clusters = data["clusters"]
        self.assertEqual(len(clusters), 100)
        self.assertEqual([c["rank"] for c in clusters], list(range(1, 101)))

        total_branches = sum(len(c["branches"]) for c in clusters)
        self.assertEqual(total_branches, 239)
        for c in clusters:
            self.assertEqual(c["branch_count"], len(c["branches"]), c["name"])
            self.assertIn(c["cluster_status"], ("todo", "in_progress", "done", "blocked"))
            if c["branch_count"] == 0:
                self.assertTrue(c["zero_branch_focus"].strip(), f"zero-branch city {c['name']} needs focus")

        zero_ranks = [c["rank"] for c in clusters if c["branch_count"] == 0]
        self.assertEqual(zero_ranks, [35, 58, 60, 75, 80])

        doc_text = doc_path.read_text(encoding="utf-8")
        for c in clusters:
            self.assertIn(f"`/city/{c['city_slug']}/`", doc_text)
            for b in c["branches"]:
                self.assertIn(b["title"], doc_text)


if __name__ == "__main__":
    unittest.main()
