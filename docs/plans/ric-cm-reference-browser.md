---
status: draft
owner: Johan Pieterse
scope: OpenRiC — new Heratio package `ahg-ric-model`
date: 2026-04-20
related:
  - docs/outreach/damigos-draft.md
---

# Plan — RiC-CM Reference Browser

## Executive summary

Build a RiC-CM 1.0 reference browser inside OpenRiC, modelled on the DLIB Ionian University NavTool but architecturally improved in one specific way: a clean **declared vs. inherited** separation on entity and relation pages (CIDOC-CRM style), with ancestor provenance tagged on every inherited row. Deliverable is a new package **`ahg-ric-model`** living in **OpenRiC's own `packages/` directory** — not Heratio's. Stack: Blade + Alpine.js on `ahg-theme-b5` (no new frontend stack); data sourced from Fuseki (RiC-O) via a new `OntologyService`, with a hybrid fallback (YAML asset) for the 42 RiC-CM attributes if RiC-O doesn't cover them. Scope: reference browser only — graph visualisation is **out of scope for OpenRiC** (collaboration with DLIB Ionian / NavTool team instead — see `docs/outreach/damigos-reply.md`).

**Strategic context**: OpenRiC is being genuinely decoupled from Heratio. The end state is OpenRiC as the authoritative RiC-O implementation, with Heratio eventually consuming RiC features via HTTP API instead of shared in-process packages. This plan therefore begins with a Phase 0 fork of `ahg-core`, `ahg-api`, `ahg-ric` from Heratio into OpenRiC — kept **intact (unpruned)** so the interface remains identical when Heratio switches to API consumption later.

**Total effort: ~10.5 dev-days (≈2 weeks solo)** — Phase 0 decouple + release scaffolding (1 d), Phase 1 load RiC-O + scaffold + services (2.5 d), Phase 2 controllers + routes (1 d), Phase 3 list views (1.5 d), Phase 4 detail views with declared/inherited (2.5 d), Phase 5 hierarchy + polish (1 d), Phase 6 tests + audit (1 d). The previously-planned spike folded into Phase 1 because probes run during planning confirmed RiC-O is not yet loaded in Fuseki.

---

## Evidence from the codebase

Not planning in the abstract. Cited from actual files read.

### OpenRiC current state

- **`composer.json`** — Laravel 12 / PHP 8.3, Tailwind 4. Declared deps: `ahg/core`, `ahg/api`, `ahg/ric` (all `@dev`). Path repo today: `/usr/share/nginx/heratio/packages/*` with `symlink: true` — **this is the coupling Phase 0 removes.** After Phase 0, the path repo will point at `OpenRiC/packages/*` only, and the three shared packages will be copies living inside OpenRiC.
- **`routes/web.php`** — only `/` welcome + entity-path redirects to `OPENRIC_FRONTEND_URL`. Reference-browser routes are a clean greenfield here.
- **`resources/`** — untracked: `css/`, `js/`, `views/` folders exist but empty of application code. The theme is provided by `ahg-theme-b5` via the package.
- **No CLAUDE.md** in OpenRiC itself. Project rules live in memory and in Heratio's CLAUDE.md.

### Heratio package anatomy (the model to copy)

- **`packages/ahg-theme-b5/`** — Blade layouts: `layout.blade.php`, `layout_1col/2col/3col.blade.php`. Each bridges to `theme::layouts.Ncol`. Partial layout at `resources/views/partials/header.blade.php`. This is the chrome we use.
- **`packages/ahg-core/`** — base services in `src/Services/`: `BrowseService`, `AclService`, `MenuService`, `AhgSettingsService`, `ClipboardService`. `BrowseService` is the base for all browse/list pages — **override `getTable()`, `getI18nTable()`, `getI18nNameColumn()`**. We'll subclass it for the reference lists.
- **`packages/ahg-ric/`** — already exists. Manages **user RiC data** (entities like places, activities, rules, instantiations) — NOT the conceptual model. Has:
  - `src/Services/SparqlQueryService.php` — queries user data in Fuseki at `heratio` dataset (per `config/ahg-ric.php`, `FUSEKI_ENDPOINT=http://localhost:3030/heratio`). Interface: `search()`, `getEntity($uri)`, `getRelationships($uri)`, `getStatistics()`, `clearCache()`.
  - `src/Services/RelationshipService.php`, `RicSerializationService.php`, `ShaclValidationService.php`.
  - `src/Http/Controllers/LinkedDataApiController.php`, `OaiPmhController.php`.
  - Routes under `/admin/ric/…` and `/ric-api/…`.
  - Conclusion: **this package is busy with instance CRUD and API concerns. Reference-model browsing is a separate concern and belongs in a new sibling package.**
- **`packages/ahg-settings/resources/views/fuseki-settings.blade.php`** — Fuseki configuration UI already exists.

### Fuseki state (verified 2026-04-20, localhost)

- Runs as Docker container `stain/jena-fuseki` (Jena 5.1.0) on port 3030.
- Admin creds: `admin` / `admin123` (from `/var/lib/fuseki/shiro.ini`).
- **Two datasets exist**:
  - **`/ric`** — Heratio's production data, ~17.9M triples.
  - **`/openric`** — OpenRiC's user data, ~7,192 triples. Uses URIs like `https://ric.theahg.co.za/entity/record/553` with types `rico:Record`, props `rico:title`, `rico:identifier`, `rico:scopeAndContent`.
