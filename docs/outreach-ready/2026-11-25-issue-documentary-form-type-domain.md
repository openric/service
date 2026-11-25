# Issue draft for openric/spec (not filed; awaiting Dr Johan Pieterse)

Title: No shape checks the domain of rico:hasDocumentaryFormType (Record or Record Part), and the mapping does not say where a record set's genre goes

Found on 24 November 2026 while checking RiC-O exports against spec v0.61.1.

In RiC-O 1.1, `rico:hasDocumentaryFormType` has the domain `rico:Record` or `rico:RecordPart` (`RiC-O_1-1.rdf`, an `owl:unionOf` of the two). A `rico:RecordSet` with a documentary form type is therefore outside the ontology: a reasoner would infer that the record set is also a Record or a Record Part.

Two things in the specification let such data pass:

1. **No shape checks it.** No file in `shapes/` mentions `rico:hasDocumentaryFormType`, so an export that puts it on a RecordSet conforms to every profile. We found one that did, an export where a series of woodblock prints carried a documentary form "Woodblock prints". It passed all the shapes with no Violation and no Warning.
2. **The mapping only uses it on Records** (finding aids, section 7 rows for ISAD(G) 3.4.5 and ISAAR(CPF) 5.3.8). It says nothing about genre access points, which AtoM-based systems hold at every level, fonds and series included. So an implementer mapping a series' genre has no guidance, and `hasDocumentaryFormType` is the obvious but wrong choice.

None of the spec's 38 JSON-LD fixtures uses the property, so a new check would not change any published result.

## Proposed fix

**A shape**, in `shapes/profiles/always-on.shacl.ttl`, as a Warning first:

```turtle
:DocumentaryFormTypeDomainShape a sh:NodeShape ;
    sh:targetSubjectsOf rico:hasDocumentaryFormType ;
    sh:severity sh:Warning ;
    sh:or ( [ sh:class rico:Record ] [ sh:class rico:RecordPart ] ) ;
    sh:message "rico:hasDocumentaryFormType applies to a Record or Record Part (RiC-O 1.1 domain), not to this node" .
```

We tested it with pySHACL on a RecordSet, a Record and a RecordPart that each carry a documentary form type. Only the RecordSet is reported. It would become a Violation after the deprecation period that change control sets.

**A mapping note** in section 7, for genre access points. For a Record or Record Part, the genre is `rico:hasDocumentaryFormType`. For a Record Set, RiC-O 1.1 has no documentary form property, so the genre is stated on its member Records, or left out of the RDF with a note saying why. Which of these OpenRiC recommends is the decision this issue asks for. RiC-AG's guidance should be checked before choosing.

## Wider point, for a separate issue if wanted

This is one case of a general gap. The shapes check required properties and the types of linked nodes, but not whether each RiC-O property sits inside its RiC-O domain. A check that is generated from the ontology file would catch the whole class, not this one property.
