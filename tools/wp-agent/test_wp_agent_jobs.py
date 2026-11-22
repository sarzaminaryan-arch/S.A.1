#!/usr/bin/env python3
"""Offline tests for job-runner safety: not_before deferral, probe health rule, auto-rollback."""
import json
import os
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

# 1b. v2.22.116: the owner's «deploy without a new backup» order travels as an explicit
#     flag, and a rollback is never blocked by the backup gate (it is the safety net).
seen = {}


class _ThemeClient:
    base = "https://example.test/wp-json/sa-agent/v1"

    def __init__(self):
        pass

    def call(self, method, route, params=None, body=None):
        seen[route] = body
        return {"updated": True}


wa.theme_install(_ThemeClient(), "2.22.116", "2.22.116", False)
_body = seen["/theme-update"]
assert _body["owner_approved_theme_update"] is True and _body["expected_version"] == "2.22.116"
assert _body.get("version") == "2.22.116" and "allow_without_backup" not in _body, seen
wa.theme_install(_ThemeClient(), "2.22.116", "2.22.116", True)
assert seen["/theme-update"].get("allow_without_backup") is True, seen
wa.theme_install(_ThemeClient(), "2.22.115", "2.22.115", True)
assert seen["/theme-update"]["version"] == "2.22.115", seen

# 1d. A push starts the release job and the agent job at the same time. When the agent wins,
#     the site answers 409 «this version is not published yet». Jobs 127 and 130 both stopped
#     there and needed a manual re-run; with --wait-for-release the install waits instead, and
#     any other refusal (a real mismatch, a 403) still fails immediately.
class _PendingRelease:
    base = "https://example.test/wp-json/sa-agent/v1"

    def __init__(self, pending_calls: int):
        self.left = pending_calls
        self.calls = 0

    def call(self, method, route, params=None, body=None):
        self.calls += 1
        if self.left:
            self.left -= 1
            raise wa.AgentError('HTTP 409 on POST /theme-update: {"code":"sa_agent_version_mismatch"}')
        return {"updated": True, "to": body.get("version")}


_slept = []
_pending = _PendingRelease(2)
assert wa.theme_install(_pending, "2.22.118", "2.22.118", True, wait_seconds=300,
                        sleep=_slept.append)["updated"] is True
assert _pending.calls == 3 and _slept == [wa.THEME_RELEASE_POLL_SECONDS] * 2, (_pending.calls, _slept)

_hard = _PendingRelease(99)
try:
    wa.theme_install(_hard, "2.22.118", "2.22.118", True, wait_seconds=0, sleep=_slept.append)
    raise AssertionError("a 409 must still fail when the job asked for no wait")
except wa.AgentError as err:
    assert "sa_agent_version_mismatch" in str(err) and _hard.calls == 1


class _Refused:
    base = "https://example.test/wp-json/sa-agent/v1"

    def call(self, method, route, params=None, body=None):
        raise wa.AgentError('HTTP 403 on POST /theme-update: {"code":"sa_photo_need_owner"}')


try:
    wa.theme_install(_Refused(), "2.22.118", "2.22.118", True, wait_seconds=300, sleep=_slept.append)
    raise AssertionError("only the not-yet-published refusal may be retried")
except wa.AgentError as err:
    assert "403" in str(err)

# The CLI flag exists, so a job step can ask for the wait.
assert wa.build_parser().parse_args(wa.step_to_argv(
    {"cmd": "theme-update", "expected_version": "2.22.118", "owner_approved_theme_update": True,
     "wait_for_release": 300})).wait_for_release == 300

# 1c. Every step written into tools/wp-agent/jobs/ must survive the job -> argv -> argparse
#     translation. A flag the parser doesn't know (e.g. a step field named "limit" when the
#     command had no --limit) makes argparse exit with code 2, and the live job stops at that
#     step with an error that is just "2" — which is exactly how job 128 died after the
#     theme had already been installed. This test turns that into a red build instead.
_parser = wa.build_parser()
_seen_cmds = set()
for _job_file in sorted((wa.REPO_ROOT / "tools/wp-agent/jobs").glob("*.json")):
    _job = json.loads(_job_file.read_text(encoding="utf-8"))
    _steps = _job.get("steps", _job if isinstance(_job, list) else [])
    for _step in _steps:
        if _step.get("cmd") in ("photos-import", "county-covers"):
            continue  # these two have their own step handlers, not the CLI parser
        _argv = wa.step_to_argv(_step)
        _ns = _parser.parse_args(_argv)  # SystemExit(2) here = a job the runner cannot run
        _seen_cmds.add(_ns.cmd)
assert {"photos-optimize", "photos-prune", "theme-update"} <= _seen_cmds, _seen_cmds
assert "photos-manage" in _parser._subparsers._group_actions[0].choices, "the owner's hide/lead/detach command must stay reachable from a job step"

# photos-optimize sends photo ids as the body's photo_ids and a batch size as limit;
# the parser flag is --photo-id, so reading a.photo_ids silently optimised everything.
_opt = _parser.parse_args(wa.step_to_argv(
    {"cmd": "photos-optimize", "owner_approved": True, "photo_id": [42, 43], "limit": 5, "city_id": 325}))
assert _opt.photo_id == [42, 43] and _opt.limit == 5 and _opt.city_id == 325, _opt
assert _parser.parse_args(wa.step_to_argv({"cmd": "photos-optimize", "owner_approved": True})).limit == 30

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
    # Tests use an isolated simulated persistent disk, never a live Actions runner.
    os.environ['GITHUB_ACTIONS'] = 'false'
    os.environ['SA_WP_AGENT_STATE_DIR'] = str(Path(d) / 'state')
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

    # 4. sync_ready_article tolerates sa_agent_nothing (noop) without marking untracked,
    #    and still rolls back real writes when a later probe fails.
    class SyncFakeClient(FakeClient):
        def call(self, method, route, params=None, body=None):
            if method == "POST" and route == "/post/10" and body and body.get("sync_ready_article"):
                raise wa.AgentError('HTTP 409 on POST /post/10: {"code":"sa_agent_nothing"}')
            return super().call(method, route, params=params, body=body)

    sfc = SyncFakeClient()
    sync_job = Path(d) / "sync.json"
    sync_job.write_text(json.dumps({"rollback_on_fail": True, "steps": [
        {"cmd": "update", "id": 10, "sync_ready_article": True, "owner_approved_live_edit": True, "reason": "t"},
        {"cmd": "update", "id": 11, "sync_ready_article": True, "owner_approved_live_edit": True, "reason": "t"},
        {"cmd": "probe", "url": ["/guide/x/"]},
    ]}), encoding="utf-8")
    srep = wa.run_job(str(sync_job), sfc)
    assert srep["ok"] is False and srep["steps"][0]["result"].get("noop") is True, srep
    assert [r["backup_id"] for r in srep["rollback"]] == [101], srep

print("OK: wp_agent job safety (not_before, health rule, auto-rollback)")
