#!/usr/bin/env python3
"""Offline tests for job-runner safety: not_before deferral, probe health rule, auto-rollback."""
import json
import os
import sys
import tempfile
from datetime import datetime, timezone
from pathlib import Path
from unittest.mock import patch

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

# Featured-photo preflight reads each exact published target and refuses to import if any
# already has a cover; the setter maps the successful Commons import receipt and uses the
# ordinary optimistic-lock + server-backup update path without overwriting an existing cover.
_plan_rel = "tools/wp-agent/outbox/20261124-tehran-attraction-featured-photo-plan.json"
_plan = json.loads((wa.REPO_ROOT / _plan_rel).read_text(encoding="utf-8"))
class _FeaturedCheckClient:
    def __init__(self, occupied_id=0):
        self.occupied_id = occupied_id
        self.calls = []
    def call(self, method, route, params=None, body=None):
        self.calls.append((method, route))
        post_id = int(route.rsplit("/", 1)[-1])
        target = next(p for p in _plan["photos"] if p["post_id"] == post_id)
        return {"id": post_id, "type": "attraction", "slug": target["slug"], "status": "publish",
                "featured_media": self.occupied_id if post_id == self.occupied_id else 0,
                "meta": {}, "modified_gmt": "2026-11-24 22:00:00"}

_check = wa.run_featured_photo_check(_ap.Namespace(plan=_plan_rel), _FeaturedCheckClient())
assert _check["checked"] == 18
try:
    wa.run_featured_photo_check(_ap.Namespace(plan=_plan_rel), _FeaturedCheckClient(_plan["photos"][0]["post_id"]))
    raise AssertionError("featured-photo-check must refuse when any target is already covered")
except wa.AgentError as err:
    assert "already has featured image" in str(err), err

class _FeaturedSetClient:
    def __init__(self, thumbnail=0):
        self.thumbnail = thumbnail
    def call(self, method, route, params=None, body=None):
        assert method == "GET" and route == "/post/5468"
        return {"id": 5468, "type": "attraction", "slug": "milad-tower", "status": "publish",
                "featured_media": self.thumbnail, "meta": {}, "modified_gmt": "2026-11-24 22:00:00"}

_fixture_batch = f"test-featured-photo-{os.getpid()}.json"
_fixture_receipt = wa.REPO_ROOT / "tools/photos/results" / f"{Path(_fixture_batch).stem}.json"
_fixture_receipt.parent.mkdir(parents=True, exist_ok=True)
_fixture_receipt.write_text(json.dumps({"batch": Path(_fixture_batch).stem,
    "imported": [{"place": "برج میلاد تهران", "id": 9876, "url": "https://example.test/photo.webp"}],
    "reused": [], "failed": [], "refused": []}), encoding="utf-8")
try:
    _setter_client = _FeaturedSetClient()
    def _pretend_update(args, client):
        assert args.expected_modified_gmt == "2026-11-24 22:00:00"
        assert wa.read_json(args.meta_file) == {"_thumbnail_id": "9876"}
        client.thumbnail = 9876
        return {"id": 5468, "backup_id": 4321}
    with patch.object(wa, "run", side_effect=_pretend_update):
        _set = wa.run_featured_photo_set(_ap.Namespace(id=5468, slug="milad-tower",
            place="برج میلاد تهران", batch=_fixture_batch), _setter_client)
    assert _set["attachment_id"] == 9876 and _set["backup_id"] == 4321
    with patch.object(wa, "run") as _unexpected_update:
        try:
            wa.run_featured_photo_set(_ap.Namespace(id=5468, slug="milad-tower",
                place="برج میلاد تهران", batch=_fixture_batch), _FeaturedSetClient(123))
            raise AssertionError("featured-photo-set must never replace an existing image")
        except wa.AgentError as err:
            assert "refusing to replace existing featured image" in str(err), err
        _unexpected_update.assert_not_called()
finally:
    _fixture_receipt.unlink(missing_ok=True)

# publish-draft is owner-gated and refuses non-draft posts before making any POST call.
try:
    wa.run(_ap.Namespace(cmd="publish-draft", id=501, owner_approved_publish=False, reason="test"), _NoCall())
    raise AssertionError("publish-draft should refuse to run without --owner-approved-publish")
except wa.AgentError as err:
    assert "owner-approved-publish" in str(err), err


class _PublishDraftClient:
    def __init__(self, status: str):
        self.status = status
        self.posts = []

    def call(self, method, route, params=None, body=None):
        if method == "GET":
            return {"id": 501, "status": self.status, "modified_gmt": "2026-11-24 12:00:00"}
        self.posts.append((route, body))
        return {"id": 501, "status": "publish", "backup_id": 900}


try:
    wa.run(_ap.Namespace(cmd="publish-draft", id=501, owner_approved_publish=True, reason="test"),
           _PublishDraftClient("publish"))
    raise AssertionError("publish-draft should refuse already-published posts")
except wa.AgentError as err:
    assert "only accepts draft" in str(err), err

assert wa.step_to_argv({"cmd": "publish-draft", "id": 501, "owner_approved_publish": True, "reason": "خوشه تهران"}) == [
    "publish-draft", "501", "--owner-approved-publish", "--reason", "خوشه تهران"
]

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
