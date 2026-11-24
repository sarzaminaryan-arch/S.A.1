#!/usr/bin/env python3
"""Unit tests for wp_agent.py draft --upsert-slug."""

import argparse
import sys
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parent))
import wp_agent  # noqa: E402


class _FakeClient:
    def __init__(self, items):
        self._items = items
        self.calls = []

    def call(self, method, path, params=None, body=None):
        self.calls.append((method, path, params, body))
        if method == "GET" and path == "/posts":
            return {"items": self._items}
        if method == "GET" and path == "/post/901":
            return {"id": 901, "modified_gmt": "2026-11-24T00:00:00Z", "status": "draft"}
        if method == "POST" and path == "/post/901":
            return {"id": 901, "status": "draft", "updated": True}
        return {"id": 902, "status": "draft"}


class UpsertAndPublishTests(unittest.TestCase):
    def test_draft_upsert_slug_skips_existing_publish(self):
        client = _FakeClient([{"id": 901, "slug": "milad-tower", "status": "publish"}])
        args = argparse.Namespace(
            cmd="draft", type="attraction",
            title="برج میلاد",
            slug="milad-tower",
            upsert_slug=True,
            parent=0,
            category=0,
            content="متن آزمایشی",
            content_file=None,
            excerpt="",
            meta=[],
            meta_file=None,
        )
        with patch.object(wp_agent, "save_local_backup"):
            res = wp_agent.run(args, client)
        self.assertTrue(res.get("skipped_existing_publish"))
        self.assertEqual(res.get("id"), 901)

    def test_draft_upsert_slug_updates_existing_draft(self):
        client = _FakeClient([{"id": 901, "slug": "milad-tower", "status": "draft"}])
        args = argparse.Namespace(
            cmd="draft", type="attraction",
            title="برج میلاد",
            slug="milad-tower",
            upsert_slug=True,
            parent=0,
            category=0,
            content="متن آزمایشی",
            content_file=None,
            excerpt="",
            meta=[],
            meta_file=None,
        )
        with patch.object(wp_agent, "save_local_backup"):
            res = wp_agent.run(args, client)
        self.assertTrue(res.get("updated"))
        self.assertEqual(res.get("id"), 901)


if __name__ == "__main__":
    unittest.main()
