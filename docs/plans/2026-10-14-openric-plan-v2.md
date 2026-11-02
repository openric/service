# OpenRiC Programme Plan

The plan of record for OpenRiC after the external review of October 2026. It merges the response plan of 14 October (v1.0) with the revised review of the same day (V2), corrects both against what was checked on the live systems, and sets the order of work: contain the production exposure first, then make the public specification trustworthy, then build OpenRiC Workspace and the Heratio migration as separate, gated workstreams.

| Field | Value |
| --- | --- |
| Document Title | OpenRiC Programme Plan |
| Author | Dr Johan Pieterse |
| Owner | The Archive and Heritage Digital Commons Group (Pty) Ltd |
| Version | 2.0 |
| Status | Proposed - for approval decision by decision (section 10) |
| Date | 14 October 2026 |
| Reference | OPENRIC-PLAN-2026-10-14-V2 |
| Classification | Internal |
| Supersedes | OpenRiC Review Response Plan v1.0 (14 October 2026) and OpenRiC Full Application Plan (10 October 2026) |
| Sources | Response plan v1.0; Revised Review and Implementation Plan V2; repository and runtime checks of 13 and 14 October 2026 |

: Document control

# 1. Where this plan stands

Three documents now exist. The 10 October plan set out to make OpenRiC "fully functional like Heratio". The v1.0 response plan of 14 October replaced that with a narrower product and a site clean-up. The V2 review, written the same afternoon, accepted the strategy and rejected parts of the execution.

V2 is right on the points that matter most. It puts security containment ahead of everything else, it refuses to treat a filtered table in a shared database as a sandbox, and it separates what a service claims from what a test has shown. This plan adopts V2's structure and gates.

Where this plan departs from V2, it is because something was checked that V2's author could not check. V2 states that it re-tested nothing and treats every v1.0 finding as reported. Several were confirmed by reading code and by direct requests to the live service, and two new facts surfaced on 14 October that make the containment step more urgent than V2 assumed. Section 2 lists them.

Two corrections to the record. The v1.0 plan was marked "Approved" because Dr Johan Pieterse approved it in the working session; V2 is right that approving a plan does not approve deployments, and this version is marked Proposed with approvals taken decision by decision. And the v1.0 document was emailed at his explicit request, not on the strength of its own "first action" line.

# 2. Evidence base

Every finding below carries how it is known. "Verified" means checked against source code or the live system on the date shown. "Reported" means stated in a source document and not re-checked.

| Finding | Evidence | Status |
| --- | --- | --- |
| The reference service (ric.theahg.co.za) and Heratio production both use the MySQL database `heratio` | `DB_DATABASE` in both `.env` files, 14 Oct | Verified |
| `OPENRIC_OPEN_WRITE=true` on the reference service | `.env` line 53, 14 Oct | Verified |
| Anonymous creates have therefore been written into Heratio's production database | `openric_open_write`: 37 rows, first 2026-06-18 18:43, last 2026-10-12 12:09 | Verified |
| The open-write bypass covers POST creates only; anonymous PUT, PATCH and DELETE get 401 | `ApiAuthenticate.php` lines 25-31, 46-51 | Verified |
| Delete routes require only the `write` scope, not `delete` | `ahg-ric/routes/api.php` lines 180-235 | Verified |
| CORS sends `Access-Control-Allow-Origin: *` for every method, including DELETE | `ApiCors.php`; live response headers | Verified |
| No API-key revocation command; revocation means editing the database | `ApiKeyService`, `IssueKey` | Verified |
| No site sends a Content-Security-Policy header | Live response headers of all five sites | Verified |
| Browse demo, Capture and the API explorer keep API keys in `localStorage` | Source of each, 14 Oct | Verified |
| Tags on `openric/spec` are not protected | No repository rulesets, 14 Oct | Verified |
| The service reports version `1.0`, verifies against spec 0.38.2, and gives `latest_spec_version` 0.43.10 | Live service description, 14 Oct | Verified |
| The service declares 7 profiles: 6 normative in full plus SPARQL Access draft; not Provenance & Event | Live service description, 14 Oct | Verified |
| The Egyptian Boat demonstration record names a fictional holder (Meridian Museum of Antiquities) and is not created by `SeedDemo` | Live record; `SeedDemo.php` | Verified |
| Heratio still loads its own `/api/ric/v1` routes and writes through them over the shared database | `AhgRicServiceProvider::boot`, heratio-dev | Verified |
| Heratio's RiC graph holds about 17.9 million triples | Fuseki count, April 2026 | Reported; re-measure before use as a benchmark |

