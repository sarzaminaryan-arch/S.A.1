# SOURCES — provenance of skill-SA-agent

All nine forks on `github.com/sarzaminaryan-arch` (cloned at fork time, 2026-09-26) were read.
"Used" = what was distilled and where; "Skipped" = deliberately left out and why.

| # | Fork (upstream) | Size | Used → where | Skipped (why) |
|---|---|---|---|---|
| 1 | `agent-skills` ← **WordPress/agent-skills** ★2.2k | 188 files, 18 skills | Router/triage mindset, block-theme & pattern rules, plugin-dev security/lifecycle/settings, REST rules, performance method, WP-CLI safety, authoring principles (short SKILL.md, refs one hop away, verification/failure sections) → `references/01`, `02`, `06`, `SKILL.md` structure | Abilities API, Playground/Blueprint, PHPStan, plugin-directory guidelines, Interactivity API (not needed for a single classic-theme site; revisit if migrating to blocks/REST) |
| 2 | `wordpress-fa` ← **wpvar/wordpress-fa** ★3 | full WP 5.8 + wp-shamsi 4.1 | Jalali hook strategy (`wp_date`/`date_i18n`), machine-format skip list, Persian digit conversion, Gregorian→Jalali algorithm, "auto-activate via mu-plugin" idea → `references/03` | The WordPress 5.8 core itself (obsolete/insecure); `wp_checkdate` override & saving Jalali post dates (corrupts date math); WooCommerce/Elementor/Gravity compat; "disable copy/admin bar" addons |
| 3 | `awesome-claude-code-subagents` ← **VoltAgent** ★25k | 100+ agent cards | Role-card format + checklists from wordpress-master, seo-specialist, content-quality-editor, accessibility-tester, security-auditor, php-pro, prompt-engineer, project-manager → `references/07`, `04`, `05` | ~90 agents for other stacks (Rust, K8s, fintech, games, ML infra) |
| 4 | `claude-code-guide` ← **zebbern** ★4.6k | guide + 74 skills | Skills/agents layout, plan mode, worktrees, settings precedence → `references/06`; `wordpress-penetration-testing` inverted into a hardening table, `secure-code-review` OWASP SOP, `localization-toolkit` hygiene → `references/02`, `03`; `repo-audit` secret scan → `06 §E` | Offensive tooling details (WPScan/Metasploit usage), pentest skills for other services, React/Three.js skills, CLI env-var catalogue |
| 5 | `skills` ← **zebbern/skills** ★29 | 2 skills | `claude-code-project` (CLAUDE.md best practices, verify/check workflows) → `assets/CLAUDE.md.template`, `06 §A`; `goal-creator` master template, gold patterns, anti-patterns → `06 §C`, `assets/goal-templates.md` | Category deep-dives beyond what the templates need |
| 6 | `prompts.chat` ← **f/prompts.chat** ★171k | 2,169 prompts (521 MB repo) | Travel Guide, SEO Prompt, Title Generator, FAQ Generator, Proofreader, Translator patterns — rewritten in Persian for entities → `references/04 §F`, `05 §B` | Everything unrelated to travel/SEO/content (2,100+ prompts); the web app itself. Repo not cloned (only `prompts.csv` + README fetched) |
| 7 | `AI-Research-SKILLs` ← **Orchestra-Research** ★13k | 98 skills (ML research infra) | One idea: schema-constrained structured output (instructor/outlines/guidance/DSPy) for entity JSON generation → `references/05 §A`; skill packaging conventions confirm the Agent Skills format | Training/fine-tuning/RLHF/inference/serving/eval harnesses/multimodal (out of scope for a WordPress content site) |
| 8 | `PraisonAI` ← **MervinPraison** ★9k | agent framework (110 MB) | Concept only: agents + tasks defined declaratively (YAML) → mirrored by the role roster + `/goal` files | Framework code, SDKs, UI — the project does not run an agent framework. Only README fetched |
| 9 | `awesome_ai_agents` ← **jim-schwoebel** ★2k | link list (350 KB README) | Nothing actionable; kept as a reading list reference | Entire list (catalogue of tools, no procedures). Only README fetched |

## Also bound in

- `data-model/MASTER_DATA_MODEL.md` v1.0 (owner-authored) — Levels 1–7 drive every rule about entities.
- Review of the uploaded `quantum-pedia.zip` (owner's parent theme) — findings became checklist items in `references/01 §I` and were fixed in `wp-content/themes/sarzaminaryan`.
- Owner directives recorded as HARD RULES: GitHub-only delivery; Persian WordPress; everything in the child theme; theme name/author.

## Licenses of extracted material

WordPress/agent-skills — GPL-2.0+/MIT (docs); wp-shamsi — GPLv3 (algorithm re-expressed, no code
copied verbatim); awesome-claude-code-subagents — MIT; zebbern repos — MIT; prompts.chat — CC0;
Vazirmatn font (bundled in theme) — OFL 1.1. This skill package: GPL-2.0-or-later (to match WordPress themes).
