---
status: draft
owner: Johan Pieterse
scope: OpenRiC — graph visualisation of user-holdings RiC-O data
date: 2026-04-24
related:
  - docs/plans/ric-cm-reference-browser.md
  - docs/outreach/damigos-reply.md
---

# Plan — User-Holdings Graph (OpenRiC)

## Executive summary

Bring a graph view of the user's archival holdings (the data in Fuseki `/openric`, not the conceptual RiC-CM model) into OpenRiC as a first-class feature. Two surfaces: an **entity-page widget** (1–2 hop neighborhood, embedded on every entity detail page) and a **full graph explorer** at `/explorer` (search → seed → expand → filter → export). Backed by SPARQL traversals against `/openric`. The conceptual-model graph (RiC-CM Modeling Playground) remains **out of scope** — that is collaboration territory with DLIB Ionian (`docs/outreach/damigos-reply.md`).

**Critical evidence-based finding**: this is **not greenfield**. Heratio has a working graph explorer (`packages/ahg-ric/resources/views/explorer.blade.php`, 475 lines, using `3d-force-graph` v1.73.3) and a 2,740-line `RicController.php` with `getGraphSummary()`, `buildGraphData()`, `buildOverviewGraph()`, `buildOverviewGraphFromDatabase()`. Both files are **already ported byte-identically** into OpenRiC's `packages/ahg-ric/`. The remaining work is: rebind storage from MySQL to OpenRiC's PostgreSQL + `/openric` Fuseki dataset; wire into the theme; add hub-collapse + perf budget for scale; harden, test, document.

**Total effort: ~10 dev-days (~2 weeks solo)** — Phase 0 audit + rebind config (1 d), Phase 1 widget on entity pages (2 d), Phase 2 explorer route + theme integration (2 d), Phase 3 hub-collapse + perf budget (2 d), Phase 4 filters + export (1.5 d), Phase 5 tests + accessibility fallback + audit (1.5 d).

---

## Evidence from the codebase

Cited from actual files read 2026-04-24 — not abstract.

### What is already ported (do not rewrite)

- **`packages/ahg-ric/src/Controllers/RicController.php`** — 2,740 lines, byte-identical to Heratio's. Includes:
  - `getGraphSummary(int $id)` at line 67 — JSON endpoint for graph data per entity.
  - `buildGraphData($recordId, $endpoint, $username, $password, $baseUri, $instanceId)` at line 1428 — focused subgraph builder.
  - `buildOverviewGraph(string $endpoint, string $username, string $password)` at line 1232 — full-dataset overview.
  - `buildOverviewGraphFromDatabase(string $baseUri, string $instanceId)` at line 1358 — MySQL fallback (will need rebind, see Phase 0).
  - Returns `{success, graphData: {nodes, edges}}` shape (verified at line 1064).
- **`packages/ahg-ric/resources/views/explorer.blade.php`** — 475 lines. Loads `https://unpkg.com/3d-force-graph@1.73.3/dist/3d-force-graph.min.js` (CDN). Has containers `#ric-explorer-graph-2d`, `#ric-explorer-graph-3d`, `#ric-fullscreen-graph`. 2D/3D toggle, dark canvas (`background:#1a1a2e`).
- **`packages/ahg-ric/resources/views/_context-sidebar.blade.php`** — entity-page sidebar partial that already calls `data.graphData.nodes`/`edges` and renders. This is the basis of the **widget**.
- **`packages/ahg-ric/routes/web.php`** — public graph endpoints already registered:
  - `GET /ric-api/relations/{id}` → `ric.public-relations`
  - `GET /ric-api/graph-summary/{id}` → `ric.public-graph-summary`
  - `GET /ric-api/timeline/{id}` → `ric.public-timeline`
  - `GET /ric-api/explain/{sourceId}/{targetId}` → `ric.public-explain`
  - `GET /ric-api/relations/types` → `ric.public-relation-types`
