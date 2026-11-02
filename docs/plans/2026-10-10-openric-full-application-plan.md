# OpenRiC Full Application Plan

An end-to-end plan to grow OpenRiC from a reference API and model browser into a complete, RiC-native archival management application with the functional reach of Heratio, built by reusing Heratio components wherever the code allows it.

| Field | Value |
| --- | --- |
| Document Title | OpenRiC Full Application Plan |
| Author | Dr Johan Pieterse |
| Owner | The Archive and Heritage Digital Commons Group (Pty) Ltd |
| Version | 0.1 |
| Status | Draft for decision |
| Date | 10 October 2026 |
| Reference | OPENRIC-PLAN-2026-10-10 |
| Classification | Internal |
| Repositories surveyed | openric/service at 0.17.0 (bfb0e61); heratio-dev, 121 packages |

: Document control

# Summary

OpenRiC today is a good API with no application around it. Version 0.17.0 serves the `/api/ric/v1` surface, a SPARQL endpoint, SHACL validation, OAI-PMH and the RiC-CM reference browser, and it claims six conformance profiles. It has no login, no staff interface that renders, no public catalogue, no search index, no queue and no schema of its own: the live service reads and writes Heratio's MySQL database, and Fuseki is filled by a batch script.

Heratio has the other half. Its 121 packages cover the whole archival lifecycle, and a survey of every package against the legacy descriptive tables shows that roughly 63 of them touch those tables lightly or not at all. Those can move across. About 26 need a real adapter, and about 26 are welded to the legacy schema and must be rebuilt against a RiC-shaped model.

The plan is therefore not a port and not a rewrite. OpenRiC builds one new thing, a RiC-native description core with its own database, and plugs Heratio's existing packages into it through six small seams. Nine phases, starting with security and ending with sector modules. My working estimate is 38 to 52 weeks for a single development stream; that figure is a planning estimate from package sizes, not a measured velocity, and section 9 says how to tighten it.

Three decisions are needed before Phase 1 starts. They are in section 3.

# Where OpenRiC stands today

The findings below come from reading the repository on 10 October 2026, not from earlier planning documents.

