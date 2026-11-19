#!/usr/bin/env python3
"""Compare intended WordPress fields with live snapshot without replaying writes."""
import unittest
import json
import os
import tempfile
from pathlib import Path
from unittest.mock import patch
import wp_agent as agent
from write_verification import make_intent, verify_intent


class HashRecoveryTests(unittest.TestCase):
    def test_matching_fields_and_meta_have_same_hash(self):
        intent = make_intent(4, {'fields': {'content': 'سلام'}, 'meta': {'foo': 'bar', 'gone': None}})
        self.assertEqual(verify_intent(intent, {'id': 4, 'content': 'سلام', 'meta': {'foo': 'bar'}}), 'match')
        self.assertNotIn('سلام', str(intent))
        self.assertNotIn('bar', str(intent))

    def test_mismatch_or_missing_fields_never_match(self):
        intent = make_intent(4, {'fields': {'content': 'سلام'}, 'meta': {}})
        self.assertEqual(verify_intent(intent, {'id': 4, 'content': 'تغییر', 'meta': {}}), 'mismatch')
        self.assertEqual(verify_intent(intent, {'id': 4, 'meta': {}}), 'unknown')
        self.assertEqual(verify_intent(intent, {'id': 5, 'content': 'سلام', 'meta': {}}), 'unknown')

    def test_non_comparable_update_is_unknown(self):
        self.assertIsNone(make_intent(4, {'fields': {}, 'meta': {}, 'terms': {'category': [3]}}))
        self.assertIsNone(make_intent(4, {'fields': {'arbitrary': 'x'}, 'meta': {}}))
        self.assertIsNone(make_intent(4, {'fields': {}, 'meta': {}}))


class JournalIntegrationTests(unittest.TestCase):
    def test_interrupted_update_is_hashed_and_inspected_but_stays_locked(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            job = root / 'job.json'
            job.write_text(json.dumps({'steps': [{'cmd': 'update', 'id': 7}]}))
            class Client:
                def __init__(self): self.calls = []
                def call(self, method, path, **kwargs):
                    self.calls.append((method, path))
                    if method == 'POST': raise KeyboardInterrupt()
                    return {'id': 7, 'content': 'سلام', 'meta': {}}
            client = Client()
            def run(args, wrapped):
                wrapped.call('POST', '/post/7', body={'fields': {'content': 'سلام'}, 'meta': {}})
            with patch.dict(os.environ, {'GITHUB_ACTIONS': 'false', 'SA_WP_AGENT_STATE_DIR': str(root / 'state')}), \
                    patch.object(agent, 'RESULTS_DIR', root / 'results'), patch.object(agent, 'run', side_effect=run):
                with self.assertRaises(KeyboardInterrupt): agent.run_job(str(job), client)
                with self.assertRaises(agent.AgentError): agent.run_job(str(job), client)
                state = json.loads((root / 'state/state.json').read_text())
                self.assertEqual(state['active']['verification'], 'match')
                self.assertNotIn('سلام', json.dumps(state, ensure_ascii=False))
                self.assertEqual(client.calls, [('POST', '/post/7'), ('GET', '/post/7')])

    def test_fetch_failure_leaves_unknown_and_blocks(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            job = root / 'job.json'
            job.write_text(json.dumps({'steps': [{'cmd': 'update', 'id': 7}]}))
            class Client:
                def call(self, method, path, **kwargs):
                    if method == 'POST': raise KeyboardInterrupt()
                    raise RuntimeError('network unavailable')
            def run(args, wrapped):
                wrapped.call('POST', '/post/7', body={'fields': {'content': 'x'}, 'meta': {}})
            with patch.dict(os.environ, {'GITHUB_ACTIONS': 'false', 'SA_WP_AGENT_STATE_DIR': str(root / 'state')}), \
                    patch.object(agent, 'RESULTS_DIR', root / 'results'), patch.object(agent, 'run', side_effect=run):
                with self.assertRaises(KeyboardInterrupt): agent.run_job(str(job), Client())
                with self.assertRaises(agent.AgentError): agent.run_job(str(job), Client())
                state = json.loads((root / 'state/state.json').read_text())
                self.assertEqual(state['active']['verification'], 'unknown')


if __name__ == '__main__':
    unittest.main()