: Evidence base, 14 October 2026

The third row is the reason this plan changes v1.0's answer on open write. v1.0 proposed keeping anonymous creates and isolating them with a filter and a daily purge. That would have left an untrusted, anonymous write path into the production database of a different product. It is withdrawn.

# 3. Decisions that bind the programme

These were settled by Dr Johan Pieterse on 14 October and are not reopened by this plan.

| Concern | Decision |
| --- | --- |
| Heratio | The integrated GLAM platform. RiC is built into it. |
| OpenRiC specification | An implementation-neutral contract, versioned and governed independently of any product. "OpenRiC" on its own always means the specification. |
| OpenRiC Workspace | A standalone archival management application for archives starting fresh. Archival scope only: no museum, library, gallery, DAM or Spectrum modules. |
| Migration | One-way, Heratio to Workspace, through documented exports and imports. Never a runtime dependency. |
| Code reuse | Reuse Heratio code by copying it into the Workspace, never by sharing packages, libraries or repositories. Every copied file carries its provenance (source path and Heratio commit), and the Workspace keeps a patch ledger so security fixes made in Heratio are not missed. |
| Canonical data | The Workspace's relational store is the system of record. The RDF graph and the search index are derived and can be rebuilt. |
| Claims | Prototype, planned, seeded, deployed, declared and tested are six different things and are labelled as such everywhere. |

: Binding decisions

V2 adds one condition to the code-reuse rule that this plan accepts: copying is not automatically safer than sharing. The patch ledger is how the Workspace avoids carrying a Heratio vulnerability after Heratio has fixed it.

# 4. P0A - Containment, before anything else ships

Nothing else in this plan is released until this step is closed. That includes the site work already prepared, which is held.

## 4.1 Disable anonymous writes now

Set `OPENRIC_OPEN_WRITE=false` on the reference service. The flag is read on every request, so the change is immediate and is reversed by setting it back. The effect is that the modelling wizard's "Create it" button stops saving. The wizard must then say so plainly, and offer the generated RiC-O for the visitor to copy, rather than fail silently.

Anonymous writing does not come back until a demonstration environment exists with its own database, its own triplestore dataset and its own API keys, holding nothing but demonstration data. A filter on a shared table is not that, and V2 is right that it must not be presented as one.

## 4.2 Deal with the 37 rows already written

The 37 anonymous creates between 18 June and 12 October sit in Heratio's production database. They are reviewed, not deleted blind:

1. Export them, with every row they created in other tables, to a file kept outside the web root.
2. Read them. Anything that is spam, abusive or test noise is removed with `openric:purge-open-write`. Anything a real person submitted in good faith is recorded and the submitter, if identifiable, is not left wondering where it went.
3. Confirm afterwards that no list, OAI-PMH feed, graph, SPARQL dataset or statistic still exposes any of them.
4. Raise a CallHub call for the incident, so it sits with the client and SLA history like any other.

## 4.3 Close the authorisation gaps

All of these are in existing files and are small:

- Delete routes require the `delete` scope (`packages/ahg-ric/routes/api.php`), as Heratio's own v1 routes already do.
- An `openric:revoke-key` command, and an expiry check exercised in a test, not only in code.
- CORS stays `*` for reads, which is right for an open-data API. For write methods it answers only the reference clients' origins. This narrows browser-based misuse; it is not authorisation, and nothing relies on it as such. Authorisation is the API-key scope check on the server.
- The service description reports its real version and the current specification version.
- Remove the duplicate `X-Frame-Options` header.

