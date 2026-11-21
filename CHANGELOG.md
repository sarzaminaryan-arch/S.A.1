# Changelog

## Unreleased

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
