# Changelog — Master Data Model

All notable changes to the data model are recorded here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) · Versioning: `MAJOR.MINOR` (MAJOR = breaking change to an active entity/URL, MINOR = additive).

## [1.3] — 2026-10-11

### Changed
- **Level 7 — publishing policy (owner decision 2026-10-11).** `content_rules.publish_policy: owner_approval`, `content_rules.enforcement: advisory`. The owner's explicit approval is the **only** publish condition. All Level 7 checks (`publish_blockers`, `minimums`, reserved `accommodation_publish_blockers`) are still evaluated and shown, but only as reminders/warnings — they never block, demote or delay a publish the owner approved.
- `publish_blockers` keeps its name and items (append-only rule); it is now read as the list of Level 7 reminders.

### Notes
- MINOR, policy-only: no entity, field, slug, URL or relation change. Child theme 2.12.7 implements it: publish gate defaults to the advisory («یادآوری») mode, a one-time migration moves sites off the old blocking default, and the ready-articles tool publishes on «تأیید مالک». The legacy blocking («سخت‌گیر») mode stays as an explicit Customizer opt-in; a published page is never demoted in any mode.

## [1.2] — 2026-10-07

### Notes
- Version number used by the child theme (`SA_MODEL_VERSION` 1.2 in theme 2.11.36) to align with the publish-gate code. No schema change was recorded in this changelog; the proposed 1.2 fields (`attraction.national_registration_number`, … ) remain **not applied**. Superseded by 1.3.

## [1.1] — 2026-09-26

### Added
- **Attraction** fields `official_website` (url, nullable → JSON-LD `sameAs`) and `last_verified_date` (date, nullable; staleness > `stale_after_days` = 365 flags «needs review»). Requested by the owner so the agent can detect outdated hours / prices / access restrictions.
- **Level 7** — `content_rules.minimums` per entity (FAQ, sources, internal links, coordinates), three new publish blockers (`missing_coordinates`, `missing_sources`, `missing_internal_links`), `uncertainty_markers` (`[نیازمند بررسی]`, `[منبع لازم]` — allowed, badged, counted) and `stale_after_days`.
- Production prompts: `content-templates/province.md`, `city.md`, `attraction.md` (owner packages #1–#3).

### Notes
- Additive only (MINOR bump). No slug/URL/relation change. Child theme regenerates `inc/entities-config.php` from the YAML (`schema/build_child_config.py`).
- Proposed for 1.2 (not applied, awaiting approval): `attraction.national_registration_number`, `attraction.national_registration_date`, `attraction.unesco_year`, `attraction.unesco_url`, `attraction.area_m2`, `attraction.elevation_m`; `last_verified_date` for all entities.

## [1.0] — 2026-09-26

### Added
- **Level 1** — six active core entities: `Province`, `City`, `Attraction`, `TravelRoute`, `LocalFood`, `Souvenir` (CPT, primary key, fields, relations).
- **Level 1** — reserved seventh entity **`Accommodation`** (`hotel`, `eco_lodge`, `guest_house`, `traditional_house`, `camping`) with locked field names, `belongs_to -> City`, `near_many -> Attraction`, and reserved inverse relation slots on Province / City / Attraction / TravelRoute. Not built in v1.0.
- **Level 2** — taxonomies `province_tax`, `attraction_type`, `travel_season`, `travel_budget`, `travel_duration`; reserved `accommodation_type`; primary taxonomy defined per entity.
- **Level 3** — relation rules + integrity rules R1–R4.
- **Level 4** — URL structure for all entities; reserved `/accommodation/{slug}` and hub page `/city/{slug}/where-to-stay`; slug rules.
- **Level 5** — eight required SEO fields for every entity; reserved sponsored-link rules for Accommodation.
- **Level 6** — internal link graph, including reserved accommodation links.
- **Level 7** — AI content rules: five mandatory sections and five publish blockers; reserved extra blockers for Accommodation.
- `schema/data-model.yaml` + generated `schema/data-model.json` + `schema/build_json.py`.
- `raw/master-data-model-v1.0.original.txt` — original delivered text preserved verbatim.

### Planned (not yet scheduled)
- Activate `Accommodation` only after explicit approval. At implementation, assign the next approved MINOR version, update the model/schema and regenerate the child config. Version `1.2` remains proposed and is not canonical until approved.
