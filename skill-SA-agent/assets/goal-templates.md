# Ready-made /goal directives for S.A.1

## /goal: publish-entity
**Category**: BUILD → VERIFY  **Scope**: one entity page (any of the 6 CPTs); excludes design changes
### Objective
A published entity page exists at its Level-4 URL with all Level-7 sections and passes the publish gate.
### Success Criteria
- Meta fields for the entity type filled or explicitly null; primary taxonomy set; parent relation set (city→province auto-filled)
- seo_title ≤ 60, seo_description 70–155, focus_keyword present; ≥ 3 FAQ items; featured image with alt
- JSON-LD validates (no errors) in Rich Results Test; breadcrumb correct; ≥ 3 internal links per Level 6
- Persian proofreading pass done; no banned AI openers
### Constraints
- MUST NOT invent facts; MUST cite sources in `sa_sources`; LIMIT intro 120–200 words
### Output Specification
Post ID, permalink, list of filled fields, screenshot of gate = pass
### Verification Method
Second agent loads the URL, runs the SKILL.md SEO checklist, confirms status `publish` and gate log clean

## /goal: child-theme-change
**Category**: BUILD  **Scope**: PHP/CSS/JS change inside `sarzaminaryan-child`; excludes parent
### Objective
The requested feature works on the front end and in admin without notices, following standards.
### Success Criteria
- `php -l` clean; `WP_DEBUG` shows no notices on affected screens
- Nonce + capability on writes; escaped output; strings i18n
- RTL and mobile verified; CHANGELOG + version bumped
### Constraints
- MUST NOT add plugins or external assets; MUST keep `sa_` prefix; LIMIT one feature per commit
### Output Specification
Commit hash(es), files changed, how to test in 3 steps
### Verification Method
Reviewer reproduces the 3 test steps on a fresh WP fa_IR install with only the two themes

## /goal: release-to-github
**Category**: DEPLOY  **Scope**: packaging + publishing themes; excludes content
### Objective
Owner can download installable zips from GitHub links and install via cPanel without edits.
### Success Criteria
- Zip root folder equals theme slug; contains `style.css` header, `screenshot.png`, no `__MACOSX`
- Assets attached to a GitHub Release tagged `themes-vX.Y.Z`; fallback raw link in `downloads/`
- Links returned in the final message and recorded in CHANGELOG
### Constraints
- MUST NOT deliver via any channel other than GitHub; MUST NOT commit tokens
### Verification Method
`curl -I` on release asset URL (authenticated) returns 302→200; unzip -l shows expected tree
