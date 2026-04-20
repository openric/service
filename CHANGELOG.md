# Changelog

## v0.2.0 — 2026-04-20

- Phase 0 — decouple OpenRiC from Heratio; fork ahg-core/api/ric; add bin/release and drift log
All notable changes to OpenRiC are recorded here. Versions follow [semver](https://semver.org/). Releases are produced by `./bin/release` — see `README.md`.

## v0.1.0 — 2026-04-09 — genesis

- Initial Laravel 12 scaffold for the OpenRiC service.
- Entity-path redirect routes (`/informationobject/{slug}`, `/actor/{slug}`, `/repository/{slug}`, etc.) with content negotiation: JSON-LD clients get the API URL, browsers redirect to `OPENRIC_FRONTEND_URL`.
- `/thumbnails/` nginx alias.
- Composer deps on `ahg/core`, `ahg/api`, `ahg/ric` (symlinked from Heratio at this stage).
- Commits: `ac47715` (scaffold), `5b9c53e` (redirects + thumbnails).
