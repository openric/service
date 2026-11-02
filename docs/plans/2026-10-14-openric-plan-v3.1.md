# OpenRiC Programme Plan - V3.1

An evidence-led programme for securing the current OpenRiC reference
service, consolidating its public specification, developing a standalone
RiC-native archival application, and establishing a verified migration
route from Heratio. Updated after the second critical review, then corrected against the
live systems on the afternoon of 14 October 2026; production actions
remain separately authorised.

| Field          | Value                                                                                                                                                                                                                                       |
|----------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Document Title | OpenRiC Programme Plan V3.1 - V3.0 corrected against the live systems                                                                                                                                                                               |
| Author         | Dr Johan Pieterse                                                                                                                                                                                                                           |
| Owner          | The Archive and Heritage Digital Commons Group (Pty) Ltd                                                                                                                                                                                    |
| Version        | 3.1                                                                                                                                                                                                                                         |
| Status         | Proposed revision - approval and deployment controlled separately by gates                                                                                                                                                                  |
| Date           | 14 October 2026                                                                                                                                                                                                                             |
| Reference      | OPENRIC-PLAN-2026-10-14-V3.1                                                                                                                                                                                                                |
| Classification | Internal                                                                                                                                                                                                                                    |
| Supersedes     | OpenRiC Programme Plan V3.0, which superseded V2.0; carries forward decisions from the response plan v1.0 and the 10 October full application plan                                                                                   |
| Sources        | OpenRiC response plan v1.0; critical review V2; uploaded programme plan V3 (internally labelled 2.0); documented repository/runtime checks of 13-14 October 2026, and new checks made on 14 October 2026 between 14:55 and 15:30 UTC, listed in section 2. |

: Document control

# What changed in V3.1

V3.0 is a good revision and this version keeps all of it: the G0A/G0B split, treating the 37 entries as an incident to reconcile rather than rows to purge, the separation of defined, declared and tested, read-only production monitoring, identifier governance and measurable non-functional criteria. V3.1 changes five things.

- **G0A has moved.** V3.0 was written before the containment change was made, so it records the change as not established. It has since been deployed and checked from outside. Section 4.1 now carries that evidence and lists the three G0A items still open.
- **Heratio's own RiC API was checked.** V3.0 asked for it to be inventoried. It has no anonymous-write bypass, but its delete routes carry the same write-scope gap as the reference service, so the G0B fix covers both.
- **Propagation names Heratio's surfaces.** The 37 entries sit in Heratio's production database, so Heratio's own catalogue, search index, graph and backups are in scope, not only the reference service's.
- **Two sequencing conflicts are resolved.** V3.0 requires a wizard notice at G0A but holds every public release until G0B; the notice is now allowed as a G0A security release. V3.0 also says a release must not be the first full site build; a CI build on every pull request now provides that.
- **Evidence is made proportionate.** One person currently holds most roles, so the evidence register is a single access-controlled incident folder with a log and hashes, and the second-person review is named as the recruitment it depends on.

The rest of this section is V3.0's own revision summary, unchanged.

# Revision summary - decisions incorporated in V3.0

This revision preserves the confirmed product boundary, the independent
codebase rule, and the three long-term workstreams. It changes the
operational order and evidence requirements; it does not declare any
production remediation completed.

G0 is split into G0A (immediate containment and evidence preservation)
and G0B (incident disposition, authorisation corrections,
backup/rollback proof, and controlled security release).

Recorded anonymous-write log entries are treated as an incident
requiring impact and propagation assessment, not merely as rows to
purge.

Release documentation distinguishes approved, deployed, verified and
closed; conformance distinguishes defined, declared and independently
tested.

Production conformance monitoring is read-only. Mutating and adversarial
tests run against isolated staging/fixture environments.

Identifier governance, measurable non-functional criteria, independent
review and copied-code vulnerability tracking become explicit exit
requirements.

# 1. Where this plan stands

Three documents now exist. The 10 October plan set out to make OpenRiC
"fully functional like Heratio". The v1.0 response plan of 14 October
replaced that with a narrower product and a site clean-up. The V2
review, written the same afternoon, accepted the strategy and rejected
parts of the execution.

V2 is right on the points that matter most. It puts security containment
ahead of everything else, it refuses to treat a filtered table in a
shared database as a sandbox, and it separates what a service claims
from what a test has shown. This plan adopts V2's structure and gates.

Where this plan departs from the earlier review, it relies on repository
and runtime checks recorded in the source plan. These checks were made
on 13-14 October 2026; they are evidence of that time, not proof of the
current runtime state. Before any deployment or closure decision, the
responsible owner must re-confirm the relevant configuration, code
revision, database state and externally observable behaviour.

Two corrections to the record. The v1.0 plan was marked "Approved"
because Dr Johan Pieterse approved it in the working session; V2 is
right that approving a plan does not approve deployments, and this
version is marked Proposed with approvals taken decision by decision.
And the v1.0 document was emailed at his explicit request, not on the
strength of its own "first action" line.

