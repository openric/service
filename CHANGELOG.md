# Changelog

## v0.8.15 — 2026-04-21

- Data backfill: new one-shot `packages/ahg-ric/database/backfill_activity_participants.sql` adds `performed_by` relations for Productions whose sibling creation events (`event.type_id=111`) name an actor the activity itself doesn't. Ran against the reference store: 10 rows inserted, productions-with-participant moved from 35 → 45 (of 222). Remaining 177 are genuine archival gaps (no creator attested anywhere in the store) and need per-record curator judgment. Idempotent; safe to re-run. Service still does not claim `provenance-event` in `openric_conformance.profiles` — 45 of 222 is not full conformance.

## v0.8.14 — 2026-04-21

- Refresh `openric_conformance.profiles` declaration in `packages/ahg-ric/routes/api.php`. Previously carried stale `0.3.0-draft` across all entries from pre-freeze authoring. Now claims openric-spec v0.36.0 and 6 of 7 profiles at their frozen versions: core-discovery 0.3.0, authority-context 0.4.0, graph-traversal 0.5.0, digital-object-linkage 0.6.0, round-trip-editing 0.7.0, export-only 0.9.0. Provenance & Event deliberately excluded — data gap per the v0.8.13 / v0.8.15 entries. Both `GET /api/ric/v1/` and `GET /conformance/badge` read this block, so leaving it stale meant clients saw one thing on `/` and another in OpenAPI.

## v0.8.13 — 2026-04-21

- `RicSerializationService::serializeActivity()` now emits `rico:resultsOrResultedIn` (records produced, from `dropdown_code='results_from'` relations) and `rico:hasOrHadParticipant` (agents involved, from `dropdown_code='performed_by'` relations — the backing DB's `rico:isOrWasPerformedBy` predicate is the RiC-O subproperty; serializer normalises up to the broader profile-level predicate). Closes the Provenance & Event reference-impl gap flagged in openric-spec `spec/profiles/provenance-event.md` §9 Q5. Activities with complete backing data now pass `:StrictProductionShape`. Activities with incomplete backing data (see v0.8.15) still fail the shape for data reasons, not impl reasons.

## v0.8.12 — 2026-04-21

- `RicSerializationService::getContactFor()` now emits `@type: rico:ContactPoint` (was `rico:Contact`, which is not a valid RiC-O class). Aligns the reference implementation with `spec/mapping.md` §5.2.2 and the newly normative `spec/profiles/core-discovery.md` §3.4.1. Heratio's identical line carries the same bug — drift log records the divergence with direction `openric→heratio`.

## v0.8.11 — 2026-04-21

- `/api/ric/v1/*` error surface migrates to RFC 7807 `application/problem+json`. 54 error-return sites in `LinkedDataApiController.php` now go through the new `packages/ahg-ric/src/Support/ProblemDetails.php` helper, emitting canonical body (`type`, `title`, `status`, `detail`, `instance`) plus extra context (`id`, `uri`, `taxonomy`, `code`, `max_bytes`, `example`). Error-type URIs live under `https://openric.org/errors/` — 9 registered types covering 404/400/422/401/403/409/413/415/500. `OpenApiSpec.php` schema `Error` → `ProblemDetails` with the enum declared. Resolves openric-spec Q6 (RFC 7807 error envelope) ahead of the v0.30.0 profile freeze.
- Legacy `RicController.php` admin endpoints (25× `{success:false}` sites) intentionally NOT migrated — out of spec scope.
- Also bundled: `bin/link-crawl.py` — one-shot broken-link crawler for ric.theahg.co.za + openric.org with SMTP digest email to johan@theahg.co.za.

## v0.8.10 — 2026-04-21

- Add openric.org navigation menu to both the static landing (`public/index.html`) and the RiC-CM reference Blade layout (`packages/ahg-ric-model/resources/views/partials/_layout.blade.php`) — sticky top bar with Start here CTA + openric.org dropdown (Spec, Guides, FAQ, Architecture, Conformance, Governance, Discussions, GitHub). Bootstrap JS bundle added to the reference layout so the dropdown toggle works. Note: `vendor/ahg/ric-model` is a copied path dep (`symlink:false`), so after pulling, run `composer install` or `rm -rf vendor/ahg/ric-model && composer install` to sync.

## v0.8.9 — 2026-04-20

- Fix ErrorException on RiC-R relation detail pages where the OWL does not declare a domain or range — OntologyService normalises all optional fields to null in listRelations() and the
  view model reads them with !empty() guards
## v0.8.8 — 2026-04-20

- OpenAPI profile tags — every /api/ric/v1/* operation now carries both its entity-group tag (Agents, Records, …) and its OpenRiC conformance-profile tag (core-discovery,
  authority-context, …). Swagger UI at /api/ric/v1/docs groups endpoints either way; implementers can see at a glance which profile a given endpoint claim covers. Reads inherit profile from entity tag;
  writes → round-trip-editing; meta endpoints (/, /health, /openapi.json, /docs, /conformance/badge) intentionally profile-free.
## v0.8.7 — 2026-04-20

- Conformance badge endpoint at GET /api/ric/v1/conformance/badge — shields.io-compatible JSON; three modes (summary blue, declared brightgreen, not-declared lightgrey); CORS-open +
  5-min cache; documented in OpenAPI; usable immediately by implementers to embed a live badge in their README
## v0.8.6 — 2026-04-20

- Service description at GET /api/ric/v1/ now emits openric_conformance.profiles declaring the six profiles this server serves; openric-spec --profile probe passes cleanly
## v0.8.5 — 2026-04-20

- Add bin/sync-org-readme helper — one command to copy + commit the landing page into the .github repo (never pushes, matching bin/release policy)
## v0.8.4 — 2026-04-20

- Landing page QC — command-order bug, stack-table honesty, peer-project reframing, hyphenation, dated version header
## v0.8.3 — 2026-04-20

- Landing page: Start here (5m/15m on-ramps) + rebalanced framing of OpenRiC vs peer projects
## v0.8.2 — 2026-04-20

- Refresh org landing page to match v0.8 state — reference browser shipped as feature-complete, clarify what's next vs what's shipped
## v0.8.1 — 2026-04-20

- Refresh org landing page to match v0.8 state — reference browser shipped as feature-complete, clarify what's next vs what's shipped
## v0.8.0 — 2026-04-20

- Phases 4–6 — RiC-CM reference browser feature-complete: detail-view polish (scope notes, broader/narrower, inherited-from anchors), expandable hierarchy tree, user guide, final audit
  against plan (10/10 green)
## v0.7.0 — 2026-04-20

- Phase 4 — detail views now render full declared/inherited UX: scope notes + examples + broader/narrower relations, data-inherited-from portability markers, cross-page anchor jumps,
  attribute inherited-by tree
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
