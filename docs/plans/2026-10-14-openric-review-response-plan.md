# OpenRiC Review Response Plan

The response to the external critical review of openric.org of 14 October 2026: what was verified, the site and specification consolidation to do first, and the three workstreams that follow - the OpenRiC specification, OpenRiC Workspace as a standalone archival application, and migration from Heratio.

| Field | Value |
| --- | --- |
| Document Title | OpenRiC Review Response Plan |
| Author | Dr Johan Pieterse |
| Owner | The Archive and Heritage Digital Commons Group (Pty) Ltd |
| Version | 1.0 |
| Status | Approved |
| Date | 14 October 2026 |
| Reference | OPENRIC-PLAN-2026-10-14 |
| Classification | Internal |
| Supersedes | OpenRiC Full Application Plan, 10 October 2026, on positioning, code reuse and phasing |

: Document control

## Context

An external reviewer assessed openric.org and the plan for a full application. The scores were documentation consistency 3/10, application readiness 3/10 and strategic potential 9/10. They first recommended two deliverables (a site and specification consolidation, and an application architecture), then revised this after Johan answered the Heratio question: three workstreams - specification, archival application, and Heratio migration - with Heratio never a technical dependency of the application.

**Dr Johan Pieterse's answer to the review's central question (14 October 2026):**
- Heratio is the integrated GLAM platform, with RiC already built in.
- The OpenRiC application is for **an archive starting fresh**: RiC-native from day one.
- It offers a **migration route from Heratio**.

This is the deliberate product decision the reviewer asked for, and the reviewer accepted it: the distinction is scope, purpose and institutional fit, not functionality Heratio lacks. It also narrows the 10 October application plan, which aimed at "fully functional like Heratio". The application is an archival working environment, not a second GLAM platform. Museum, library, gallery and DAM modules leave its scope.

Two audits on 14 October checked the review against the repositories and live sites.

**The reviewer is partly out of date.** Since v0.43.11 (13 October), the version numbers, the institutional briefing's v0.2.0 and SPARQL wording, and the architecture page's v0.2.0 are all fixed.

**Confirmed and open:**
- The profile inventory contradicts itself: "7 normative + 1 draft" against the real 12 (7 normative, 5 draft).
- Hand-typed counts are wrong:
  - fixtures: pages say 27 or ~20; there are 35 directories and 43 in the manifest;
  - schemas: pages say 19; there are 21.
- proof.md's "live numbers" are mostly wrong: 713 records on the page, 419 live.
- The drift log is dated 25 April, frames everything around v0.36/v0.37, and says the service "claims 6 of 7".
- Relative-time phrases have gone stale: "Three weeks ago", and "badge is aspirational" although the badge is live.
- architecture.md describes 5 capture tiles; there are 10, plus edit, delete and upload.
- No versioned archive of older specs exists, and the v0.43.11 GitHub release was never created.

**Finding D is worse than reported.** The Egyptian Boat is presented as "held by The British Museum". The live record's holder is the fictional "Meridian Museum of Antiquities", and its description is machine-generated Afrikaans. `SeedDemo.php` does not create it, so the page's seeding explanation is also wrong.

**Finding F:**
- Seven pages say "a contract, not a product".
- data-management.md describes an OpenRiC admin UI on PostgreSQL that is the source of truth for Heratio.
- architecture.md correctly says the service shares Heratio's MySQL.

**Finding C, plus live exposures:**
- API keys are kept in `localStorage` by Browse, Capture and the API explorer.
- `OPENRIC_OPEN_WRITE=true` allows anonymous creates in production; the wizard relies on it.
- Delete routes need only the `write` scope.
- CORS is `*` for every method.
- No CSP is set anywhere.
- There is no key-revocation command.
- The service reports `version: "1.0"` instead of 0.17.0, and `latest_spec_version` 0.43.10.
- Anonymous DELETE is correctly refused with 401.

**Disclosure:** `openric-spec/_data/profiles.json` (the 12 profiles, taken from each profile page header) was written before plan mode. It is uncommitted and unreferenced. Deliverable 1 adopts it.

## Deliverable 1 - site and specification consolidation (P0)

Exit condition, taken from the review: an external developer can determine the correct contract and the implementation status without meeting conflicting official guidance.

### 1a. One source of truth (repo `/usr/share/nginx/openric-spec`)

This extends the `version.json` pattern: data files are the truth, pages render them, and `bin/check-versions.py` fails CI on anything hand-typed that disagrees.

- **Data files.**
  - `_data/profiles.json` is kept.
  - `_data/inventory.json` holds schemas, fixtures done and fixtures planned. `bin/release` regenerates it, next to its existing `version.json` mirror step, from `schemas/*.schema.json` and `fixtures/manifest.json`.
