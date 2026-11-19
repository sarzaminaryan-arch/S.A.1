#!/usr/bin/env python3
"""Emit changed county slugs for the entire push or pull request; fail closed."""
import os
import re
import subprocess
import sys

PREFIX = 'tools/wp-agent/replace_src/data/'


def git(*args):
    return subprocess.run(['git', *args], check=True, stdout=subprocess.PIPE).stdout


def changed(event, before, head, base):
    if event == 'pull_request':
        start = git('merge-base', base, head).decode().strip()
        args = ['diff', '--name-only', '-z', '--diff-filter=ACMRT', start, head, '--', PREFIX]
    elif event == 'push':
        if not before or re.fullmatch('0+', before):
            args = ['diff-tree', '--root', '--no-commit-id', '--name-only', '-r', '-z', '--diff-filter=ACMRT', head, '--', PREFIX]
        else:
            args = ['diff', '--name-only', '-z', '--diff-filter=ACMRT', before, head, '--', PREFIX]
    else:
        return []  # Manual dispatch runs guards, no changed-page build.
    paths = git(*args).decode('utf-8').split('\0')
    slugs = []
    for path in filter(None, paths):
        if not path.startswith(PREFIX) or not path.endswith('.json'):
            continue
        slug = path[len(PREFIX):-5]
        if not re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', slug):
            raise ValueError(f'Invalid county slug: {slug!r}')
        slugs.append(slug)
    return sorted(set(slugs))


if __name__ == '__main__':
    try:
        print(' '.join(changed(os.environ['EVENT_NAME'], os.getenv('PUSH_BEFORE', ''),
                               os.environ['HEAD_SHA'], os.getenv('BASE_SHA', ''))))
    except (subprocess.CalledProcessError, ValueError, UnicodeError) as exc:
        print(f'Cannot determine changed county files: {exc}', file=sys.stderr)
        sys.exit(1)
