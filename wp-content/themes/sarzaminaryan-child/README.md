# Sarzamin Aryan Child — 2.21.0

WordPress child theme for Sarzamin Aryan. It requires WordPress 6.4+, PHP 7.4+, and the parent theme `sarzaminaryan`. No plugin is introduced by this release.

## SEO behavior

- Rank Math remains the final source for the `<title>` tag. A saved Rank Math title (`rank_math_title`) takes precedence; the theme's legacy `sa_seo_title` is used only when Rank Math has no saved title. The same precedence is documented in `inc/rank-math-fixes.php`.
- Rank Math description fallbacks are provided for `/blog/`, `/about/`, `/contact/`, and taxonomy archives only when the saved description is blank or a recognized placeholder. Existing editorial descriptions are retained.
- Empty public post-type/taxonomy archives get `noindex,follow` and are omitted from Rank Math sitemaps. They become indexable again when their first published record appears.
- Static page schema uses `WebPage`, `AboutPage`, and `ContactPage`; duplicate top-level nodes with the same `@id` are removed. Post/article schema is not changed.
- Rendered HTTP links are upgraded only for the explicit allowlist in `inc/https-links.php`. Other hosts remain unchanged and are documented in the repository's pending report. Wikipedia/Wikimedia links are preserved.

## Install

1. Install the parent theme `sarzaminaryan` first; leave it available but inactive.
2. In WordPress, open **Appearance → Themes → Add New → Upload Theme** and upload `sarzaminaryan-child.zip`.
3. Activate **Sarzamin Aryan Child**. Existing settings and content are not rewritten by this release.
4. If a route returns 404 after activation, open **Settings → Permalinks** and save once to refresh rewrite rules.

The installable archive must contain one top-level folder named exactly `sarzaminaryan-child/`. It excludes Git metadata, reports, QA tools, and dependencies.

## Roll back

1. Keep a database and files backup before updating a production site.
2. Upload and activate the previously installed `sarzaminaryan-child` package (or restore its files from the hosting backup).
3. Save **Settings → Permalinks** once if routes changed. This release does not perform destructive content/database migrations, so reverting theme files restores the prior rendering and hooks.

## QA

Standalone theme tests are in `tests/`; run them with PHP CLI, for example:

```sh
php wp-content/themes/sarzaminaryan-child/tests/test-w-citations.php
php wp-content/themes/sarzaminaryan-child/tests/test-rank-math-fixes.php
```

`php -l` and PHPCS should be run against the theme before release. `tools/verify_live.py` is intentionally an owner-run GET-only checker; no live URLs are requested during build/review.