# 2. Evidence base

The following is the evidence baseline from the 13-14 October 2026
source audit. "Verified" means verified by that audit against source
code or the then-live environment; it does not mean independently
re-tested for this document. "Reported" means not verified by that
audit. Any production action requires a new, dated observation and
retained evidence.

| Finding                                                                                                                                                                                              | Evidence                                                                                                                                       | Status                                         |
|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------|
| The reference service (ric.theahg.co.za) and Heratio production both use the MySQL database `heratio`                                                                                                | `DB_DATABASE` in both `.env` files, 14 Oct                                                                                                     | Verified                                       |
| `OPENRIC_OPEN_WRITE=true` on the reference service                                                                                                                                                   | `.env` line 53, 14 Oct                                                                                                                         | Verified                                       |
| 37 entries were found in the openric_open_write tracking table; verify which entries correspond to distinct persisted entity creations and dependent writes before assigning impact or cleaning data | `openric_open_write: 37 logged entries between 2026-06-18 and 2026-10-12 (source audit, 14 Oct); entity/dependency reconciliation outstanding` | Verified                                       |
| The open-write bypass covers POST creates only; anonymous PUT, PATCH and DELETE get 401                                                                                                              | `ApiAuthenticate.php` lines 25-31, 46-51                                                                                                       | Verified                                       |
| Delete routes require only the `write` scope, not `delete`                                                                                                                                           | `ahg-ric/routes/api.php` lines 180-235                                                                                                         | Verified                                       |
| CORS sends `Access-Control-Allow-Origin: *` for every method, including DELETE                                                                                                                       | `ApiCors.php`; live response headers                                                                                                           | Verified                                       |
| No API-key revocation command; revocation means editing the database                                                                                                                                 | `ApiKeyService`, `IssueKey`                                                                                                                    | Verified                                       |
| No site sends a Content-Security-Policy header                                                                                                                                                       | Live response headers of all five sites                                                                                                        | Verified                                       |
| Browse demo, Capture and the API explorer keep API keys in `localStorage`                                                                                                                            | Source of each, 14 Oct                                                                                                                         | Verified                                       |
| Tags on `openric/spec` are not protected                                                                                                                                                             | No repository rulesets, 14 Oct                                                                                                                 | Verified                                       |
| The service reports version `1.0`, verifies against spec 0.38.2, and gives `latest_spec_version` 0.43.10                                                                                             | Live service description, 14 Oct                                                                                                               | Verified                                       |
| The service declares 7 profiles: 6 normative in full plus SPARQL Access draft; not Provenance & Event                                                                                                | Live service description, 14 Oct                                                                                                               | Verified                                       |
| The Egyptian Boat demonstration record names a fictional holder (Meridian Museum of Antiquities) and is not created by `SeedDemo`                                                                    | Live record; `SeedDemo.php`                                                                                                                    | Verified                                       |
| Heratio still loads its own `/api/ric/v1` routes and writes through them over the shared database                                                                                                    | `AhgRicServiceProvider::boot`, heratio-dev                                                                                                     | Verified                                       |
| `OPENRIC_OPEN_WRITE=false` deployed on the reference service | `.env` line 53 changed by Dr Johan Pieterse, 14 Oct, just before 15:00 UTC | Verified |
| The change is effective without a cache clear or restart | No `bootstrap/cache/config.php`; `ApiAuthenticate.php` line 28 reads the flag with `env()` on every request | Verified |
| A keyless POST to the reference service is refused | `POST /api/ric/v1/places` with an empty body returned 401 "API key required", 14 Oct; public `GET` still 200 | Verified |
| Heratio's RiC API has no anonymous-write bypass | No `OPENRIC_OPEN_WRITE` in Heratio's `.env` or code; keyless `POST https://heratio.org/api/ric/v1/places` returned 401, 14 Oct | Verified |
| Heratio's RiC delete routes also require only the `write` scope | `heratio/packages/ahg-ric/routes/api.php` line 165 group | Verified |
| Heratio's RiC graph holds about 17.9 million triples                                                                                                                                                 | Fuseki count, April 2026                                                                                                                       | Reported; re-measure before use as a benchmark |

: Evidence base, 14 October 2026

The third row is the reason this plan changes v1.0's answer on open
write. v1.0 proposed keeping anonymous creates and isolating them with a
filter and a daily purge. That would have left an untrusted, anonymous
write path into the production database of a different product. It is
withdrawn.

# 3. Decisions that bind the programme

These were settled by Dr Johan Pieterse on 14 October and are not
reopened by this plan.

