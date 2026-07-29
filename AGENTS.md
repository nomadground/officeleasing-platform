# Agent Instructions — OFFICE LEASING

## Required reading

Before repository migration or branch integration work, read:

1. `README.md`
2. `docs/MIGRATION_HANDOFF.md`
3. this file

## Scope

This repository contains only:

- officeleasing.co.kr WordPress platform code
- officeleasing-core
- GeneratePress child theme
- Leasing Flyer
- directly related documentation, tests, and deployment scripts

The NMD project remains in its existing repository and must not be moved or modified as part of OFFICE LEASING work.

## Required behavior

- Inspect only task-relevant files first.
- Preserve the Core/Theme responsibility boundary.
- Prefer small, reviewable commits.
- Do not introduce secrets, generated archives, backups, database dumps, or unrelated project files.
- Do not invent ACF field names, taxonomy terms, URLs, helper functions, or data.
- Confirm actual implementation before changing architecture.
- Use WordPress APIs and existing project helpers.
- Run available syntax and regression checks.
- Report changed files, test evidence, assumptions, and unresolved risks.

## Migration-specific rule

When asked to migrate branches from `nomadground/nmd-company-os`, follow `docs/MIGRATION_HANDOFF.md` exactly. Copy and verify only these branches:

- `officeleasing`
- `claude/leasing-flyer-mvp-analysis-wt2g7k`
- `claude/mobile-layout-print-fixes-tfils7`

Do not modify the source repository, merge the branches, remove NMD data, clean mixed-scope files, or begin feature development during the migration task.

## OFFICE LEASING conventions

- Building is the public content unit.
- Listing is internal inventory data.
- Core owns data, calculations, queries, cache, security, and permalink logic.
- Child theme owns templates, styles, scripts, accessibility, and rendering.
- SEO, AIO, GEO, and UX must be implemented through visible, crawlable, user-facing structure rather than hidden keyword content.
