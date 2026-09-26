---
name: skill-SA-agent
description: >
  Use for ANY work on the Sarzamin Aryan project (sarzaminaryan.ir) — the Persian WordPress
  travel knowledge-graph site (provinces, cities, attractions, routes, local foods, souvenirs,
  reserved accommodation). Triggers: WordPress child-theme code, CPT/taxonomy/meta changes,
  templates, RTL/Persian/Jalali, SEO/schema/Google standards, AI content generation for entities,
  security/performance hardening, releases to GitHub, or reviewing uploaded theme files.
  Distilled from the owner's GitHub forks (WordPress/agent-skills, wordpress-fa, subagents,
  claude-code-guide, zebbern/skills, prompts.chat, …) and bound to data-model/MASTER_DATA_MODEL.md.
metadata:
  author: "محمدرضا لک (Mohammadreza Lak) — assembled by Arena.ai Agent Mode"
  version: "1.0"
  project: "S.A.1 / sarzaminaryan.ir"
compatibility: "WordPress 6.5+ (PHP 7.4+, PHP 8.x preferred). Classic parent theme `sarzaminaryan` + child `sarzaminaryan-child`. No build step, no external CDN."
---

# skill-SA-agent — Sarzamin Aryan project operating skill

Single skill that tells an AI agent **how to work on this project correctly**. Depth lives in
`references/` (one hop away). Source-of-truth documents it must obey:

| Document | Role |
|---|---|
| `data-model/MASTER_DATA_MODEL.md` | Entities, fields, relations, URLs, SEO fields, publish rules (Levels 1–7) |
| `data-model/schema/data-model.yaml` | Machine-readable mirror of the model |
| `wp-content/themes/sarzaminaryan/` | Parent theme (reviewed fork of quantum-pedia) |
| `wp-content/themes/sarzaminaryan-child/` | **All project work happens here** |
| `skill-SA-agent/SOURCES.md` | Where each rule came from |

## HARD RULES (never violate)

1. **Delivery channel = GitHub only.** The owner can download files *only* from GitHub
   (`https://github.com/sarzaminaryan-arch/S.A.1`). Every deliverable must be committed/pushed
   and returned as a GitHub link (release asset or `raw`/`blob` URL). Never say "download from
   the workspace" or attach files elsewhere. Installation on the server is done manually via
   cPanel from that GitHub download.
2. **Everything in the child theme.** Features that plugins normally provide (CPTs, taxonomies,
   meta fields, SEO tags, schema, Jalali dates, security, performance) are implemented in
   `sarzaminaryan-child`. Parent theme is only touched for bug fixes / standards.
3. **Persian WordPress (fa_IR, RTL).** Official WordPress fa_IR build from fa.wordpress.org
   (never the old wordpress-fa 5.8 fork). All visible strings Persian; machine-readable dates
   stay Gregorian ISO-8601 (see `references/03-persian-rtl-jalali.md`).
4. **Data model is law.** Do not invent entities/fields/URLs. Names are append-only; bump the
   model version + CHANGELOG on any change. Publish-gate rules (Level 7) are enforced in code.
5. **Security baseline on every PHP change:** sanitize on input, escape on output, nonce +
   capability on every write, `$wpdb->prepare()` for SQL, no direct `$_POST` arrays.
6. **No external assets** (fonts, CSS, JS from CDNs). Bundle locally with licenses.
7. **Theme identity:** Theme Name `Sarzamin Aryan` (slug `sarzaminaryan`), Author
   `محمدرضا لک`, Text Domain `sarzaminaryan` (child: `sarzaminaryan-child`), function prefix
   `sarzaminaryan_` (parent) / `sa_` (child).

## When to use / inputs required

- Repo root (this repository) and which theme directory is targeted.
- Task type (see routing table). If unclear, ask **one** question, then proceed.
- Target WP/PHP versions if a feature depends on them (default: WP 6.5+, PHP 8.1).

## Procedure — route by task