| Concern               | Decision                                                                                                                                                                                                                                                                       |
|-----------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Heratio               | The integrated GLAM platform. RiC is built into it.                                                                                                                                                                                                                            |
| OpenRiC specification | An implementation-neutral contract, versioned and governed independently of any product. "OpenRiC" on its own always means the specification.                                                                                                                                  |
| OpenRiC Workspace     | A standalone archival management application for archives starting fresh. Archival scope only: no museum, library, gallery, DAM or Spectrum modules.                                                                                                                           |
| Migration             | One-way, Heratio to Workspace, through documented exports and imports. Never a runtime dependency.                                                                                                                                                                             |
| Code reuse            | Reuse Heratio code by copying it into the Workspace, never by sharing packages, libraries or repositories. Every copied file carries its provenance (source path and Heratio commit), and the Workspace keeps a patch ledger so security fixes made in Heratio are not missed. |
| Canonical data        | The Workspace's relational store is the system of record. The RDF graph and the search index are derived and can be rebuilt.                                                                                                                                                   |
| Claims                | Prototype, planned, seeded, deployed, declared and tested are six different things and are labelled as such everywhere.                                                                                                                                                        |

: Binding decisions

V2 adds one condition to the code-reuse rule that this plan accepts:
copying is not automatically safer than sharing. The patch ledger is how
the Workspace avoids carrying a Heratio vulnerability after Heratio has
fixed it.

# 4. P0A - Production containment and security remediation

Immediate containment must start before non-security releases. Security
investigation and repairs proceed on an expedited change path.
Development and documentation work may continue in isolated branches,
but public releases remain held until G0B confirms the safety controls
and authorises them. Approval of this plan is not deployment approval.

## 4.1 G0A - Disable and verify anonymous writes

Authorise and deploy OPENRIC_OPEN_WRITE=false on the reference service.
Do not assume an environment-file edit takes effect immediately: inspect
the application configuration source, Laravel configuration cache,
PHP-FPM/worker lifecycle and the deployed release before asserting the
effective value. Preserve the previous configuration securely, but do
not restore anonymous writes as a routine rollback. From an approved
external test point, demonstrate that unauthenticated creation is denied
and that no record or related write is persisted. Record the exact UTC
timestamp, build/commit, operator and test evidence.

**State on 14 October 2026.** The change has been made and checked:

- `OPENRIC_OPEN_WRITE=false` was set on the reference service shortly before 15:00 UTC by Dr Johan Pieterse; the service revision is `bfb0e61` with no change to application code.
- V3.0 is right not to assume an environment-file edit takes effect. Here it does: the configuration is not cached, and the middleware reads the flag with `env()` on each request. The same fact is a latent hazard worth recording. If anyone later runs `config:cache`, `env()` outside a config file returns null, and the flag reads as false. That fails closed, which is the safe direction, but the flag should move into a config file in the G0B release so its value is explicit.
- From outside the host, a keyless `POST /api/ric/v1/places` returned 401 "API key required". The request was refused in authentication middleware, before any controller ran, so it could not have written anything.

Three G0A items remain open: a recount of `openric_open_write` showing no entry after 2026-10-12 12:09:18, the evidence folder of section 4.2, and the wizard notice. Until all three are done, G0A is not closed.

Anonymous writing must not be restored against any Heratio-connected
production data. A future interactive demo needs isolated storage, graph
dataset, credentials, quotas, monitoring and teardown policy. Its
restoration is a separate security decision; filtering "demo" rows
inside a shared production database is not isolation.

## 4.2 Incident assessment and disposition of the 37 tracked entries

The earlier audit identified 37 rows in the open-write tracking table
between 18 June and 12 October 2026. Those are tracking entries, not yet
a reconciled count of distinct persisted entities, linked records or
exposed copies. Treat the episode as a security/data-integrity incident
pending a documented risk and privacy assessment; it is not
automatically proof of a reportable personal-data breach.

1.  Preserve, in one incident folder outside every web root
    (`/var/lib/openric-incidents/2026-10-14-open-write/`, readable by the
    incident owner only), a point-in-time export of the 37
    tracking entries, related database rows, relevant application/access
    logs, current indexing/harvest state, and system configuration.
    Record hashes, provenance, custody and retention. Keep incident
    evidence outside the web root.
2.  Reconcile each tracking entry to the actual transaction and affected
    entities, relationships, files, authority records and versions.
    Classify as test, spam, good-faith, or indeterminate; get data-owner
    approval before any deletion, quarantine or correction. Keep a
    disposition register and restore path.
3.  Trace downstream propagation to search indexes, OAI-PMH feeds,
    graph/SPARQL projections, caches, exports, backups and any public
    URLs. Because the entries sit in Heratio's production database, this
    includes Heratio's own surfaces: its public catalogue, its
    Elasticsearch index, the `/ric` Fuseki dataset filled by its sync,
    its OAI-PMH feed and its database backups, as well as the reference
    service's `openric` dataset. Distinguish deletion of current exposure from lawful
    preservation in incident evidence or backups. Rebuild and verify
    each affected projection rather than merely checking the source
    table.
