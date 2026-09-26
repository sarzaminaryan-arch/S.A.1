# Sarzamin Aryan (S.A.1) — project conventions

@AGENTS.md
@data-model/MASTER_DATA_MODEL.md

## Commands
- Lint PHP: `find wp-content/themes -name '*.php' -print0 | xargs -0 -n1 php -l`
- Rebuild schema JSON: `python3 data-model/schema/build_json.py`
- Build theme zips: see skill-SA-agent/references/06-agent-workflow.md §D
- Local WP (optional): `npx @wp-env/cli start` with themes mapped, or WordPress Playground CLI

## Stack
- WordPress 6.5+ (fa_IR, RTL), PHP 8.1+, MySQL 8 / MariaDB 10.6+
- Parent theme `sarzaminaryan` (classic, no build), child `sarzaminaryan-child` (all features)
- No plugins by policy; no external CDNs; fonts bundled (Vazirmatn OFL)

## Project structure
- `data-model/` source of truth · `skill-SA-agent/` operating skill · `wp-content/themes/` themes
- `downloads/` release zips (GitHub is the only delivery channel)

## Code style
- WordPress Coding Standards (tabs, Yoda conditions, spaces inside parens), prefix `sa_` (child) / `sarzaminaryan_` (parent)
- Every string i18n with text domain; every output escaped; every write nonce + capability

## Rules
- Never rename data-model fields/slugs; add + deprecate. Bump version + CHANGELOG.
- Entities: classic editor + meta boxes; publish gate enforces Level 7.
- Dates: DB/JSON-LD Gregorian; display Jalali via `sa_jalali_date()`.
- Deliverables → commit → push → GitHub link. No other channel.

## Git
- Branch `main`; commits: conventional (`feat(child): …`); releases tagged `themes-vX.Y.Z`
