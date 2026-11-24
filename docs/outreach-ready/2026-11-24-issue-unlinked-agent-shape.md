# Issue draft for openric/spec (not filed; awaiting Dr Johan Pieterse)

Title: UnlinkedAgentShape reports linked agents as unlinked: it counts only hasCreator, hasAccumulator and hasOrHadParticipant

Found on 24 November 2026 while running the Graph Traversal and full-graph shapes of spec v0.61.1 against RiC-O exports.

`:UnlinkedAgentShape` (in `shapes/full-graph.shacl.ttl` and `shapes/profiles/graph-traversal.shacl.ttl`, Graph Traversal 0.7.0, section 5.2) reports a Person, CorporateBody or Family as "not linked to any records or activities" unless some node points at it by `rico:hasCreator`, `rico:hasAccumulator` or `rico:hasOrHadParticipant`. Agents linked to records or activities in other ordinary ways are reported as unlinked:

- **The holder of a record.** `rico:hasOrHadHolder` is how the mapping places a repository (ISAD(G) 3.4 and ISDIAH). Every export that names its repository this way gets the result on the repository.
- **The performer of an activity, as Provenance and Event allows.** That profile says `rico:isOrWasPerformedBy` "is also canonical ... implementations MAY emit either" beside `rico:hasOrHadParticipant`. An activity that uses the inverse leaves its performer reported as unlinked. Provenance and Event Q3 also hands its existence check to this shape, so the two profiles disagree.
- **An agent that is a record's subject** (`rico:hasOrHadSubject`), and the inverse forms of all of these written from the agent's side (`rico:isOrWasHolderOf`, `rico:performsOrPerformed`, `rico:isCreatorOf`).

The result is only `sh:Info`, so no claim fails. But it is noise that hides the agents that really are unlinked, and the shape's message says something untrue about valid data.

## To reproduce

```turtle
@prefix rico: <https://www.ica.org/standards/RiC/ontology#> .
@prefix ex: <https://example.org/> .
ex:fonds a rico:RecordSet ; rico:title "Example fonds" ;
    rico:hasOrHadHolder ex:repo ; rico:hasOrHadSubject ex:subjectperson .
ex:repo a rico:CorporateBody ; rico:name "Example Archive" .
ex:digitisation a rico:Activity ; rico:isOrWasPerformedBy ex:photographer ;
    rico:resultsOrResultedIn ex:fonds .
ex:photographer a rico:Person ; rico:name "A. Photographer" .
ex:subjectperson a rico:Person ; rico:name "Subject Person" .
```

`pyshacl -s shapes/full-graph.shacl.ttl -d example.ttl` gives three `UnlinkedAgentShape` results, for `ex:repo`, `ex:photographer` and `ex:subjectperson`, although each is linked.

## Proposed fix (patch to Graph Traversal 0.7.x)

An agent counts as linked if any RiC-O object property joins it to a Record Resource, an Instantiation or an Activity, in either direction. That is closer to the shape's own message than a longer list of named properties:

```sparql
FILTER NOT EXISTS {
    { ?x ?p $this } UNION { $this ?p ?x }
    FILTER (STRSTARTS(STR(?p), "https://www.ica.org/standards/RiC/ontology#"))
    { ?x a rico:RecordSet } UNION { ?x a rico:Record } UNION { ?x a rico:RecordPart }
    UNION { ?x a rico:Instantiation } UNION { ?x a rico:Activity }
}
```

Tested with pySHACL on the example above plus one Person with no links at all. The current shape reports all four agents. With this filter, only the Person with no links is reported.

The same change goes into both shape files, with the section 5.2 table row in `spec/profiles/graph-traversal.md` reworded to match and a fixture for each link above. If a named list is preferred instead, it should hold at least `hasOrHadHolder`, `isOrWasPerformedBy` and `hasOrHadSubject` and their inverses.

Related, not part of this fix: the shape targets Person, CorporateBody and Family, while Provenance and Event also accepts `rico:Mechanism` and `rico:Agent` as performers.
