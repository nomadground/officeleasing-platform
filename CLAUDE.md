# Claude Code Instructions — OFFICE LEASING

## Required reading

Before repository migration or branch integration work, read:

1. `README.md`
2. `docs/MIGRATION_HANDOFF.md`
3. this file

For normal feature or fix work, read this file first, then only the task-relevant files and any specifically referenced documentation.

## Project boundary

Work only on OFFICE LEASING and Leasing Flyer in this repository. Do not import, modify, or recreate unrelated NMD files.

## Architecture

- `officeleasing-core`: CPT, taxonomy, ACF integration, calculations, query helpers, permalink helpers, cache and save hooks.
- GeneratePress child theme: templates, template parts, CSS, JavaScript and rendering.
- Leasing Flyer: keep as an independently identifiable plugin/module inside this repository.
- Public browsing is Building-centered. Listing records are internal and should redirect to their parent Building where applicable.

## Resource-efficient working rules

1. Do not reread or rescan the entire repository at the start of every task.
2. Start with this file, the assigned task, the latest relevant commit or diff, and only the directly related files.
3. Expand the inspection scope only when an actual dependency is discovered.
4. Do not repeatedly summarize the whole project when the repository instructions already contain the context.
5. Do not regenerate, inspect, or compare full ZIP packages during normal development.
6. Work directly from tracked source files and Git diffs. Build ZIP packages only for an explicit release, deployment, or user-requested handoff.
7. Do not commit generated ZIP files, build archives, caches, logs, database exports, or unrelated backups.
8. Keep each task small and bounded. Do not combine unrelated implementation, refactoring, cleanup, and documentation work.
9. Before editing, state the task scope and the small set of files expected to be inspected or changed.
10. Prefer existing repository maps, helpers, documentation, recent commits, and diffs over broad rediscovery.
11. Reuse existing helpers and components before creating new ones.
12. Avoid unrelated formatting changes, broad renames, and speculative refactoring.
13. At each completed milestone: review the diff, run relevant checks, commit, push, and report what was completed and what remains.

## WordPress and content rules

1. Never hardcode public URLs when WordPress APIs or project URL helpers can generate them.
2. Do not add fake data, temporary `#` links, hidden SEO text, or duplicate schema/meta output.
3. Guard against missing ACF or Core dependencies; public pages must not fatal.
4. Do not commit secrets, `wp-config.php`, `.env`, database exports, credentials, or deployment keys.
5. Finish with syntax checks, changed-file summary, test results, risks, and remaining work.

## Migration-specific rule

When asked to migrate the three OfficeLeasing branches from `nomadground/nmd-company-os`, follow `docs/MIGRATION_HANDOFF.md` exactly. The operation is copy-and-verify only. Do not modify the source repository, merge the branches, clean mixed files, or begin feature work in the same task.

## Branch policy

- `main`: stable baseline
- `feature/*`: isolated feature development
- `fix/*`: isolated bug fixes

Do not work directly on `main` unless explicitly requested.
