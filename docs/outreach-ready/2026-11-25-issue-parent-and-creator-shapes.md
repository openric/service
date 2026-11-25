# Issue draft for openric/spec (not filed; awaiting Dr Johan Pieterse)

Title: The shapes contradict the mapping on a record's parent (isOrWasPartOf against isOrWasIncludedIn) and on a creator's class (Agent)

Found on 25 November 2026 while checking RiC-O exports against spec v0.61.1. Data that follows the mapping exactly is reported by the shapes: a Warning for every correctly linked item, and a Violation for a creator whose kind is not known.

## 1. A record's parent

The mapping (section 7, the *Parent* row) gives a record's parent as `rico:isOrWasIncludedIn`, inverse `rico:includesOrIncluded`. That is RiC-O 1.1's relation between a Record Resource and the Record Set it belongs to: its domain is Record or RecordSet, and its range is RecordSet.

The shapes look for `rico:isOrWasPartOf`, RiC-O's general part relation (domain and range Thing):

| Shape | File | Effect |
|---|---|---|
| `RecordShape` property, "Record should be part of a RecordSet" (Warning) | `openric.shacl.ttl` lines 75 and 103, `profiles/core-discovery.shacl.ttl` lines 66 and 94 | A record with `rico:isOrWasIncludedIn` gets the Warning anyway |
| `RelationConsistencyShape`, "Parent (isOrWasPartOf target) should be a RecordSet" | `openric.shacl.ttl` 445, `profiles/graph-traversal.shacl.ttl` 40 | The parent's type is never checked for `isOrWasIncludedIn` |
| `OrphanedRecordShape`, "Record has no parent and is not a top-level fonds" | `full-graph.shacl.ttl` 58, `profiles/graph-traversal.shacl.ttl` 96 | Every included record is reported as an orphan |

A record listed only from its parent's side (`rico:includesOrIncluded`, which the reference service sends on a record set) is reported the same way.

The implementations differ as a result:
- The Workspace sends `rico:isOrWasIncludedIn`, as the mapping says.
- The reference service's graph edges, and the `subgraph-depth-1` and `subgraph-depth-2` fixtures, use `rico:isOrWasPartOf` for a record's parent.

Places are not affected. Authority and Context nests places with `rico:isOrWasPartOf`, which is right for them.

**Proposed fix.** The three shapes count a parent given by `rico:isOrWasIncludedIn`, by `rico:includesOrIncluded` from the parent's side, or by `rico:isOrWasPartOf`. They keep `rico:isOrWasPartOf` only through the deprecation period that change control sets, and `RelationConsistencyShape` also targets `rico:isOrWasIncludedIn`. For example, the RecordShape check as SPARQL:

```turtle
:RecordParentShape a sh:NodeShape ;
    sh:targetClass rico:Record ;
    sh:severity sh:Warning ;
    sh:sparql [ sh:message "Record should be included in a RecordSet" ; sh:select """
        SELECT $this WHERE { FILTER NOT EXISTS {
            { $this rico:isOrWasIncludedIn ?p } UNION { ?p rico:includesOrIncluded $this }
            UNION { $this rico:isOrWasPartOf ?p } } }""" ] .
```

The graph edges and the two subgraph fixtures move to `rico:isOrWasIncludedIn` in the same release, with a note in the drift log.

## 2. A creator's class

The mapping row for ISAD(G) 3.2.1 says `rico:hasCreator` → `rico:Agent`. RiC-O 1.1 gives the range of `rico:hasCreator` as `rico:Agent`. Provenance and Event accepts a performer typed `rico:Person`, `rico:CorporateBody`, `rico:Family`, `rico:Mechanism` or `rico:Agent`.

The creator shape in `openric.shacl.ttl` (line 461) and `profiles/graph-traversal.shacl.ttl` (line 56) accepts only Person, CorporateBody or Family, as a **Violation**. So a system that knows a creator's name but not its kind, which is common in data migrated from AtoM, fails by doing what the mapping says.

**Proposed fix.** Accept the five classes Provenance and Event accepts. Add an Info shape for a creator typed only `rico:Agent`, saying to give its kind where it is known. Reword the mapping row to: `rico:hasCreator` → a `rico:Person`, `rico:CorporateBody` or `rico:Family`, or `rico:Agent` when the kind is not known.

## Tested

The two proposed shapes were run with pySHACL on a small graph containing:
- an item included by `isOrWasIncludedIn`;
- an item linked the old way by `isOrWasPartOf`;
- an item listed only by its parent's `includesOrIncluded`;
- a record part;
- an orphan;
- creators typed Person, typed only Agent, and typed Place.

| | Current shapes | Proposed |
|---|---|---|
| Item by `isOrWasIncludedIn` | Warning: not part of a RecordSet | Passes |
| Item listed by the parent | Warning | Passes |
| Item by `isOrWasPartOf` | Passes | Passes (during deprecation) |
| Orphan | Warning | Warning |
| Creator typed only `rico:Agent` | **Violation** | Info: give its kind |
| Creator typed `rico:Place` | Violation | Violation |

The change makes the shapes more permissive where the mapping is followed and keeps every real fault. It fits a patch to the shapes, with the move away from `isOrWasPartOf` in the next minor release.