- **Critical finding**: **neither dataset has RiC-O OWL definitions loaded.** Both datasets return 0 for `COUNT(?c) WHERE { ?c a owl:Class }`. The project memory "RiC-O loaded" referred to the namespace being in *use*, not the ontology being *loaded as OWL*. We will need to load the OWL ourselves — see Phase 1.
- OpenRiC's `.env` has no Fuseki config today. Phase 0 adds `FUSEKI_URL`, `FUSEKI_DATASET_DATA=openric`, `FUSEKI_DATASET_MODEL=openric-model`, `FUSEKI_USER`, `FUSEKI_PASSWORD`.

---

## Architectural decisions

### 1. Package shape — new package in OpenRiC's own `packages/` dir

- **Decision**: new Laravel package at **`/usr/share/nginx/OpenRiC/packages/ahg-ric-model/`**, namespace `AhgRicModel\`. Owned by OpenRiC, not Heratio. Never symlinked from Heratio.
- **Why not extend `ahg-ric`**: `ahg-ric` is about user entity CRUD / API / validation / OAI-PMH. Ontology reference browsing is a different domain; bolting it into `ahg-ric` mixes concerns and grows an already-busy package.
- **Why not in Heratio's `packages/`**: per the decoupling decision (see Phase 0), OpenRiC must be able to boot on a server that has no Heratio directory on disk. Any new OpenRiC-owned package lives in `OpenRiC/packages/`.
- **Naming**: `ahg-ric-model` (conceptual *model* browser) is clearer than `ahg-ric-reference` (ambiguous — reference data vs. reference entries) or `ahg-ric-nav` (too NavTool-specific).
- **OpenRiC wiring**: after Phase 0 restructures the composer path repo to `packages/*` (OpenRiC-local), add `"ahg/ric-model": "@dev"` and `composer update` resolves. Package self-registers via `AhgRicModelServiceProvider`.
- **Zero Heratio imports**: no `use AhgCore\…` lines that would make this package non-portable. Design it as if it will eventually ship as a standalone public package on Packagist.

### 2. Data source — hybrid (SPARQL against a dedicated model dataset + YAML for attributes)

- **Decision**:
  - **New Fuseki dataset `/openric-model`** — holds the RiC-O OWL ontology (classes, subclass hierarchy, object/datatype properties, domain/range, inverses). Loaded in Phase 1 from the official ICA-EGAD/RiC-O GitHub release (latest version at implementation time). Kept strictly separate from `/openric` (user data) — different concerns, different lifecycles.
  - **`OntologyService`** queries `/openric-model` via SPARQL for classes + relations + hierarchy.
  - **YAML asset** in `resources/data/ric-cm/{version}.yaml` holds the 42 RiC-CM **attributes** (since RiC-CM attributes are a presentation concept above the OWL and aren't fully expressed in the RiC-O ontology).
- **Why this shape**:
  - Two datasets, not one: loading RiC-O OWL into `/openric` would pollute the inference closure over user instance data — if an ontology asserts `rico:Record rdfs:subClassOf rico:RecordResource`, sudden auto-classification changes over user data. Keep them separate.
  - Hybrid SPARQL+YAML: the OWL has what it has; anything it doesn't model (RiC-CM attributes as a distinct concept) goes in YAML. We don't invent fake OWL triples to flatten this — we're honest about the two sources.
  - Clean-room: zero shipping of NavTool data. The YAML is sourced from the ICA RiC-CM 1.0 PDF (authoritative text, not Damigos's curation).
- **Caching**: `Cache::remember('ricmodel.entities.{version}', 86400, …)`. Reference data is near-immutable. Cache bust via `artisan ric-model:rebuild-cache`.

### 3. URL structure — versioned, with a stable "latest" alias

- **Public** (no auth — it's a reference). **Unversioned URLs always serve the latest RiC-CM version**:
  - `/reference/ric-cm/` — index (hierarchy + counts)
  - `/reference/ric-cm/entities` — list of 19 entities
  - `/reference/ric-cm/entities/{id}` — detail (e.g. `/reference/ric-cm/entities/RiC-E02`)
  - `/reference/ric-cm/attributes`, `/reference/ric-cm/attributes/{id}`
  - `/reference/ric-cm/relations`, `/reference/ric-cm/relations/{id}`
  - `/reference/ric-cm/relation-attributes`, `/reference/ric-cm/relation-attributes/{id}`
- **Versioned URLs** for citation stability — e.g. a 2028 paper citing `/reference/ric-cm/1.0/entities/RiC-E05` must never 404:
  - `/reference/ric-cm/{version}/…` — same sub-routes as above.
  - Example: `/reference/ric-cm/1.0/entities/RiC-E05`, `/reference/ric-cm/2.0/entities/RiC-E05`.
  - Version identifiers match the YAML asset filenames (`1.0.yaml`, `1.0.2.yaml`, etc.).
- **Why versioned**: explicit design for RiC-CM 2.x. When ICA releases RiC-CM 2.0, we ship it as an additional YAML asset; `/reference/ric-cm/1.0/…` keeps working for anyone who linked it.
- **Unversioned URL behaviour**: internally 302-redirect to the latest version's URL (`/reference/ric-cm/entities/RiC-E05` → `/reference/ric-cm/1.0/entities/RiC-E05`). This keeps bookmarks current while preserving citability.
- **Why `/reference/` prefix**: reserves namespace for CIDOC-CRM, ISO 15489, PREMIS later.
- **Why IDs in URLs, not slugs**: `RiC-E02` is the actual stable identifier in the ontology.

### 4. View layer — Blade + Alpine.js on `ahg-theme-b5`

- **Decision**: Blade + Alpine.js. No Inertia, no Vue, no Livewire.
- **Why**: Heratio's entire convention is Blade + theme-b5. Introducing a new frontend stack for one package is wrong (bundle cost, onboarding cost, maintenance cost). Alpine covers the only interactive bit we need: "Show inherited" toggle. If Alpine isn't already in the theme (verify in Phase 1), we add it via the layout partial.

### 5. Inheritance computation — server-side, in `OntologyService`

- **Decision**: compute declared vs. inherited in the service layer; views receive pre-structured sections.
- **Data contract** for entity page (see §7 below): `declaredAttributes`, `inheritedAttributes` (each row has `inheritedFrom: {id, name}`), `declaredRelations`, `inheritedRelations`. View just iterates sections.
- **Why server-side**: deterministic, cacheable, testable, and — critically — **portable**. The logic is the "improvement over NavTool" we want to offer upstream (see §8). Keeping it in a service (not a Blade helper) means a JS port to contribute upstream to NavTool is a straightforward translation.
- **No flattening**: declared relations stay declared. The view never sees "Agent + Person + Group + Mechanism + Family" as an undifferentiated Domain list.

### 6. Search — not in Phase 1

- 19 + 42 + 170 + 6 = 237 items total. Client-side string match on the list pages is sufficient. No ES indexing for the reference model. Revisit if users ask for fuzzy/semantic search across the model.

### 7. Authentication — public read, admin rebuild

- Browse routes: `web` middleware only, no auth. This is public reference documentation.
- `artisan ric-model:rebuild-cache` and `artisan ric-model:reload-yaml` — CLI only, not routed.
- Admin-facing "model version" info page at `/admin/reference/ric-cm/status` (behind auth) — shows cache age, RiC-O version loaded, YAML asset hash.

---

## Phase 0 — Decouple OpenRiC from Heratio (1 dev-day)

**Runs before everything else. Without this, OpenRiC can't boot on a server without Heratio on disk, and the whole "decoupled" story is theatre.**

### Goal

OpenRiC becomes self-contained: it owns copies of `ahg-core`, `ahg-api`, `ahg-ric`, vendored into `OpenRiC/packages/`, with no symlink or path reference into `/usr/share/nginx/heratio/`.

### Deliverables

1. **New directory**: `/usr/share/nginx/OpenRiC/packages/`.
2. **Copy (not symlink)** the three current dependencies:
   - `cp -r /usr/share/nginx/heratio/packages/ahg-core OpenRiC/packages/`
   - `cp -r /usr/share/nginx/heratio/packages/ahg-api OpenRiC/packages/`
   - `cp -r /usr/share/nginx/heratio/packages/ahg-ric OpenRiC/packages/`
   - Copy intact — **no pruning, no namespace changes, no interface modifications**. When Heratio later consumes RiC via OpenRiC's HTTP API, the current package interface is still the reference contract. Pruning now would break that alignment.
3. **Update `OpenRiC/composer.json`** — replace the repositories block:
   ```json
   "repositories": [
       {
           "type": "path",
           "url": "./packages/*",
           "options": {
               "symlink": false
           }
       }
   ]
   ```
4. **`composer update ahg/*`** — refreshes lock against the new location.
5. **`.env` additions** — Fuseki connection config for OpenRiC (was absent). Add to `.env.example` and local `.env`:
   ```
   FUSEKI_URL=http://localhost:3030
   FUSEKI_DATASET_DATA=openric
   FUSEKI_DATASET_MODEL=openric-model
   FUSEKI_USER=admin
   FUSEKI_PASSWORD=admin123
   ```
6. **Release flow scaffolding** — per the "Release flow" section above:
   - `version.json` at repo root, initial content: `{"version": "0.1.0", "codename": "genesis", "released": "2026-04-20"}`.
   - `bin/release` script, executable, implementing the steps from §Release flow.
   - `CHANGELOG.md` seeded with a `## v0.1.0 — 2026-04-20` heading and the pre-existing commit messages summarized.
   - Document `bin/release` usage in `README.md`.
7. **Drift tracking** — create `docs/drift-log.md` with a header explaining purpose and the first entry being Phase 0 itself ("vendored ahg-core/api/ric from heratio@<SHA>").
8. **Boot verification**: `php artisan route:list` runs clean; existing OpenRiC routes (welcome + entity redirects) still work.
9. **Inaugural release**: `./bin/release minor "Phase 0 — decouple OpenRiC from Heratio; vendor ahg-core/api/ric; add release flow"` — produces tag `v0.2.0`.

### Acceptance criteria

- Temporarily renaming `/usr/share/nginx/heratio` → `heratio.bak` and running `composer dump-autoload && php artisan route:list` produces no errors. Restore after verification.
- `OpenRiC/composer.lock` contains only local path references under `OpenRiC/packages/`; zero paths into `heratio/`.
- `grep -r "heratio" OpenRiC/composer*.json OpenRiC/composer*.lock` returns zero hits (outside documentation/comments).
- `./bin/release patch "no-op test"` (on a throwaway branch) bumps version, creates tag, does not push. Revert before merging Phase 0.
- OpenRiC has a tagged `v0.2.0` on `main` with all the above committed.

### Risks

- **Heratio and OpenRiC `ahg-ric` drift**: Heratio still has its own copy of `ahg-ric`, and both will evolve. This is intentional — divergence is the point. But we must document which is authoritative going forward. **Decision: OpenRiC's copy becomes authoritative for RiC concerns; Heratio's becomes legacy, to be removed when Heratio starts consuming OpenRiC's API.**
- **Future fixes to shared packages**: bug fix in `ahg-core` needs to land in both copies until the Heratio-via-API transition completes. Low friction if we periodically `diff` the two trees; we'll track drift in `docs/drift-log.md` (one-line entries noting cherry-picks).

### What Phase 0 does NOT do

- Does not extract the packages to separate git repos (that's the "option 2" future state we decided to defer).
- Does not publish anything to Packagist.
- Does not modify Heratio in any way — Heratio still has its identical copies; they just stop being OpenRiC's source.
- Does not touch `ahg-theme-b5` or other Heratio-only packages that OpenRiC doesn't depend on today.

---

## Spike resolved on 2026-04-20 (before plan write)

The original plan had a spike to verify RiC-O attribute coverage. That spike ran during planning and the result is:

**Neither `/ric` nor `/openric` has RiC-O OWL loaded — both contain only instance data using the RiC-O namespace.** So the question "does the loaded OWL cover the 42 attributes?" is moot: nothing is loaded. Phase 1 therefore owns the load step.

SPARQL probe results (for the record):

```
/ric      — 17,912,075 triples, 0 owl:Class definitions, 0 rico: classes
/openric  —      7,192 triples, 0 owl:Class definitions, 0 rico: classes
```

This means the hybrid data-source design (SPARQL for classes/relations/hierarchy against `/openric-model`, YAML for RiC-CM attributes) is the correct shape, **and Phase 1 includes downloading and loading the RiC-O OWL** as its first step — not a separate pre-phase activity.

---

## Phase plan

**Rule (per standing memory):** each phase ships complete or doesn't start. No TODOs, no stubs, no partial implementations. Each phase ends with an **audit checklist** that must read 100% before we move on.

### Phase 1 — Load RiC-O, scaffold package, `OntologyService` (2.5 dev-days)

**Goal**: RiC-O OWL is loaded into a dedicated Fuseki dataset; `ahg-ric-model` package exists and self-registers; `OntologyService` returns real data from SPARQL + YAML for all four types.

**Deliverables**:

*Data-load (0.5 d — the former "spike" task):*
- **Create Fuseki dataset `/openric-model`** via Fuseki admin UI or `curl -u admin:admin123 -X POST http://localhost:3030/$/datasets --data 'dbName=openric-model&dbType=tdb2'`.
- **Download RiC-O OWL**: fetch latest from `github.com/ICA-EGAD/RiC-O/releases/latest` (record git tag + SHA). Save to `packages/ahg-ric-model/resources/data/ric-o/{version}.ttl`.
- **Load into Fuseki**: `curl -u admin:admin123 -X POST -H 'Content-Type: text/turtle' --data-binary @ric-o.ttl http://localhost:3030/openric-model/data`.
- **Verify**: `SELECT (COUNT(DISTINCT ?c) AS ?n) WHERE { ?c a owl:Class }` returns ≥ 19 on `/openric-model`.
- **Source the 42 RiC-CM attributes**: extract from ICA RiC-CM 1.0 PDF (or, if Damigos grants license, from NavTool's `store_data.json` with attribution). Produce `packages/ahg-ric-model/resources/data/ric-cm/1.0.yaml` — **complete file, all 42 entries, no TBD rows**.
- **Provenance file**: `packages/ahg-ric-model/resources/data/ric-cm/loaded-versions.md` records RiC-O version/SHA and RiC-CM attribute source.

*Package scaffold (0.5 d):*
- `packages/ahg-ric-model/composer.json` (namespace `AhgRicModel\`, require `laravel/framework`, no Heratio imports).
- `src/Providers/AhgRicModelServiceProvider.php` — registers config, routes, views, console commands.
- `config/ahg-ric-model.php` — Fuseki endpoint, dataset name, cache TTL, default model version.

*Services (1.5 d):*
- `src/Services/OntologyService.php` with public methods:
  - `listEntities(?string $version = null): array`
  - `getEntity(string $id, ?string $version = null): ?array`
  - `listAttributes(?string $version = null): array`
  - `getAttribute(string $id, ?string $version = null): ?array`
  - `listRelations(?string $version = null): array`
  - `getRelation(string $id, ?string $version = null): ?array`
  - `listRelationAttributes(?string $version = null): array`
  - `getRelationAttribute(string $id, ?string $version = null): ?array`
  - `getHierarchy(?string $version = null): array` (tree structure)
  - `listAvailableVersions(): array`
  - `latestVersion(): string`
- `src/Services/InheritanceResolver.php` — **pure PHP, zero Laravel imports** (so it remains portable for upstream JS contribution). Methods:
  - `resolveEntityMembers(array $entity, array $allEntities, array $hierarchy, array $relations, array $attributes): array` — returns `['declaredAttributes' => …, 'inheritedAttributes' => …, 'declaredRelations' => …, 'inheritedRelations' => …]` with `inheritedFrom: {id, name}` on every inherited row. **No flattening.**
  - Documented at file head with a contract block + note that the JS port signature must match one-for-one.

**Acceptance criteria**:
- `curl -s http://localhost:3030/openric-model/sparql?query=SELECT+(COUNT(*)+AS+?n)+WHERE+{?s+?p+?o}` returns a non-zero count matching the loaded RiC-O OWL size.
- `App::make(OntologyService::class)->listEntities()` returns exactly 19 entries for RiC-CM 1.0.
- `->getEntity('RiC-E05')` returns `Record` with declared attributes/relations AND inherited ones, each inherited row tagged with ancestor `{id, name}`.
- `->getRelation('RiC-R016')` returns `has successor` with declared Domain/Range as single entries (not flattened). Domain = `{id: RiC-E01, name: Thing}` (or whatever RiC-O actually declares — no expansion).
- `->listAvailableVersions()` returns `['1.0']`.
- `php artisan test packages/ahg-ric-model` passes. Unit coverage: each public service method has a test asserting the structured contract.
- `grep -r "use Illuminate\\|use Laravel" packages/ahg-ric-model/src/Services/InheritanceResolver.php` returns zero hits (portability constraint).

**Risks**:
- RiC-O OWL's property naming may not align 1:1 with RiC-CM 1.0 relation IDs (`RiC-R016`). Mitigation: the service layer normalises between OWL URIs and RiC-CM IDs using a mapping file derived during the load step.
- SPARQL perf on repeated hierarchy traversal — mitigated by 24h cache.
- If the latest RiC-O release deviates from RiC-CM 1.0 in structurally significant ways, fall back to the v1.0 RiC-O release (record in `loaded-versions.md`).

### Phase 2 — Controllers, routes, versioned URLs (1 dev-day)

**Goal**: every reference route (versioned and unversioned) returns the right data shape to the view layer, with caching in place.

**Deliverables**:
- `src/Controllers/EntityController.php` — `index(?string $version)`, `show(?string $version, string $id)`.
- Same shape for `AttributeController`, `RelationController`, `RelationAttributeController`.
- `src/Controllers/ReferenceIndexController.php` — `/reference/ric-cm/` and `/reference/ric-cm/{version}/` landing.
- `routes/web.php` registers **both** route families using a single route group + route-model-binding on `{version}`:
  - `/reference/ric-cm/…` — resolves `$version` to `OntologyService::latestVersion()`, redirects to `/reference/ric-cm/{latestVersion}/…` with 302.
  - `/reference/ric-cm/{version}/…` — serves directly.
- `src/Http/Middleware/EnsureVersionExists.php` — 404s if `{version}` is not in `OntologyService::listAvailableVersions()`.
- `app/Console/Commands/RebuildCache.php` — `artisan ric-model:rebuild-cache [--version=…|--all]`.
- `app/Console/Commands/LoadOntology.php` — `artisan ric-model:load-ontology {file} [--dataset=openric-model]` — reusable for future version loads.

**Acceptance criteria**:
- `/reference/ric-cm/entities` 302-redirects to `/reference/ric-cm/1.0/entities`.
- `/reference/ric-cm/1.0/entities` returns 200 with full list.
- `/reference/ric-cm/2.0/entities` returns 404 (version not loaded yet).
- `/reference/ric-cm/1.0/entities/RiC-E05` returns 200; `/reference/ric-cm/1.0/entities/RiC-E99` returns 404.
- `artisan ric-model:rebuild-cache` clears and warms cache; subsequent page hits <50 ms.
- Fuseki down → graceful degradation with a `not-configured` partial (pattern exists at `heratio/packages/ahg-ric/resources/views/not-configured.blade.php` — our copy of `ahg-ric` after Phase 0 has the same file; use as reference).

### Phase 3 — List views (1.5 dev-days)

**Goal**: all four list pages render with filter, sort, and navigation.

**Deliverables**:
- `resources/views/index.blade.php` — landing, shows hierarchy tree + counts.
- `resources/views/entities/index.blade.php`, `attributes/index.blade.php`, `relations/index.blade.php`, `relation-attributes/index.blade.php`.
- `resources/views/partials/_search-filter.blade.php` — client-side filter input (Alpine).
- `resources/views/partials/_breadcrumb.blade.php` — shared breadcrumb.
- All extend `theme::layouts.2col` (per §4 decision).

**Acceptance criteria**:
- Each list renders full data (19 / 42 / 170 / 6 rows).
- Alpine filter narrows visible rows on keystroke, no server roundtrip.
- Mobile viewport: table collapses to cards (Bootstrap 5 responsive utilities).
- Every row links to the detail page; detail page links back with breadcrumb.

### Phase 4 — Detail views with declared/inherited (2.5 dev-days)

**The headline phase.** This is where OpenRiC does what NavTool doesn't.

**Deliverables**:
- `resources/views/entities/show.blade.php`:
  - Header block: name, ID, definition, scope notes, examples, comments.
  - "**Declared attributes**" section — always visible, table.
  - "**Declared relations**" section — always visible, table with Domain / Relation / Range columns; Domain/Range are single declared entries (not expanded).
  - "**Inherited attributes**" section — collapsed by default, Alpine toggle, each row tagged `(from RiC-E02 Record Resource)` linking to ancestor.
  - "**Inherited relations**" section — same pattern.
  - "**Subclasses (browsing aid)**" section — collapsed, clearly labelled as navigation, not semantic.
  - Ancestor chain breadcrumb at top (`Thing > Record Resource > Record > …`).
- `resources/views/relations/show.blade.php`:
  - Header: ID, name, inverse pair, cardinality, definition.
  - "**Declared Domain**" — single entity, linked.
  - "**Declared Range**" — single entity, linked.
  - "**Subclasses covered (for navigation)**" expander — shows descendants, explicitly marked as a browsing aid, not a domain/range claim.
  - Broader / Narrower relations sections.
- `resources/views/attributes/show.blade.php` — declared on [Entity], inherited by descendants list.
- `resources/views/relation-attributes/show.blade.php` — simpler; applies-to-all-relations pattern.
- Pest/PHPUnit feature tests asserting:
  - Entity show page has the four sections.
  - Inherited rows carry `data-inherited-from` attribute for each ancestor (for both test assertion and upstream JS portability).
  - Relation show page shows declared Domain/Range as single entries, never flattened into a sub-class list.

**Acceptance criteria**:
- Manual walkthrough of `RiC-E05 Record`: declared attributes match RiC-CM 1.0 spec exactly; inherited attributes show the full chain from `Record Resource → Thing`; each inherited row has its provenance tag.
- Manual walkthrough of `RiC-R016 has successor`: Domain = Agent (not Agent + Person + Group + …). "Subclasses" expander lists descendants with a clear `Use these links to navigate; the relation's domain is Agent.` note.
- A11y: tab-order sensible, toggles keyboard-operable, `aria-expanded` correct.

### Phase 5 — Hierarchy tree + polish (1 dev-day)

**Deliverables**:
- `resources/views/partials/_hierarchy.blade.php` — expandable tree from Thing down (19 nodes, four levels). Shown on landing page and sidebar.
- Cross-link polish: clicking an inherited tag jumps to ancestor's **declared** section (anchor); back-link restores context.
- Empty-state and loading-state polish (only relevant during Fuseki outages).
- Responsive pass; print stylesheet for the reference pages.

**Acceptance criteria**:
- Tree is keyboard-navigable, expand/collapse via Alpine, persists state in session storage.
- All cross-links work without JS enabled (progressive enhancement — Alpine is the layer over a working baseline).

### Phase 6 — Tests, documentation, audit (1 dev-day)

**Deliverables**:
- `tests/Unit/OntologyServiceTest.php` — full coverage of the 8 public methods.
- `tests/Unit/InheritanceResolverTest.php` — declared/inherited correctness for at least `RiC-E02`, `RiC-E05`, `RiC-R016`.
- `tests/Feature/ReferenceBrowserTest.php` — hits every route, asserts structure.
- `packages/ahg-ric-model/README.md` — install, config, cache commands.
- `docs/reference-browser-user-guide.md` in OpenRiC — links to `/reference/ric-cm/`, explains the declared/inherited distinction for users.
- Final audit pass: every item below reads ✓.

**Audit checklist (must be 100% before announcing done)**:
- [ ] All 9 routes return 200 and a full page (no partial / TODO / placeholder content).
- [ ] Every service method has a unit test; coverage ≥ 80% on `src/Services/`.
- [ ] Feature tests pass for every detail page.
- [ ] No `TODO`, `FIXME`, `@todo` strings anywhere in `packages/ahg-ric-model/`.
- [ ] No hardcoded RiC-CM data in controllers or views; everything flows through `OntologyService`.
- [ ] Cache rebuild command works and is documented.
- [ ] Package README exists and is accurate.
- [ ] OpenRiC `composer.json` updated; `composer update` resolves cleanly.
- [ ] Manual inspection of `RiC-E05 Record`, `RiC-R016 has successor`, `RiC-A01 Accruals` shows the declared/inherited separation correctly.
- [ ] No console errors on any page; Lighthouse a11y ≥ 90.

---

## UX mocks

### Entity page — `/reference/ric-cm/entities/RiC-E05`

```
┌───────────────────────────────────────────────────────────────────────┐
│  Reference / RiC-CM / Entities / Record                               │
├───────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Record                                         [ RiC-E05 ]           │
│                                                                       │
│  Ancestors:  Thing ▸ Record Resource ▸ Record                         │
│                                                                       │
│  Definition                                                           │
│  A record resource made or received as a whole unit in the course     │
│  of some activity…                                                    │
│                                                                       │
│  Scope notes · Examples · Comments                                    │
│  [ expandable panels ]                                                │
│                                                                       │
├───────────────────────────────────────────────────────────────────────┤
│  Declared attributes (3)                                              │
│  ┌──────────────────┬────────────────────────────────────┐            │
│  │ RiC-A22 Origin.. │ Information on …                  │            │
│  │ RiC-A24 Record.. │ A description of …                │            │
│  │ …                │                                    │            │
│  └──────────────────┴────────────────────────────────────┘            │
│                                                                       │
│  Declared relations (7)                                               │
│  ┌──────────┬───────────────────────────┬────────────────┐            │
│  │ Relation │  Name                     │  Range         │            │
│  │ RiC-R001 │  is related to            │  Thing         │            │
│  │ RiC-R016 │  has successor            │  Record        │            │
│  │ …        │                           │                │            │
│  └──────────┴───────────────────────────┴────────────────┘            │
│                                                                       │
│  ▶ Inherited attributes (12)                                          │
│  ▶ Inherited relations (34)                                           │
│  ▶ Subclasses (browsing aid) (0)                                      │
│                                                                       │
├───────────────────────────────────────────────────────────────────────┤
│  EXPANDED: ▼ Inherited attributes                                     │
│  ┌──────────────────┬────────────────────────┬──────────────────────┐│
│  │ RiC-A10 Content… │ Textual description …  │ from Record Resource ││
│  │ RiC-A18 Language │ The language of …      │ from Record Resource ││
│  │ …                │                         │                      ││
│  └──────────────────┴────────────────────────┴──────────────────────┘│
│  (each "from X" is a link to that ancestor's declared section)        │
└───────────────────────────────────────────────────────────────────────┘
```

### Relation page — `/reference/ric-cm/relations/RiC-R016`

```
┌───────────────────────────────────────────────────────────────────────┐
│  Reference / RiC-CM / Relations / has successor                       │
├───────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  has successor                                  [ RiC-R016 ]          │
│  Inverse:  has predecessor  [ RiC-R016i ]                             │
│  Cardinality:  M to M                                                 │
│                                                                       │
│  Definition                                                           │
│  Relates a record to the record that succeeds it …                    │
│                                                                       │
├───────────────────────────────────────────────────────────────────────┤
│  Domain                                                               │
│    Record  [ RiC-E05 ]          ← single declared entry, linked       │
│                                                                       │
│  Range                                                                │
│    Record  [ RiC-E05 ]          ← single declared entry               │
│                                                                       │
│  ▶ Domain subclasses (for navigation, 0)                              │
│  ▶ Range subclasses (for navigation, 0)                               │
│                                                                       │
│    Note:  The subclass lists are navigation aids. The relation's      │
│    declared domain is Record; clicking a subclass takes you to its    │
│    declared-relations section so you can browse from there.           │
│                                                                       │
├───────────────────────────────────────────────────────────────────────┤
│  Broader relations                                                    │
│    is related to  [ RiC-R001 ]                                        │
│                                                                       │
│  Narrower relations                                                   │
│    (none)                                                             │
└───────────────────────────────────────────────────────────────────────┘
```

### Attribute page — `/reference/ric-cm/attributes/RiC-A01`

```
┌───────────────────────────────────────────────────────────────────────┐
│  Reference / RiC-CM / Attributes / Accruals                           │
├───────────────────────────────────────────────────────────────────────┤
│  Accruals                                       [ RiC-A01 ]           │
│                                                                       │
│  Declared on                                                          │
│    Record Set  [ RiC-E03 ]      ← single declared entity              │
│                                                                       │
│  ▶ Also applies to (inherited by, 0)                                  │
│                                                                       │
│  Definition · Specifications · Value schema · Repeatability · …       │
└───────────────────────────────────────────────────────────────────────┘
```

---

## Data contract (what the view receives)

### Entity show page — controller passes this to the view:

```php
[
    'entity' => [
        'id'             => 'RiC-E05',
        'name'           => 'Record',
        'definition'     => '…',
        'scopeNotes'     => ['…', '…'],
        'examples'       => ['…'],
        'comments'       => ['…'],
        'ancestors'      => [
            ['id' => 'RiC-E01', 'name' => 'Thing'],
            ['id' => 'RiC-E02', 'name' => 'Record Resource'],
        ],
        'descendants'    => [],  // subclasses (browsing aid only)
    ],
    'declaredAttributes' => [
        ['id' => 'RiC-A22', 'name' => 'Origination Date', 'definition' => '…'],
        // …
    ],
    'inheritedAttributes' => [
        [
            'id'            => 'RiC-A10',
            'name'          => 'Content Type',
            'definition'    => '…',
            'inheritedFrom' => ['id' => 'RiC-E02', 'name' => 'Record Resource'],
        ],
        // …
    ],
    'declaredRelations' => [
        [
            'id'     => 'RiC-R016',
            'name'   => 'has successor',
            'domain' => ['id' => 'RiC-E05', 'name' => 'Record'],
            'range'  => ['id' => 'RiC-E05', 'name' => 'Record'],
        ],
        // …
    ],
    'inheritedRelations' => [
        [
            'id'            => 'RiC-R001',
            'name'          => 'is related to',
            'domain'        => ['id' => 'RiC-E01', 'name' => 'Thing'],
            'range'         => ['id' => 'RiC-E01', 'name' => 'Thing'],
            'inheritedFrom' => ['id' => 'RiC-E01', 'name' => 'Thing'],
        ],
        // …
    ],
]
```

**Note**: `declaredRelations` and `inheritedRelations` both hold declared-shape rows (single domain, single range). No expansion over descendants. Expansion is purely a navigation concern handled in the "Subclasses (browsing aid)" section, which is a separate panel with different semantics.

---

## Upstream-contribution readiness (if Damigos grants license)

If Damigos adds a LICENSE to `DLIB-Ionian-University/ric-cm-nav`, the following modules are clean-room portable to JavaScript for a PR back to NavTool:

- `src/Services/InheritanceResolver.php` → JS module `inheritanceResolver.js`. Same method signatures, same data shape. The resolver is pure: takes `entities`, `hierarchy`, `relations`, `attributes` arrays → returns structured declared/inherited. No Laravel deps.
- Data contract above → directly drives NavTool's `EntityCardView.vue` / `RelationCardView.vue` with minimal refactor on their side (replace their `data_service.js` flattening with calls to the resolver).
- UX mocks above → reusable sketches for their PR description.

**Implication for our code**: write `InheritanceResolver` with zero Laravel-specific imports (pure PHP arrays, no Eloquent, no `Cache` facade, no `config()`). Dependencies are injected via constructor. That way the JS port is one-for-one, and we can publish it as a gist or PR attachment alongside the license request.

---

## Resolved decisions (from 2026-04-20 conversation)

1. **Fuseki dataset for the ontology** — create new `/openric-model` dataset (Phase 1). Existing `/openric` stays for user data. Admin creds `admin`/`admin123` are in `/var/lib/fuseki/shiro.ini`; must be moved to OpenRiC's `.env` as `FUSEKI_USER` / `FUSEKI_PASSWORD`. Public SPARQL reads are allowed anonymously per Fuseki's default dataset config.
2. **RiC-O version** — use the latest published release from `github.com/ICA-EGAD/RiC-O` at Phase 1 execution time. Download the OWL file fresh; record the git tag/SHA in `resources/data/ric-cm/loaded-versions.md` for provenance.
3. **URL prefix** — `/reference/ric-cm/…` (with versioned sub-path for citation stability — see §3.3 above).
4. **Authentication** — public. No auth middleware on browse routes. Admin-only `/admin/reference/ric-cm/status` behind auth for cache/version inspection.
5. **Release flow** — defined now (see new "Release flow" section below). `version.json` + `bin/release` script, modelled on Heratio's.
6. **Drift cadence** — per-bug-fix. Every cherry-pick from Heratio's shared packages into OpenRiC's copies (or vice-versa) adds a one-line entry to `docs/drift-log.md` at commit time.
7. **Multi-version RiC-CM** — design for 2.x from day one. YAML asset structure is `resources/data/ric-cm/{version}.yaml`; URL structure supports `/reference/ric-cm/{version}/…`; unversioned URLs redirect to latest.
8. **Damigos reply** — ship clean-room regardless. The `InheritanceResolver` and declared/inherited data contract are the contribution we'd propose upstream; they stay portable (pure PHP, no Laravel imports) so a JS port is straightforward if and when permission arrives.

---

## Release flow for OpenRiC (defined as part of this plan)

Modelled on Heratio's `bin/release` but scoped to OpenRiC's own lifecycle. Created as a Phase 0 deliverable.

### Files

- **`version.json`** at repo root — `{"version": "0.1.0", "codename": "…", "released": "YYYY-MM-DD"}`.
- **`bin/release`** — shell script. Required arg: `patch|minor|major`. Required arg: commit message.
- **`CHANGELOG.md`** — markdown, human-written, updated before each release.

### `bin/release` behaviour

```
./bin/release patch "Short description of changes"
./bin/release minor "Feature X" --issue 42
./bin/release major "Breaking change"
```

Steps (bail on any failure — no partial releases):
1. Require clean working tree (`git status --porcelain` empty).
2. Require on branch `main` (configurable later if we add release branches).
3. Bump `version.json` per semver arg.
4. Prepend a heading to `CHANGELOG.md` with the new version + date + message.
5. `git add version.json CHANGELOG.md`.
6. Create commit: `vX.Y.Z — {message}` (with `--issue N` appending `(#N)`).
7. Create annotated git tag `vX.Y.Z`.
8. **Do NOT push.** Print the `git push && git push --tags` command for the user to run. Per standing memory rule: never push without explicit user instruction.

### Rules

- **Never push without using `bin/release`.** Matches existing memory rule ("Always push with version control: update version.json + git tag"). The script enforces the invariant.
- **Never bypass hooks** (`--no-verify` etc.) in `bin/release` — per memory rule.
- **`version.json` is modified only by `bin/release`** — per Heratio convention.
- **Tag commits are atomic** — one version bump per commit, not bundled with unrelated changes.

### First use

Phase 0 itself ends with `./bin/release minor "Phase 0 — decouple OpenRiC from Heratio; vendor ahg-core/api/ric; add bin/release"`. That's the inaugural use.

---

## Open items

None blocking. Two items to track:

- **RiC-O GitHub release URL** — confirm the canonical download URL at Phase 1 time (`github.com/ICA-EGAD/RiC-O/releases/latest`). ICA-EGAD occasionally reorganizes; verify before scripting the fetch.
- **Damigos reply** — if/when it arrives, the only knock-on is whether we also lift his curated RiC-CM attribute descriptions (under the new license). If yes, the YAML source changes from "extracted from ICA PDF" to "derived from NavTool store_data.json under license, attributed"; plan otherwise unchanged.

---

## Not in this plan (deferred)

- **RiC-CM Modeling Playground** (graph viz of the **conceptual model**) — **out of scope for OpenRiC** (decision 2026-04-24). Collaborate with DLIB Ionian University (Matt Damigos) instead, who flagged direct overlap with their archival linked-data research. See `docs/outreach/damigos-reply.md` and the `project_damigos_collaboration` memory.
- **User-holdings graph** (graph viz of the **user's actual archival data** in `/openric`) — **IN SCOPE**, planned separately in `docs/plans/user-holdings-graph.md`. Sequenced after this reference-browser ships so entity URIs are clickable destinations from graph nodes. Most code is already ported from Heratio (`RicController` graph methods, `explorer.blade.php`); remaining work is rebind + harden, ~2 weeks.
- **SPARQL query playground** — browser UI for running arbitrary SPARQL against Fuseki. Tempting but out of scope; would live in its own package if needed.
- **Validator integration** — "this entity I'm cataloguing should probably be RiC-E07 Activity, not RiC-E06 Record Part" suggestions. Phase 6+ when we have enough user data for training or rules.
- **i18n of the reference model** — RiC-CM 1.0 is English-only; translations would require ICA/EGAD cooperation. Revisit when translations exist upstream.
