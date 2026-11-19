#!/usr/bin/env python3
"""Isolated crash/recovery tests; no live WordPress calls."""
import json
import os
import subprocess
import sys
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import wp_agent as agent


class StateRecoveryTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.job = self.root / 'example.json'
        self.job.write_text(json.dumps({'rollback_on_fail': True, 'steps': [
            {'cmd': 'update', 'id': 1}, {'cmd': 'ping'}]}))
        self.env = patch.dict(os.environ, {'SA_WP_AGENT_STATE_DIR': str(self.root / 'state'),
                                            'GITHUB_ACTIONS': 'false'}, clear=False)
        self.env.start()
        self.addCleanup(self.env.stop)
        self.results = patch.object(agent, 'RESULTS_DIR', self.root / 'results')
        self.results.start()
        self.addCleanup(self.results.stop)
        class Client:
            def __init__(self): self.calls = []
            def call(self, method, route, **kwargs):
                self.calls.append((method, route))
                return {'restored': True}
        self.client = Client()

    def test_crash_after_update_requires_manual_reconciliation(self):
        with patch.object(agent, 'run', side_effect=[{'backup_id': 21}, KeyboardInterrupt()]):
            with self.assertRaises(KeyboardInterrupt): agent.run_job(str(self.job), self.client)
        with patch.object(agent, 'run') as run:
            with self.assertRaises(agent.AgentError): agent.run_job(str(self.job), self.client)
            run.assert_not_called()
        self.assertEqual(self.client.calls, [])

    def test_unchanged_read_only_job_resumes_automatically(self):
        self.job.write_text(json.dumps({'steps': [{'cmd': 'ping'}]}))
        with patch.object(agent, 'run', side_effect=KeyboardInterrupt()):
            with self.assertRaises(KeyboardInterrupt): agent.run_job(str(self.job), self.client)
        with patch.object(agent, 'run', return_value={'ok': True}) as run:
            report = agent.run_job(str(self.job), self.client)
        self.assertTrue(report['ok'])
        run.assert_called_once()

    def test_changed_read_only_job_does_not_auto_resume(self):
        self.job.write_text(json.dumps({'steps': [{'cmd': 'ping'}]}))
        with patch.object(agent, 'run', side_effect=KeyboardInterrupt()):
            with self.assertRaises(KeyboardInterrupt): agent.run_job(str(self.job), self.client)
        self.job.write_text(json.dumps({'steps': [{'cmd': 'ping'}, {'cmd': 'ping'}]}))
        with self.assertRaises(agent.AgentError): agent.run_job(str(self.job), self.client)

    def test_crash_during_write_blocks_unknown_outcome(self):
        with patch.object(agent, 'run', side_effect=KeyboardInterrupt()):
            with self.assertRaises(KeyboardInterrupt): agent.run_job(str(self.job), self.client)
        with patch.object(agent, 'run') as run:
            with self.assertRaises(agent.AgentError): agent.run_job(str(self.job), self.client)
            run.assert_not_called()
        self.assertEqual(self.client.calls, [])

    def test_corrupt_state_fails_closed_before_work(self):
        folder = self.root / 'state'
        folder.mkdir()
        (folder / 'state.json').write_text('{bad')
        with patch.object(agent, 'run') as run:
            with self.assertRaises(agent.AgentError): agent.run_job(str(self.job), self.client)
            run.assert_not_called()

    def test_sigkill_read_only_job_restarts(self):
        self.job.write_text(json.dumps({'steps': [{'cmd': 'ping'}]}))
        code = """import os, signal, sys
from pathlib import Path
from unittest.mock import patch
import wp_agent as a
class Client: pass
a.RESULTS_DIR = Path(sys.argv[2])
def run(*args): os.kill(os.getpid(), signal.SIGKILL)
with patch.object(a, 'run', side_effect=run): a.run_job(sys.argv[1], Client())
"""
        result = subprocess.run([sys.executable, '-c', code, str(self.job), str(self.root / 'results')],
                                env={**os.environ, 'PYTHONPATH': str(Path(agent.__file__).parent)},
                                capture_output=True)
        self.assertEqual(result.returncode, -9, result.stderr)
        with patch.object(agent, 'run', return_value={'ok': True}):
            self.assertTrue(agent.run_job(str(self.job), self.client)['ok'])
        self.assertEqual(self.client.calls, [])

    def test_malformed_active_state_does_not_trigger_recovery(self):
        folder = self.root / 'state'
        folder.mkdir()
        (folder / 'state.json').write_text(json.dumps({'version': 1, 'finished': {},
            'active': {'job': 'example.json', 'phase': 'invalid', 'backups': [21]}}))
        with self.assertRaises(agent.AgentError): agent.run_job(str(self.job), self.client)
        self.assertEqual(self.client.calls, [])

    def test_state_inside_checkout_is_rejected(self):
        with patch.dict(os.environ, {'SA_WP_AGENT_STATE_DIR': str(agent.REPO_ROOT / '.agent')}):
            with self.assertRaises(agent.AgentError): agent.run_job(str(self.job), self.client)

    def test_github_hosted_runner_uses_default_local_journal(self):
        with patch.dict(os.environ, {'GITHUB_ACTIONS': 'true', 'RUNNER_ENVIRONMENT': 'github-hosted',
                                     'SA_WP_AGENT_STATE_DIR': '', 'HOME': str(self.root)}), \
                patch.object(agent, 'run', side_effect=[{'backup_id': 21}, {'ok': True}]) as run:
            agent.run_job(str(self.job), self.client)
        self.assertEqual(run.call_count, 2)
        self.assertTrue((self.root / '.local/state/sa-wp-agent/state.json').is_file())


if __name__ == '__main__':
    unittest.main()
