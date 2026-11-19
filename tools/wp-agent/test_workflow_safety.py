#!/usr/bin/env python3
"""Offline regression checks for production workflow entry points."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class ProductionWorkflowTests(unittest.TestCase):
    def test_root_readme_points_to_operational_guides(self):
        readme = (ROOT / 'README.md').read_text(encoding='utf-8')
        for path in ('AGENTS.md', 'tools/wp-agent/README.md', 'ai_cleaner/README.md',
                     'docs/repository-retention-policy.md'):
            self.assertIn(path, readme)
            self.assertTrue((ROOT / path).is_file())

    def test_secret_steps_are_explicitly_guarded_by_main(self):
        workflow = (ROOT / '.github/workflows/wp-agent.yml').read_text(encoding='utf-8')
        for name in ('Check secrets are set', 'Run pending jobs'):
            step = workflow.split(f'      - name: {name}\n', 1)[1].split('\n      - name:', 1)[0]
            self.assertIn("        if: ${{ github.ref == 'refs/heads/main' }}", step)
            self.assertIn('${{ secrets.SA_APP_PASSWORD }}', step)

    def test_agent_is_manual_main_only(self):
        workflow = (ROOT / '.github/workflows/wp-agent.yml').read_text(encoding='utf-8')
        triggers = workflow.split('\non:\n', 1)[1].split('\npermissions:', 1)[0]
        self.assertIn('  workflow_dispatch:', triggers)
        self.assertNotIn('  push:', triggers)
        self.assertNotIn('  schedule:', triggers)
        self.assertIn("if: ${{ github.ref == 'refs/heads/main' }}", workflow)
        self.assertIn('ref: ${{ github.sha }}', workflow)
        self.assertIn('INPUT_JOB:-', workflow)
        self.assertNotIn('jobs=$(ls tools/wp-agent/jobs/', workflow)


if __name__ == '__main__':
    unittest.main()
