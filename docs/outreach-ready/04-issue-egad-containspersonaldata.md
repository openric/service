---
target: https://github.com/ICA-EGAD/RiC-O/issues/new
title: Add `containsPersonalData` privacy-compliance flag
labels: enhancement, openric-upstream-proposal
status: send-ready as of 2026-05-25
related-notification: workbench/B7
source-of-truth: openric-spec/docs/upstream-proposals/containsPersonalData.md
---

Hello EGAD,

Filing from the OpenRiC implementation project ([openric.org](https://openric.org)). During our v0.37.0 RiC-O 1.1 conformance audit we found a recurring need for a **first-class privacy-compliance flag** on `rico:RecordResource` — and no canonical RiC-O 1.1 property to express it.

In jurisdictions with privacy regulation (POPIA in South Africa, GDPR in the EU, PIPEDA in Canada, CCPA in California, the UK Data Protection Act, etc.) every public-access archival platform needs to know *at the resource level* whether records contain personally identifiable information. This is upstream of what the actual jurisdictional restriction says — the boolean "does this record contain PII?" is a separate concern from "what rule governs access to PII?"

The latter is well-modelled in RiC-O 1.1 via `rico:isOrWasRegulatedBy` → `rico:Rule`. The former — a simple `xsd:boolean` flag at the resource level — is not.

### Suggested IRI

```
rico:containsPersonalData
```

### Suggested domain / range

- `rdfs:domain` — `rico:RecordResource` (RecordSet, Record, RecordPart)
- `rdfs:range` — `xsd:boolean`

### Suggested label / definition

- `rdfs:label` — "contains personal data" (en) / "contient des données personnelles" (fr) / "contiene datos personales" (es)
- `rdfs:comment` — "True if the resource is known to contain personally identifiable information (PII), as defined by the relevant jurisdictional privacy regulation. The specific rules governing access to that PII MUST be expressed separately via `rico:isOrWasRegulatedBy` → `rico:Rule`."

### Why this and not just a Rule link

A `rico:Rule` link expresses *governance* (under which mandate / law / policy this resource is restricted). It does NOT express *factual content* (this resource contains PII). The two are separable:

- A resource may carry `rico:containsPersonalData true` without yet having an `isOrWasRegulatedBy` link (the institution knows the PII is there but hasn't yet attached the formal governance Rule).
- A resource may have `isOrWasRegulatedBy` → some Rule without containing PII (e.g. a Rule restricting access for cultural-property reasons).

API consumers (search interfaces, catalogue front-ends, reading-room software) need to filter on the boolean directly; making them traverse Rule chains and inspect rule-types defeats the purpose.

### Cross-walks

| Source | Element |
|---|---|
| GDPR | Article 4(1) "personal data" definition |
| POPIA (ZA) | §1 "personal information" definition |
| CCPA (US-CA) | §1798.140(o) "personal information" |
| ISAD(G) | (not directly mapped) |
| PREMIS | (not directly mapped) |

### OpenRiC's interim placement

Currently `openricx:containsPersonalData` (datatype property, range `xsd:boolean`), declared in [`/ns/ext/v1.ttl`](https://openric.org/ns/ext/v1.ttl). On upstream adoption, rename to `rico:containsPersonalData` and retire openricx term.

### Why this proposal is conservative

We are NOT proposing a full privacy ontology. PII detection thresholds, redaction state, lawful-basis declarations, retention-period interactions — all out of scope for this single boolean. Those belong in a per-jurisdiction extension (POPIA-specific, GDPR-specific, etc.) layered on top of this primitive.

Thanks,
Johan Pieterse / OpenRiC
