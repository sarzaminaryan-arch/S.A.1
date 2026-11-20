"""Verify a reciprocal CSV contract against captured 2026 theme pages (no requests).

python tools/live-site/check_relation_html.py docs/live-site/province-links/fars-links.csv MIRROR
MIRROR contains e.g. province/fars/index.html and city/shiraz/index.html.
"""
import argparse
import csv
from collections import Counter, defaultdict
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlsplit


def key(url):
    p = urlsplit(url)
    return (p.scheme.lower(), p.netloc.lower(), p.path.rstrip('/'), p.query, p.fragment)


class Links(HTMLParser):
    def __init__(self, source, wanted):
        super().__init__()
        self.source = source
        self.wanted = wanted
        self.marked = Counter()
        self.article = Counter()
        self.in_main = False

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'main':
            self.in_main = True
        if tag == 'a' and 'href' in attrs:
            dest = key(urljoin(self.source, attrs['href'] or ''))
            if 'data-sa-relation' in attrs:
                self.marked[dest] += 1
            if dest in self.wanted and (self.in_main or 'data-sa-relation' in attrs):
                self.article[dest] += 1

    def handle_endtag(self, tag):
        if tag == 'main':
            self.in_main = False


def check(contract, mirror):
    graph = defaultdict(set)
    errors = []
    with contract.open(encoding='utf-8-sig', newline='') as file:
        for row in csv.DictReader(file):
            src, dst = row['مبدأ'], row['مقصد']
            if src == dst or dst in graph[src]:
                errors.append(f'invalid contract edge: {src} -> {dst}')
            graph[src].add(dst)
    if not graph:
        errors.append('empty contract')
    for src, destinations in graph.items():
        for dst in destinations:
            if src not in graph.get(dst, set()):
                errors.append(f'missing reverse contract edge: {dst} -> {src}')
        path = mirror / urlsplit(src).path.strip('/') / 'index.html'
        if not path.is_file():
            errors.append(f'no captured page: {src}')
            continue
        wanted = Counter(key(dst) for dst in destinations)
        links = Links(src, wanted)
        links.feed(path.read_text(encoding='utf-8'))
        links.close()
        for label, counter in [('relation', links.marked), ('article', links.article)]:
            for dst in sorted(wanted.keys() | counter.keys()):
                if wanted[dst] != counter[dst]:
                    errors.append(f'{src} -> {dst[2]} [{label}]: expected {wanted[dst]}, found {counter[dst]}')
    return errors


if __name__ == '__main__':
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('contract', type=Path)
    ap.add_argument('mirror', type=Path)
    args = ap.parse_args()
    problems = check(args.contract, args.mirror)
    for problem in problems:
        print(problem)
    print(f'{len(problems)} discrepancies')
    raise SystemExit(bool(problems))
