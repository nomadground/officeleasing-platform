# Migration Handoff — OFFICE LEASING / Leasing Flyer

## Purpose

Move only the OFFICE LEASING website code and Leasing Flyer work from the existing repository into this repository, while leaving all NMD data, branches, documents, and code unchanged in the original repository.

## Source repository

- Repository: `nomadground/nmd-company-os`

## Target repository

- Repository: `nomadground/officeleasing-platform`

## Branches to copy

Copy these branches from the source repository to the target repository using the same branch names:

1. `officeleasing`
2. `claude/leasing-flyer-mvp-analysis-wt2g7k`
3. `claude/mobile-layout-print-fixes-tfils7`

This is a copy operation, not a move.

## Non-negotiable preservation rule

The source repository must remain unchanged.

Do not:

- delete or rename source branches;
- remove NMD files;
- rewrite source history;
- force-push to the source repository;
- modify NMD Blueprint, Business Plan, Nest, Residence, or company operating-system data;
- merge any of the three branches in the source repository.

## Target repository rules

- Keep the existing `main` branch and its `README.md`, `CLAUDE.md`, and `AGENTS.md`.
- Do not overwrite or force-push `main`.
- Create the three copied branches alongside `main`.
- Preserve each branch's complete snapshot and Git history when possible.
- Do not merge the copied branches together during migration.
- Do not open a production merge PR until the branch comparison and scope audit are complete.

## Scope to retain after migration

The target repository is intended to contain:

- `officeleasing-core`;
- the GeneratePress child theme used by officeleasing.co.kr;
- HINT Leasing Flyer / Leasing Flyer plugin code;
- related tests, project documentation, and deployment scripts;
- only the shared helpers and assets directly required by those components.

## Out of scope

Do not intentionally migrate:

- NMD Blueprint or Business Plan documents;
- NMD Nest or NMD Residence code;
- NMD corporate operating-system files;
- unrelated experiments or prototypes;
- credentials, API keys, `.env`, `wp-config.php`;
- database dumps, logs, caches, generated ZIP archives, or local backups.

Because the requested branches may still contain mixed NMD history, first preserve the branches exactly. Perform cleanup later in a dedicated branch or PR after the migration is verified. Do not destructively remove files during the initial copy.

## Required migration procedure

1. Confirm all three source branches exist.
2. Record each source branch's current head commit SHA.
3. Add `nomadground/officeleasing-platform` as an additional Git remote.
4. Push each source branch to the target repository under the same branch name.
5. Confirm each target branch head SHA matches its source branch head SHA.
6. Confirm target `main` was not overwritten.
7. Confirm the source repository working tree, branches, and remote history were not changed.
8. Run a basic secret scan or at minimum inspect tracked files for obvious credentials before any later merge.
9. Report the result before beginning branch cleanup or integration.

## Mandatory staged save / checkpoint policy

Do not perform the migration or later cleanup as one large, unrecorded operation.

At the end of every phase below, stop, verify, and save the result before continuing:

### Checkpoint 1 — Source audit

- Confirm the three source branches exist.
- Record the head SHA for each branch.
- Record whether the source working tree is clean.
- Save the audit result in the task report or a dedicated migration note.
- Do not change source files or source branch history.

### Checkpoint 2 — Branch copy

- Copy one branch at a time.
- Verify the target branch immediately after each push.
- Confirm the target head SHA matches the source head SHA.
- Do not wait until all three branches are copied before checking the first branch.

Recommended order:

1. `officeleasing`
2. `claude/leasing-flyer-mvp-analysis-wt2g7k`
3. `claude/mobile-layout-print-fixes-tfils7`

### Checkpoint 3 — Migration verification

- Confirm all three branches exist in the target repository.
- Confirm target `main` is unchanged.
- Confirm the source repository is unchanged.
- Record branch names and SHAs in the completion report.
- Stop here and request review before cleanup or integration.

### Checkpoint 4 — Clean baseline preparation

Only after migration approval:

- Create a separate cleanup or integration branch.
- Do not clean directly on `main` or on the preserved copied branches.
- Commit work in small logical units.
- Each commit should represent one purpose only, such as:
  - remove NMD-only files;
  - normalize repository paths;
  - import OfficeLeasing Core;
  - import the GeneratePress child theme;
  - import Leasing Flyer;
  - add or update tests;
  - update documentation.
- Run relevant checks after each commit.
- Push after each verified milestone so completed work is not left only in a local working tree.

### Checkpoint 5 — Feature development

For future OfficeLeasing development:

- Work only on a dedicated `feature/*` or `fix/*` branch.
- Divide large work into phases before editing.
- At the end of each phase:
  1. review changed files;
  2. run available syntax/tests;
  3. create a focused commit;
  4. push the branch;
  5. report completed scope, remaining scope, and risks.
- Do not combine unrelated features, migrations, refactors, and bug fixes in one commit.
- Do not leave a large completed change uncommitted while starting the next phase.

## Verification checklist

- [ ] `officeleasing` exists in the target repository.
- [ ] `claude/leasing-flyer-mvp-analysis-wt2g7k` exists in the target repository.
- [ ] `claude/mobile-layout-print-fixes-tfils7` exists in the target repository.
- [ ] Each copied branch head SHA matches the source.
- [ ] Target `main` still contains the repository instruction files.
- [ ] No source branch was deleted, renamed, reset, or force-pushed.
- [ ] No NMD data was removed from the source repository.
- [ ] No branches were merged during migration.
- [ ] No secrets or deployment credentials were intentionally introduced.
- [ ] Each migration phase was verified and recorded before the next phase began.
- [ ] Later cleanup and feature work use small commits and milestone pushes.

## Post-migration analysis

After all three branches are present in this repository, analyze them before integration:

1. Find their common ancestor and branch relationships.
2. Compare `officeleasing` against both Claude branches.
3. Identify which branch contains the latest complete Leasing Flyer state.
4. Identify OfficeLeasing Core and child-theme changes independently from Flyer changes.
5. Detect NMD-only files that should remain out of the eventual clean baseline.
6. Propose an integration plan with small, reviewable commits or PRs.
7. Do not merge or delete anything until the plan is reviewed.

## Expected completion report

Report:

- source and target repository names;
- all copied branch names;
- source and target head SHA for each branch;
- whether history was preserved;
- whether `main` remained unchanged;
- whether the source repository remained unchanged;
- secret-check result;
- checkpoint-by-checkpoint status;
- commits and pushes created during any approved cleanup phase;
- any branch conflicts, mixed-scope files, or migration risks;
- recommended next step for establishing the clean OfficeLeasing baseline.

## Instruction for AI agents

Claude, Claude Code, and Codex must read these files before working:

1. `README.md`
2. `CLAUDE.md` or `AGENTS.md`, as applicable
3. `docs/MIGRATION_HANDOFF.md`

During migration, the only objective is to copy and verify the three branches. Do not begin Home V1 development, broad refactoring, URL changes, data-model changes, or branch integration in the same task.

For all later work, follow the mandatory staged save/checkpoint policy. A task is not considered complete merely because files were edited locally; each completed phase must be verified, committed when applicable, pushed, and reported before the next phase begins.
