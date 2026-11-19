#!/usr/bin/env python3
import unittest
import json
from unittest.mock import patch
from types import SimpleNamespace
import check_build_gate
from check_build_gate import gate_state

SHA = 'abcd'


def run(status='completed', conclusion='success', **kw):
    return {'id': 1, 'created_at': '2026-01-01', 'head_sha': SHA, 'event': 'push',
            'head_branch': 'main', 'status': status, 'conclusion': conclusion, **kw}


class BuildGateTests(unittest.TestCase):
    def test_success(self):
        self.assertEqual(gate_state({'workflow_runs': [run()]}, SHA), 'success')

    def test_missing_wrong_sha_and_wrong_branch_wait(self):
        for data in ([], [{**run(), 'head_sha': 'other'}], [{**run(), 'head_branch': 'arena/x'}]):
            self.assertEqual(gate_state({'workflow_runs': data}, SHA), 'waiting')

    def test_failure_and_cancel_are_blocked(self):
        for result in ('failure', 'cancelled', 'timed_out', 'skipped', None):
            self.assertEqual(gate_state({'workflow_runs': [run(conclusion=result)]}, SHA), 'failure')

    def test_pending_newer_run_must_not_use_old_success(self):
        newer = {**run(), 'id': 2, 'status': 'in_progress', 'conclusion': None}
        self.assertEqual(gate_state({'workflow_runs': [run(), newer]}, SHA), 'waiting')

    def test_malformed_is_error(self):
        with self.assertRaises(ValueError):
            gate_state({}, SHA)


class GateExitTests(unittest.TestCase):
    def test_uncertain_and_failed_responses_immediately_fail_closed(self):
        cases = [
            SimpleNamespace(returncode=1, stdout=''),
            SimpleNamespace(returncode=0, stdout='not json'),
            SimpleNamespace(returncode=0, stdout=json.dumps({'workflow_runs': []})),
            SimpleNamespace(returncode=0, stdout=json.dumps({'workflow_runs': [run(status='queued', conclusion=None)]})),
            SimpleNamespace(returncode=0, stdout=json.dumps({'workflow_runs': [run(conclusion='cancelled')]})),
        ]
        for response in cases:
            with self.subTest(response=response), patch.dict('os.environ', {'GATE_SHA': SHA, 'GATE_REPOSITORY': 'a/b'}), \
                    patch.object(check_build_gate.subprocess, 'run', return_value=response) as call:
                self.assertEqual(check_build_gate.main(), 1)
                call.assert_called_once()

    def test_exact_commit_success_allows(self):
        response = SimpleNamespace(returncode=0, stdout=json.dumps({'workflow_runs': [run()]}))
        with patch.dict('os.environ', {'GATE_SHA': SHA, 'GATE_REPOSITORY': 'a/b'}), \
                patch.object(check_build_gate.subprocess, 'run', return_value=response):
            self.assertEqual(check_build_gate.main(), 0)


if __name__ == '__main__':
    unittest.main()
