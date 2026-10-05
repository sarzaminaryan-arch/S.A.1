# AGENTS.md — lean repository rules for S.A.1

1. **Delivery channel is GitHub only.** Push code to `sarzaminaryan-arch/S.A.1`, then return GitHub links. Installable ZIPs belong in GitHub Releases, not inside the repository tree.
2. **Active code lives in `wp-content/themes/sarzaminaryan-child/`.** Parent-theme edits are allowed only when a parent fix is unavoidable.
3. Obey `data-model/MASTER_DATA_MODEL.md`; names/slugs are append-only and the publish gate remains Level 7.
4. Use `skill-SA-agent/SKILL.md` as the operating procedure for theme work, QA and releases.
5. Persian WordPress/fa_IR/RTL; machine dates stay Gregorian; no external CDNs/plugins.
6. Security baseline: sanitize input, escape output, nonce + capability for writes.
7. Keep this repository lean: do not commit generated ZIPs, image packs, raw media dumps, exported archives, one-off importer sources or one-off delivery files. Store those as GitHub Release assets or external archives.
8. Keep operational docs small and source-like: `docs/`, `data-model/`, `skill-SA-agent/`, `.github/`, the parent theme and the child theme are the retained working set.
9. Before pushing, run relevant checks (`git diff --check`, JS syntax checks, and PHP lint where available). GitHub Actions performs PHP lint for theme releases.
10. Work on Arena's assigned branch for the session; do not switch or push to another branch during an Arena task.
