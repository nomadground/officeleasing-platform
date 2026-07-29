# AI Agent Roles and Workflow — OFFICE LEASING

## Purpose

This document defines how GPT, Claude, Claude Code, and Codex collaborate on the OFFICE LEASING platform. Each agent must understand its default role before beginning work and must not duplicate another agent's work without a clear reason.

The user's direct instruction always has priority over these defaults. Product decisions must also follow `docs/PROJECT_PRINCIPLES.md`.

## Role map

### GPT — product planning and requirement owner

GPT is the primary planning agent.

Default responsibilities:

- define business goals, user needs, feature scope, and priorities;
- design information architecture, page flow, SEO, AIO, GEO, and UX requirements;
- convert conversations into implementation-ready requirements and acceptance criteria;
- decide what should be built and why;
- review outcomes from the user and product perspective;
- prepare concise handoff instructions for Claude, Claude Code, or Codex.

GPT should not ask another agent to redesign an already approved plan from scratch unless a material conflict or missing requirement is found.

### Claude — planning review, content analysis, and overall design review

Claude is the primary planning-review, architecture-review, and content-development agent.

Default responsibilities:

- review GPT's plan for omissions, conflicts, complexity, and feasibility;
- review UX, design consistency, system boundaries, and implementation sequence;
- break large initiatives into safe phases and milestones;
- review major architecture, data-model, URL, admin-system, or site-wide UI decisions;
- perform overall consistency review before large integrations or releases;
- identify risks and recommend corrections before implementation begins;
- analyze existing `hintoffice.com` checklist, guide, and Insight content;
- identify duplicate, overlapping, contradictory, outdated, or missing concepts;
- design topic clusters, migration strategy, cross-linking, and search-intent separation;
- write and revise new OFFICE LEASING Insight content when assigned;
- cross-check terminology, claims, calculations, examples, metadata, internal links, and CTA logic across related content.

Claude is not required for every small code change. Use Claude primarily for large features, structural changes, complex UX, architecture decisions, content-system work, major content production, or work that has become unclear.

### Claude Code — primary implementation agent

Claude Code is the default primary coding agent.

Default responsibilities:

- inspect the current repository and task-relevant files;
- implement WordPress PHP, `officeleasing-core`, GeneratePress child-theme templates, CSS, JavaScript, and Leasing Flyer changes;
- preserve established architecture and project helpers;
- implement approved content structures, metadata fields, internal-link components, templates, schema integration, and publishing workflows;
- run syntax checks and available regression tests;
- work in small milestones;
- review diffs, commit, push, and report at each completed milestone;
- update directly related technical documentation when necessary.

Claude Code must read `docs/PROJECT_PRINCIPLES.md`, `CLAUDE.md`, and `AGENTS.md` before ordinary implementation work. It must also read any task-specific document explicitly referenced by the user or repository instructions.

Claude Code should not independently rewrite Claude-assigned source content or redo a completed content analysis unless implementation reveals a concrete conflict that needs review.

### Codex — secondary implementation, code review, and corrective patch agent

Codex is the default secondary coding and technical-validation agent.

Default responsibilities:

- review Claude Code changes using the relevant diff rather than rescanning the whole repository;
- inspect WordPress best practices, security, performance, query efficiency, regressions, and failure handling;
- add or improve focused tests;
- implement narrow, clearly bounded tasks when assigned;
- create corrective patches for validated findings;
- act as backup implementation capacity when Claude Code is unavailable or the user explicitly assigns Codex.

Codex should not independently reimplement an entire feature that Claude Code is already building. Parallel implementation is allowed only when the work is deliberately split into non-overlapping scopes.

## Default delivery workflow

Use this sequence unless the user requests otherwise:

1. **GPT — plan**
   - define the objective, scope, constraints, acceptance criteria, and priority;
   - avoid unnecessary implementation detail when existing repository rules already define it.

2. **Claude — review or content work when needed**
   - review large, structural, risky, or ambiguous plans;
   - analyze and write content when the task concerns Insight, migration, topic clusters, or editorial consistency;
   - return only material corrections, missing decisions, and a practical phased plan;
   - skip this step for small, isolated fixes unless requested.

3. **Claude Code — implement**
   - read repository instructions and only task-relevant files;
   - implement one milestone at a time;
   - test, review the diff, commit, push, and report before starting the next milestone.

4. **Codex — validate and patch**
   - review the completed milestone or PR diff;
   - report only evidence-backed issues;
   - make a small corrective patch when assigned;
   - do not broaden the task into unrelated refactoring.

5. **Final product review**
   - GPT checks business, UX, SEO/AIO/GEO, and user-facing acceptance criteria;
   - Claude performs overall review when the change is large, cross-cutting, or content-intensive.

## Work-size routing

### Small fixes

Examples: copy changes, one CSS bug, narrow PHP guard, isolated URL correction.

Default route:

`GPT requirement -> Claude Code implementation -> Codex review only when risk justifies it`

Claude planning review is optional.

### Medium features

Examples: one page section, one filter, one admin workflow, one schema component.

Default route:

`GPT plan -> optional Claude review -> Claude Code implementation -> Codex diff review`

### Content-system or editorial work

Examples: existing-content audit, topic-cluster design, Insight rewrite, cross-linking map, search-intent separation.

Default route:

`GPT objective and priorities -> Claude analysis and writing -> GPT/Claude approval -> Claude Code implementation of fields/templates/workflow -> Codex technical review when needed`

### Large or structural changes

Examples: data model, permalink architecture, full homepage, listing system, admin architecture, major plugin integration.

Default route:

`GPT plan -> Claude overall review -> Claude Code phased implementation -> Codex review per milestone -> GPT/Claude final review`

## Resource-efficient collaboration rules

All agents must:

- avoid rereading or rescanning the entire repository for every task;
- begin with repository instructions, the assigned task, the latest relevant diff or commit, and directly related files;
- expand scope only when an actual dependency is discovered;
- avoid regenerating or comparing full ZIP packages during normal development;
- use tracked source files and Git diffs;
- create ZIP files only for explicit release, deployment, or user-requested handoff;
- avoid duplicate planning, duplicate implementation, duplicate content analysis, and repeated full-project summaries;
- keep work small, reviewable, and recoverable through milestone commits;
- never let multiple coding agents edit the same files concurrently unless the scopes and ownership are explicitly separated.

## Handoff format

When one agent hands work to another, provide only what is needed:

- objective;
- current branch and relevant commit or PR;
- exact scope;
- files or components likely involved;
- acceptance criteria;
- constraints and non-goals;
- tests already run;
- unresolved risks or decisions.

For content handoff, also include:

- source URLs or source documents reviewed;
- migration strategy;
- approved title, slug, search intent, metadata, internal links, and CTA targets;
- unresolved factual or legal review items.

Do not include the full project history when the repository documents already contain it.

## Conflict and authority rules

- The user's latest direct instruction has highest authority.
- `docs/PROJECT_PRINCIPLES.md` is the product-principle source of truth.
- Approved requirements should not be silently redesigned during coding.
- Claude Code may flag implementation blockers but should not expand product scope without approval.
- Codex may identify and patch defects but should not replace the approved architecture with a new one during review.
- Claude may recommend plan or content changes, but implementation starts only after material changes are accepted.
- When agents disagree, preserve the current working state, document the conflict, and request one decision rather than producing competing implementations.