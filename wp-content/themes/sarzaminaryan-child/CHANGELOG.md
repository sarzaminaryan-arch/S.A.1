# Changelog — sarzaminaryan-child

## 1.0.1 — 2026-09-26

- Data model **v1.1**: `attraction.official_website` (→ JSON-LD `sameAs`) and `attraction.last_verified_date`; new `date` field type (ISO input, Jalali hint, stale warning after `sa_stale_after_days()` = 365).
- Publish gate reads per-entity minimums from the model (`sa_content_minimums()`): FAQ ≥ n, sources ≥ n (lines with a URL before the `---` separator), internal links ≥ n (same-site `<a href>` in content), coordinates required. New non-blocking warnings: uncertainty markers count, missing/stale verification date. Admin badge «بازبینی».
- Visible **منابع** section on entity singles (`sa_sources_section()`), official domains followed, others `nofollow`; text after a `---` line stays private (editor FACT CHECK notes).
- `[نیازمند بررسی]` / `[منبع لازم]` rendered as `<mark class="sa-flag">` badges in content and FAQ answers (Helpful Content: transparency over guessing).
- `sa_transliterate_fa()` for Persian slugs on publish; number formatting drops `.0`; RTL stylesheet appended instead of replaced (parent).

## 1.0.0 — 2026-09-26

First release. Everything the project needs lives in this child theme (no plugins).

- Data model v1.0 wired in: `inc/entities-config.php` is generated from `data-model/schema/data-model.yaml` by `data-model/schema/build_child_config.py` — never edit by hand.
- 6 CPTs + 5 taxonomies, 31 provinces seeded from `data/provinces.php` on activation.
- Meta boxes (fields / relations / SEO / FAQ / sources), R1 denormalisation (city → province), R4 delete guard, province_tax mirroring, reverse lookups.
- Publish gate (Level 7) with hard/soft mode, list-table badge, dashboard widget.
- SEO head, JSON-LD `@graph`, breadcrumbs, sitemap tuning, security hardening, performance tweaks, Jalali dates + Persian digits.
- Front page (hero, stats, provinces, entity sections, latest posts), all singles/archives/taxonomies, 404, search.
- Live-tested on WordPress 7.1.2 / PHP 8.4 with the SQLite drop-in: 30+ front-end and admin views render with zero PHP notices; gate blocked an incomplete attraction; Persian slugs transliterated on publish.