- **Render instead of type.** Use Liquid such as `{{ site.data.profiles | where: "status", "normative" | size }}`.
  - Tables and cards become loops over the data: spec/index.md, spec/profiles/index.md and the profiles.md catalogue, which gains the 5 missing drafts.
  - Counts are rendered on index, faq, about, ric-baseline, for-institutions, for-developers, proof, conformance, data-management and governance.
  - Front-matter descriptions cannot run Liquid, so they lose their counts.
- **Checker rules.**
  - Every `spec/profiles/*.md` header matches its data entry.
  - A number before "normative", "draft", "profiles", "schemas" or "fixtures" must be rendered or match the data. Legacy `L2-*` rows and `vX.Y.x` roadmap ranges are exempt.
  - Relative-time phrases are banned in built pages ("weeks ago", "last month", "this quarter", "this week", "yesterday", "recently").
  - The drift log's "Last updated" date must not be earlier than any date inside it.
- **Generated `/status/` page.** It fetches, client-side, the service description (`/api/ric/v1/`), the badge and the list totals, then shows:
  - the service version and the spec version it verifies against;
  - the profiles it declares, against the spec's 12;
  - entity counts;
  - the time of the fetch.

  It degrades to a link if the fetch fails, and proof.md's stats point to it. The service sets CORS `*`, so the browser fetch works. The script is one file in `assets/js/`, in the same pattern as `wizard.js`.
- **Public conformance matrix** on `/status/`: implementation × profile, with result, date and source. It starts with the reference service's declared profiles. A row for each external implementation is added when one exists.
- **Page kinds.**
  - Front matter gains `kind: education | current | spec | record`, and the layout shows one banner line for each kind:
    - record pages: "Historical record, last updated <date> - current state: /status/";
    - spec pages: "Specification v<version>".
  - `/spec/versions/` lists the git tags, linking each to the GitHub tree at that tag. That gives immutable archives with no duplicated content.

### 1b. Content corrections the checks cannot catch

- **drift-log.md:** a new dated lead stating that the service verifies 0.38.2 against spec 0.43.11 and declares 7 profiles, with no provenance-event.
- **architecture.md:** the capture section updated, "docs/ on main", and the "Three weeks ago" line removed.
- **spec/conformance.md:** the badge is live; the L4 badge example is removed.
- **governance.md:** the roadmap "Now" row.
- **related-implementations.md:** the dead callout pointer.
- **Remaining relative-time lines:** dated rewrites.
- **Evidence labels (finding D).** An include `_includes/evidence.html` with four labels: third-party verified, derived, synthetic demonstration, participating institution.
  - Applied on proof.md and five-minutes.md.
  - The Egyptian Boat is a **synthetic demonstration record (decided)**. Both pages drop the British Museum attribution and the incorrect seeding claim, and carry the "synthetic demonstration" label.
  - "RiC-O 1.1 conformant", "round-trip guaranteed" and "production consumer" either link to their test and date or are reworded.
  - Nothing claims that the seven normative profiles have been demonstrated; provenance-event is not declared.
- **Contract versus application (finding F).** The application is named **OpenRiC Workspace (decided)**; "OpenRiC" alone always means the specification. "OpenRiC is a contract" stays, and a new **Application** page states Johan's positioning: archives starting fresh, the Heratio migration route, and that the application is one implementation of the contract, not the contract. It is presented as an honest roadmap, with nothing shown as built.
  - data-management.md Topology A is reframed as that planned application.
  - Today's shared-MySQL truth is stated.
  - "All four" becomes "all three".

### 1c. Navigation and surfaces

- **Navigation** follows the review: Home, Explore, Learn RiC, Application, Developers, Specification, About.
- **`explore.md`** is regrouped into Public discovery, Archivist workspace (planned) and Developer tools.
- **Browse demo** opens read-only. The server and key panel moves under "Developer tools".
- **Keys move from `localStorage` to `sessionStorage`** in Browse, the API explorer and Capture (`openric-capture/docs/index.html:275`).
- **Swagger UI** is pinned and loaded from cdnjs.
- **A `<meta http-equiv="Content-Security-Policy">`** goes in the layout and app pages. GitHub Pages cannot set headers.

### 1d. Service fixes (repo `/usr/share/nginx/OpenRiC`, which is production)

- **Delete scope:** delete routes use `api.auth:delete` (`packages/ahg-ric/routes/api.php:180-235`).
- **CORS:** `ApiCors.php` keeps `*` for reads. For write methods and `X-API-Key` preflights it echoes only allow-listed origins.
- **Revocation:** a new `openric:revoke-key` command (sets `is_active=0`) beside `IssueKey`.
- **Service description:** reports the service's own `version.json`, and takes `latest_spec_version` from config.
- **Headers:** one `X-Frame-Options`, not two.
- **Open write becomes a sandbox (decided).** Anonymous creates stay available to the wizard but are isolated from the real data. The existing machinery already gets most of the way: `openric_open_write` records every anonymous create, `OpenWriteFilter` hides them from public lists, and `openric:purge-open-write` removes them. The work is:
  - make the filter apply on every read path: lists, show, OAI-PMH, graph, SPARQL sync and stats;
  - schedule the purge daily in `routes/console.php`;
  - have the wizard say its saves are temporary.

  No separate database unless the audit of read paths shows a leak the filter cannot close.