- **`packages/ahg-ric/src/Services/RelationshipService.php`** — exists. Method `getRelatedEntities(int $entityId, ?string $relationType = null)` does both MySQL + SPARQL paths and merges (line 70). **Coupling issue:** `loadConfig()` (line 46) reads from MySQL `setting` and `setting_i18n` tables — Heratio storage. Needs rebind (Phase 0).
- **`packages/ahg-ric/resources/views/_ric-view-*.blade.php`** — actor, donor, function, repository, rights-holder, storage, term — each already has a "Open in Graph Explorer" button (`fa-project-diagram`). The wiring exists; the views just need to be reachable from the new theme.

### Fuseki state (verified 2026-04-24, localhost)

| Dataset | Triples | RiC-O loaded? | Use here |
|---|---:|---|---|
| `/openric` | 7,192 | n/a (instance data) | **graph data source** |
| `/openric-model` | 16,500 | **Yes — 107 `rico:` classes, 243 `owl:Class` total** | type metadata for legends/colours |
| `/ric` | 17,912,075 | n/a (instance data) | Heratio prod — not used by OpenRiC |

- Container `fuseki` (stain/jena-fuseki, Jena 5.1.0) up 8 days on `localhost:3030`. Auth `admin / admin123`.
- `/openric-model` having RiC-O loaded supersedes the 2026-04-20 plan note that said "neither dataset has RiC-O loaded as OWL". Do not act on that older note.

### Data shape in `/openric` (sized 2026-04-24)

**Entity-type distribution (top 11):**
| Count | Type |
|---:|---|
| 652 | `rico:Record` |
| 371 | `rico:AgentName` |
| 235 | `rico:Person` |
| 184 | `rico:DateRange` |
| 180 | `rico:Place` |
| 180 | `rico:PlaceName` |
| 164 | `skos:Concept` |
| 132 | `rico:CorporateBody` |
| 57 | `rico:RecordSet` |
| 4 | `rico:Family` |
| 1 | `rico:RecordPart` |

**Top relations (top 6 of 24 measured):**
| Count | Predicate |
|---:|---|
| 545 | `rico:isOrWasIncludedIn` (record→record-set tree) |
| 371 | `rico:hasAgentName` |
| 289 | `rico:hasOrHadPlaceOfOrigin` |
| 131 | `rico:hasOrHadSubject` |
| 30 | `rico:hasOrHadCreator` |
| 30 | `rico:history` |

**Implication for sizing:** `/openric` is small (~2,160 typed entities). The full dataset fits in a single 2D Cytoscape/3d-force-graph render with no perf engineering. **But the same code must scale to Heratio-class datasets** (`/ric` has 17.9M triples — ~5,000× bigger). Phase 3 hub-collapse + perf budget is therefore non-negotiable; it's the only difference between "works on demo data" and "works in production".

### Frontend stack constraints

- **`package.json`**: Tailwind 4, Vite 8, Laravel Vite Plugin 3. No React/Vue/Alpine declared at root level (per the reference-browser plan, Alpine.js is the intended interaction layer in `ahg-theme-b5`).
- **`composer.json`**: Laravel 12 / PHP 8.3, packages `ahg/core`, `ahg/api`, `ahg/ric`, `ahg/ric-model` all `@dev`.
- **OpenRiC `.env` line 46**: `FUSEKI_URL=http://localhost:3030`. **Note**: this is the base, not a dataset-scoped URL. The reference-browser plan calls for splitting to `FUSEKI_URL`, `FUSEKI_DATASET_DATA=openric`, `FUSEKI_DATASET_MODEL=openric-model`, `FUSEKI_USER`, `FUSEKI_PASSWORD`. Phase 0 here aligns with that.

### Library choice — `3d-force-graph` (already in use), not Cytoscape.js

