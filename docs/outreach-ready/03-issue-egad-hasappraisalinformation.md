---
target: https://github.com/ICA-EGAD/RiC-O/issues/new
title: Add `hasAppraisalInformation` for ISAD(G) 3.3.2 appraisal / destruction / scheduling
labels: enhancement, openric-upstream-proposal
status: send-ready as of 2026-05-25
related-notification: workbench/B7
source-of-truth: openric-spec/docs/upstream-proposals/hasAppraisalInformation.md
---

Hello EGAD,

I'm filing this from the OpenRiC implementation project ([openric.org](https://openric.org)) — we're an implementation-neutral HTTP/API contract for RiC-aligned archival data, with a [live conformance probe](https://github.com/openric/spec/blob/main/conformance/probe.sh) passing 30/30 against [`ric.theahg.co.za/api/ric/v1`](https://ric.theahg.co.za/api/ric/v1/) as of today. During our v0.37.0 RiC-O 1.1 conformance audit ([openric.org/audit/ric-o-1.1-audit.html](https://openric.org/audit/ric-o-1.1-audit.html)) we found that ISAD(G) element **3.3.2 (Appraisal, destruction and scheduling)** has no clean canonical mapping in RiC-O 1.1.

ISAD(G) 3.3.2 captures the appraisal rationale for a `RecordResource` — why the records were retained, on what schedule they will be destroyed, and which appraisal authority decided. It is a single archival-semantic concept widely used in production cataloguing.

OpenRiC currently models this as `openricx:hasAppraisalInformation` in our extension namespace ([openricx ontology](https://openric.org/ns/ext/v1.html)), but it would be much cleaner to have a canonical RiC-O property.

### Suggested IRI

```
rico:hasAppraisalInformation
```

### Suggested domain / range

- `rdfs:domain` — `rico:RecordResource` (covers RecordSet, Record, RecordPart)
- `rdfs:range` — `xsd:string` (literal datatype property), with a SHOULD-be-typed-as-Rule companion if the appraisal is governed by a formal schedule

### Suggested label / definition

- `rdfs:label` — "has appraisal information" (en) / "a comme information d'évaluation" (fr) / "tiene información de valoración" (es)
- `rdfs:comment` — "Connects a Record Resource to a textual statement of appraisal rationale, destruction schedule, retention period, or disposal authority."

### Why a string and not a Rule

OpenRiC originally considered modelling appraisal as a `rico:Rule` instance with a `hasOrHadRuleType` of "appraisal". That works for institutions whose appraisal decisions are governed by a formal disposition schedule (a Rule); it doesn't work for the common case where appraisal information is a free-form note ("retained as evidential — see correspondence file 1987-04"). A datatype property captures both cleanly: the value is a literal, and the more formal expression via `rico:isOrWasRegulatedBy` → `rico:Rule` remains available alongside.

### Cross-walks

| Source | Element |
|---|---|
| ISAD(G) | 3.3.2 Appraisal, destruction and scheduling |
| ISAAR(CPF) | (not mapped — agent-side appraisal authorities map to Mandate) |
| EAD 2002 | `<appraisal>` |
| EAC-CPF | (not mapped) |

### OpenRiC's interim placement

Currently `openricx:hasAppraisalInformation` (datatype property), declared in [`/ns/ext/v1.ttl`](https://openric.org/ns/ext/v1.ttl). On adoption upstream, OpenRiC will rename to `rico:hasAppraisalInformation` and retire the openricx term in the next minor spec release (per our extension-namespace versioning policy at [`ns/ext/v1.html`](https://openric.org/ns/ext/v1.html#versioning-policy)).

Happy to sit on the term in `openricx:` indefinitely if EGAD prefers a different modelling approach. Equally happy to align with whatever shape the eventual upstream takes.

Thanks,
Johan Pieterse / OpenRiC
