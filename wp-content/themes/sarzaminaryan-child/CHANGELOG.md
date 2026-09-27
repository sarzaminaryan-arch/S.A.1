# Changelog — sarzaminaryan-child

## 1.0.3 — 2026-09-26

- `sa-hero` (1600×700) is now cropped with `array( 'center', 'top' )` and `.sa-entity__hero-media img` gets `object-position: center top`: the province featured posters (`assets/featured/provinces/*.webp`, 1600×900) carry their title in the upper band, so a centred crop cut it off. Run a thumbnail regeneration for images uploaded before this version.
- Companion plugin `wp-content/plugins/sa-province-importer-b01` (batch 01 of the province drafts) writes the same `sa_*` meta keys, `sa_faq` JSON and `sa_sources` text this theme reads; no theme code path changed for it.

## 1.0.2 — 2026-09-26

- **SEO-plugin coexistence** (`inc/schema.php`): with Rank Math / Yoast / AIOSEO / SEOPress / TSF active the theme no longer drops its whole JSON-LD graph. Site-level nodes (Organization, WebSite, BreadcrumbList, BlogPosting/WebPage, CollectionPage) are left to the plugin; the entity node (AdministrativeArea/TouristDestination/… from the meta fields) and FAQPage (from the FAQ box) are still printed, using the same `home_url( '/#organization' )` @id convention so references resolve. `add_filter( 'sa_schema_with_plugin', '__return_false' )` restores the old all-off behaviour. `inc/seo.php` keeps yielding titles/meta/OG to the plugin as before.
- **External links in content** (`sa_content_external_links()`, `the_content` @13): same rel policy as the sources box — `sa_source_rel()` (official domains followed, others `nofollow noopener external`) plus `target="_blank"`; existing `rel`/`target` attributes are respected, same-site links untouched. Needed for the inline citations `<sup>[n](URL)</sup>` the production prompts now place after sourced facts (n = row in the sources box, which renders as an `<ol>`).
- CSS for inline citations (`.entry-content sup a`).

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
