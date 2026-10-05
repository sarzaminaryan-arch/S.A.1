# Sarzamin Aryan (S.A.1) — project conventions

@AGENTS.md
@data-model/MASTER_DATA_MODEL.md
@skill-SA-agent/SKILL.md

## Commands
- JS syntax: `node --check path/to/file.js`
- PHP lint when PHP is available: `find wp-content/themes/sarzaminaryan-child -name '*.php' -print0 | xargs -0 -n1 php -l`
- Release packaging: GitHub Actions workflow `.github/workflows/release-child-theme.yml`

## Active structure
- `.github/` — release automation for the active theme only.
- `wp-content/themes/sarzaminaryan-child/` — active WordPress child theme and all runtime code.
- `docs/` — small operational documentation only; generated article drafts/assets are not retained.
- `data-model/` — canonical data model and schema source.
- `skill-SA-agent/` — operating procedure and references.

## Repository policy
- Do not commit generated ZIPs, release archives, raw image packs, temporary delivery files or one-off importer source trees after their releases are published.
- Installable ZIPs must be published as GitHub Release assets.
- Keep the tree source-first so Arena and GitHub operations stay light.