4.  Open an incident record in the appropriate operational system
    (CallHub where authorised). Record time line, root cause, owners,
    scope, data-integrity impact, any privacy/contractual notification
    decision, containment proof, disposition, follow-up controls and
    evidence references.

Notification decision: identify whether personal, confidential or
client-controlled information was affected, which entity is the
responsible party/operator, and whether legal, contractual or
institutional notification duties apply. Have the responsible owner
document that decision and the supporting evidence; do not infer a
reportable breach from an anonymous-write flag alone.

Before using any purge command, execute a dry run in an isolated
restoration, inspect all referential dependencies and obtain an explicit
data-owner authorisation. Do not perform uncontrolled DELETE operations
on live Heratio data to prove cleanup.

## 4.3 Close the authorisation gaps

Address the authorisation gaps on the reference service and inventory
equivalent write/delete routes exposed by Heratio's separately
registered RiC API. Changes are not automatically "small": shared
production data, permissions and deployments require targeted regression
tests and rollback evidence.

- The delete-scope fix is made in both places: the reference service and Heratio's own copy of the RiC routes, which carry the same gap. Each is released through its own repository and release process, Heratio through heratio-dev first.
- Require an explicit delete scope for every destructive endpoint
  (including alternative HTTP verbs and bulk operations), with
  object-level authorisation and deny-by-default rules. Test
  cross-institution and restricted records in staging; verify that
  Heratio's own RiC routes do not bypass the reference-service
  policy.
- Implement key revocation, expiry, rotation and audit logging.
  Demonstrate revoked/expired/under-scoped tokens failing in isolated
  automated tests, and document emergency credential rotation and
  incident-response procedures.
- For public read-only resources, an explicit wildcard CORS policy can
  be appropriate after confirming no sensitive response data is
  included. For writes and credential-bearing requests, restrict allowed
  origins, methods and headers; nevertheless, CORS is browser policy,
  never an authorisation substitute. Server-side scope and object
  permissions remain mandatory.
- Publish separate values for the deployed service build, OpenRiC
  specification release supported, declared profiles, and dated verified
  conformance results. Do not describe a declared profile as tested
  unless a retained test artefact supports it.
- Remove the duplicate `X-Frame-Options` header.
- Move the open-write flag from a direct `env()` call into a config file, defaulting to false, so a future `config:cache` cannot change its meaning silently.

## 4.4 Headers and browser credentials

`ric.theahg.co.za` is served by nginx on our own host, so it gets a real
`Content-Security-Policy` response header there, tested with a browser
after deployment.

openric.org, the viewer and Capture are served by GitHub Pages, which
cannot set response headers. The choice is between putting an edge proxy
in front of them and accepting a documented limitation. Until that
decision is made, these pages get a `<meta>` CSP as a partial measure.
It is recorded as partial; it does not cover framing or reporting.

For the interim developer tools, use low-privilege, short-lived,
revocable tokens and explain that moving browser credentials from
localStorage to sessionStorage only reduces persistence, not XSS
exposure. No privileged institutional token belongs in browser storage.
Workspace authentication uses secure server-managed sessions or an
equivalent backend-for-frontend design, with CSRF protection and
role-based permissions.

## 4.5 G0A and G0B - Separate release gates

| Evidence                      | Required result                                                                                                                                                                                       |
|-------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| G0A: runtime state            | Change record showing configuration actually loaded, process/cache actions if necessary, build ID and timestamp; unauthenticated create attempts demonstrably refused with no persisted data.         |
| G0A: evidence preservation    | Tracking rows, relevant audit logs, topology and backups preserved with hashes, access controls and a named incident owner. No destructive cleanup performed before preservation.                     |
| G0A: communication            | Affected demo/wizard UI clearly states saves are unavailable; the incident record is opened and immediate operational stakeholders are informed.                                                      |
| G0B: authorisation            | Automated staging tests prove keyless, wrong-scope, revoked, expired and cross-object writes/deletes are refused; Heratio and reference-service routes both inventoried.                              |
| G0B: incident impact          | All 37 tracking entries reconciled; affected entities and dependent projections assessed; data owner approves disposition and any notification decision.                                              |
| G0B: recovery                 | Verified backup and successful test restore; controlled deployment, monitoring and rollback procedures available before security release.                                                             |
| G0B: independent verification | A reviewer other than the change implementer checks evidence where feasible; if the same person performs both roles, a documented exception and external/second-person follow-up review is mandatory. |

: G0A immediate containment and G0B remediation evidence

# 5. P0B - A trustworthy public specification

## 5.1 Prepared changes held until G0B security closure

One exception: the wizard notice is part of G0A. Since anonymous saving was switched off, the wizard's "Create it" button receives a 401 and gives the visitor no explanation. A minimal site release containing only that notice, and offering the generated RiC-O for copying, ships before G0B. Nothing else from the held batch goes with it.

The source plan reports a prepared, locally checked site-change batch
that had not been released as of its audit. Revalidate the branch, CI
status and deployment target before release. All published
status/profile counts must derive from canonical manifests and dated
evidence; current runtime values are not established by this revision.

