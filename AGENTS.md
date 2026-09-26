# AGENTS.md — rules for any AI agent working in this repository

1. **Delivery channel is GitHub only.** The owner (محمدرضا لک) can only download files from
   `https://github.com/sarzaminaryan-arch/S.A.1` (Release assets or raw links). Push first, then
   return links. Installation happens manually via cPanel.
2. Work happens in `wp-content/themes/sarzaminaryan-child/`. Parent theme `sarzaminaryan` only for fixes.
3. Obey `data-model/MASTER_DATA_MODEL.md` (v1.0). Names are append-only. Publish gate = Level 7.
4. Persian WordPress (fa_IR, RTL). Machine dates stay Gregorian. No external CDNs/plugins.
5. Security: sanitize in, escape out, nonce + capability on writes.
6. Use `skill-SA-agent/SKILL.md` as the operating procedure.
