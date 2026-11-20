"""Report candidate stale county counts in province copy. Read-only; no replacements."""
from collections import Counter
import re

from audit_province_links import load


def main():
    profiles = load('wp-content/themes/sarzaminaryan-child/data/province-profiles.json')['provinces']
    records = load('docs/live-site/registry/live-articles.json')['records']
    guides = Counter(r['province'] for r in load('tools/wp-agent/template_worklist.json') if r['status'] == 'publish')
    print('| استان | فیلد | عبارت ثبت‌شده | شمار پروفایل (سال نقشه) | راهنماهای منتشرشده در فهرست کار |')
    print('|---|---|---|---|---|')
    for r in records:
        if r['post_type'] != 'province' or r['status'] != 'publish':
            continue
        profile = profiles[r['slug']]
        count = profile['stats']['county_count']
        for field in ('excerpt', 'sa_seo_title', 'sa_seo_description', 'sa_og_title', 'sa_og_description'):
            text = (r if field == 'excerpt' else r['seo']).get(field) or ''
            for match in re.finditer(r'(?<!\d)(\d+)\s+شهرستان\b', text):
                if int(match[1]) != count or int(match[1]) != guides[r['slug']]:
                    print(f"| {profile['name']} | `{field}` | {match[0]} | {count} ({profile['map_year']}) | {guides[r['slug']]} |")


if __name__ == '__main__':
    main()