The source plan reports both positive and deliberately failing local
checks and renders using Liquid/kramdown. Re-run those checks on the
release commit, execute a full reproducible Jekyll build in controlled
CI, and verify the published Pages result before G1 closes. A release
must not serve as its first full-build test. A GitHub Actions workflow builds the full site with the GitHub Pages toolchain on every pull request and on main, and keeps the built site as an artefact. That is a small addition to the existing `ci.yml`, and it closes the gap that no full Jekyll build is possible on the host.

## 5.2 Changes V2 requires before that batch ships

- **Keep specification-defined, service-declared, independently tested
  and last-tested-at as distinct fields, each linked to the relevant
  immutable release and test artifact. The "tested" state must remain
  "not run" or "no evidence" unless a real conformance report exists; do
  not convert it automatically from a service declaration.**
- **Versions page.** It currently calls tags "preserved, unchanged".
  Without tag protection that is not true. Either a repository ruleset
  blocks changes to release tags, or the wording changes. The preferred
  answer is both: protect the tags, and attach a packaged specification
  bundle with a SHA-256 checksum to every GitHub release, so each
  version has a fixed, verifiable artifact.
- **Drift log.** The rewritten lead already states today's verified
  service facts and dates them. It keeps the older Phase G table, marked
  as recorded in April and not re-verified row by row.

V2 is also right that pattern checks cannot catch every contradiction.
The checks catch the class of error that has actually recurred,
hand-typed counts and stale dates. A scheduled human review of
current-state pages covers the rest.

## 5.3 Second batch

- **Evidence labels.** Four labels used everywhere data is shown as
  proof: independently sourced, derived, synthetic demonstration,
  participating institution, each with source, rights and date where
  they apply. The Egyptian Boat becomes a labelled synthetic
  demonstration record. The British Museum attribution is removed, and
  so is the claim that the seed command created it; the page states
  where the record actually came from.
- **Unqualified claims.** "RiC-O 1.1 conformant", "round-trip
  guaranteed" and "production consumer" each get the build, test, date
  and scope that support them, or are reworded.
- **Application page.** Introduces OpenRiC Workspace as in development
  and the Heratio migration as planned, not operational, using the
  positioning text agreed with the reviewer.
- **Navigation.** Home, Explore, Learn RiC, Application, Developers,
  Specification, About.
- **Surfaces.** Browse opens with no key field and no edit or delete
  controls. The server and key panel moves to a deliberate developer
  view.

## 5.4 How it ships

Publish separate, reviewable site and service releases after G0B is
signed off. CI must build exactly the tagged/approved commit; preserve
build logs, release artifacts, checksums, deployment approval and
rollback instructions. G1 closes only when an independent reader can
identify current specification, declared implementation support and
dated tested support without contradictions.

# 6. Workstream S - the specification

The specification owner keeps it implementation-neutral and versioned on
its own line. The Workspace's choices of database, authentication or
workflow must never appear in it as requirements.

Deliverables: version/deprecation policy; machine-readable normative and
draft profiles; fixture corpus and provenance; offline conformance
runner; immutable dated results with build/hash/environment metadata;
and a public matrix generated from those results. Run tests that mutate,
delete, stress or attempt privilege escalation only against isolated
disposable environments. Production scheduled monitoring is limited to
authorised, bounded, read-only checks and health observation; it must
not change live records.

A second implementation built outside AHG is a milestone, not a side
task. Two frontends, a fixture backend, or two AHG-built services do not
count. Recruitment starts now from the implementations already listed on
the site, with a named target and a defined pass condition on at least
one profile.

Conformance evidence policy: each matrix result names the
implementation/version, tested specification/profile, runner version,
fixture dataset, exact date, pass/fail/untested state and retained
report location. Distinguish self-assessment from genuinely independent
validation. A failure is never silently replaced by the previous passing
badge.

# 7. Workstream W - OpenRiC Workspace

A standalone archival application. Its own repository, database,
deployment and release line. Heratio does not have to exist for it to
run.

## 7.1 Scope

Accession, appraisal, arrangement, description, authority control,
contextual relationships, ingest and digital objects, rights and access,
governance, publication, public discovery, export. Preservation is an
integration with a preservation system such as Archivematica; an upload
and a checksum are never described as preservation.

## 7.2 First usable acceptance per capability

| Capability                  | First usable acceptance                                                                                                                                             |
|-----------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Description and arrangement | Build a fonds, series and item structure; rearrange it with identifiers unchanged; inspect the revision history.                                                    |
| Authority and context       | Link an agent, activity, place and date to records, recording the basis, dates, author and certainty of each relationship.                                          |
| Ingest and digital objects  | Import a batch with checksums, quarantine and validation, review, a storage receipt and an error report.                                                            |
| Workflow and permissions    | Draft to review to publish under role permissions, with embargo enforced and the audit trail shown.                                                                 |
| Provenance and uncertainty  | Tell apart sourced statements, transformed ones, machine suggestions and conflicting assertions. AI output never becomes an archival fact without editorial review. |
| Public discovery            | Search, walk the hierarchy and the graph, with an accessible non-graph view and stable citations.                                                                   |
| Interoperability            | Import and export in versioned formats, with errors a person can act on and conformance visible to the administrator.                                               |