## 4.4 Headers and browser credentials

`ric.theahg.co.za` is served by nginx on our own host, so it gets a real `Content-Security-Policy` response header there, tested with a browser after deployment.

openric.org, the viewer and Capture are served by GitHub Pages, which cannot set response headers. The choice is between putting an edge proxy in front of them and accepting a documented limitation. Until that decision is made, these pages get a `<meta>` CSP as a partial measure. It is recorded as partial; it does not cover framing or reporting.

API keys move from `localStorage` to `sessionStorage` in the three browser tools. That shortens how long a key survives; it does nothing against a script injected into the page. The real fix is that no browser holds a privileged key at all, which is how OpenRiC Workspace will work: sessions on the server, keys never in the page. The browser tools stay as developer utilities, used with low-scope keys.

## 4.5 Exit evidence for G0

| Evidence | Required result |
| --- | --- |
| Write isolation | A keyless POST to any entity endpoint returns 401 and writes nothing |
| Authorisation | Negative tests for keyless, under-scoped and wrong-scope writes and deletes all refused, and logged |
| Key control | One key revoked and shown refused; expiry demonstrated in a test |
| Exposure | The 37 rows reviewed, dispositioned and recorded; CallHub call open |
| Operational safety | Database backup taken and a restore of it tested before the service release; rollback written down |

: P0A exit evidence

# 5. P0B - A trustworthy public specification

## 5.1 Already prepared, held until G0

A first batch of site changes is built and checked but not released. It makes every profile count, table and catalogue render from one data file (`_data/profiles.json`), every schema and fixture count render from a generated inventory, and it adds checks so a hand-typed count that disagrees fails CI. It adds a generated status page, a versions page, page-kind banners, a re-dated drift log, and a corrected architecture page, which until now claimed Heratio no longer had a private write path.

Every check was tested both ways: green on the real tree, red on deliberately broken copies. Each changed page was rendered through the same Liquid and kramdown engines GitHub Pages uses. A full Jekyll build happens only on GitHub's side, so the first real build is the release itself.

## 5.2 Changes V2 requires before that batch ships

- **Four separate facts, never merged.** The status page shows, for each profile: what the specification defines, what the service declares, what a test run has shown, and when. Today the third column reads "not run" for every profile, because no automated conformance result is published. That is the honest state and the page says it.
- **Versions page.** It currently calls tags "preserved, unchanged". Without tag protection that is not true. Either a repository ruleset blocks changes to release tags, or the wording changes. The preferred answer is both: protect the tags, and attach a packaged specification bundle with a SHA-256 checksum to every GitHub release, so each version has a fixed, verifiable artifact.
- **Drift log.** The rewritten lead already states today's verified service facts and dates them. It keeps the older Phase G table, marked as recorded in April and not re-verified row by row.

V2 is also right that pattern checks cannot catch every contradiction. The checks catch the class of error that has actually recurred, hand-typed counts and stale dates. A scheduled human review of current-state pages covers the rest.

## 5.3 Second batch

- **Evidence labels.** Four labels used everywhere data is shown as proof: independently sourced, derived, synthetic demonstration, participating institution, each with source, rights and date where they apply. The Egyptian Boat becomes a labelled synthetic demonstration record. The British Museum attribution is removed, and so is the claim that the seed command created it; the page states where the record actually came from.
- **Unqualified claims.** "RiC-O 1.1 conformant", "round-trip guaranteed" and "production consumer" each get the build, test, date and scope that support them, or are reworded.
- **Application page.** Introduces OpenRiC Workspace as in development and the Heratio migration as planned, not operational, using the positioning text agreed with the reviewer.
- **Navigation.** Home, Explore, Learn RiC, Application, Developers, Specification, About.
- **Surfaces.** Browse opens with no key field and no edit or delete controls. The server and key panel moves to a deliberate developer view.

## 5.4 How it ships

Small, reviewable changes, site and service released separately. Each release states its dependencies and its rollback. Gate G1 closes when an outside developer can find the current contract, the service's declared support and its tested results without meeting a contradiction.