Earlier (in chat) Cytoscape.js was suggested. Evidence overrides: the working Heratio code uses `3d-force-graph` (Vasturco). It supports both 2D (via `force-graph` peer) and 3D, has WebGL rendering by default (Three.js under the hood), is MIT-licensed, and is what users have already seen working. **Decision: stay on `3d-force-graph`.** Re-evaluating mid-port would burn 3+ days and lose existing UX. Cytoscape would only win if we needed advanced layouts (`cose-bilkent`, `dagre`); we don't — force-directed is the right default for the archival relationship topology shown above.

One change from CDN to bundled: vendor `3d-force-graph@1.73.3` and `force-graph@1.x` via npm and ship through Vite. Reasons: (a) air-gapped/offline archive deployments are a real OpenRiC use case, (b) CDN pinning to `unpkg.com` is a supply-chain risk for a system handling cultural heritage data, (c) Vite already in stack — no new tooling. Cost: ~2 hours; payoff: durable.

---

## Architectural decisions

### 1. Two surfaces, one backend

- **Widget** (Phase 1): embedded panel on each entity detail page. Seed = current entity. Hop = 1, capped 50 nodes, no filters, click-node-to-navigate-to-its-page. Uses `/ric-api/graph-summary/{id}`. Lightweight; loads inline; degrades gracefully to a relations table when JS off.
- **Explorer** (Phase 2): dedicated `/explorer` route. Seed = search result. Expand-on-click. 2D/3D toggle. Filters (type, relation, date, ACL). Save/share via URL state. Export PNG/SVG/JSON-LD subgraph. Uses same backend endpoints + a new `/ric-api/expand` for incremental neighbour fetches.
- Shared backend: `RelationshipService` + `RicController` graph methods, both already ported. Both surfaces consume `{nodes, edges}` JSON; widget uses focused subgraph, explorer uses lazy expansion of same shape.

### 2. Backend rebind — not rewrite

`RelationshipService::loadConfig()` (line 46) reads from MySQL `setting`/`setting_i18n`. OpenRiC uses PostgreSQL with no `setting` table. Two options:
- **(a) Port the `setting` table** to OpenRiC. Bad — drags Heratio admin-UI assumptions in.
- **(b) Make `loadConfig()` env-first**, falling back to the table only if it exists. Good — OpenRiC reads from `.env`, future Heratio→API split keeps working.

Decision: **(b)**. Wrap the `Schema::hasTable('setting')` check (already there at line 49) so env wins by default. Three lines changed in one file.

### 3. ACL on every traversal step

The widget and explorer must respect ACLs that exist in the `/openric` dataset (Heratio pattern: `BrowseService::applyAclFilter`, plus per-entity `ahg-core` ACL). When expanding a node, the server filters out neighbour edges to entities the user can't see. **Never client-side** — that leaks existence.

### 4. Hub-collapse rule (Phase 3)

When a node has > N (default 25) neighbours of a given relation type, the SPARQL response returns one **synthetic "group" node** labelled "247 records" with a click-to-expand affordance, instead of 247 actual nodes. Threshold per-relation-type, configurable via `config/ahg-ric.php`. Without this, Heratio-class datasets crash the renderer the moment a user expands a productive Person.

### 5. Performance budget (Phase 3)

Hard caps, enforced in `RicController` and surfaced in UI when hit:
- Max nodes per render: 500 (force-graph 2D), 5,000 (3D/WebGL).
- Max hop depth: 4.
- Server SPARQL timeout: 5 s per traversal.
- Cache: per-entity neighborhood, TTL 15 min, keyed on `(entity_uri, hop, user_acl_hash)`.
- Each cap returns `{truncated: true, reason: …}` so the UI can warn rather than silently lose nodes.

### 6. Vendor `3d-force-graph` via Vite

Move from CDN to bundled. Add to `package.json`:
- `3d-force-graph@^1.73.3`
- `force-graph@^1.43.0` (peer for the 2D mode)
- `three@^0.158.0` (peer of 3d-force-graph)

Update `explorer.blade.php` to import via `@vite('resources/js/graph.js')`. New entry `resources/js/graph.js` exports init helpers used by both widget and explorer.

