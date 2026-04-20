# Changelog

## v0.6.0 — 2026-04-20

- Phase 4 — detail views now render full declared/inherited UX: scope notes + examples + broader/narrower relations, data-inherited-from portability markers, cross-page anchor jumps,
  attribute inherited-by tree
## v0.5.0 — 2026-04-20

- Phase 4 — detail views now render full declared/inherited UX: scope notes + examples + broader/narrower relations, data-inherited-from portability markers, cross-page anchor jumps,
  attribute inherited-by tree
## v0.4.0 — 2026-04-20

- Phases 2+3 — RiC-CM reference browser is now live: versioned URLs, Bootstrap 5 views, Alpine-driven client-side filter, mobile responsive, print CSS, WCAG focus indicators
## v0.3.0 — 2026-04-20

- Phase 1 — load RiC-O v1.1 into Fuseki /openric-model; ship ahg-ric-model with OntologyService + InheritanceResolver (pure PHP, portable)
## v0.2.0 — 2026-04-20

- Phase 0 — decouple OpenRiC from Heratio; fork ahg-core/api/ric; add bin/release and drift log
All notable changes to OpenRiC are recorded here. Versions follow [semver](https://semver.org/). Releases are produced by `./bin/release` — see `README.md`.

## v0.1.0 — 2026-04-09 — genesis

- Initial Laravel 12 scaffold for the OpenRiC service.
- Entity-path redirect routes (`/informationobject/{slug}`, `/actor/{slug}`, `/repository/{slug}`, etc.) with content negotiation: JSON-LD clients get the API URL, browsers redirect to `OPENRIC_FRONTEND_URL`.
- `/thumbnails/` nginx alias.
- Composer deps on `ahg/core`, `ahg/api`, `ahg/ric` (symlinked from Heratio at this stage).
- Commits: `ac47715` (scaffold), `5b9c53e` (redirects + thumbnails).
