#!/usr/bin/env python3
"""Offline tests for job-runner safety: not_before deferral, probe health rule, auto-rollback."""
import json
import sys
import tempfile
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import wp_agent as wa  # noqa: E402

# photos-import refuses a batch path outside the repository and a missing batch.
try:
    wa.run_photos_import({"batch": "../../etc/passwd.json"})
    raise AssertionError("photos-import should refuse a batch outside the repository")
except wa.AgentError as err:
    assert "inside the repository" in str(err), err
try:
    wa.run_photos_import({})
    raise AssertionError("photos-import should require a batch")
except wa.AgentError as err:
    assert "needs batch" in str(err), err

# photos-prune is owner-gated: no flag, no call (the site enforces the same rule).
class _NoCall:
    def call(self, *a, **k):  # pragma: no cover - must never run
        raise AssertionError("the site must not be called without the owner flag")
import argparse as _ap
try:
    wa.run(_ap.Namespace(cmd="photos-prune", city_id=71, owner_approved=False, dry_run=True), _NoCall())
    raise AssertionError("photos-prune should refuse to run without --owner-approved")
except wa.AgentError as err:
    assert "owner-approved" in str(err), err

# The live cover pipeline requires an explicit owner flag before launching npm or making requests.
try:
    wa.run_county_covers({"owner_approved": False})
    raise AssertionError("county cover sync should reject missing owner approval")
except wa.AgentError as err:
    assert "owner_approved" in str(err)
try:
    wa.run_county_covers({"owner_approved": True, "start_index": -1})
    raise AssertionError("county cover sync should reject a negative resume offset")
except wa.AgentError as err:
    assert "start_index" in str(err)

# 1. not_before defers a job (no result file is written) until the time has passed.
t = datetime(2026, 11, 16, 5, 0, tzinfo=timezone.utc)
assert wa.job_deferred({"not_before": "2026-11-16T06:00:00Z"}, t)
assert not wa.job_deferred({"not_before": "2026-11-16T04:00:00Z"}, t)
assert not wa.job_deferred({}, t)

# 2. Health rule after a live edit.
ok = {"url": "u", "status": 200, "title": "x", "robots": "index, follow", "h1": 1}
assert wa.unhealthy_pages([ok]) == []
assert wa.unhealthy_pages([{**ok, "status": 500}])
assert wa.unhealthy_pages([{**ok, "robots": "noindex, follow"}])
assert wa.unhealthy_pages([{**ok, "h1": 2}])


# 3. A failing probe in a rollback_on_fail job restores every backup of that job, newest first.
class FakeClient:
    base = "https://example.test/wp-json/sa-agent/v1"

    def __init__(self):
        self.calls = []
        self.n = 100

    def call(self, method, route, params=None, body=None):
        self.calls.append((method, route))
        if method == "GET" and route.startswith("/post/"):
            return {"id": int(route.split("/")[2]), "modified_gmt": "2026-01-01 00:00:00"}
        if method == "POST" and route.startswith("/post/"):
            self.n += 1
            return {"id": int(route.split("/")[2]), "backup_id": self.n}
        if route.startswith("/restore/"):
            return {"restored": int(route.split("/")[2])}
        raise AssertionError(route)


wa.save_local_backup = lambda name, data: wa.REPO_ROOT / "backup" / "test"  # no disk writes in tests
wa.probe_url = lambda url: {"url": url, "status": 200, "title": "t", "robots": "noindex", "h1": 1}
with tempfile.TemporaryDirectory() as d:
    html = Path(d) / "a.html"
    html.write_text("<p>متن</p>", encoding="utf-8")
    job = Path(d) / "job.json"
    job.write_text(json.dumps({"rollback_on_fail": True, "steps": [
        {"cmd": "update", "id": 1, "content_file": str(html), "owner_approved_live_edit": True, "reason": "t"},
        {"cmd": "update", "id": 2, "content_file": str(html), "owner_approved_live_edit": True, "reason": "t"},
        {"cmd": "probe", "url": ["/city/a/"]},
    ]}), encoding="utf-8")
    wa.RESULTS_DIR = Path(d) / "results"
    fc = FakeClient()
    rep = wa.run_job(str(job), fc)
    assert rep["ok"] is False and "probe failed" in rep["steps"][-1]["error"], rep
    assert [r["backup_id"] for r in rep["rollback"]] == [102, 101], rep["rollback"]
    assert ("POST", "/restore/102") in fc.calls and ("POST", "/restore/101") in fc.calls
    # deferred job: no result written
    later = Path(d) / "later.json"
    later.write_text(json.dumps({"not_before": "2999-01-01T00:00:00Z", "steps": [{"cmd": "ping"}]}), encoding="utf-8")
    assert wa.run_job(str(later), fc) == {"deferred": True, "not_before": "2999-01-01T00:00:00Z"}
    assert not (wa.RESULTS_DIR / "later.json").exists()

print("OK: wp_agent job safety (not_before, health rule, auto-rollback)")