# 6. Workstream S - the specification

The specification owner keeps it implementation-neutral and versioned on its own line. The Workspace's choices of database, authentication or workflow must never appear in it as requirements.

The deliverables are a version and deprecation policy, machine-readable profiles, the fixture pack, a conformance harness that produces a dated, stored result, and the public matrix built from those results. The harness runs on a schedule against the reference service, so the "tested" column fills from evidence rather than from a declaration.

A second implementation built outside AHG is a milestone, not a side task. Two frontends, a fixture backend, or two AHG-built services do not count. Recruitment starts now from the implementations already listed on the site, with a named target and a defined pass condition on at least one profile.

# 7. Workstream W - OpenRiC Workspace

A standalone archival application. Its own repository, database, deployment and release line. Heratio does not have to exist for it to run.

## 7.1 Scope

Accession, appraisal, arrangement, description, authority control, contextual relationships, ingest and digital objects, rights and access, governance, publication, public discovery, export. Preservation is an integration with a preservation system such as Archivematica; an upload and a checksum are never described as preservation.

## 7.2 First usable acceptance per capability

| Capability | First usable acceptance |
| --- | --- |
| Description and arrangement | Build a fonds, series and item structure; rearrange it with identifiers unchanged; inspect the revision history. |
| Authority and context | Link an agent, activity, place and date to records, recording the basis, dates, author and certainty of each relationship. |
| Ingest and digital objects | Import a batch with checksums, quarantine and validation, review, a storage receipt and an error report. |
| Workflow and permissions | Draft to review to publish under role permissions, with embargo enforced and the audit trail shown. |
| Provenance and uncertainty | Tell apart sourced statements, transformed ones, machine suggestions and conflicting assertions. AI output never becomes an archival fact without editorial review. |
| Public discovery | Search, walk the hierarchy and the graph, with an accessible non-graph view and stable citations. |
| Interoperability | Import and export in versioned formats, with errors a person can act on and conformance visible to the administrator. |

: Workspace capabilities and first acceptance

## 7.3 Architecture guardrails

One transactional write model is the system of record. The search index and the RDF graph are fed from it through an outbox, can be rebuilt from it at any time, and show their lag. Entity IRIs are stable and do not depend on internal IDs or the host name. Storage, search, object processing and identity sit behind replaceable adapters. Public discovery, the archivist workspace and developer tools are three separate experiences; no public page carries an edit or delete control.

PostgreSQL is the candidate database. It is not decided. It is tested against representative data, imports, query patterns and operations before G2 closes.

## 7.4 The vertical slice

Sign in, create and arrange a record, link an agent and an activity, attach a digital object, submit for review, approve, publish, find it in the public catalogue and the graph, export it, and inspect its provenance and audit history. An archivist completes this without touching an API. The slice is run twice: once on fresh entry and once on a migrated Heratio specimen.

# 8. Workstream M - migration from Heratio

Heratio produces a versioned export; the Workspace consumes it through a documented import contract. The Workspace never reads Heratio's tables or calls Heratio's services, and the pipeline runs offline on an exported bundle. The existing RiC extractor and serialisers in Heratio are the starting point for the export side.

| Stage | Evidence and controls |
| --- | --- |
| Assess | Inventory of record classes, authorities, relationships, object types, rights, identifiers, unsupported GLAM data, volumes and privacy obligations. |
| Map | Versioned transformation rules; identifiers kept, or mapped durably from `urn:ahg:ric:<type>:<id>` to the new IRI; every loss or approximation stated. |
| Dry run | Repeatable import into an isolated target; the same input gives the same, explained result; retries are idempotent. |
| Validate | Structure and relationship checks, SHACL where it applies, checksums, counts, sampled field-by-field comparison, link integrity. |
| Exceptions | Blockers, warnings, deliberate omissions and unresolved access-rule mappings listed and approved by the data owner. GLAM-specific data is reported, never silently dropped. |
| Cutover | Source frozen or changes captured; delta load; final reconciliation; rollback; institutional sign-off. |
| Aftercare | Old identifiers resolve or redirect; audit evidence kept; staging copies retained or disposed of by decision. |

