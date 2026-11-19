#!/usr/bin/env python3
import unittest
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


if __name__ == '__main__':
    unittest.main()
