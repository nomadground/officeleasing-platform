# Claude Code Instructions — OFFICE LEASING

## Required reading

Before repository migration or branch integration work, read:

1. `README.md`
2. `docs/MIGRATION_HANDOFF.md`
3. this file

## Project boundary

Work only on OFFICE LEASING and Leasing Flyer in this repository. Do not import, modify, or recreate unrelated NMD files.

## Architecture

- `officeleasing-core`: CPT, taxonomy, ACF integration, calculations, query helpers, permalink helpers, cache and save hooks.
- GeneratePress child theme: templates, template parts, CSS, JavaScript and rendering.
- Leasing Flyer: keep as an independently identifiable plugin/module inside this repository.
- Public browsing is Building-centered. Listing records are internal and should redirect to their parent Building where applicable.

## Working rules

1. Read this file and the files directly related to the assigned task first.
2. Do not scan or refactor the entire repository without a concrete need.
3. Keep each task small and report the files inspected before editing.
4. Reuse existing helpers and components before creating new ones.
5. Never hardcode public URLs when WordPress APIs or project URL helpers can generate them.
6. Do not add fake data, temporary `#` links, hidden SEO text, or duplicate schema/meta output.
7. Guard against missing ACF or Core dependencies; public pages must not fatal.
8. Do not commit secrets, `wp-config.php`, `.env`, database exports, generated ZIPs, caches, logs, or unrelated backups.
9. Avoid unrelated formatting changes and broad renames.
10. Finish with syntax checks, changed-file summary, test results, risks, and remaining work.

## Migration-specific rule

When asked to migrate the three OfficeLeasing branches from `nomadground/nmd-company-os`, follow `docs/MIGRATION_HANDOFF.md` exactly. The operation is copy-and-verify only. Do not modify the source repository, merge the branches, clean mixed files, or begin feature work in the same task.

## Branch policy

- `main`: stable baseline
- `feature/*`: isolated feature development
- `fix/*`: isolated bug fixes

Do not work directly on `main` unless explicitly requested.