: Workspace capabilities and first acceptance

## 7.3 Architecture guardrails

Use one authoritative transactional write model. Search and RDF graph
projections are rebuilt through a durable outbox with idempotent
consumers, retry/dead-letter handling, consistency checks, alerting and
a visible lag indicator. Mint persistent identifiers in a governed
namespace rather than exposing internal IDs or assuming the deployment
hostname will never change. Maintain durable resolution and
tombstone/redirect behaviour for renamed, migrated, merged or deleted
entities. Keep public discovery, archival editing and developer tools
separate, including their authorisation boundaries.

PostgreSQL is a candidate, not an approved choice. Benchmark
representative description and relationship workloads, migration scale,
indexing, graph-projection throughput, restore and operational burden;
document alternatives and decision evidence at G2. Avoid a second
writable graph "source of truth".

## 7.4 The vertical slice

Sign in, create and arrange a record, link an agent and an activity,
attach a digital object, submit for review, approve, publish, find it in
the public catalogue and the graph, export it, and inspect its
provenance and audit history. An archivist completes this without
touching an API. The slice is run twice: once on fresh entry and once on
a migrated Heratio specimen.

## 7.5 Non-functional acceptance and identifier governance

The G2 architecture charter must approve quantitative service-level
objectives and measured methods before implementation expands. Do not
insert invented production performance targets into this plan; derive
them from pilot institutions, expected collection sizes, risk appetite
and measured baselines.

Reliability and recovery: documented backup policy, approved RPO/RTO,
successful restore drill, disaster-recovery ownership, audit-log
retention, and upgrade/rollback tests.

Performance and scale: representative collections, entity/relationship
counts, concurrent users, search and hierarchy traversal timings, batch
import rate, RDF projection catch-up and large-export completion
metrics.

Security and privacy: threat model, tenancy/record-level access matrix,
cross-tenant negative tests, object download controls, upload
quarantine, secret rotation, audit tamper-resistance and
privacy/data-retention decisions.

Accessibility and usability: WCAG 2.2 AA test scope,
keyboard/screen-reader pathways, accessible non-graph alternative, and
completion of the vertical slice by an unaided practising archivist.

Persistent identity: documented IRI minting rules, organisational
control of the resolving namespace, migration mapping, merges/splits,
redirects/tombstones, link-checking and long-term resolution after
domain or hosting changes.

Evidence quality: no archival or inferred assertion is promoted
silently; source, transformation, human approval, confidence and
contradictory assertions remain inspectable.

# 8. Workstream M - migration from Heratio

Heratio creates a versioned, integrity-protected and authorised export
bundle; Workspace imports it through an independently documented,
offline contract. Workspace has no live database/API dependency on
Heratio. Export tooling is source-side, and import tooling is
destination-side; both have explicit versions, chain-of-custody
metadata, access controls and an inventory of every included or
intentionally excluded class of information.

| Stage      | Evidence and controls                                                                                                                                                                                                                                                           |
|------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Assess     | Inventory of record classes, authorities, relationships, object types, rights, identifiers, unsupported GLAM data, volumes and privacy obligations.                                                                                                                             |
| Map        | Versioned transformation and rights/permission mappings; durable ID mapping and long-term resolver policy; record every lossy or ambiguous transform and require owner approval for policy downgrades.                                                                        |
| Dry run    | Repeatable import into an isolated target; the same input gives the same, explained result; retries are idempotent.                                                                                                                                                             |
| Validate   | Full reconciliation of counts, parent-child and semantic relationships, protected-record visibility, identifier-resolution checks, digital-object checksum equality and SHACL where applicable; risk-based field sampling supplements, not replaces, complete integrity checks. |
| Exceptions | Exceptions classified by severity and data-owner action. Unresolved rights, embargo or identity conflicts fail closed and block cutover; unsupported GLAM fields are explicitly accounted for, never silently dropped.                                                          |
| Cutover    | Approved source freeze or captured change stream; delta import and final reconciliation; privacy/security sign-off, rollback decision point, old-identifier resolver and institutional acceptance recorded.                                                                     |
| Aftercare  | Old identifiers resolve or redirect; audit evidence kept; staging copies retained or disposed of by decision.                                                                                                                                                                   |

: Migration stages

Migration is proven by a reconciliation report, never by an
import-success message. The 17.9-million-triple figure is a target to
re-measure, not a result.

