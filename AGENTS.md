# Agent Instructions — OFFICE LEASING

These instructions apply to **GPT, Claude, Claude Code, Codex, and any other agent** working with this repository.

## Product source of truth

Before planning, implementation, review, or handoff work, read:

1. `docs/PROJECT_PRINCIPLES.md`
2. `docs/AGENT_ROLES.md`
3. only the task-specific planning and implementation documents relevant to the request.

Default role assignment:

- **GPT**: product planning, requirements, priorities, SEO/AIO/GEO/UX, and acceptance criteria.
- **Claude**: planning review, architecture review, UX/design consistency, content analysis and writing, milestone planning, and overall review for large changes.
- **Claude Code**: primary implementation agent for WordPress PHP, `officeleasing-core`, GeneratePress child theme, CSS, JavaScript, Leasing Flyer, tests, commits, and pushes.
- **Codex**: secondary implementation, focused code review, security/performance/WordPress review, regression validation, tests, and corrective patches.

The user's latest direct instruction overrides these defaults.

Do not duplicate work by having multiple agents independently redesign, reanalyze, rewrite, or reimplement the same scope. Parallel work is allowed only when ownership and file boundaries are explicitly separated.

## Required reading by task type

### Repository migration or branch integration

Read:

1. `README.md`
2. `docs/PROJECT_PRINCIPLES.md`
3. `docs/AGENT_ROLES.md`
4. `docs/MIGRATION_HANDOFF.md`
5. `CLAUDE.md` when running in Claude Code
6. this file

### Ordinary feature or fix work

- GPT and Claude: read `docs/PROJECT_PRINCIPLES.md`, `docs/AGENT_ROLES.md`, and only the relevant planning documents.
- Claude Code: read `docs/PROJECT_PRINCIPLES.md`, `CLAUDE.md`, `AGENTS.md`, `docs/AGENT_ROLES.md`, then only task-relevant files.
- Codex and other coding agents: read `docs/PROJECT_PRINCIPLES.md`, `AGENTS.md`, `docs/AGENT_ROLES.md`, then the relevant diff and files.

### Insight or content-system work

Also read the relevant files under `docs/content/`. Claude owns existing-content analysis, topic clustering, migration strategy, cross-checking, and new Insight writing unless the user assigns another agent.

## Project scope

This repository contains only:

- officeleasing.co.kr WordPress platform code;
- `officeleasing-core`;
- GeneratePress child theme;
- Leasing Flyer;
- directly related documentation, tests, and deployment scripts.

The NMD project remains in its existing repository and must not be moved or modified as part of OFFICE LEASING work.

## Resource-efficient behavior

- Do not reread or rescan the entire repository at the beginning of every task.
- Start from the task, the latest relevant commit or diff, and only directly related files.
- Expand inspection scope only when a real dependency is found.
- Do not repeatedly restate the full project background when repository documents already contain it.
- Do not create, compare, or inspect full ZIP packages during normal development.
- Work from tracked source files and Git diffs.
- Create ZIP packages only for an explicit release, deployment, or user-requested handoff.
- Do not commit generated archives, caches, logs, database dumps, credentials, or unrelated backups.
- Keep work small, bounded, and reviewable.
- Avoid unrelated formatting changes, broad renames, and speculative refactoring.
- At each milestone: review the diff, run relevant checks, commit, push, and report completed and remaining scope.

## Implementation and review rules

- Follow `docs/PROJECT_PRINCIPLES.md` as the product-principle source of truth.
- Claude Code is the default primary coder unless the user assigns another agent.
- Codex should normally review a completed milestone or implement a narrow, non-overlapping task.
- Codex should review relevant diffs rather than restart repository discovery.
- Claude should review large, structural, risky, ambiguous, or content-intensive plans; it is optional for small fixes.
- GPT owns the approved product requirement and final user-facing acceptance criteria.
- Do not silently redesign approved requirements during implementation.
- Preserve the Core/Theme responsibility boundary.
- Prefer small, reviewable commits.
- Do not invent ACF field names, taxonomy terms, URLs, helper functions, facts, or data.
- Confirm actual implementation before changing architecture.
- Use WordPress APIs and existing project helpers.
- Run available syntax, regression, responsive, and relevant performance checks.
- Report changed files, test evidence, assumptions, and unresolved risks.
- Do not work directly on `main` unless explicitly requested.
- Do not begin a second feature in the same task unless the first milestone has been committed, pushed, and reported.

## Performance and search-quality gate

A feature is incomplete when it works functionally but materially harms speed, stability, desktop/mobile usability, crawlability, SEO, AIO, or GEO.

Do not introduce:

- unbounded queries;
- full-dataset initial loads;
- automatic loading of all map markers;
- N+1 or repeated meta queries;
- duplicate schema or metadata;
- heavy scripts, maps, charts, or AI modules on pages where they are not needed.

Use bounded queries, pagination, caching, precomputed values, selective loading, responsive images, and deferred non-critical assets.

## Migration-specific rule

When asked to migrate branches from `nomadground/nmd-company-os`, follow `docs/MIGRATION_HANDOFF.md` exactly. Copy and verify only these branches:

- `officeleasing`
- `claude/leasing-flyer-mvp-analysis-wt2g7k`
- `claude/mobile-layout-print-fixes-tfils7`

Do not modify the source repository, merge the branches, remove NMD data, clean mixed-scope files, or begin feature development during the migration task.

## OFFICE LEASING conventions

- OFFICE LEASING is a Seoul-wide prime-office leasing platform, not a Gangnam-only small-office site.
- `사무실 임대` is the primary market keyword.
- Public navigation should use meaningful submarket names rather than exposing `ETC` as a long-term brand label.
- Building is the primary public content unit.
- Listing is internal inventory data.
- Core owns data, calculations, queries, cache, security, and permalink logic.
- Child theme owns templates, styles, scripts, accessibility, and rendering.
- SEO, AIO, GEO, and UX must be implemented through visible, crawlable, user-facing structure rather than hidden keyword content.