### 7. Accessibility — table fallback (Phase 5)

A graph alone fails WCAG 2.2 AA. Every graph view (widget + explorer) must include a paired data-equivalent: an accessible relations table (entity, relation, target, direction) that renders for screen readers and as a `<noscript>` fallback. Already partly present in `_relation-editor.blade.php`; refactor as shared partial.

---

## Phase plan

### Phase 0 — audit + rebind config (1 day)

1. Run the explorer end-to-end against `/openric` as currently configured. Document what breaks. Expected breakages: MySQL `setting` lookup, dataset-scoped URLs, theme bridge to `ahg-theme-b5`.
2. Rewrite `RelationshipService::loadConfig()` env-first per decision §2.
3. Split `.env` Fuseki config: add `FUSEKI_DATASET_DATA=openric`, `FUSEKI_DATASET_MODEL=openric-model`, `FUSEKI_USER=admin`, `FUSEKI_PASSWORD=admin123`. Update `config/ahg-ric.php` to compose `fuseki.url + '/' + fuseki.dataset_data`.
4. Smoke test: `curl /ric-api/graph-summary/{id}` returns valid `{success, graphData: {nodes, edges}}` against a real `/openric` Record URI.
5. Acceptance: above curl returns >0 nodes for a known seed entity; logs show no MySQL query attempts.

### Phase 1 — widget on entity pages (2 days)

1. Vendor `3d-force-graph` + `force-graph` + `three` via npm; add `resources/js/graph.js` with `initWidget(containerEl, seedUri)` and `initExplorer(containerEl)` exports.
2. Adapt `_context-sidebar.blade.php` to use the bundled init (drop CDN `<script>` references).
3. Add the widget mount to `_ric-view-record.blade.php`, `_ric-view-actor.blade.php`, `_ric-view-place.blade.php`. Cap height 320px. Title "Relationships" with a "Open in Explorer" link.
4. Server cap: `getGraphSummary` enforces `max_nodes=50`, `max_hop=1` for widget calls (parameter-driven, default 50).
5. Acceptance: widget renders on Record/Person/Place pages within 500 ms (cached), nodes are clickable and navigate to entity pages, ACL hides forbidden neighbours.

### Phase 2 — explorer route + theme integration (2 days)