Acceptance must include negative tests demonstrating that formerly
restricted Heratio content remains restricted in Workspace and is never
accidentally indexed publicly. Reconcile the complete identifier set and
linked relationships, not only sampled descriptive fields. Report
item-level failures and provide rerunnable, idempotent correction
procedures.

# 9. Gates, owners and timing

| Gate                             | Evidence                                                                                                                                                                        | Accepted by                                                                            |
|----------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------|
| G0A Immediate containment        | Effective anonymous-write denial and no persistence; logs/DB evidence preserved; incident opened and UI warning displayed                                                       | Production/security owner, with retained verification evidence                         |
| G0B Security-remediation closure | Authorisation and token controls proven; incident impact/propagation assessed; disposition and notification decision documented; backup/restore and rollback demonstrated       | Production owner plus independent reviewer, or documented conflict-of-duties exception |
| G1 Trustworthy specification     | Reproducible documentation build; reconciled versions; protected release artifacts; declared and independently tested results visibly distinct                                  | Specification owner and an external reader/reviewer                                    |
| G2 Signed architecture           | Workspace charter; domain model, threat model, permission matrix, persistent-identifier policy, migration contract, database benchmark, non-functional SLOs and ranged estimate | Product/archival/technical leads, with explicit recorded sign-off                      |
| G3 Vertical slice                | Archivist performs all workflows on fresh and migrated data; audit/rights/evidence checks; CI reproduces the slice; accessibility baseline met                                  | Practising archivist not acting as the implementing developer                          |
| G4 Operational validation        | Measured SLOs; backups/restores; tenancy and rights isolation; file safety; offline mutating conformance suite; read-only production monitoring; accessibility evidence         | Operations/security owner and independent acceptance reviewer                          |
| G5 Institutional pilot           | Agreement; lawful dataset; migration/cutover plan; defined outcome and usability metrics; recorded institutional acceptance                                                     | Pilot institution/data owner and programme sponsor                                     |

: Delivery gates

The source programme identifies Dr Johan Pieterse as interim owner
across most functions. This concentration is a delivery and assurance
risk, not a substitute for independent approval. Assign a named deputy
for operations/incident response (a role still to be filled; until it is, the exception is recorded as V3.0 requires), maintain access and runbook
continuity, and commission external specification review plus
independent archivist acceptance before G3/G4. If separation of duties
is impossible for an emergency action, record the exception and arrange
retrospective review.

Publish a dependency-based milestone forecast with uncertainty ranges at
G2, and re-forecast after the G3 vertical slice using observed
throughput and defect rates. G0A has immediate operational urgency; it
should not wait for a programme forecast. Other gate dates are proposed
only after a named owner, capacity allocation and agreed acceptance
evidence exist.

# 10. Risks

| Risk                                                       | Severity | Treatment                                                                                                                                                                                   |
|------------------------------------------------------------|----------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Anonymous writes into production data                      | Critical | Status must not be assumed closed from approval. Deploy and verify disabling, preserve incident evidence, trace downstream copies, then close G0A/G0B separately.                           |
| Rights or tenant leak in the Workspace                     | Critical | Object-level authorisation, threat model at G2, adversarial tests at G4                                                                                                                     |
| Claims run ahead of tested conformance                     | High     | Declared and tested shown apart; scheduled conformance runs                                                                                                                                 |
| Copied code misses a Heratio security fix                  | High     | Copy/commit provenance and licence checks, automated dependency/SBOM scanning, security-advisory alerts, periodic upstream patch comparison and mandatory reconciliation in a patch ledger. |
| Migration loses relationships or access rules              | High     | Exception register, sampled comparison, data-owner sign-off                                                                                                                                 |
| Graph and database drift apart                             | High     | Outbox with retry and dead-letter, rebuild, visible lag, consistency checks                                                                                                                 |
| Scope grows into a second GLAM platform                    | High     | Change control against the archive-only charter                                                                                                                                             |
| Single person holds every role                             | High     | Independent reviewer and acceptance archivist, named deputy, access/runbook continuity, recorded separation-of-duties exceptions and independent follow-up.                                 |
| Documentation drifts again                                 | Medium   | Generated pages, CI checks, scheduled review                                                                                                                                                |
| No outside implementer or pilot                            | Medium   | Recruitment from now, with defined pass and success criteria                                                                                                                                |
| Unresolved anonymous-write propagation or breach duties    | Critical | Preserve evidence and map search/RDF/harvest/export/backups; controlled data-owner disposition; documented POPIA/contractual notification decision where relevant.                          |
| Identifier namespace breaks after host change or migration | High     | Stable IRI policy, resolver ownership, redirect/tombstone tests, complete identifier reconciliation and continuous link integrity monitoring.                                               |
| Unmeasured recovery, security or performance               | High     | G2 quantitative SLOs and test datasets; G4 independent evidence for restore, object-level permissions, throughput, projection lag and resilience.                                           |
| Mutating conformance tests affect production               | High     | Read-only production monitoring and isolated staging/fixture tests for creates, deletes, privilege, load and failure recovery.                                                              |

