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
9. **Province prompt v1.2 (2026-09-28).** (a) The history section (H2 3) joins the priority group: 700–1,200 words, a
   continuous summary from prehistory → ancient states ruling the area → Islamic-era dynasties in order (each only if a
   source ties it to this area) → creation of the province in its modern form (year + decree), plus a mandatory dynasty
   table (no minimum row count, every row sourced). (b) The rest of the article focuses on attractions, natural beauty and
   the modern face of the province (new landmark/luxury buildings, traditional bazaars, modern malls) via a fixed fifth H3
   in H2 23 `### بازارها، مراکز خرید و بناهای شاخص امروزی` (no keyword; malls/towers are not entities → bold name +
   citation, no «(به‌زودی)») and new paragraphs in H2 19 and 21; no prices/hours/user ratings. (c) Every statement needs a
   source; when credible layers are empty, Google Maps (`[نقشه]`: existence, location, coordinates, category, distance) and
   lower-credibility identifiable sources (`[غیررسمی]`: the place's own site, news sites, travel sites, blogs, official social
   pages, Wikipedia) are allowed for descriptive content — real opened URLs only, no tag in text, listed in BLOCK 8 «۱-ب».
   Official statistics stay official-only; user reviews/ratings are never a source. (d) The single absolute red line is
   guessing (hedges without a citation, generalising from a similar province, unsourced dynasty attributions) — see
   `content-templates/province.md` §2-ج. `seo_audit.py` prints v1.2 WARN lines (history length, dynasty table, fifth H3,
   unsourced hedges); the 13 pre-v1.2 articles get their history sections expanded in a later `mode: update` pass.
10. **Province content-review policy v1.3 (2026-09-28) — binding for every new article and every review pass.**
   (a) Humanize: the article body must read natural and human; strip all machine/editorial markers — «(به‌زودی)»,
   «[نیازمند بررسی]», «[منبع لازم]», «[URL لازم]», stray «؟» inside names, and v1.0 problem narratives.
   (b) No problem/unreliable-source reports in the article: BLOCK 8 lists verified rows only (no «اختلاف منابع/غیررسمی»
   theatre), BLOCK 9 carries no unofficial-source tag counts; drop tags instead of debating them.
   (c) Community-feedback closing section: every article ends with an H2 inviting locals of that province to report
   mistakes and missing info via [Mail@sarzaminaryan.ir](mailto:Mail@sarzaminaryan.ir); texts fixed by readers after
   publish — this replaces strict pre-publish gatekeeping on descriptive content (official stats stay official-only).
   (d) BLOCK 5 hygiene: every source row must carry a real, opened URL — delete rows without links; verify every URL
   answers HTTP 200 (no 404) before shipping (curl -sI loop or fetch check); every row is cited at least once in text.
   (e) Purpose: reviews stay fast and correct — research → humanize → link-check → audit → status → build.
