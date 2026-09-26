# Changelog — Master Data Model

All notable changes to the data model are recorded here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) · Versioning: `MAJOR.MINOR` (MAJOR = breaking change to an active entity/URL, MINOR = additive).

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
- **1.1** — activate `Accommodation` (see Appendix A in `MASTER_DATA_MODEL.md`).
