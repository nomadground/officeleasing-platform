# OFFICE LEASING Platform

Private WordPress platform repository for **officeleasing.co.kr**.

This repository is intended to contain only the OFFICE LEASING website and Leasing Flyer code extracted from the existing NMD workspace. The NMD project remains in its current repository and is not migrated here.

## Required agent workflow

Before planning, coding, reviewing, or handing off work, read:

- `docs/AGENT_ROLES.md`
- `AGENTS.md`
- `CLAUDE.md` when working in Claude Code

Default role map:

- **GPT**: product planning, requirements, priorities, SEO/AIO/GEO/UX, and acceptance criteria.
- **Claude**: planning review, architecture review, UX/design consistency, milestone planning, and overall review for large changes.
- **Claude Code**: primary implementation agent.
- **Codex**: secondary implementation, focused code review, validation, tests, and corrective patches.

Do not duplicate planning or implementation. Use the workflow and handoff rules in `docs/AGENT_ROLES.md`.

## Required migration document

Before copying, comparing, or integrating the OfficeLeasing branches, read:

- `docs/MIGRATION_HANDOFF.md`

The requested source branches are:

- `officeleasing`
- `claude/leasing-flyer-mvp-analysis-wt2g7k`
- `claude/mobile-layout-print-fixes-tfils7`

They must be copied from `nomadground/nmd-company-os` into this repository without modifying or deleting any NMD data in the source repository.

## Scope

- `officeleasing-core` WordPress plugin
- GeneratePress child theme for officeleasing.co.kr
- Leasing Flyer plugin/module
- Shared project documentation, tests, and deployment scripts directly related to OFFICE LEASING

## Out of scope

- NMD Blueprint and Business Plan documents
- NMD Nest / Residence code
- NMD corporate operating system files
- Unrelated prototypes, backups, exports, credentials, database dumps, and deployment ZIP archives

## Intended structure

```text
officeleasing-platform/
├─ wp-content/
│  ├─ plugins/
│  │  ├─ officeleasing-core/
│  │  └─ leasing-flyer/
│  └─ themes/
│     └─ officeleasing-generatepress-child/
├─ docs/
│  ├─ AGENT_ROLES.md
│  └─ MIGRATION_HANDOFF.md
├─ tests/
├─ scripts/
├─ AGENTS.md
├─ CLAUDE.md
└─ README.md
```

## Repository rules

- `main` is the stable baseline.
- Feature work is performed on `feature/*` branches.
- Public pages are Building-centered; Listing records remain internal data.
- PHP/data/query/URL logic belongs in `officeleasing-core`.
- Templates, CSS, JavaScript, and rendering belong in the child theme.
- Do not hardcode public URLs when a WordPress API or project URL helper is available.
- Do not commit credentials, API keys, `wp-config.php`, `.env`, database dumps, vendor backups, or generated ZIP files.
- Do not reread the entire repository or rebuild ZIP packages during ordinary development.
- Work from task-relevant files and Git diffs, then save progress through small tested milestone commits.

## Migration status

The target repository and its instruction documents have been initialized. The three requested source branches still need to be copied and verified according to `docs/MIGRATION_HANDOFF.md`.
