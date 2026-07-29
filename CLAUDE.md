# Claude Code Instructions — OFFICE LEASING

## Role

Claude Code is the default **primary implementation agent** for this repository.

Read `docs/PROJECT_PRINCIPLES.md` for the product source of truth and `docs/AGENT_ROLES.md` for the collaboration model:

- GPT owns product planning and requirements;
- Claude reviews plans, architecture, UX/design consistency, and writes/analyzes Insight content;
- Claude Code performs the primary implementation;
- Codex performs secondary implementation, focused review, validation, tests, and corrective patches.

The user's latest direct instruction overrides this default role map.

## Required reading

### Repository migration or branch integration

Read:

1. `README.md`
2. `docs/PROJECT_PRINCIPLES.md`
3. `AGENTS.md`
4. `docs/AGENT_ROLES.md`
5. `docs/MIGRATION_HANDOFF.md`
6. this file

### Normal feature or fix work

Read:

1. `docs/PROJECT_PRINCIPLES.md`
2. `CLAUDE.md`
3. `AGENTS.md`
4. `docs/AGENT_ROLES.md`
5. only task-relevant files and specifically referenced documentation.

Do not reread the entire repository when these documents and the latest relevant diff already provide enough context.

## Project boundary

Work only on OFFICE LEASING and Leasing Flyer in this repository. Do not import, modify, or recreate unrelated NMD files.

OFFICE LEASING is a Seoul-wide, prime-office-focused platform built around the keyword `사무실 임대`. It must not be implemented as a Gangnam-only small-office clone of `hintoffice.com`.

## Architecture

- `officeleasing-core`: CPT, taxonomy, ACF integration, calculations, query helpers, permalink helpers, cache, security, and save hooks.
- GeneratePress child theme: templates, template parts, CSS, JavaScript, accessibility, responsive behavior, and rendering.
- Leasing Flyer: keep as an independently identifiable plugin/module inside this repository.
- Public browsing is Building-centered. Listing records are internal vacancy data and should redirect to their parent Building where applicable.
- Public navigation should use meaningful Seoul submarket names; do not treat `ETC` as the long-term user-facing label.

## Primary implementation duties

Claude Code should normally:

- implement WordPress PHP, templates, CSS, JavaScript, and Leasing Flyer changes;
- preserve approved requirements and repository architecture;
- implement approved content structures and publishing workflows without redoing Claude's content analysis;
- use existing helpers and components before creating new ones;
- work one milestone at a time;
- review the diff and run relevant checks before each commit;
- commit, push, and report each completed milestone before starting another feature;
- hand completed milestone diffs to Codex when review is requested;
- flag blockers or requirement conflicts without silently expanding scope.

Do not independently redesign an approved GPT/Claude plan. Report the conflict and request one decision.

## Resource-efficient working rules

1. Do not reread or rescan the entire repository at the start of every task.
2. Start with these instructions, the assigned task, the latest relevant commit or diff, and only directly related files.
3. Expand inspection scope only when an actual dependency is discovered.
4. Do not repeatedly summarize the whole project when repository instructions already contain the context.
5. Do not regenerate, inspect, or compare full ZIP packages during normal development.
6. Work directly from tracked source files and Git diffs. Build ZIP packages only for an explicit release, deployment, or user-requested handoff.
7. Do not commit generated ZIP files, build archives, caches, logs, database exports, or unrelated backups.
8. Keep each task small and bounded. Do not combine unrelated implementation, refactoring, cleanup, and documentation work.
9. Before editing, state the task scope and the small set of files expected to be inspected or changed.
10. Prefer repository maps, helpers, documentation, recent commits, and diffs over broad rediscovery.
11. Avoid unrelated formatting changes, broad renames, and speculative refactoring.
12. At each completed milestone: review the diff, run relevant checks, commit, push, and report completed and remaining scope.
13. Do not edit the same files concurrently with Codex or another coding agent unless ownership is explicitly separated.

## Performance-first rules

Speed and stability are non-negotiable completion criteria.

- Do not introduce unbounded queries or full-dataset initial loads.
- Do not automatically load every listing or map marker.
- Avoid N+1 queries, repeated meta queries, and repeated calculations.
- Use pagination, bounded queries, selective fields, caching, and precomputed values where appropriate.
- Load maps, charts, AI modules, and heavy scripts only when needed.
- Use responsive images and defer non-critical assets.
- Test with representative data volume, not only empty or sample data.
- When a requested feature would materially degrade speed, present a lighter implementation option.

## WordPress and search-quality rules

1. Never hardcode public URLs when WordPress APIs or project URL helpers can generate them.
2. Do not add fake data, temporary `#` links, hidden SEO text, or duplicate schema/meta output.
3. Guard against missing ACF or Core dependencies; public pages must not fatal.
4. Do not commit secrets, `wp-config.php`, `.env`, database exports, credentials, or deployment keys.
5. Preserve SEO, AIO, GEO, canonical, indexation, internal-link, and structured-data behavior.
6. Finish with syntax checks, changed-file summary, test results, performance considerations, risks, and remaining work.

## Migration-specific rule

When asked to migrate the three OfficeLeasing branches from `nomadground/nmd-company-os`, follow `docs/MIGRATION_HANDOFF.md` exactly. The operation is copy-and-verify only. Do not modify the source repository, merge the branches, clean mixed files, or begin feature work in the same task.

## Branch policy

- `main`: stable baseline
- `planning/*`: planning and approved specifications
- `content/*`: content drafting and editorial work
- `feature/*`: isolated feature development
- `fix/*`: isolated bug fixes

Do not work directly on `main` unless explicitly requested.