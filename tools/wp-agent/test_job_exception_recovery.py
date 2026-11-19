#!/usr/bin/env python3
"""Targeted offline tests for exception recovery; no network or production access."""
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import wp_agent as agent


class RecoveryTests(unittest.TestCase):
    def test_unexpected_exception_records_failure_and_restores_backups(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            job = root / 'job.json'
            job.write_text(json.dumps({'rollback_on_fail': True, 'steps': [
                {'cmd': 'update', 'id': 1}, {'cmd': 'update', 'id': 2}, {'cmd': 'ping'}]}))
            class Client:
                def __init__(self): self.restored = []
                def call(self, method, route, **kwargs):
                    self.restored.append(route)
                    return {'restored': route}
            client = Client()
            with patch.dict('os.environ', {'GITHUB_ACTIONS': 'false', 'SA_WP_AGENT_STATE_DIR': str(root / 'state')}), patch.object(agent, 'RESULTS_DIR', root / 'results'), patch.object(
                    agent, 'run', side_effect=[{'backup_id': 101}, {'backup_id': 102}, RuntimeError('unexpected')]):
                report = agent.run_job(str(job), client)
            self.assertFalse(report['ok'])
            self.assertIn('RuntimeError', report['steps'][-1]['error'])
            self.assertEqual(client.restored, ['/restore/102', '/restore/101'])
            self.assertFalse(json.loads((root / 'results' / 'job.json').read_text())['ok'])

    def test_rollback_continues_after_unexpected_restore_error(self):
        class Client:
            def call(self, method, route, **kwargs):
                if route == '/restore/2': raise RuntimeError('restore failed')
                return {'restored': route}
        result = agent.rollback(Client(), [1, 2])
        self.assertIn('restore failed', result[0]['error'])
        self.assertEqual(result[1]['backup_id'], 1)


if __name__ == '__main__':
    unittest.main()