Deployment follows the vendor-mirror care in memory: check permissions, run `php -l`, and run the ahg-ric tests.

### 1e. Release

- Two spec patch releases: first 1a, then 1b and 1c.
- Create the missing v0.43.11 GitHub release.
- One service release.

## After P0 - three distinct workstreams

The reviewer's revised recommendation (14 October, after Johan's positioning answer) is adopted:
- Specification, application and migration are three separate engineering responsibilities.
- They get three names on the site: the OpenRiC standard, OpenRiC Workspace, and developer tools.
- The reviewer withdrew the demand that the application justify itself by doing things Heratio cannot. The distinction is **scope, purpose and institutional fit**. An archive that needs no library, museum, gallery or GLAM administration needs a focused archival system.

**The architectural principle that governs all three: Heratio must never be a technical dependency of OpenRiC Workspace.** Heratio is a supported migration source and an interoperable peer, nothing more. The Workspace must be deployable, operable and maintainable with no Heratio code, database or service present.

This reverses decision 2 of the 10 October plan, which proposed shared Composer packages developed on heratio-dev first and consumed by both products. That would make every Workspace release wait on Heratio's.

The replacement rule: **reuse code, do not share it.** The Workspace copies each piece of Heratio code it needs into its own repository and owns it from then on. No package, library or repository is shared between the two products. OpenRiC already does this with ahg-ric and logs divergence in `drift-log.md`. Fixes may be ported between the two by hand, but neither builds against the other.

The service's current dependency on Heratio's MySQL database is the first thing this principle retires.

### Workstream S - the OpenRiC specification

The specification is stabilised independently of either application. P0 (Deliverable 1 above) is its first instalment. After that:
- the conformance matrix;
- versioned archives;
- the second independent implementation as a strategic milestone, with named recruitment targets from related-implementations.md.

Neither OpenRiC Workspace nor Heratio counts as that second implementation, because both are AHG lineage. The Workspace's implementation choices (database, auth, workflow) must never surface as requirements in the specification.

### Workstream W - OpenRiC Workspace, the archival application

The 10 October plan (`docs/plans/2026-10-10-openric-full-application-plan.md`) is rewritten as the Workspace charter:

1. **Positioning.** A dedicated archival management application for archives establishing a new digital environment, built on RiC.
   - Heratio remains the integrated GLAM platform with RiC built in.
   - The charter carries the reviewer's comparison table: identity, users, starting point and integration.
2. **Scope is archival work only.**
   - In: accession, appraisal, arrangement, description, authority control, contextual relationships, ingest and digital objects, rights and access, publication, export.
   - Preservation is integrated honestly. Delegate to Archivematica where appropriate, and never claim preservation for upload plus checksum.
   - Out: museum, library, gallery, DAM, Spectrum and every other sector module.
3. **Seven capabilities**, in place of the nine breadth-first phases:
   - Description and arrangement.
   - Authority and relationship management.
   - Ingest and digital objects.
   - Workflow and institutional control. Multi-tenancy is specified as a security architecture, not an organisation column.
   - Provenance and accountable change.
   - Public discovery: a catalogue plus a graph view, with accessible alternatives.
   - Export and interoperability, with a conformance results dashboard visible to administrators.
