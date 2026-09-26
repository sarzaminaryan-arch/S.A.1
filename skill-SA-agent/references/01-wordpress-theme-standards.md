# 01 — WordPress theme & code standards (distilled)

Sources: `WordPress/agent-skills` (wordpress-router, wp-block-themes, wp-patterns,
wp-plugin-development, wp-rest-api, wp-performance, wp-wpcli-and-ops, docs/principles),
`awesome-claude-code-subagents/wordpress-master`, quantum-pedia review findings.

## A. Routing (from wordpress-router)

Classify before touching code: `wp-theme` (classic, PHP templates — **this project**),
`wp-block-theme` (theme.json/templates HTML), `wp-plugin`, `wp-site`. Route by intent:
templates/CSS → theme workflow; hooks/CPT/settings/security → plugin-style workflow (applies to
child `functions.php` + `inc/`); REST → rest workflow; slow → performance workflow; WP-CLI ops →
ops safety rules. Always: prefer existing conventions/tooling of the repo; ask for target versions
only if a feature depends on them.

## B. Classic theme essentials (Theme Check / handbook)

- `style.css` header: Theme Name, Theme URI, Author, Author URI, Description, Version, Requires at
  least, Tested up to, Requires PHP, License (GPLv2+), License URI, Text Domain, Tags.
  Child adds `Template: sarzaminaryan`.
- Required calls: `wp_head()` before `</head>`, `wp_footer()` before `</body>`, `wp_body_open()`
  right after `<body>`, `language_attributes()`, `bloginfo('charset')`, `body_class()`,
  `post_class()`, `the_content()`, `comments_template()` where comments apply.
- `add_theme_support`: `title-tag`, `post-thumbnails`, `html5` (all), `automatic-feed-links`,
  `responsive-embeds`, `custom-logo`, `editor-styles`, `customize-selective-refresh-widgets`,
  `align-wide`, `wp-block-styles` (optional). Set `$GLOBALS['content_width']`.
- `screenshot.png` 1200×900. `readme.txt`. `languages/` with `.pot`/`.po`/`.mo` if strings are English.
- Assets via `wp_enqueue_style/script` with version; **child theme** enqueues parent stylesheet
  first then its own with dependency; scripts in footer (`true` or `strategy => 'defer'`).
- Template hierarchy you will use: `front-page.php`, `index.php`, `single.php`,
  `single-{post_type}.php`, `archive-{post_type}.php`, `taxonomy-{taxonomy}.php`, `page.php`,
  `search.php`, `404.php`, `template-parts/content-{post_type}.php` via `get_template_part()`.
- Pluggable parent functions wrapped in `if ( ! function_exists() )` so the child can override.
- `.screen-reader-text` and `:focus-visible` styles are mandatory (accessibility); skip link first
  in `<body>`; `aria-expanded`/`aria-controls` on toggles; one `<h1>` per view.

## C. CPT / taxonomy / meta (plugin-style rules applied in the child)

- Register on `init`; flush rewrite rules **only** on `after_switch_theme` (after registering).
- CPT args to set deliberately: `public`, `has_archive`, `rewrite => ['slug' => L4 slug,
  'with_front' => false]`, `supports`, `menu_icon`, `show_in_rest` (false = classic editor; true =
  block editor + REST), `taxonomies`, labels in Persian.
- Taxonomy: `hierarchical`, `rewrite slug`, `show_admin_column`, `show_in_rest`. Primary taxonomy
  per entity is defined in the model (L2) and enforced at publish time (L7).
- Meta: `register_post_meta()` with `type`, `single`, `sanitize_callback`, `auth_callback`, and
  `show_in_rest` when needed. Meta boxes: nonce (`wp_nonce_field`/`wp_verify_nonce`), capability
  (`current_user_can('edit_post', $id)`), skip autosave/revisions, read explicit `$_POST` keys,
  `wp_unslash()` then sanitize per type, `delete_post_meta` on empty.
- Relations: store IDs (`int`/`int[]`), never titles. Denormalize `province` from `city` on save
  (model rule R1). Compute `has_many` by query (`meta_query` on the child's `sa_*_id`), cache with
  transients keyed by parent ID + `last_changed`.
- Settings/options: Settings API or Customizer with `sanitize_callback`; capability
  `manage_options`/`edit_theme_options`.
- Lifecycle: activation/deactivation are plugin concepts; for themes use `after_switch_theme`
  (seed terms, flush) and `switch_theme` (cleanup, flush). Seeding must be idempotent
  (`term_exists` before `wp_insert_term`).

## D. Security baseline (always)

Sanitize early, escape late, by context: `esc_html`, `esc_attr`, `esc_url`, `esc_textarea`,
`wp_kses_post`, `wp_json_encode` for JSON in `<script>`. Nonce ≠ authorization: pair with
capability checks. `$wpdb->prepare()` for SQL. AJAX: `wp_ajax_*` verify nonce+cap,
`wp_ajax_nopriv_*` treated as hostile. Full checklist: `02-wordpress-security-hardening.md`.

## E. REST (only if enabled later)

Unique namespace `sa/v1`; always `permission_callback`; JSON-schema `args`; never read
`$_GET/$_POST` in endpoints; expose CPT/tax with `show_in_rest` + `rest_base`; `register_rest_field`
for computed fields; don't strip core fields.

## F. Performance (backend-first)

1. Measure before/after (same URL, warmed cache): `curl -w '%{time_starttransfer}'`, Query Monitor,
   `wp profile stage/hook`, `wp doctor check`.
2. Fix the dominant category only: DB (N+1, `fields=>'ids'`, avoid heavy meta queries, cache),
   autoloaded options (`wp option list --autoload=on --format=total_bytes`), object cache
   (transients with invalidation), remote HTTP (timeouts + cache), cron (idempotent, off request path).
3. Frontend quick wins for this theme: no 404 assets (fonts!), one CSS bundle, JS deferred in
   footer, local fonts `font-display: swap` + `preload` for the main woff2, lazy images except LCP
   (featured image `loading="eager"` + `fetchpriority="high"`), remove emoji script,
   `wp-embed` script only if needed, `add_image_size` only for sizes actually used.
4. Never flush caches / enable `SAVEQUERIES` on production without approval.

## G. Block themes & patterns (for future migration — not used now)

theme.json (`settings` = what UI allows, `styles` = defaults), `templates/*.html`, `parts/*.html`
(no nesting), `patterns/*.php` with header `Title/Slug/Categories`; patterns = static block markup,
PHP runs at registration (no runtime queries); use preset slugs; style hierarchy core → theme →
child → user customizations; slug normalisation trap (`3xl` → `3-xl`).

## H. WP-CLI / ops safety

Assume production is unsafe. Confirm `--path`/`--url`. `wp db export` before risky ops.
`wp search-replace OLD NEW --dry-run` first, then real run, then `wp cache flush` + `wp rewrite flush`.
High-risk (need explicit OK): `db reset/import`, `search-replace`, bulk deletes, mass updates.
Scripts: `set -euo pipefail`, print commands, destructive steps behind `--apply`.

## I. Review checklist used on quantum-pedia (reuse for any uploaded theme)

Header/footer wiring · escaping of every echo · i18n + text domain · enqueue order/versions ·
missing CSS for utility classes (`.screen-reader-text`, toggles) · RTL rules that double-flip
(`row-reverse` in RTL is a bug) · dead Customizer settings · duplicate image sizes · missing
`$content_width`, `screenshot.png`, `readme.txt`, `languages/` · asset 404s (fonts) ·
`Tested up to` currency · mobile menu visibility · semantic landmarks · one `<h1>` · pingback/XML-RPC.
