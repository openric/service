# Changelog

## v0.9.1 — 2026-05-25

### Spec-canonical semantic URIs + spec_version refresh

- **New: `/id/{kind}/{id}` semantic URI resolver** (`packages/ahg-ric/routes/web.php`). Implements openric-spec viewing-api §3.1's recommended two-layer URI pattern: a stable linked-data identity URI separate from the API-endpoint URI. 13 kinds per the spec: `record`, `record-set`, `record-part`, `agent`, `person`, `corporate-body`, `family`, `mechanism`, `place`, `rule`, `activity`, `instantiation`, `function`. Content-negotiates Accept and 303-redirects to either the JSON-LD API endpoint (`/api/ric/v1/{collection}/{id}` for machine clients) or the public slug page (browsers). Sets `Vary: Accept` on every response. Numeric ids on slug-keyed collections (records / agents / repositories) are resolved through the `slug` table. `corporate-body` falls back to `/repositories/…` when the resolved entity lives in the ISDIAH repository table rather than the actor table.
- **Spec version tracker bumped 0.37.1 → 0.38.0** in `$openricConformance` (`packages/ahg-ric/routes/api.php`). Spec v0.38.0 (Wave B) is additive on top of v0.37.1 — sparql-access SHACL + fixtures, related-implementations + outreach drafts, extension proposals — none of which require new service behaviour beyond declaring the new version string. Six normative profile claims unchanged; sparql-access remains Draft + opt-in.

## v0.9.0 — 2026-04-25

### Phase G — service migration to spec v0.37.x (RiC-O 1.1 namespace remediation)

**Major version bump.** This release brings the OpenRiC reference service into conformance with spec v0.37.0/v0.37.1 (the RiC-O 1.1 namespace remediation completed on the spec side on 2026-04-25). The change set is **breaking for downstream consumers** — emitted JSON-LD shapes now use canonical RiC-O 1.1 property names and the `openricx:` extension namespace; old field names (`rico:heldBy`, `rico:hasInstantiation`, `rico:hasSubject`, etc.) are no longer emitted.

**What downstream consumers must update**

