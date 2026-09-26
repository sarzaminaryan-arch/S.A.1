# Changelog — sarzaminaryan-child

## 1.0.0 — 2026-09-26

First release. Everything the project needs lives in this child theme (no plugins).

- Data model v1.0 wired in: `inc/entities-config.php` is generated from `data-model/schema/data-model.yaml` by `data-model/schema/build_child_config.py` — never edit by hand.
- 6 CPTs + 5 taxonomies, 31 provinces seeded from `data/provinces.php` on activation.
- Meta boxes (fields / relations / SEO / FAQ / sources), R1 denormalisation (city → province), R4 delete guard, province_tax mirroring, reverse lookups.
- Publish gate (Level 7) with hard/soft mode, list-table badge, dashboard widget.
- SEO head, JSON-LD `@graph`, breadcrumbs, sitemap tuning, security hardening, performance tweaks, Jalali dates + Persian digits.
- Front page (hero, stats, provinces, entity sections, latest posts), all singles/archives/taxonomies, 404, search.
- Live-tested on WordPress 7.1.2 / PHP 8.4 with the SQLite drop-in: 30+ front-end and admin views render with zero PHP notices; gate blocked an incomplete attraction; Persian slugs transliterated on publish.