| Area | What exists | What is missing |
| --- | --- | --- |
| Data | 11 Laravel migrations (users, sessions, jobs, openric_*) and SQL installers for ric_place, ric_rule, ric_activity, ric_instantiation, ric_relation_meta | Any migration for object, slug, information_object, actor, repository, relation, term, digital_object. The live `.env` points at Heratio's database |
| Graph | Fuseki datasets `openric` (instance) and `openric-model` (RiC-O 1.1) | Write-through. `ric_sync.sh` runs a Python extractor in batch; API writes never reach Fuseki |
| API | Full CRUD on 8 entity types, relations, import (CSV/JSON, 10k rows), upload, JSON-LD, Turtle, RDF/XML, OAI-PMH | EAD, EAC-CPF, EAC-F; profile negotiation (#13) |
| Auth | API keys with read/write/delete scopes | Login, users, roles, groups, ACL. `/admin/ric/*` carries only `web` middleware |
| Staff UI | 46 views in ahg-ric | 22 of them extend `theme::` layouts and no theme namespace is registered, so they cannot render |
| Public UI | Reference browser, graph explorer | A catalogue. `/` is the stock Laravel welcome page; record URLs redirect to Heratio |
| Search | SQL LIKE and SPARQL label match | An index, full text, facets |
| Digital objects | Upload to `storage/app/uploads`, ImageMagick thumbnails | Derivatives, IIIF, checksums, OCR |
| Operations | One scheduled task, one CI workflow, about 85 tests | Queue workers (`QUEUE_CONNECTION=sync`), a dev instance, production config (`APP_ENV=local`) |

: Current state of the OpenRiC service

Two of these matter more than the rest. First, `/usr/share/nginx/OpenRiC` is the production service; there is no OpenRiC equivalent of heratio-dev, so every change today is a change to ric.theahg.co.za. Second, `OPENRIC_OPEN_WRITE=true` allows anonymous creates on the entity endpoints. That was a sensible choice for a demonstration API. It is not a setting an application holding an institution's records can keep.

On the admin routes: `/admin/ric` and `/admin/ric/config` both returned 404 from the public site when checked today, so something in front of Laravel is blocking them. The application itself does not. That protection should live in the code.

# Decisions needed first

## Decision 1 - what is the system of record

Heratio's ADR 0003 states that relational is the source of truth and the graph is a derived, regenerable projection. The status document of April 2026 records "Moving off MySQL - do not reopen". The OpenRiC project rules say `ric_*` tables stay in Heratio.

A fully functional OpenRiC cannot keep that last rule, because an application that borrows another product's database is not standalone. My recommendation keeps the first two and retires the third:

- OpenRiC gets its **own MySQL 8 database**, with a schema shaped by RiC-CM (Record Resource, Instantiation, Agent, Activity, Rule, Place, Date, and relations as first-class rows) instead of by ISAD(G).
- Relational stays the system of record. Fuseki stays a projection, but a transactional one, fed by an outbox table and a queue worker so a saved record is in the graph within seconds.
- "RiC-native" then means the model, the forms, the identifiers and the API are RiC all the way down, not that every write is a SPARQL update.

The alternative, graph as system of record, is cleaner on paper and costs the reuse. Every one of the 63 portable packages stores its data in SQL sidecar tables keyed by an integer `object_id` and expects transactions, joins and row-level audit. Rebuilding those on a triplestore would turn a year into several.

## Decision 2 - how Heratio code is shared

Heratio loads its packages through a symlinked path repository. OpenRiC vendors copies (`symlink: false`) and tracks divergence by hand in `drift-log.md`. That works for four packages. It will not work for sixty.

Recommendation: the portable packages become shared Composer packages published from one source, required by both products at tagged versions. Changes are made on heratio-dev first, as the dev-first rule already requires, and OpenRiC picks them up by version bump. The four packages OpenRiC already owns stay authoritative in OpenRiC.

## Decision 3 - the OpenRiC dev instance

Stand up `/usr/share/nginx/openric-dev` with its own database, its own Fuseki datasets and its own php-fpm storage drop-in before any Phase 1 code is written. Nothing in this plan should be developed on the live service.

# Target architecture

One Laravel 12 application on PHP 8.3, the same stack as today. Around it:

| Layer | Choice | Source |
| --- | --- | --- |
| System of record | MySQL 8, database `openric`, RiC-CM shaped | New |
| Graph | Fuseki, datasets `openric` and `openric-model`; a separate public dataset holding published triples only | Existing, extended |
| Search index | Elasticsearch, fed from the same outbox as the graph | ahg-search adapted |
| Queue | Redis, with Laravel workers under systemd | New to OpenRiC |
| Images | Cantaloupe IIIF on port 8182 | Heratio configuration |
| File store | OCFL object layout under a storage root | ahg-ocfl |
| AI | `https://ai.theahg.co.za/ai/v1/` only, never a node port | Gateway |
| Shell | The shared OpenRiC shell from openric.org wrapped as the `theme::` layouts | ahg-theme-b5 plus shell v1 |

: Target components

The public SPARQL endpoint deserves a note. Access control enforced in PHP does nothing for a client that queries Fuseki directly, so restricted material must never be in the dataset the public endpoint reads. Publishing a record copies its triples into the public dataset; restricting it removes them. That is simpler than query rewriting and it cannot leak.

## The six seams

The Heratio survey found that the portable packages depend on six things from ahg-core, and nothing else. OpenRiC supplies each one:

1. **Object registry.** Keep the integer `object` table and add an IRI column. Sidecar tables keyed by `object_id` then work unchanged, and no package needs re-keying.
2. **Entity resolver.** Heratio already has `AhgCore\Contracts\DescriptionRepository`, `AgentRepository`, `RelationRepository`, `FunctionRepository` and `PlaceRepository`, bound to MySQL implementations and used by nothing outside ahg-core. OpenRiC implements the same contracts over its own schema. Packages that look up a title or slug by direct query are changed once, in Heratio, to call the contract.
3. **Settings.** `AhgSettingsService` and the `ahg_settings` table, taken as they are.
4. **Menus and dropdowns.** `MenuService` and `ahg_dropdown`. The rule against ENUMs and hardcoded option lists carries over.
5. **Theme.** Register the `theme::` namespace. This alone brings the 22 dead ahg-ric admin views back.
6. **Users and ACL.** A user provider and an `AclService` with the same method names, backed by OpenRiC's tables.

# What gets reused

Counts are PHP files and direct references to legacy descriptive tables, measured by search across each package.

| Tier | Packages | Treatment |
| --- | --- | --- |
| A - as is | audit-trail, backup, help, theme-b5, menu-manage, observability, inference-receipts, ai-compliance, provenance-ai, articles, functions-docs, harris-matrix, rdm, media-streaming, multi-tenant, sharepoint, annotations, ftp-upload, jobs, cart, artwork-request (0 to 3 references each) | Require and configure |
| B - thin adapter | workflow, settings, translation, statistics, jobs-manage, dropdown-manage, custom-fields, forms, static-page, landing-page, feedback, favorites, share-link, integrity, ocfl, pdf-tools, media-processing, archivematica, scan, ingest, image-ar, gis, condition, security-clearance, access-request, request-publish, researcher-manage, marketplace, vendor, loan, doi-manage, ai-chatbot, z3950, biblio-frbr, biblio-bf, resourcesync, version-control (4 to 29 references) | Swap direct lookups for the entity resolver |
| C - real adapter | acl, user-manage, preservation, iiif-collection, 3d-model, c2pa, privacy, icip, extended-rights, search, discovery, dedupe, authority-resolution, federation, oai, export, portable-export, metadata-extraction, label, data-migration, records-manage, spectrum, museum, gallery, dam, archaeology (30 to 95 references) | Rework the data access layer, keep services and views |
| D - rebuild | information-object-manage (969), api (651), core (531), actor-manage (298), term-taxonomy (293), metadata-export (213), accession-manage (184), ai-services (168), repository-manage, storage-manage, donor-manage, rights-holder-manage, function-manage, and the per-standard forms rad, dacs, mods, dc | Rebuild on the RiC core; reuse views and validation where they fit |
| Not reused | mva-claims (client-specific, separate repository), naz, nmmz, narssa (jurisdiction plugins), cdpa and ipsas unless a client needs them | Leave in Heratio |

: Heratio package reuse by tier

The large mixed packages (research at 443 files, library at 263, semantic-search, exhibition, heritage-manage, display, reports) sit between C and D. Their own-table parts port cleanly and their record-facing parts do not. They are scheduled last for that reason.

One thing is deliberately not carried over. Heratio has a package per descriptive standard, each with its own edit form. OpenRiC already holds RiC-O 1.1 in `openric-model` and an `OntologyService` that reads it, so its edit forms should be generated from the model, with ISAD(G), DACS, RAD and Dublin Core offered as named views over the same RiC record. Four packages become four configuration files.

# Phases

| Phase | Outcome | Main reuse | Weeks |
| --- | --- | --- | --- |
| 0 - Secure and stabilise | Dev instance; auth middleware on admin routes; open write off outside the demo tenant; `APP_ENV=production`; issues #2, #4, #5 closed | deploy/ as is | 2 |
| 1 - Foundation | Own database and RiC schema; six seams; login, users, roles; Redis queue; outbox and graph write-through; audit trail | theme-b5, settings, menu-manage, dropdown-manage, audit-trail, jobs, user-manage, acl | 5 to 7 |
| 2 - Description core | Staff create, edit, arrange and publish for record sets, records, parts, instantiations, agents, activities, places, rules, relations with role types (#11); model-driven forms; SHACL on save; revisions | ahg-ric, ahg-ric-model, workflow, version-control, custom-fields, translation | 7 to 9 |
| 3 - Ingest and digital objects | Accession to description; bulk and scan ingest; derivatives; IIIF image and presentation following the pattern from #15; capture.openric.org posting into the same core | ingest, scan, ftp-upload, media-processing, metadata-extraction, pdf-tools, iiif-collection, 3d-model | 5 to 7 |
| 4 - Discovery and public access | Indexed search with facets; public catalogue replacing the redirect to Heratio; graph explorer and viewer on every record; static pages; UI translation | search, discovery, landing-page, static-page, favorites, cart, share-link, feedback, semantic-search | 5 to 6 |
| 5 - Rights and access control | ACL on every read path including graph traversal; published-only public dataset; embargo; clearance; reading-room requests; POPIA handling | security-clearance, extended-rights, privacy, access-request, researcher-manage, request-publish | 4 to 5 |
| 6 - Preservation | Fixity, format identification, virus scan, OCFL storage, PREMIS events expressed as RiC activities, backup, C2PA | preservation, integrity, ocfl, archivematica, backup, c2pa | 4 to 5 |
| 7 - Interoperability and migration | EAD, EAC-CPF and EAC-F in and out (#14); profile negotiation (#13); DOI; federation; a one-command migration from a Heratio or AtoM database | oai, export, portable-export, resourcesync, doi-manage, federation, data-migration, authority-resolution, dedupe | 4 to 6 |
| 8 - Administration and sectors | Reports, statistics, multi-tenancy, help, observability; then museum, library, gallery, DAM and records management as optional modules | reports, statistics, multi-tenant, help, observability, spectrum, museum, library, gallery, dam, records-manage | 2 to 5 for the core, sectors separately |

: Delivery phases

Phase 0 is not optional and is not preparation; it fixes a live service. Phases 1 and 2 are the only ones that are mostly new code, and they are where the plan can go wrong. Everything from Phase 3 on is integration work whose size is known from the package counts.

After Phase 4 OpenRiC is a usable catalogue with description, ingest and public access. That is the natural point for a first institutional pilot, and the right moment to revisit the estimates for phases 5 to 8 against real velocity.

## End to end, as a record travels

A donor offers material. Staff log an accession (Phase 3), which creates an Activity and an Agent. Files arrive by upload, FTP or scanner and are checksummed, virus-scanned and written to OCFL storage (Phases 3 and 6); each becomes an Instantiation with derivatives and an IIIF manifest. An archivist arranges and describes the material in model-driven forms, linking creators, functions, places and mandates (Phase 2). SHACL validates on save. A reviewer publishes through the workflow, which writes the outbox; one worker updates the search index and the graph, and copies the published triples to the public dataset. A researcher finds the record by search or by walking the graph (Phase 4), sees only what the rights and clearance rules allow (Phase 5), and requests a restricted item. Every step lands in the audit trail, and every preservation action is itself a RiC Activity (Phase 6). The same record leaves as JSON-LD, EAD or OAI-PMH (Phase 7).

# Risks

**Schema design.** The RiC-shaped relational schema is the one piece with no precedent in either codebase, and a mistake there is expensive to undo. Mitigation: design it against the existing `/api/ric/v1` contract and the six conformance profiles, which already describe every entity and relation the service promises, and keep the API passing its conformance suite from the first migration.

**Shared packages drifting.** Sixty packages consumed by two products will diverge unless there is one source. Decision 2 addresses it; without that decision the estimate does not hold.

**The adapter work in Heratio.** Replacing direct table lookups with contract calls touches about 38 Heratio packages. It is mechanical, it improves Heratio, and it must go through heratio-dev and the normal release, which makes Heratio's release cadence a dependency of this plan.

**Scale.** The OpenRiC instance dataset is about 7,000 triples. Heratio's is 17.9 million. Write-through, the explorer and the public dataset copy all need testing at the larger figure before Phase 4 is called done.

**AI calls.** Several Heratio packages have the gateway address hardcoded or defaulted in their own code. When they are shared they should read one configuration key, so that node selection stays with the gateway.

# Open issues absorbed by this plan

Issues #2, #4 and #5 fall into Phase 0. Issue #3 (reference browser 3D graph) and the unfinished parts of the user-holdings graph plan fall into Phase 4. Issue #11 is Phase 2, #15 is Phase 3, #13 and #14 are Phase 7. Issues #7, #8, #10, #12, #16 and #17 are specification work and continue on their own track; none of them blocks the application.

# Tightening the estimate

The week ranges come from package sizes and coupling counts. Two short pieces of work would replace them with measurements: porting one Tier A package (audit-trail) and one Tier B package (workflow) into a dev instance through the six seams, and drafting the Phase 1 schema against the API contract. Both fit inside Phase 0 and Phase 1, and the results should be used to re-plan before Phase 2 is committed.

# Next steps

1. Decide the three points in section 3.
2. Run Phase 0 on the live service and stand up openric-dev.
3. Draft the RiC schema and the six seams as a design note for review.
4. Port audit-trail and workflow as the measured trial.
