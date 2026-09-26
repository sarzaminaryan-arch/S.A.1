# 06 — Agent workflow, project config & release procedure

Sources: `zebbern/skills` (claude-code-project: CLAUDE.md rules; goal-creator: `/goal` template,
gold patterns, anti-patterns), `zebbern/claude-code-guide` (skills/agents layout, plan mode,
worktrees), `WordPress/agent-skills/docs` (principles: small composable skills, short SKILL.md,
deterministic scripts, eval scenarios), `repo-audit` (secret scanning).

## A. Project configuration (for Claude Code / Cursor / Codex)

```
S.A.1/
├── CLAUDE.md                 # ≤200 lines: commands, stack, rules; imports @AGENTS.md
├── AGENTS.md                 # shared agent conventions (hard rules incl. GitHub-only delivery)
├── .claude/skills/skill-SA-agent -> ../../skill-SA-agent   (or copy)
├── .claude/agents/*.md       # role cards from references/07-agents-roster.md
└── .claude/rules/            # path-scoped rules, e.g. wp-content/themes/**: PHP standards
```
Template: `assets/CLAUDE.md.template`. Keep personal prefs in `CLAUDE.local.md` (gitignored).
Install skill elsewhere: copy `skill-SA-agent/` into `~/.claude/skills/` or run
`npx skills add sarzaminaryan-arch/S.A.1 --skill skill-SA-agent` (if the repo is public).

## B. Working loop (plan → build → verify → release)

1. **Plan mode first** for anything touching > 3 files: list files, risks, verification.
2. **Small commits** with conventional messages: `feat(child): …`, `fix(parent): …`,
   `docs(model): …`, `chore(release): …`.
3. **Verify** with deterministic checks before claiming done: `php -l`, grep for unescaped
   `echo $`, grep for missing text domain, `git diff --stat` scoped to intended files.
4. **Report** using the SKILL.md verification checklist, findings by severity, links to commits.
5. **Isolation**: for risky refactors use a git worktree/branch, merge via PR.

## C. `/goal` directive (goal-creator master template)

```
## /goal: <Short Name>
**Category**: EXPLORE | DEFINE | PLAN | BUILD | VERIFY | DEPLOY | MONITOR | REFLECT
**Scope**: one line — covers / excludes
### Objective*  one observable end-state sentence
### Success Criteria*  pass/fail bullets (≥3)
### Constraints*  MUST NOT / MUST / LIMIT
### Output Specification*  exact files, formats, names
### Verification Method*  how a second agent confirms without redoing the work
### Failure Modes to Prevent  mode → prevention
```
Gold patterns: end-state first; structured output contract; constraint-first safety; second-agent
test; Given/When/Then for multi-step. Anti-patterns: vague aspiration, process-as-goal,
unverifiable criteria, kitchen-sink scope. Ready-made goals: `assets/goal-templates.md`.

## D. Release procedure (GitHub-only delivery — HARD RULE)

```bash
# 1. version bump + changelog
#    style.css "Version:", CHANGELOG.md entry (date, changes)
# 2. lint
find wp-content/themes -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v "No syntax errors" || true
# 3. build zips (folder name must equal theme slug; no macOS junk)
cd wp-content/themes && zip -rq ../../downloads/sarzaminaryan-vX.Y.Z.zip sarzaminaryan -x '*.DS_Store' '__MACOSX/*'
zip -rq ../../downloads/sarzaminaryan-child-vX.Y.Z.zip sarzaminaryan-child
# 4. commit + push
git add -A && git commit -m "chore(release): themes vX.Y.Z" && git push origin main
# 5. GitHub Release (API, token with Contents: write) + upload assets
#    POST /repos/{owner}/{repo}/releases  {tag_name:"themes-vX.Y.Z", name, body}
#    POST {upload_url}?name=sarzaminaryan-vX.Y.Z.zip  (Content-Type: application/zip)
# 6. Return links:
#    https://github.com/sarzaminaryan-arch/S.A.1/releases/download/themes-vX.Y.Z/<asset>.zip
#    fallback: https://github.com/sarzaminaryan-arch/S.A.1/raw/main/downloads/<asset>.zip
```
Owner installs via cPanel → File Manager → `wp-content/themes/` → Upload zip → Extract,
or WP admin → Appearance → Themes → Add New → Upload Theme. Activate **child**.

## E. Secrets & tokens

Tokens are used only in-memory for the push (`-c credential.helper=…`), never written to files or
`.git/config`. Recommend the owner revoke a token after each session; fine-grained token scoped to
`S.A.1` with Contents: Read & Write. Run a secret scan (`git log -p | grep -E 'github_pat_|ghp_'`)
before publishing the repo.
