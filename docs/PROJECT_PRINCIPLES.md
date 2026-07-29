# OFFICE LEASING Project Principles

## 1. Source-of-truth statement

`officeleasing.co.kr` is not a larger copy of `hintoffice.com`. It is a separate, performance-first platform for professional office leasing across Seoul, with a stronger focus on prime offices, major office buildings, structured building data, and enterprise-grade user experience.

The primary market keyword is **사무실 임대**. The platform must expand beyond the existing Gangnam-centered, small-to-mid-sized office inventory model into a Seoul-wide office-leasing information architecture.

## 2. Core differentiation from hintoffice.com

### Geographic scope

- `hintoffice.com`: primarily Gangnam-centered inventory and lead generation.
- `officeleasing.co.kr`: GBD, YBD, CBD, Seongsu, Songpa, Seocho, Yongsan, and other meaningful Seoul office submarkets.

Internal taxonomy may temporarily use a technical fallback such as `ETC`, but public navigation, headings, URLs, and copy should prefer meaningful district or submarket names. `ETC` must not become the long-term public brand label for Seoul expansion areas.

### Product focus

- `hintoffice.com`: comparatively high share of small-to-mid-sized office listings.
- `officeleasing.co.kr`: prime office, large building, corporate office, and professionally managed office-building information.

Public information should emphasize the building itself: location, scale, specifications, common areas, access, parking, tenant profile, operating quality, active vacancies, and lease conditions.

### Experience standard

The product must feel fast, professional, clear, and reliable on both desktop and mobile. UX/UI quality includes not only appearance but also information hierarchy, comparison speed, responsive behavior, accessibility, and perceived performance.

## 3. Non-negotiable priorities

These are cross-cutting requirements, not features to add later.

1. **Speed and stability**
2. **SEO, AIO, and GEO readiness**
3. **Desktop and mobile optimization**
4. **Professional UX/UI**
5. **Prime-office specialization**
6. **Seoul-wide geographic scalability**
7. **Operational efficiency for staff**
8. **AI-agent and automation extensibility**

A feature is not complete when it works functionally but materially harms speed, crawlability, structured understanding, mobile usability, or stability.

## 4. Performance-first architecture

The existing `hintoffice.com` experience demonstrates that large accumulated listing and media data can make maps and inventory exploration feel slow. OFFICE LEASING must be designed from the start for substantial data growth.

Required principles:

- never load all listings, markers, images, or building data on initial page load;
- use bounded queries, pagination, selective fields, caching, and precomputed values where appropriate;
- avoid unbounded queries, N+1 queries, repeated meta queries, and repeated calculations;
- load maps, charts, AI modules, and heavy scripts only when needed;
- initialize maps by viewport, interaction, or explicit user intent rather than automatically loading the full dataset;
- use responsive images, compression, modern formats where supported, and lazy loading below the fold;
- keep critical rendering paths small and defer non-critical assets;
- measure performance using representative production-scale data, not only empty or sample datasets;
- treat database growth, media growth, and map-marker growth as design constraints from the first implementation.

When performance and a proposed feature conflict, the implementation agent must present a lighter alternative instead of silently accepting a slow design.

## 5. SEO, AIO, and GEO definitions

For this project:

- **SEO** means search-engine discoverability, indexing control, internal linking, canonical handling, crawlable information architecture, and useful page content.
- **AIO** means content and data structured so AI-assisted search and answer systems can accurately understand, summarize, and cite the platform.
- **GEO** means generative-engine optimization: clear entities, relationships, summaries, evidence, structured data, and consistent terminology for generative search systems. It does not merely mean geographic optimization.

All three must be implemented through visible, useful, crawlable content and reliable structured data. Hidden keyword blocks, fabricated data, duplicate metadata, and duplicate schema output are prohibited.

## 6. Public information architecture

- Building is the primary public content unit.
- Listing is internal vacancy and lease-condition data linked to a Building.
- Building pages combine durable building information with active Listing data.
- Region and district pages act as search and navigation hubs.
- Insight content explains office-leasing decisions and links users to relevant regions, buildings, listings, and Contact.
- Contact is a conversion path, not an isolated page.
- Leasing Flyer is a client-proposal tool that can connect to platform data while remaining an independently identifiable module.

## 7. AI and automation principle

AI Agent, natural-language property search, automated shortlist generation, and Leasing Flyer automation are planned expansion capabilities.

They must influence the initial data model, metadata consistency, and API boundaries, but they must not increase initial page weight or block the core launch. AI functionality should be added progressively, with explicit performance and privacy review.

## 8. Completion gate for every feature

Before a milestone is considered complete, confirm:

- the feature meets the approved user and business requirement;
- relevant desktop and mobile layouts work;
- no unbounded query or unnecessary full-dataset load was introduced;
- map, image, script, and API loading remain appropriately deferred or bounded;
- SEO/AIO/GEO structure is preserved or improved;
- canonical, indexation, schema, and internal-link behavior are not duplicated or broken;
- relevant syntax, regression, and performance checks were run;
- the milestone was committed, pushed, and reported with remaining risks.

## 9. Decision hierarchy

When documents appear to conflict, use this order:

1. the user's latest direct instruction;
2. `docs/PROJECT_PRINCIPLES.md`;
3. approved task-specific planning documents;
4. `docs/PROJECT_OVERVIEW.md` and `docs/BRAND_POSITIONING.md`;
5. `AGENTS.md`, `CLAUDE.md`, and `docs/AGENT_ROLES.md` for workflow;
6. older implementation notes or branch-specific drafts.

Do not silently choose between conflicts. Preserve the working state and report the inconsistency when it affects implementation.