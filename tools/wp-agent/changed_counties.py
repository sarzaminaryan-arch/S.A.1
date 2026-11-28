#!/usr/bin/env python3
"""Emit changed county slugs for the entire push or pull request; fail closed."""
import os
import re
import subprocess
import sys

PREFIX = 'tools/wp-agent/replace_src/data/'


def git(*args):
    return subprocess.run(['git', *args], check=True, stdout=subprocess.PIPE).stdout


def commit_exists(sha):
    """آیا این شناسه یک کامیت واقعی و در دسترس است؟

    پس از force-push یا rebase، `github.event.before` می‌تواند به کامیتی اشاره کند
    که دیگر در مخزن نیست. قبلاً `git diff` با این شناسه خطا می‌داد و کل گام
    «build» قرمز می‌شد، در حالی که هیچ فایل شهرستانی تغییری نکرده بود.
    """
    if not sha or re.fullmatch('0+', sha):
        return False
    probe = subprocess.run(['git', 'cat-file', '-e', f'{sha}^{{commit}}'],
                           stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    return probe.returncode == 0


def fallback_base(head):
    """مبنای جایگزین وقتی `github.event.before` دیگر وجود ندارد (force-push/rebase).

    ترتیب اولویت: `origin/main`، `origin/master`، سپس والدِ خودِ head.
    اگر هیچ‌کدام نبود، None برمی‌گردد و caller کل درخت را بررسی می‌کند.
    """
    for ref in ('origin/main', 'origin/master', f'{head}~1'):
        probe = subprocess.run(['git', 'rev-parse', '--verify', '--quiet', f'{ref}^{{commit}}'],
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if probe.returncode == 0:
            return probe.stdout.decode().strip()
    return None


def changed(event, before, head, base):
    if event == 'pull_request':
        start = git('merge-base', base, head).decode().strip()
        args = ['diff', '--name-only', '-z', '--diff-filter=ACMRT', start, head, '--', PREFIX]
    elif event == 'push':
        start = before if commit_exists(before) else fallback_base(head)
        if start:
            args = ['diff', '--name-only', '-z', '--diff-filter=ACMRT', start, head, '--', PREFIX]
        else:
            # هیچ مبنای قابل اتکایی نیست: همهٔ فایل‌های داده در درخت را بررسی کن.
            args = ['ls-tree', '-r', '--name-only', '-z', head, '--', PREFIX]
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
