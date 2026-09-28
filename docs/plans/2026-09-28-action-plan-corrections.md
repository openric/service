# Corrections to the September 2026 OpenRiC planning documents

**Date:** 28 September 2026
**Author:** Dr Johan Pieterse
**Concerns:** `OpenRiC_RiC_Monday_Action_Plan_2026-09-28.docx` and `OpenRiC_UX_Streamlining_Review_2026-09.docx`

## Why this note exists

Both planning documents were verified against the four OpenRiC repositories on
28 September 2026 before any work was started. A large part of the Monday Action
Plan asks for work that already exists and shipped months ago, and the UX
Review's highest-priority finding names the wrong target version. Acting on
either document literally would duplicate finished work in the first case and
entrench a stale version claim in the second.

This note records what is actually true, so the plans are not re-actioned from
scratch later.

## The four repositories

| Repository | Path | Remote | Role |
|---|---|---|---|
| Reference service | `/usr/share/nginx/OpenRiC` | `openric/service` | ric.theahg.co.za, Laravel, version line 0.17.0 |
| Specification + site | `/usr/share/nginx/openric-spec` | `openric/spec` | openric.org, Jekyll, version line 0.43.10 |
| Viewer | `/usr/share/nginx/openric-viewer` | - | viewer.openric.org |
| Capture | `/usr/share/nginx/openric-capture` | - | capture.openric.org |

The service and the specification carry **separate version lines on purpose**.
Service 0.17.0 against spec 0.43.10 is not drift, and neither document should be
read as saying it is. What the site needs is a compatibility statement, not a
single merged number.

## Monday Action Plan - already done

The plan presents these as Monday P0/P1 work. Each already exists.

**The `openricx:` extension register.** Published at `ns/ext/v1.md` with the
machine-readable ontology at `ns/ext/v1.ttl`. It covers 48 terms, sorted into
the three categories the plan asks for - response-envelope classes, pragmatic
API shortcuts with a canonical RiC-O expansion, and candidate upstream proposals
- with per-term disposition and confidence flags held in the RiC-O 1.1
conformance audit of April 2026.

**The commitment not to mint in the RiC-O namespace.** Already stated in the
register's opening paragraph, and already the governing rule for every term in
it. The plan's "important constraint" is existing policy.

**The RiC standards baseline.** RiC-O 1.1 is the normative target. RiC-AG v0.1
was promoted to a first-class section of `related-implementations.md` in v0.38.1
(25 May 2026), and `spec/mapping.md` §1.2 declares RiC-AG the upstream
authoritative reference for the ISAD(G) / ISAAR(CPF) / ISDF / ISDIAH crosswalk.
What is genuinely missing is a single page that states all four parts of the
stack in one place - that part of the plan stands.

**The watch list.** `drift-log.md` already tracks upstream RiC-O activity in
exactly the form the plan's section 11 proposes. The plan's "language relation
proposals" watch item is further along than watching: `openricx:relationHasLanguage`
already exists, and the OpenRiC comment on RiC-O issue #146 is in `docs/`.

**Workstream A, assertion and provenance modelling.** Substantially built and
specified. The **Inferred-Provenance** draft profile shipped in spec v0.43.0
(19 June 2026) and carries `prov:wasGeneratedBy`, `openricx:assertionStatus`,
confidence, and receipts, with the governing rule that absence of provenance
means an asserted fact. Its SHACL shape is `shapes/profiles/inferred-provenance.shacl.ttl`.
The plan's proposed element table is close to what is already normative.

What remains open in workstream A is narrower than the plan suggests: modelling
two competing assertions about the same fact without forcing consensus, and the
supersession/revision path that retains the earlier assertion.

## Monday Action Plan - genuinely new

Two workstreams are not yet built and are worth the effort:

- **Workstream B, RiC + IIIF.** No recommended Record Resource to Instantiation
  to IIIF Manifest pattern exists today.
- **Workstream C, cross-model interoperability.** No RiC-O to Wikidata, Linked
  Art or CIDOC CRM mapping exists. The plan's advice to scope this to 5-10
  operationally significant concepts rather than a full alignment is sound.

## UX Review - the version finding is right, the target is wrong

The review's P0 is real. The drift is three generations deep, not two:

| Version string | Where it appears | Status |
|---|---|---|
| 0.43.10 | `version.json`, git tag `v0.43.10` | **Current** |
| v0.43.0 | `CHANGELOG.md` head, `spec/profiles/*` | Last changelogged release; patches .1 to .10 undocumented |
| v0.37.0 | home, `spec/index.md`, `README.md`, `for-developers.html`, `ns/ext/v1.md` | Stale |
| v0.2.0 | `governance.md`, `for-institutions.md`, `SETUP_GITHUB.md`, with legacy L1-L4 conformance wording | Badly stale |

The review recommends synchronising the site on v0.37.0 and describes that as
the current state. It is not. **Do not standardise on v0.37.0** - it is itself
two generations behind. The correct target is whatever `version.json` holds at
build time.

The review also recommends defining a single source of truth, "for example
version.json in the spec repository". That file already exists and is already
correct. The defect is not its absence; it is that **nothing consumes it**.
There is no `_data` directory, no page references `site.data`, and every version
string on the site is typed by hand into prose. The fix is plumbing, not
authorship:

1. Expose `version.json` to Jekyll as site data.
2. Replace hand-typed version strings on current-facing pages with the rendered
   value.
3. Rewrite the stale conformance narrative in `governance.md` and
   `for-institutions.md` from L1-L4 to the current profile-based model.
4. Add a CI check that fails when a current-facing page carries a version string
   that is not the one in `version.json`, exempting `CHANGELOG.md`,
   `drift-log.md` and `docs/`.

## What the UX Review gets right

The remainder of the review is grounded - Appendix A lists the public surfaces
it actually visited on 26 September 2026 - and its substantive findings stand
without correction: configuration-before-value on Browse, Viewer and Capture;
architecture-first rather than task-first navigation; destructive controls
present in unauthenticated discovery mode; and no shared presentation shell
across the four domains. Those should be actioned as written.

## Standing correction

Neither document was written against the repositories. Before actioning a
planning document for OpenRiC, check it against `version.json`, `CHANGELOG.md`,
`drift-log.md` and `ns/ext/v1.md` first. All four are current, and all four were
already answering questions these plans asked.
