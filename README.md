# OFFICE LEASING Platform

Private WordPress platform repository for **officeleasing.co.kr**.

This repository is intended to contain only the OFFICE LEASING website and Leasing Flyer code extracted from the existing NMD workspace. The NMD project remains in its current repository and is not migrated here.

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

## Migration status

The target repository has been initialized. Source code migration is pending confirmation of the exact source branch and paths containing OFFICE LEASING and Leasing Flyer.