: Initial risk register

# 11. Approvals

This revision is a proposed programme-control document. Past
working-session decisions remain recorded below, but an "approved"
action is not a deployed, tested or closed action. Only a fresh, dated
verification can change those runtime status fields. No production
write, deletion, restore, configuration change or release is authorised
merely by issuing this document.

| Decision                                                                 | State                                                                                                                      |
|--------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| Product boundary and code-reuse rule                                     | Decided 14 October 2026                                                                                                    |
| Disable anonymous writes on the reference service                        | Deployed 14 October 2026, shortly before 15:00 UTC; keyless POST refused with 401 from outside the host. Recount of the tracking table outstanding |
| Review and disposition of the 37 anonymous rows                          | Approved to start 14 October 2026: read-only export to the incident folder and a CallHub incident call. No deletion before written data-owner disposition |
| Authorisation, key and header fixes (section 4.3, 4.4)                   | Pending implementation, staging tests, controlled deployment and G0B evidence                                              |
| Release of the held site batch, with the section 5.2 changes             | Pending; release conditioned on G0B closure and separate release approval                                                  |
| Tag protection on `openric/spec`                                         | Pending                                                                                                                    |
| Workspace charter and migration design (G2)                              | Pending                                                                                                                    |
| G0A: effective runtime state, evidence preservation, incident initiation | No completed-state claim until production verification artefacts are attached                                              |
| G0B: incident impact, propagation, privacy decision and security patch   | Pending; must include owner acceptance and second-person evidence check or exception                                       |
| Identifier namespace / resolution policy and non-functional SLOs at G2   | Pending architecture decision supported by measurable acceptance methods                                                   |

: Approval record

# 12. Next actions, in order

1.  G0A: done for the change itself (section 4.1). Still to do: recount
    the tracking table to show no entry after 12 October, preserve the
    configuration and logs in the incident folder, and ship the wizard
    notice.
2.  Open the incident and preserve the 37 tracking entries, dependent
    rows, logs and affected projections. Reconcile entries to actual
    transactions and determine notification obligations; do not purge
    before written data-owner disposition.
3.  G0B: stage and test authorisation, key lifecycle, route coverage,
    headers and recovery. Prove the backup can be restored before
    controlled deployment, then obtain independent closure evidence.
4.  After G0B: rebuild the documentation reproducibly, make
    declaration/evidence distinctions visible, protect tags and hash
    release bundles, then approve and verify the public release.
5.  Recruit an external specification reviewer and an independent
    practising archivist now; appoint an incident deputy and publish
    owners, review responsibilities and escalation path.
6.  Prepare the Workspace charter, identifier policy, non-functional SLO
    proposals and first vertical-slice acceptance script; inventory
    Heratio exports and run migration experiments on authorised,
    isolated copies only.

# Appendix A - Minimum evidence register and verification checklist

While one person holds most roles, the register is kept deliberately simple: one access-controlled folder per change or incident, holding a plain-text log with UTC times, the artefacts, and a `SHA256SUMS` file. The fields below go in that log. Heavier tooling is not needed until a second person reviews.

For every gate, store the evidence artefact outside public web roots
with controlled access. Each record must include: unique change/incident
ID, decision owner, affected system and environment, code/configuration
version, UTC execution time, tester, expected result, observed result,
evidence location and SHA-256 where relevant, reviewer, and explicit
residual risk.

G0A evidence: effective environment/configuration proof, external denied
request and DB non-persistence evidence, preserved event/log exports,
incident record and wizard status.

G0B evidence: route inventory, successful negative authorisation tests,
key revoke/expiry results, controlled backup restore, rollback
rehearsal, data-owner incident disposition, downstream-propagation
checks and review signature.

G1 evidence: release manifest, commit/tag protection, reproducible CI
build, integrity checksum, links/checker output, profile declaration and
conformance results with date, scope and test reporter.

G2/G3 evidence: signed charter and design decisions, representative
benchmark, IRI resolution tests, fresh/migrated vertical-slice
recording, permissions/audit tests and archivist evaluation.

G4/G5 evidence: quantified service-level results, restoration and
security test records, operational runbooks, institutional
data-governance agreements, reconciliation and pilot acceptance.

# Appendix B - Interpretation and scope of this revision

Most runtime facts and file paths in this document come from the 13-14 October audits recorded in V2 and V3.0. The rows added to section 2 in V3.1 were observed directly on 14 October 2026 and are dated there. Nothing in this document confirms the state of production after that afternoon; any later action starts from a fresh, dated check.

The architecture and programme controls added here are recommendations
and proposed acceptance requirements. They do not alter the agreed
product positioning: Heratio is the RiC-enabled integrated GLAM
platform; OpenRiC is the independent contract; OpenRiC Workspace is the
standalone RiC-native archival application for archives starting fresh,
with a controlled Heratio migration path.
