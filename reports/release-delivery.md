# 2.21.0 delivery status

- Draft PR: https://github.com/sarzaminaryan-arch/S.A.1/pull/22 (head: `arena/938cb410-s-a-1`; no merge).
- Draft GitHub Release: `sarzaminaryan-child-v2.21.0` (not published).
- Installable archive: `dist/sarzaminaryan-child-v2.21.0.zip` (26,368,049 bytes; 477 files; internal folder exactly `sarzaminaryan-child/`). Its filename matches `inc/github-updater.php` and `.github/workflows/release-child-theme.yml`.
- Direct GitHub download (private-repository access required): https://github.com/sarzaminaryan-arch/S.A.1/raw/4d12596a0c61294abdb68b082078e27af0c90a57/dist/sarzaminaryan-child-v2.21.0.zip
- SHA-256: `7cce16250ab334b5f481901df63a8f0079b8d10713c6386da99a441cfc8d8707`.
- Root cause of WordPress remaining on 2.20.0: the updater deliberately ignores draft releases and requires a published, non-prerelease asset named exactly `sarzaminaryan-child-v2.21.0.zip`; GitHub currently reports 2.20.0 as Latest, while the 2.21.0 draft has no asset. Direct asset uploads failed with EOF from `uploads.github.com`.
- GitHub Actions run `38954782266` also failed at the pre-release ready-guides consistency check, before ZIP build/publish; `python3 tools/content-lint/build_theme_data.py --check` reports that `data/ready-guides.php` differs from the tracked guide articles. No publish step ran. Publishing is intentionally left disabled because the requested release state is Draft.
- Once the owner uploads this correctly named ZIP to the draft and approves publishing, WordPress can detect it; use the theme's “Check now” action to clear the six-hour release cache.
- No live-site or external-host requests were sent. `tools/verify_live.py` is owner-run only and requires an explicit authorization flag.
