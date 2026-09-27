# AGENTS.md — rules for any AI agent working in this repository

1. **Delivery channel is GitHub only.** The owner (محمدرضا لک) can only download files from
   `https://github.com/sarzaminaryan-arch/S.A.1` (Release assets or raw links). Push first, then
   return links. Installation happens manually via cPanel.
2. Work happens in `wp-content/themes/sarzaminaryan-child/`. Parent theme `sarzaminaryan` only for fixes.
3. Obey `data-model/MASTER_DATA_MODEL.md` (v1.0). Names are append-only. Publish gate = Level 7.
4. Persian WordPress (fa_IR, RTL). Machine dates stay Gregorian. No external CDNs/plugins.
5. Security: sanitize in, escape out, nonce + capability on writes.
6. Use `skill-SA-agent/SKILL.md` as the operating procedure.
7. **Province delivery report.** After every province article, run `python3 content-templates/tools/province_status.py`
   (updates `content/provinces/STATUS.md` + the status block in `content/README.md`) and put the generated
   31-province table (stage ✅ written / 🟡 external draft awaiting editor filter / — unwritten, words, FAQ, sources,
   citations, image, importer batch, next) at the END of the delivery message. External drafts land in
   `content/provinces/drafts/` first; only filter-passed files go to `content/provinces/{slug}.md`.
8. **Province prompt v1.1 (2026-09-27).** Research priority = qualitative sections (culture, legends, dress, music, food,
   souvenirs, villages, attractions, facts, season, itinerary); heritage portals, credible local sources and specialised
   travelogues are allowed to fill gaps instead of repeating `[نیازمند بررسی]`; every article is a stable overview valid
   through September 2026 (Mehr 1405). See `content-templates/province.md` §2-ب.
