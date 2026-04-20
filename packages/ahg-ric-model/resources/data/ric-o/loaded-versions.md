# RiC-O data provenance

This package ships the canonical RiC-O ontology from ICA-EGAD. On install, the OWL file is loaded into the Fuseki dataset named by `FUSEKI_DATASET_MODEL` (default `openric-model`).

## Currently loaded

| Item | Value |
|---|---|
| **Version** | RiC-O v1.1 |
| **Published** | 2025-05-27 |
| **Source** | [`ICA-EGAD/RiC-O`](https://github.com/ICA-EGAD/RiC-O/releases/tag/v1.1) |
| **Tag SHA** | `49db95e7474ad5ba786817ccdfced61b47967749` |
| **OWL file** | `RiC-O_1-1.rdf` (RDF/XML, 1.65 MB) |
| **Loaded** | 2026-04-20 |
| **Triples** | 16,500 |
| **License** | CC BY 4.0 (declared via `cc:license` on `owl:Ontology`) |
| **Creator** | International Council on Archives Expert Group on Archival Description (ICA EGAD) |
| **Publisher** | International Council on Archives |

## Load verification (2026-04-20)

SPARQL probes against the freshly loaded dataset confirmed:

| Canonical item | Count | Matches RiC-CM 1.0? |
|---|---:|---|
| Entities (`^Corresponds to RiC-E##`, case-insensitive) | 19 | ✓ (E01–E18 plus E22) |
| Attributes (`[Cc]or+esponds to RiC-A##`, typo-tolerant) | 42 | ✓ |
| Non-inverse relations (`^RiC-R##`, excludes `RiC-R##i`) | 84 | ≠ 170 — see note below |
| Object properties with any RiC-R## marker | 154 | — |

### Note on the "corrresponds" typo

RiC-O v1.1 has a one-off typo in the annotation for **`rico:name`** / **RiC-A28 (Name)** — the value reads `"Corrresponds to RiC-A28"` (three Rs). All SPARQL queries that filter on the `rico:RiCCMCorrespondingComponent` annotation use a lenient regex (`[Cc]or+esponds to`) to match both the canonical spelling and the typo. Without the lenient regex, attribute count drops from 42 to 41 — that single missing row is RiC-A28.

### Note on relation count (84 vs. NavTool's 170)

DLIB Ionian University's NavTool lists 170 non-inverse RiC-CM relations. RiC-O v1.1's canonical `^RiC-R##` markers yield 84 non-inverse. The gap reflects a real difference between the RiC-CM conceptual-model document and the RiC-O OWL implementation: RiC-O consolidates related relations under broader object properties. For this reference browser we present what RiC-O provides — which is authoritative and machine-queryable — rather than what older RiC-CM drafts or secondary curations enumerate.

## CSV component lists (also shipped)

Bundled alongside the OWL as supplementary context. Not loaded into Fuseki; referenced by the `OntologyService` as a fallback enumeration aid when needed.

- `RiC-O_1-1_list-of-classes.csv` (112 KB)
- `RiC-O_1-1_list-of-datatype-properties.csv` (60 KB)
- `RiC-O_1-1_list-of-object-properties.csv` (281 KB)

All are from the same `49db95e` upstream tag and carry the same CC BY 4.0 license.

## Attribution (CC BY 4.0 compliance)

Any UI or API response backed by this data must credit:

> Records in Contexts-Ontology (RiC-O) v1.1 is published by the International Council on Archives, Expert Group on Archival Description (ICA EGAD), under CC BY 4.0.

The package's `README.md` and the reference-browser views render this credit prominently.

## Rebuilding

```bash
# Reload RiC-O v1.1 into Fuseki (drops and re-posts the default graph).
curl -u "$FUSEKI_USER:$FUSEKI_PASSWORD" -X PUT \
  "$FUSEKI_URL/$FUSEKI_DATASET_MODEL/data?default" \
  -H "Content-Type: application/rdf+xml" \
  --data-binary @packages/ahg-ric-model/resources/data/ric-o/RiC-O_1-1.rdf

# Or via artisan (Phase 2 task):
php artisan ric-model:load-ontology \
  packages/ahg-ric-model/resources/data/ric-o/RiC-O_1-1.rdf \
  --dataset=openric-model
```