4. **Evidence and uncertainty as a design quality**, not a competitive claim.
   - Every relationship records what is connected, how, when, on what evidence and asserted by whom.
   - Inferred and asserted statements stay distinct (the Inferred-Provenance profile), and competing assertions are kept (open issue #17).
   - AI suggestions never become authoritative without editorial review.
   - The evidence labels from 1b are part of the data model.
5. **Architecture.**
   - One authoritative write model.
   - Transactional correctness first, with the RDF projection second through an outbox.
   - Stable public IRIs, independent of internal IDs and deployment domain.
   - Replaceable services (storage, search, object processing, identity) behind interfaces.
   - Three separate experiences: public discovery, archivist workspace, developer tools.

   The database is no longer tied to Heratio reuse, so the charter decides it on merit. PostgreSQL is the candidate both the review and data-management.md name; it is benchmarked on the migrated Heratio dataset before P2.
6. **Programme**, which is dependency-driven and carries no week estimates until P2 has measured velocity:
   - P1 is the signed-off charter. It includes the reuse list: which Heratio code is copied in, which is rebuilt, and which is left out.
   - **P2 is one vertical slice:** login, describe, link an agent and an activity, attach an object, review and approve, publish, discover in the catalogue and graph, export, inspect the audit history. Exit: an archivist completes it without touching an API.
   - P3 is production validation: permissions, restore, integrity, graph consistency, scale, accessibility, conformance, import and export fidelity.
   - P4 is a pilot with an external archive.
7. **Backlog from the review:**
   - a WCAG 2.2 AA audit of the graph viewer and wizard;
   - an institutional deployment guide;
   - task-based help paths;
   - one traceable homepage example.

### Workstream M - Heratio migration and interoperability

Migration is a primary engineering requirement, designed and tested from the start of P1, not added after the application.

It is a formally specified, one-way mechanism. It reads Heratio as a source and writes the Workspace through its own import interface, so it creates no runtime coupling. The existing assets give it a head start:
- the `ric_extractor` mapping (`packages/ahg-ric/tools/`);
- `RicSerializationService`, which already turns Heratio rows into RiC-O;
- Heratio's own export packages.

| Data or capability | Migration requirement |
| --- | --- |
| Archival descriptions | Preserve content and hierarchy |
| RiC entities | Preserve entities and identifiers, or keep a traceable identifier map (old `urn:ahg:ric:<type>:<id>` to new IRI) |
| Relationships | Preserve semantic relationships and their documented context, including role types |
| Authority records | Preserve agents, places and controlled vocabularies |
| Digital objects | Transfer or securely reference files, metadata and checksums, verified after transfer |
| Provenance | Preserve source information and change history where Heratio holds it (audit trail) |
| Access restrictions | Translate rights, restrictions, embargoes and security clearance into the Workspace permission model |
| GLAM-specific data | Identify unsupported data (museum, library, gallery, DAM fields) and report it in the exception register, never silently drop it |

: Heratio to OpenRiC Workspace migration requirements

Deliverables:
- **A compatibility report** before any data moves.
- **Declared transformation rules**, versioned.
- **SHACL validation** of the output.
- **An exception register.**
- **A reconciliation report** that proves the result by counts, checksums and sampled field-level comparison, not by an import-success message.

The test corpus is a copy of the Heratio dataset, the 17.9-million-triple `/ric` scale, so migration is proven at real size before P4. The P2 vertical slice also runs on migrated data, not only on fresh entry.

### Website message

The Application page (1b) uses the reviewer's positioning copy, adapted to the name and house style, and says clearly that the application is in development and migration is not yet operational:

> **Archival management, built around context.** OpenRiC Workspace is a dedicated archival management application in development, built on the International Council on Archives' Records in Contexts (RiC) standard. Designed for archives establishing a new digital management environment, it will bring archival description, authority control, contextual relationships, digital objects and public discovery into one RiC-native workspace. For institutions using Heratio, a defined migration pathway will support the move of compatible archival information into the standalone Workspace.

The site then distinguishes three identities throughout: the OpenRiC standard, OpenRiC Workspace, and developer tools.

The charter's .docx is rebuilt from the revised markdown.

## First action after approval

Email this full plan to johan@theahg.co.za, as requested on 14 October. Build a .docx from it with the AHG letterhead template, attach it, and put the summary in the body.

## After approval, outside the repos

- **Project memory:** record the positioning decision and the rule that comes with it.
  - Heratio is the integrated GLAM platform with RiC.
  - OpenRiC Workspace is the standalone archival application for archives starting fresh, with migration from Heratio.
  - Heratio is never a technical dependency of the Workspace. Heratio code is reused by copying it into the Workspace, never shared (Johan, 14 October: "REUSE CODE NOT SHARE"). Migration is one-way through the Workspace's import interface.
- **KM:** publish the closure of each deliverable.

## Verification

- **Checkers:** `python3 bin/check-versions.py` and `bin/check-links.py` are green.
- **Negative tests:** each of these must fail the check:
  - re-typing "7 normative + 1 draft";
  - adding "three weeks ago";
  - deleting a profile's data entry.
- **Live site after each Pages build:** curl home, `/spec/`, `/spec/profiles/`, `/profiles.html`, `/status/`, `/application/` and `/proof.html`.
  - Profile counts read 12/7/5 everywhere.
  - `/status/` shows live figures and the conformance matrix.
  - The Egyptian Boat carries its label.
- **Service:** run `php artisan test` for ahg-ric, then these read-only checks:
  - an OPTIONS request from a disallowed origin gets no ACAO for DELETE;
  - `GET /api/ric/v1/` shows 0.17.0;
  - a single XFO header;
  - a keyless DELETE returns 401.
- **Process:** no write against live data without approval. Commits go through gate 3 and releases through the `1/Y/y` confirmation.
