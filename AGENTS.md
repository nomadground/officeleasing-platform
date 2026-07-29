# Agent Instructions — OFFICE LEASING

These instructions apply to **GPT, Claude, Claude Code, Codex, and any other agent** working with this repository.

## Agent role source of truth

Read `docs/AGENT_ROLES.md` before planning, implementation, review, or handoff work.

Default role assignment:

- **GPT**: product planning, requirements, priorities, SEO/AIO/GEO/UX, and acceptance criteria.
- **Claude**: planning review, architecture review, UX/design consistency, milestone planning, and overall review for large changes.
- **Claude Code**: primary implementation agent for WordPress PHP, `officeleasing-core`, GeneratePress child theme, CSS, JavaScript, Leasing Flyer, tests, commits, and pushes.
- **Codex**: secondary implementation, focused code review, security/performance/WordPress review, regression validation, tests, and corrective patches.

The user's latest direct instruction overrides these defaults.

Do not duplicate work by having multiple agents independently redesign or reimplement the same feature. Parallel coding is allowed only when file ownership and scope are explicitly separated.

## Required reading

Before repository migration or branch integration work, read:

1. `README.md`
2. `docs/AGENT_ROLES.md`
3. `docs/MIGRATION_HANDOFF.md`
4. `CLAUDE.md` when running in Claude Code
5. this file

For ordinary feature or fix work:

- GPT and Claude should read `docs/AGENT_ROLES.md` and only the project documents relevant to the requested planning or review;
- Claude Code must read `CLAUDE.md`, `AGENTS.md`, and `docs/AGENT_ROLES.md` first;
- Codex and other coding agents must read `AGENTS.md` and `docs/AGENT_ROLES.md` first;
- then read only task-relevant files and specifically referenced documentation.

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
- Start from the task, the latest relevant commit or diff, and only the directly related files.
- Expand the inspection scope only when a real dependency is found.
- Do not repeatedly restate the full project background when repository documentation already contains it.
- Do not create, compare, or inspect full ZIP packages during normal development.
- Work from tracked source files and Git diffs.
- Create ZIP packages only for an explicit release, deployment, or user-requested handoff.
- Do not commit generated archives, caches, logs, database dumps, credentials, or unrelated backups.
- Keep work small, bounded, and reviewable.
- Avoid unrelated formatting changes, broad renames, and speculative refactoring.
- At each milestone: review the diff, run relevant checks, commit, push, and report completed and remaining scope.

## Implementation and review rules

- Claude Code is the default primary coder unless the user assigns another agent.
- Codex should normally review a completed milestone or implement a narrow, non-overlapping task.
- Codex should review relevant diffs rather than restart repository discovery.
- Claude should review large, structural, risky, or ambiguous plans; it is optional for small fixes.
- GPT owns the approved product requirement and final user-facing acceptance criteria.
- Do not silently redesign approved requirements during implementation.
- Preserve the Core/Theme responsibility boundary.
- Prefer small, reviewable commits.
- Do not invent ACF field names, taxonomy terms, URLs, helper functions, or data.
- Confirm actual implementation before changing architecture.
- Use WordPress APIs and existing project helpers.
- Run available syntax and regression checks.
- Report changed files, test evidence, assumptions, and unresolved risks.
- Do not work directly on `main` unless explicitly requested.
- Do not begin a second feature in the same task unless the first milestone has been committed, pushed, and reported.

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