: Migration stages

Migration is proven by a reconciliation report, never by an import-success message. The 17.9-million-triple figure is a target to re-measure, not a result.

# 9. Gates, owners and timing

| Gate | Evidence | Accepted by |
| --- | --- | --- |
| G0 Containment | Section 4.5 complete | Production and security owner |
| G1 Trusted contract | Consistent pages, protected tags and release bundles, status page with declared and tested shown apart | Specification owner |
| G2 Signed architecture | Workspace charter, decision records, threat model, data model, permission model, migration specification, database test result | Product, archival and technical leads |
| G3 Vertical slice | The slice on fresh and migrated data, repeatable in CI, accessibility baseline | A practising archivist |
| G4 Operational validation | Restore, tenant isolation, conformance, scale, accessibility, file safety, monitoring | Security and operations leads |
| G5 Institutional pilot | Pilot agreement, data governance, success measures, recorded feedback | Pilot institution and sponsor |

: Delivery gates

V2 asks for named owners for specification, Workspace, migration, security and release. In practice these are one person today, Dr Johan Pieterse. The plan names him for each and states it openly. Two roles cannot be filled internally: an external reviewer for the specification, and a practising archivist to accept G3. Both are recruitment tasks that start now, because they gate later work.

On timing, V2 is right that waiting until the vertical slice for any estimate is too late for planning. Ranged estimates with stated uncertainty are produced at G2, from the charter and the database test, and re-forecast at G3 from measured throughput. No fixed delivery dates appear before then.

# 10. Risks

| Risk | Severity | Treatment |
| --- | --- | --- |
| Anonymous writes into production data | Critical | Disabled now (section 4.1); demo returns only in an isolated environment |
| Rights or tenant leak in the Workspace | Critical | Object-level authorisation, threat model at G2, adversarial tests at G4 |
| Claims run ahead of tested conformance | High | Declared and tested shown apart; scheduled conformance runs |
| Copied code misses a Heratio security fix | High | Provenance on every copied file, patch ledger, dependency scanning |
| Migration loses relationships or access rules | High | Exception register, sampled comparison, data-owner sign-off |
| Graph and database drift apart | High | Outbox with retry and dead-letter, rebuild, visible lag, consistency checks |
| Scope grows into a second GLAM platform | High | Change control against the archive-only charter |
| Single person holds every role | High | Stated openly; external reviewer and archivist recruited; work gated so nothing ships unreviewed |
| Documentation drifts again | Medium | Generated pages, CI checks, scheduled review |
| No outside implementer or pilot | Medium | Recruitment from now, with defined pass and success criteria |

: Initial risk register

# 11. Approvals

This document approves nothing on its own. Each line below is a separate decision, and any production change still goes through its own confirmation.

| Decision | State |
| --- | --- |
| Product boundary and code-reuse rule | Decided 14 October 2026 |
| Disable anonymous writes on the reference service | Done 14 October 2026; a keyless POST now returns 401, reads unaffected |
| Review and disposition of the 37 anonymous rows | Pending |
| Authorisation, key and header fixes (section 4.3, 4.4) | Pending |
| Release of the held site batch, with the section 5.2 changes | Pending, after G0 |
| Tag protection on `openric/spec` | Pending |
| Workspace charter and migration design (G2) | Pending |

: Approval record

# 12. Next actions, in order

1. Disable anonymous writes and confirm from outside that a keyless POST is refused.
2. Export and review the 37 anonymous rows; raise the CallHub call.
3. Take and test a database backup; then release the authorisation, revocation and header fixes to the reference service, with negative tests.
4. Make the section 5.2 changes to the held site batch, protect the tags, add release bundles, and release.
5. Start recruiting the external specification reviewer and the acceptance archivist.
6. Draft the Workspace charter and the vertical-slice acceptance script; begin the migration inventory on an exported copy, with no live writes.