| Task | Read first | Then |
|---|---|---|
| Add/change CPT, taxonomy, meta, relation | `data-model/MASTER_DATA_MODEL.md` L1–L4, `references/01-wordpress-theme-standards.md` §CPT | edit `inc/post-types.php`, `inc/taxonomies.php`, `inc/meta-fields.php` in child; keep slugs per L4 |
| Template / page / CSS work | `references/01-wordpress-theme-standards.md` §Templates, §CSS/RTL | override in child; classic template hierarchy; logical CSS properties |
| SEO, schema, Google | `references/04-seo-schema-google.md` | `inc/seo.php`, `inc/schema.php`, `inc/breadcrumbs.php`; validate with Rich Results Test |
| Persian / dates / digits / fonts | `references/03-persian-rtl-jalali.md` | `inc/jalali.php`; never convert `datetime=` or JSON-LD dates |
| Security / hardening / review | `references/02-wordpress-security-hardening.md` | `inc/security.php`; run the review checklist and report by severity |
| Performance | `references/01-wordpress-theme-standards.md` §Performance | measure first (baseline), one bottleneck at a time |
| Generate entity content with AI | `references/05-content-pipeline.md` | produce all 5 sections (L7); structured JSON per schema; run quality pass |
| Multi-step / big task | `references/06-agent-workflow.md`, `assets/goal-templates.md` | write a `/goal`, plan → build → verify → release |
| Delegate roles | `references/07-agents-roster.md` | pick role card; keep hard rules |
| Release / hand-off | `references/06-agent-workflow.md` §Release | zip theme(s) → commit → push → GitHub Release → return links |

### Standard change loop

1. **Triage**: identify theme dir, hook points, existing conventions (`sa_` prefix, `inc/` modules).
2. **Plan**: list files to touch; check the model for names/slugs; check Level 7 impact.
3. **Build**: small, prefixed functions; `function_exists` guards only for pluggable parent functions; i18n every string (`sarzaminaryan-child`).
4. **Verify** (minimum): `php -l` on every changed PHP file; no PHP notices with `WP_DEBUG`;
   URLs resolve after `flush_rewrite_rules()` (done on `after_switch_theme`); escaping present;
   RTL renders; schema validates; mobile menu works.
5. **Release**: bump version in `style.css`, update `CHANGELOG.md`, zip, push, Release, links.

## Verification checklist (copy into the final report)

- [ ] `php -l` clean on all changed files
- [ ] Theme Check essentials: `style.css` header, `screenshot.png`, `wp_head`/`wp_footer`/`wp_body_open`, `title-tag`, `$content_width`, escaping, i18n, no hard-coded scripts
- [ ] Data-model conformance: slugs/URLs (L4), relations (L3), publish gate (L7)
- [ ] SEO: one `<h1>`, title/description, canonical, OG, JSON-LD valid, breadcrumbs, `noindex` on search/thin pages
- [ ] RTL/Persian: `dir="rtl"` from `language_attributes()`, fonts local, digits/Jalali only in display strings
- [ ] Security: nonce+cap on writes, sanitized/escaped, XML-RPC off, no user enumeration
- [ ] Performance: no 404 assets, fonts `font-display: swap`, JS in footer, images lazy except LCP
- [ ] Delivered via GitHub link(s)

## Failure modes

- **Fatal on activation** → missing `require` path, function redeclare (parent/child), PHP version syntax. Check `wp-content/debug.log`.
- **404 on entity URLs** → rewrite rules not flushed; slugs mismatch with L4; `has_archive` missing.
- **Meta not saving** → nonce/cap check failing, autosave/revision not skipped, wrong `$_POST` key.
- **Styles “ignored”** → child `style.css` enqueued before parent; caching; RTL file not loaded.
- **Schema errors** → dates converted to Jalali; missing `image`; wrong `@type`.
- **Owner cannot download** → you did not push / did not provide a GitHub link. Fix immediately.

## Escalation

Ask the owner only when: a decision changes the data model, a design/brand judgment is needed,
or a destructive operation (search-replace, DB import, deleting content) is required.
