#!/usr/bin/env python3
"""Wait for the latest main push Build gates run on the exact commit, fail closed."""
import json
import os
import subprocess
import sys
import time
from urllib.parse import urlencode


def gate_state(data, sha):
    runs = data.get('workflow_runs')
    if not isinstance(runs, list):
        raise ValueError('missing workflow_runs')
    relevant = [r for r in runs if r.get('head_sha') == sha and r.get('event') == 'push'
                and r.get('head_branch') == 'main']
    if not relevant:
        return 'waiting'
    latest = max(relevant, key=lambda r: (r.get('created_at', ''), r.get('id', 0)))
    if latest.get('status') != 'completed':
        return 'waiting'
    return 'success' if latest.get('conclusion') == 'success' else 'failure'


def main():
    sha, repo = os.environ['GATE_SHA'], os.environ['GATE_REPOSITORY']
    endpoint = f"repos/{repo}/actions/workflows/build-gates.yml/runs?" + urlencode(
        {'head_sha': sha, 'event': 'push', 'branch': 'main', 'per_page': 100})
    deadline = time.monotonic() + 1200
    while True:
        proc = subprocess.run(['gh', 'api', endpoint], text=True, capture_output=True)
        if proc.returncode:
            print('Build gate API request failed; production is blocked.', file=sys.stderr)
            return 1
        try:
            state = gate_state(json.loads(proc.stdout), sha)
        except (ValueError, TypeError, AttributeError) as exc:
            print(f'Invalid build gate response: {exc}', file=sys.stderr)
            return 1
        if state == 'success':
            print(f'Build gates passed for {sha}')
            return 0
        if state == 'failure' or time.monotonic() >= deadline:
            print(f'Build gate {state}; production is blocked for {sha}', file=sys.stderr)
            return 1
        time.sleep(15)


if __name__ == '__main__':
    sys.exit(main())
