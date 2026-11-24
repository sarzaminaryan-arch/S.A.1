# Changelog

## Unreleased

- رودمپ صفحات نازک شهرستان (۲۰۲۶-۱۱-۲۴): سند تصمیم `docs/live-site/thin-counties-plan-2026-11-24.md` (آستانهٔ مصوب مالک: ۷۰۰ واژه؛ noindex,follow + خروج خودکار از سایت‌مپ؛ بدون 410/301؛ خزش باز) و پیاده‌سازی در قالب فرزند **2.24.22** (`SA_COUNTY_MIN_INDEX_WORDS`، دلیل جدید `low_depth`، برچسب فارسی، تست‌های مرزی ۶۹۵/۷۰۰).
- One-time plugin `sa-editorial-fix` 1.0.0 (stage 2 of `docs/live-site/content-remediation-roadmap.md`): faithful PHP port of `tools/wp-agent/content_fix.py` (note sentences/clauses, note list items, in-page duplicate sentences, summary-item shortening, key-facts table cells, empty blocks/headings) with owner guards — 8% max word-drop (above ⇒ report-only «needs rewrite»), banned-output-words check, full backup before every write, one-click rollback, idempotent dry-run preview on activation; 12 reference-engine fixtures (`tests/fixtures.json`) + `tests/test-engine.php` wired into `build-gates.yml`.
- docs: `content-remediation-roadmap.md` — staged content-remediation roadmap with live counts (13 critical classes / ≈618 units: 249 solved, 21 in stage 2, ≈248 remaining), per-stage test methods and owner actions, exit criteria for moving to new articles.

- Child theme 2.24.10 & Audit Plugin 1.2.1: expose `guide` CPT in `sa_agent_post_types()` and add diff-backed `sync_ready_article` mode (attaching bundled `assets/img/guides/*.webp` covers, FAQs, and updated ready-article bodies under `owner_approved_live_edit`); preserve `wp_slash` on `sa_faq` JSON in `sa_ready_articles_write_meta()` and register `sa-ready-guides` under `edit.php?post_type=guide`; normalize CRLF/LF in `sa-live-article-audit` 1.2.1 canonical meta comparison; add `wp-agent` jobs `20261122-05` and `20261122-06`.
- Child theme 2.24.9 & Audit Plugin 1.2.0: preserve percent-encoded Persian URLs in `sa_sources` and `extra_places`; deduplicate repeated internal body links at render time; round county population `>= 1000` on legacy identity cards; match bare-slug capital counties (`*-city`) without touching 301 redirect sources; add `guide` CPT + numeric coordinate comparison to `sa-live-article-audit` 1.2.0; prepare verified `wp-agent` meta/sources/draft-copy remediation jobs.
- Child theme 2.24.5: keep registered counties in their canonical province despite stale parent metadata; prefer the exact published county over a legacy slug, and avoid duplicate or placeholder geographic links in the 2026 province/county templates.

- LAK-17: restrict WordPress jobs to manual dispatch on main with an explicit job.
- LAK-19: require the exact checked-out commit's successful main-push build gate.
- LAK-14: block child-theme releases on licensed-photo test failures and prevent release from working branches.
- LAK-18: protect front matter, fenced/inline code, and comments during source cleanup.
- LAK-23: remove and renumber grouped citations without dangling separators.
- LAK-20: detect county data changes across the full push or pull request and fail on diff errors.
- LAK-22: install and run cleaner regression tests in build gates.
- LAK-16: add a root contributor guide.

These changes are pending review and CI; they do not claim resolution of the remaining review issues.
