#!/usr/bin/env python3
"""Regression tests for the county run protocol (۲ آذر ۱۴۰۵).

The owner's order was «سند رو ثبت کن که برای همه یکسان انجام بدی» — a document
nobody can silently break. So the rules of `docs/live-site/county-run-protocol.md`
are tested here, not just described there:

  1) the queue order in the document and in template_worklist.json is the same;
  2) two counties of one province cannot be open at the same time;
  3) a county cannot be marked `done` without its three witnesses
     (build bundle, evidence brief, gate record);
  4) the run never opens a second province while one is still open.

Runs offline with a temporary state file: the real `county_run_state.json`
is never touched.
"""
import json
import re
import sys
import tempfile
from pathlib import Path
from unittest.mock import patch

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

import county_run as cr  # noqa: E402

ROOT = HERE.parent.parent
DOC = ROOT / "docs/live-site/county-run-protocol.md"


def fresh():
    """An empty state with the current province set, in a temp file."""
    tmp = Path(tempfile.mkdtemp()) / "state.json"
    return tmp, {"province": "khuzestan", "counties": {}}


def main() -> int:
    rows = cr.load_worklist()
    assert len(rows) == 483, len(rows)

    # 1) the document's Khuzestan queue is the worklist order, row for row
    kh = cr.counties_of(rows, "khuzestan")
    assert len(kh) == 29, len(kh)
    doc = DOC.read_text(encoding="utf-8")
    block = doc.split("## ۵. صف خوزستان", 1)[1].split("این جدول", 1)[0]
    slugs = re.findall(r"\| `([a-z0-9\-]+)` \|", block)
    assert slugs == [r["slug"] for r in kh], slugs[:3]
    assert slugs[0] == "ahvaz", "خوزستان با اهواز شروع می‌شود"

    # 2) one county at a time inside a province
    STATE_PATH, state = fresh()
    with patch.object(cr, "STATE", STATE_PATH):
        cr.save_state(state)
        assert cr.main(["county_run.py", "start", "ahvaz"]) == 0
        # a second county in the same province is refused
        assert cr.main(["county_run.py", "start", "dezful"]) == 1
        assert cr.next_slug(cr.load_state(), rows, "khuzestan") == "ahvaz"

        # 3) done needs three witnesses
        with patch.object(cr, "missing_witnesses", lambda slug: ["x"]):
            assert cr.main(["county_run.py", "done", "ahvaz"]) == 1
            assert cr.load_state()["counties"]["ahvaz"]["status"] == "in_progress"
        # the witness list itself names what is missing when a slug has no files yet
        empty_root = Path(tempfile.mkdtemp())
        empty_briefs = empty_root / "docs/live-site/briefs"
        empty_outbox = empty_root / "tools/wp-agent/outbox"
        empty_briefs.mkdir(parents=True)
        empty_outbox.mkdir(parents=True)
        with patch.object(cr, "ROOT", empty_root), \
             patch.object(cr, "BRIEFS", empty_briefs), \
             patch.object(cr, "OUTBOX", empty_outbox):
            missing = cr.missing_witnesses("dezful")
        for want in ("outbox/*-replace-dezful/*.html", "briefs/dezful.md", "briefs/dezful-evidence.md", "briefs/dezful-gates.md"):
            assert any(want in m for m in missing), (want, missing)
        # with the witnesses present the same call succeeds
        assert any(cr.OUTBOX.glob("*-replace-*/eslamshahr-*.html")), \
            "the eslamshahr reference build must be in the repo"
        with patch.object(cr, "missing_witnesses", lambda slug: []):
            assert cr.main(["county_run.py", "done", "ahvaz"]) == 0
        assert cr.load_state()["counties"]["ahvaz"]["status"] == "done"

        # 4) a second province cannot open while one is still open
        cr.main(["county_run.py", "start", "dezful"])
        assert cr.main(["county_run.py", "start", "tehran"]) == 1
        # after the province is closed, the next one opens
        state = cr.load_state()
        state["counties"]["dezful"]["status"] = "done"
        with patch.object(cr, "missing_witnesses", lambda slug: []):
            cr.save_state(state)
            assert cr.main(["county_run.py", "start", "tehran"]) == 0
        assert cr.load_state()["province"] == "tehran"

        # blocked needs a reason, done needs a real county
        assert cr.main(["county_run.py", "block", "mashhad", "--why", " "]) == 1
        assert cr.main(["county_run.py", "block", "nope", "--why", "x"]) == 1

    # 5) the state file carries every Khuzestan county exactly once
    real = json.loads((HERE / "county_run_state.json").read_text(encoding="utf-8"))
    assert set(real["counties"]) == {r["slug"] for r in kh}, "خوزستان: یک ردیف برای هر شهرستان"
    assert all(v["status"] in cr.STATUSES for v in real["counties"].values())
    print("county_run: OK — order, one-at-a-time, three witnesses, one province")
    return 0


if __name__ == "__main__":
    sys.exit(main())