1. Port `explorer.blade.php` to render inside `ahg-theme-b5` `layout.blade.php` (currently it's standalone). Top bar: search box + 2D/3D toggle + overview button.
2. Wire search to existing `/ric-api/autocomplete`; on selection, seed graph and load 2-hop neighborhood.
3. Add `GET /ric-api/expand?node={uri}&rel={uri?}&hop=1` endpoint in `RicController` for incremental neighbour fetching. Response is `{nodes, edges, truncated}`.
4. Fullscreen mode (`#ric-fullscreen-graph` already in markup at line 166): bind a button.
5. Acceptance: explorer loads in <1 s on `/openric`, search → seed → expand 3 nodes works without page reload, fullscreen toggles cleanly.

### Phase 3 — hub-collapse + perf budget (2 days)

1. Implement hub-collapse in `RelationshipService::getRelatedEntities`: if the count for a `(source, predicate)` pair exceeds threshold, return a synthetic node `{id: hash(source,pred), type: 'rico:GroupCollapse', label: '{n} {pred}', collapsed: true, sourceUri, predicateUri, count: n}`.
2. Click handler in `graph.js`: collapsed-group node → fire `expand?node=…&group=…` which paginates the actual neighbours (page size 50).
3. Wire perf caps per decision §5; surface truncation in UI as a banner.
4. Cache layer: Laravel `Cache` tag `ric-graph`, keyed `(entity_uri, hop, user_acl_hash)`, TTL 15 min. Invalidate on entity write via `RicEntityService` events.
5. Acceptance: synthetic test fixture with one Person + 200 Records — widget renders Person + 1 group node, explorer expand-group paginates 50 at a time.

### Phase 4 — filters + export (1.5 days)

1. Filter sidebar in `/explorer`: entity-type checkboxes (auto-populated from `?type` distinct query against current subgraph), relation-type checkboxes, date-range slider (uses `rico:expressedDate` / `rico:hasBeginningDate`).
2. URL state: filters + seed + zoom serialised to query string for share/bookmark.
3. Export buttons: PNG (canvas snapshot), SVG (force-graph SVG renderer fallback), JSON-LD (`CONSTRUCT { ?s ?p ?o } WHERE { … }` over visible nodes — server-side via new `GET /ric-api/export?nodes=…&format=jsonld`).
4. Acceptance: filtering hides nodes without re-fetch; URL roundtrip restores exact view; JSON-LD export validates against RiC-O.

### Phase 5 — tests + accessibility + audit (1.5 days)

1. Unit tests: `RelationshipService::getRelatedEntities` (with/without ACL, hub-collapse, hop limits) — Pest.
2. Integration tests: hit live Fuseki `/openric` with seeded fixtures; assert node counts, ACL filtering, perf caps.
3. Browser test (Playwright): widget renders on Record page; explorer search→seed→expand→export PNG.
4. Accessibility: paired relations-table partial; keyboard nav (Tab cycles nodes, Enter activates); ARIA labels; screen-reader announcement on expand. Test with axe.
5. Documentation: `docs/features/graph.md` with architecture diagram, perf tuning, ACL model.
6. Audit script: `php artisan ahg:audit graph` validates wiring (controller endpoints respond, view files present, JS bundle includes 3d-force-graph, env vars set).
7. Version bump (`version.json`) and tag per the version-control rule.

---

## Open questions / risks

- **Heratio coupling not yet measured.** `RelationshipService::getMysqlRelations` (line 73 area) reads a Heratio MySQL `relation` table. OpenRiC has no such table. Phase 0 must verify whether the SPARQL path alone covers `/openric` — likely yes since `/openric` data was inserted via SPARQL, but confirm. If MySQL path is genuinely needed, port to a Postgres equivalent or remove and rely solely on SPARQL.
- **Theme bridge.** Heratio's `explorer.blade.php` extends Heratio's layout. OpenRiC's `ahg-theme-b5` may not have an exact equivalent — Phase 2 budget assumes a clean port; if it spirals, separate the theme bridge as a Phase 2.5.
- **3d-force-graph SVG export.** WebGL/Canvas → SVG is non-trivial. If the SVG export proves expensive in Phase 4, ship PNG-only and defer SVG to a follow-up; PNG is the dominant ask anyway (publication, slides).
- **`force-graph` ACL recheck on cached responses.** If a user's ACL changes mid-session, cache could leak. Solution: ACL hash in cache key (already in plan). Risk = mostly theoretical for archives where ACLs change rarely.
- **`3d-force-graph` upstream activity.** Last published 2024 per npm. Stable, but unmaintained-adjacent. Mitigation: vendoring (decision §6) means a future fork is straightforward; we're not blocked on upstream.

## Not in this plan (out of scope)

- **RiC-CM Modeling Playground** (graph viz of the conceptual model) — collaboration with DLIB Ionian, not OpenRiC build. See `docs/outreach/damigos-reply.md`.
- **SPARQL query playground** — would live in its own package if needed.
- **Time-series animation** (graph evolution over date ranges) — interesting for archival use, parking until user demand.
- **GraphQL subscriptions for live graph updates** — multi-user live editing is a separate, much larger initiative.

## Acceptance — done means

1. `/openric` graph viz works in OpenRiC against real data, end-to-end, with no MySQL dependency.
2. Widget on entity pages loads in <500 ms from cache.
3. Explorer handles a hub Person with 200+ records without crashing the renderer (hub-collapse).
4. ACL is enforced on every traversal step, never client-side.
5. Accessibility: keyboard nav + screen-reader fallback table on every graph surface.
6. Audit script returns 100% green.
7. Version tagged and documented in `docs/features/graph.md`.