- **Heratio**: any code reading `record["rico:heldBy"]`, `record["rico:hasInstantiation"]`, `agent["rico:hasAgentName"]` etc. needs to switch to the canonical names below. ✅ Heratio v0.125.2 shipped 2026-04-25 with the dual-read pattern (Phase G' c).
- **Viewer (viewer.openric.org)**: edge-predicate dispatch must accept the new canonical property IRIs and the new activity-type pattern (every event is `rico:Activity` + `rico:hasActivityType <vocab IRI>`, no more `rico:Production` / `rico:Accumulation` classes). ✅ openric-viewer v0.3.0 shipped 2026-04-25 with the dual-read pattern (Phase G' a).
- **Capture client**: form fields posting under old names will be silently dropped on v0.9.0 servers; update to canonical names. ✅ openric-capture v0.5.0 shipped 2026-04-25; capture is namespace-agnostic by design — only doc/framing changes were needed (Phase G' b).

**Re-attempt context.** The first attempt at this release (commit `c4c2867`) was reverted (`ea4f417`) on the same day so that the consumer-first sub-phases G' a / b / c could ship and propagate before the producer flip. With viewer v0.3.0, capture v0.5.0, and Heratio v0.125.2 all on origin and tolerant of both pre-v0.37 and v0.37+ shapes, this v0.9.0 replays the original change set and adds the OpenRiC service's own consumer-side dual-read on `_ric-panel.blade.php` (the mini-graph Blade partial) so that the in-service explorer UI also reads the new shape correctly.

**Major changes**

- **Conformance declaration** (`packages/ahg-ric/routes/api.php` `$openricConformance`): `spec_version` bumped from `0.36.0` to `0.37.1`. Six profile claims unchanged (provenance-event still gated on data-backfill, sparql-access not claimed by default).
- **JSON-LD `@context`** (`RicSerializationService::ricoContext()` new helper): every emitted record now binds `rico`, `openric`, **`openricx`**, `rdf`, `rdfs`, `xsd`, `skos`, `dcterms`, `owl`. 8 inline @context blocks across the serializer collapsed to use the helper.
- **Activity remodel** — every event is `@type: rico:Activity` with `rico:hasActivityType <https://openric.org/vocab/activity-type/{kind}>` (per spec v0.37 §6.5). `eventTypeToRic` array (which mapped source `creation` → `Production`, etc.) replaced by `eventTypeToActivityType` returning vocab slugs; `activityTypeIri()` resolves to full IRIs. Production/Accumulation/CustodyEvent class assertions removed from emitted JSON-LD; SHACL `:ProductionShape` / `:AccumulationShape` in `tools/ric_shacl_shapes.ttl` rewritten as `sh:SPARQLTarget` filtering on `rico:Activity` + `hasActivityType`.
- **Rule-regulation remodel** — emissions for AccessRestriction (`RicSerializationService::1240`) and SecurityClassification (line 786) now use the canonical `rico:Rule` + `rico:hasOrHadRuleType <https://openric.org/vocab/rule-type/{kind}>` pattern instead of the non-canonical `rico:AccessRestriction` / `rico:SecurityClassification` classes.
- **OpenAPI tag descriptions** (`packages/ahg-ric/src/Support/OpenApiSpec.php`): Repositories tag clarified as ISDIAH API surface canonical to `rico:CorporateBody`; Functions tag clarified as ISDF API surface canonical to interim `openricx:Function`; Activities tag explains the hasActivityType pattern; Graph tag clarifies SPARQL is non-normative under the optional sparql-access Draft profile.
- **OAI-PMH `rico_ld` envelope** (`OaiPmhController::renderRicoLd`): inner element renamed from `<rico:jsonld>` (non-canonical) to `<openricx:jsonld>`; `xmlns:openricx` now declared on the wrapping element.
- **FindingAid / AuthorityRecord query** (`RicController::1441`): rewrote SPARQL to use the canonical RiC-O 1.1 documentary-form-types vocabulary IRI (`https://www.ica.org/standards/RiC/vocabularies/documentaryFormTypes#`) instead of the wrong `rico:` prefix expansion. These named individuals are NOT in the `rico:` ontology namespace.
- **Mechanical RENAMEs across the service** (~25 terms, applied by Phase G script): `hasInstantiation→hasOrHadInstantiation`, `isInstantiationOf→isOrWasInstantiationOf`, `hasSubject→hasOrHadSubject`, `hasLanguage→hasOrHadLanguage`, `hasName→hasOrHadName`, `legalStatus→hasOrHadLegalStatus`, `dateType→hasDateType`, `extentType→hasExtentType`, `ruleType→hasOrHadRuleType`, `normalizedDate→normalizedDateValue`, `startDate→hasBeginningDate`, `dateOfEstablishment→hasBeginningDate`, `performs→performsOrPerformed`, `hasMandate→authorizingMandate`, `hasRecordPart→includesOrIncluded`, `isContainedIn→isOrWasIncludedIn`, `tookPlaceAt→isAssociatedWithPlace`, `precedes→precedesInTime`, `isOrWasAssociatedWithDate→isAssociatedWithDate`, `hasAgentName→hasOrHadAgentName`, `hasPlaceName→hasOrHadPlaceName`, `heldBy→hasOrHadHolder`, `isOrWasHeldBy→hasOrHadHolder`, `hasHolding→isOrWasHolderOf`, `isOrWasLocatedAt→hasOrHadLocation`, `hasPlace→isAssociatedWithPlace`, `hasProvenance/Of→hasOrganicProvenance/isOrganicProvenanceOf`, `isDescribedBy→isOrWasDescribedBy`, `hasAccessRestriction/hasAccessPolicy/hasSecurityClassification→isOrWasRegulatedBy`, `isOrWasControlledBy→hasOrHadController`, `managedBy→hasOrHadManager`, plus DROPs/cross-namespace moves to skos/dcterms/openricx.
- **EXTENSION renames** to `openricx:` (~30 terms): all List envelope classes (RecordList, AgentList, etc.), DateRange/DateRangeSet, ContactPoint, description, descriptiveNote, hasMimeType, languageCode, streetAddress/city/country/postalCode/telephone/email, jurisdiction, alternativeForm/normalizedForm/otherName, technicalCharacteristics, productionTechnicalCharacteristics, hasAppraisalInformation, containsPersonalData, hasOrHadPolicy, hasInternalStructure, hasBroaderGeographicalContext / hasNarrowerGeographicalContext, etc.
- **Consumer-side dual-read on `_ric-panel.blade.php`** (new in this re-attempt; not in original `c4c2867`). The mini-graph Blade partial's inline JS now uses the same `localName` / `getEffectiveType` / dual-read `getColor(node)` pattern shipped in openric-viewer v0.3.0 and Heratio v0.125.2. Activity-bucket colour entries expanded (Mechanism, Custody, Transfer, Publication, Reproduction); a v0.37+ `rico:Activity` node carrying `attributes.activityType` (slug) or `attributes.hasActivityType` (IRI) resolves to the same colour as a pre-v0.37 `rico:Production` etc., and back-compat string callers still work. Sanity tests covering pre-v0.37 + v0.37+ + null/unknown all pass.

**Audit metric**: pre-Phase-G the service emitted 137 distinct `rico:*` tokens missing from canonical RiC-O 1.1; post-Phase-G that's 5 — all in `MUST NOT emit X` documentation prose (Production, Accumulation, agent, jsonld, SecurityClassification mentions in code comments explaining what was removed). Zero genuine emit-context violations remain.

**Provenance & Event profile claim** still NOT made — backing data has 177 Production rows missing `resultsOrResultedIn` / `hasOrHadParticipant`. Data-backfill task unchanged.

**SPARQL Access (Draft) profile** not claimed by default. Implementations wishing to advertise SPARQL MAY add `{'id': 'sparql-access', 'version': '0.1.0', 'conformance': 'partial', 'access': 'public-read', 'rate_limit': '60/minute/IP', 'max_query_time_seconds': 30, 'endpoint': '/api/ric/v1/sparql'}` to `$openricConformance['profiles']` in `routes/api.php` after mounting their SPARQL endpoint.

## v0.8.19 — 2026-04-24

- User-holdings graph — Phase 3 (hub-collapse + perf budget). `RelationshipService::getGraphSummaryByUri` rewritten as a two-step SPARQL: first a cheap `GROUP BY ?p ?direction` COUNT to discover relation-bucket shape, then a selective details query via `VALUES ?p` for buckets below threshold. Buckets whose neighbour count exceeds the configured threshold collapse into a single synthetic `GroupCollapse` node (`{id: group:<hash>, type: 'GroupCollapse', count: N, center_uri, predicate, predicate_label, direction}`) plus one edge from the centre — so a Person with 200 Records renders as Person + 1 group node, not 201 individual nodes. Response shape gains structured `reasons: string[]` (subset of `['hub_collapsed', 'max_nodes']`), `threshold: int`, `max_nodes: int` — widget and explorer surface the flags distinctly instead of a single "capped" chip.

- New paginated expansion path for hub buckets: `RelationshipService::expandGroup(centerUri, predicateUri, direction, page, perPage)` with endpoint `GET /ric-api/expand-group?node=…&predicate=…&direction=out|in&page=1&per_page=50`. Returns `{center_uri, predicate, direction, page, per_page, total, has_more, nodes, edges}`. Per-page cap 200 (hard), default 50.

- Configurable thresholds in `config/ahg-ric.php`:
  - `hub_collapse.threshold` (default 25, env `RIC_HUB_COLLAPSE_THRESHOLD`) — per-bucket count above which the collapse engages. Per-request override via `?collapse_threshold=N` on graph-summary-by-uri.
  - `graph_cache_seconds` (default 900, env `RIC_GRAPH_CACHE_SECONDS`) — TTL for `Cache::remember`-wrapped graph + group responses, keyed on `(uri, maxNodes, threshold)` and `(center, predicate, direction, page, perPage)` respectively.

- Cache layer: `Cache::remember` now wraps `getGraphSummaryByUri` and `expandGroup`. Invalidation is manual — new artisan command `php artisan ric:graph-cache:clear` flushes the cache (whole-cache flush because most drivers can't prefix-delete; operators with Redis can pattern-delete `ric-graph:*` directly). Write-paths into Fuseki are separate and don't know about the graph cache, so TTL-based staleness bounds it for now; event-driven invalidation deferred until we have a write-event bus.

- Explorer (`resources/js/explorer.js`) grew GroupCollapse handling: group nodes render grey (`#6b7280`), larger size (val=7 vs. 4 for typed entities). Clicking a group node fires `expandGroup(groupNode, page=1)` against the new endpoint, ingests the paginated nodes + edges into the existing state, and updates the group node's label to `"N/M · predicate"`. Fully-expanded groups dim to `#374151` and shrink. Status bar now shows explicit reason chips: `(hubs collapsed)`, `(capped)`, or `(hub + cap)`. Bundle rebuilt via `npm run build` — same size profile as Phase 2 (~1.9 MB / 507 KB gzipped).

- Widget (`_context-sidebar.blade.php`) renders hub buckets before the typed-entity list under a "Large buckets" header — each bucket shown as "<predicate> — <count>". Footer chips updated to match the explorer: separate `hubs collapsed` (info blue) and `capped at N` (warning yellow) badges with tooltips explaining each.

- Smoke tests (`/openric`, record 901990 is the hub — 68 outgoing `openricx:hasOrHadPlaceOfOrigin`):
  - default `threshold=25`: `nodes=2 edges=1 reasons=['hub_collapsed']`, one GroupCollapse with `count=68`.
  - `collapse_threshold=1000 max_nodes=200`: collapse suppressed, `nodes=69 groups=0` — confirms threshold actually controls behaviour.
  - `expand-group` `page=1 per_page=20`: returns 20 nodes + 20 edges, `has_more=true total=68`.
  - `expand-group` `page=4 per_page=20`: returns last 8 nodes, `has_more=false` (20×3 + 8 = 68).
  - `expand-group` invalid `direction` / `predicate` → HTTP 400.
  - Cache hit second-call latency: 64 ms end-to-end (includes curl + artisan-serve overhead).
  - `php artisan ric:graph-cache:clear` flushes and is picked up on next request.
  - Regression: Phase 2 `/admin/ric/explorer` still 200, `/ric-api/search?q=France` still returns 2.

- Known limits (deferred):
  - Cache is whole-cache-flush on clear (not tag-aware) — good enough for database cache driver in OpenRiC today, will want Redis + tag invalidation for multi-tenant use.
  - No event-driven invalidation yet. If a write lands in Fuseki, the graph cache is stale until TTL expires (15 min) or `ric:graph-cache:clear` is run. Phase 4+ hookup to `RicEntityService` write events is the clean way to close this.
  - Hub detection scales with distinct predicates × 2 directions (cheap). Label lookup for the centre node is a separate SPARQL round-trip — could be folded into the count query later if latency bites.
  - ACL hash not yet part of the cache key — OpenRiC has no user ACLs on the graph path today. When one lands, the cache key needs extending to include it or the cache must be scoped per-ACL.

- Note: `vendor/ahg/ric` is a path-repo copy (`"symlink": false`); after pulling run `rm -rf vendor/ahg/ric && composer install`. Re-run `npm run build` to pick up the explorer.js changes.

## v0.8.18 — 2026-04-24

- User-holdings graph — Phase 2 (explorer route + theme integration). `packages/ahg-ric/resources/views/explorer.blade.php` rewritten as a self-contained page: dropped `@extends('theme::layouts.1col')` (no Heratio theme in OpenRiC), loads Bootstrap 5 + Font Awesome from CDN for chrome, bundles the graph renderer via Vite (`@vite(['resources/js/explorer.js'])`). Dropped the Heratio-only create-entity modal, timeline view, semantic-search link, and 'Back to RiC Dashboard' button — out of scope for the minimum viable explorer. New `resources/js/explorer.js` (~280 lines) is a self-contained ES module: imports `force-graph` (2D) and `3d-force-graph` (3D, which bundles three.js under the hood), exposes `window.RicExplorer.boot(rootEl)`, supports search→seed→expand→2D/3D toggle→fullscreen, dedupes nodes+edges across expansions, and honours the server `truncated` flag. Dependencies added to `package.json`: `3d-force-graph ^1.80.0`, `force-graph ^1.51.4`, `three ^0.162.0`. `vite.config.js` input expanded to include `resources/js/explorer.js`; `npm run build` produces `public/build/assets/explorer-*.js` (~1.9 MB / 507 KB gzipped — three.js dominates; loaded only when the explorer page is opened).

- Two new backend endpoints to drive the explorer:
  - `GET /ric-api/search?q=…&limit=15` — SPARQL-based label search across `/openric`. Matches `rico:title` ∪ `skos:prefLabel` ∪ `rico:textualValue` ∪ `rdfs:label` via `CONTAINS(LCASE(...))`. Returns `[{uri, label, type}]` for any `rico:` or `skos:` entity. Short-circuits to empty results on queries < 2 chars. `RelationshipService::searchByLabel(string $query, int $limit = 15)`.
  - `GET /ric-api/expand?node=<uri>&max_nodes=25` — fetches the neighbourhood of any URI-identified node. Wraps `getGraphSummaryByUri` under the hood so it inherits the `truncated` + `max_nodes` response contract. `RelationshipService::expandNode(string $uri, int $maxNodes = 25)`.

- Smoke tests (all against `/openric`, verified via `php artisan serve`):
  - `GET /admin/ric/explorer` → 200, 6,262 bytes, contains `ric-explorer-root` / `RicExplorer.boot` / `ric-autocomplete-input` / Vite-injected `explorer-*.js` markers.
  - `GET /ric-api/search?q=France` → 2 results: a `Place` and its `PlaceName` (`https://ric.theahg.co.za/entity/place/901207` + name). Type extraction strips namespace.
  - `GET /ric-api/expand?node=…&max_nodes=20` on hub record 901990 → `nodes=20 edges=19 truncated=true` (record has 69 total).
  - `GET /ric-api/expand?node=not-a-url` → `HTTP 400`.
  - `GET /ric-api/search?q=` (empty) → `success=true results=0` (no SPARQL call).
  - Regression: Phase 1 dev-widget still renders (`/ric-api/dev/widget`); Phase 0 `graph-summary-by-uri` still returns capped data.

- Known limits (deferred):
  - Bootstrap 5 + Font Awesome still load from CDN in `explorer.blade.php` and `_dev-widget.blade.php`. Offline-archive deployments need these bundled via Vite too — deferred to a later hygiene pass.
  - SPARQL `CONTAINS(LCASE(...))` label search is linear. Fine on `/openric`'s 7,192 triples, wouldn't scale to Heratio-class (17.9M) — an Elasticsearch-backed `/ric-api/search` will slot in behind the same JSON contract if needed.
  - The legacy `/ric-api/autocomplete` endpoint (Heratio MySQL, Records only, int-ID) is untouched — still there for Heratio-side callers; the explorer uses the new SPARQL `/ric-api/search` instead.
  - Other Heratio-era views in `packages/ahg-ric/resources/views/` (index, config, sync-status, entities/*, etc.) still `@extends('theme::layouts.1col')` — they're orphaned admin pages, out of Phase 2 scope, will surface whenever their owning feature gets a proper adaptation.

- Note: `vendor/ahg/ric` is a path-repo copy (`"symlink": false`); after pulling run `rm -rf vendor/ahg/ric && composer install`. Vite build assets (`public/build/*`) are produced by `npm install && npm run build`.

## v0.8.17 — 2026-04-24

- User-holdings graph — Phase 1 (portable relationships widget). `_context-sidebar.blade.php` rewritten as a URI-first include: `@include('ahg-ric::_context-sidebar', ['resourceUri' => $uri, 'explorerUrl' => '/explorer', 'maxNodes' => 50])`. No more Heratio `ric_view_mode` session gate, no more int-ID assumption — any host (Heratio, future OpenRiC frontend, third-party) can mount it by passing a RiC URI. Fetches `/ric-api/graph-summary-by-uri?uri=…&max_nodes=…` and renders entities grouped by RiC-O type with icon/count/top-5 labels and a truncation-warning chip when the server cap engages. Server-side cap added to `RelationshipService::getGraphSummaryByUri(string $uri, ?string $centerLabel = null, int $maxNodes = 50)` — hard ceiling 500, SPARQL LIMIT set to `maxNodes * 2`, response gains `truncated` + `max_nodes` fields so the widget can surface when the graph was capped. `RicController::getGraphSummaryByUri` accepts `?max_nodes=` query param. Dev-demo route `GET /ric-api/dev/widget?uri=…` renders the widget standalone (Bootstrap 5 + Font Awesome from CDN for the demo only) — gated on `APP_DEBUG=true`, 404s otherwise. Smoke tests: `max_nodes=10` returns 10 nodes / `truncated: true`; default cap (no param) returns 50 nodes / `truncated: true` against record 901990 (hub, 69 total relations); invalid URI returns HTTP 400; dev-widget HTML contains the widget markup. Scope note: original plan envisaged mounting the widget directly on OpenRiC entity detail pages, but OpenRiC today is headless (`routes/web.php` redirects all `/informationobject/*`, `/actor/*`, etc. to `OPENRIC_FRONTEND_URL`) — Phase 1 therefore ships the widget as a publishable include + dev-demo, and the 3d-force-graph vendoring originally planned for Phase 1 defers to Phase 2 (explorer), where a graph renderer is actually needed. The Heratio widget this was adapted from is also list-based, not force-graph — decision matches upstream precedent.

## v0.8.16 — 2026-04-24

- User-holdings graph — Phase 0 (audit + rebind config). `RelationshipService::loadConfig()` and `RicController::getFusekiConfig()` are now env-first (OpenRiC `FUSEKI_*` vars win, Heratio `setting`/`ahg_settings` tables are the fallback, not the primary). `config/ahg-ric.php` unifies the two previously-drifting Fuseki config blocks, reads the actual `FUSEKI_*`/`FUSEKI_DATASET_*` vars, and exposes `fuseki.dataset_model` for the RiC-CM reference browser. New `RelationshipService::getGraphSummaryByUri(string $uri)` + `RicController::getGraphSummaryByUri` + `GET /ric-api/graph-summary-by-uri?uri=…` — URI-first entry point the Phase 1 widget/explorer will consume (the int-ID route stays for Heratio back-compat). Four siblings (`ShaclValidationService`, `RicSerializationService`, `SparqlQueryService`, `LinkedDataApiController`) moved off the broken `heratio.fuseki_endpoint` config lookup onto `ahg-ric.fuseki_endpoint`. `resolveEntityUri()` column-name bug fixed (`fuseki_uri` → `ric_uri`). Five `services.ric.*` fallback strings in `RicController` repointed with OpenRiC defaults. Preflight error messages now name the correct env vars (`FUSEKI_URL` / `FUSEKI_DATASET_DATA`). Smoke test: `GET /ric-api/graph-summary-by-uri?uri=https%3A%2F%2Fric.theahg.co.za%2Fentity%2Frecord%2F901990` returns `{success: true, graph: {total_nodes: 69, total_edges: 68}}` against real `/openric` data. Residual-staleness scan across `packages/ahg-ric/src/` and `config/` for `heratio.fuseki`, `services.ric.fuseki`, `fuseki_uri`, and `RIC_FUSEKI_URL is not set`: **0 matches**. Note: `vendor/ahg/ric` is a path-repo copy (`"symlink": false` in `composer.json`), so after pulling run `rm -rf vendor/ahg/ric && composer install` to sync.

## v0.8.15 — 2026-04-21

- Data backfill: new one-shot `packages/ahg-ric/database/backfill_activity_participants.sql` adds `performed_by` relations for Productions whose sibling creation events (`event.type_id=111`) name an actor the activity itself doesn't. Ran against the reference store: 10 rows inserted, productions-with-participant moved from 35 → 45 (of 222). Remaining 177 are genuine archival gaps (no creator attested anywhere in the store) and need per-record curator judgment. Idempotent; safe to re-run. Service still does not claim `provenance-event` in `openric_conformance.profiles` — 45 of 222 is not full conformance.

## v0.8.14 — 2026-04-21

- Refresh `openric_conformance.profiles` declaration in `packages/ahg-ric/routes/api.php`. Previously carried stale `0.3.0-draft` across all entries from pre-freeze authoring. Now claims openric-spec v0.36.0 and 6 of 7 profiles at their frozen versions: core-discovery 0.3.0, authority-context 0.4.0, graph-traversal 0.5.0, digital-object-linkage 0.6.0, round-trip-editing 0.7.0, export-only 0.9.0. Provenance & Event deliberately excluded — data gap per the v0.8.13 / v0.8.15 entries. Both `GET /api/ric/v1/` and `GET /conformance/badge` read this block, so leaving it stale meant clients saw one thing on `/` and another in OpenAPI.

## v0.8.13 — 2026-04-21

- `RicSerializationService::serializeActivity()` now emits `rico:resultsOrResultedIn` (records produced, from `dropdown_code='results_from'` relations) and `rico:hasOrHadParticipant` (agents involved, from `dropdown_code='performed_by'` relations — the backing DB's `rico:isOrWasPerformedBy` predicate is the RiC-O subproperty; serializer normalises up to the broader profile-level predicate). Closes the Provenance & Event reference-impl gap flagged in openric-spec `spec/profiles/provenance-event.md` §9 Q5. Activities with complete backing data now pass `:StrictProductionShape`. Activities with incomplete backing data (see v0.8.15) still fail the shape for data reasons, not impl reasons.

## v0.8.12 — 2026-04-21

- `RicSerializationService::getContactFor()` now emits `@type: openricx:ContactPoint` (was `rico:Contact`, which is not a valid RiC-O class). Aligns the reference implementation with `spec/mapping.md` §5.2.2 and the newly normative `spec/profiles/core-discovery.md` §3.4.1. Heratio's identical line carries the same bug — drift log records the divergence with direction `openric→heratio`.

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
