# Issue draft for openric/service (not filed; awaiting Dr Johan Pieterse)

Title: Turtle and RDF/XML exports drop nested blank nodes (instantiations, extents)

Found on 24 November 2026 by the conformance probe of spec v0.61.0 (Export-Only, "one triple set in the three formats"), run read-only against ric.theahg.co.za (service v0.18.2).

`GET /api/ric/v1/records/title-of-object/export` gives 16 triples as JSON-LD, 7 as Turtle (`?format=ttl`) and 5 as RDF/XML (`?format=rdf`). The Turtle and RDF/XML keep the record's `rico:hasExtent` and `rico:hasOrHadInstantiation` links, but the blank nodes they point to are empty. Lost from both:

- the instantiation: `rico:Instantiation`, `rico:identifier "marble_statue_ultra_high_res.tiff"`, `openricx:hasMimeType "image/tiff"`, and its extent (`rico:quantity 75504986`, unit `bytes`);
- the record's extent: `rico:Extent` with `"1x2x3"`.

So the serialisers drop nested blank nodes. Export-Only requires the same triple set in every format.

Seen in passing, in the JSON-LD too: the extents use `rico:Extent` with the unit as a literal on `rico:hasExtentType`, an object property. Digital Object Linkage deprecated that in v0.54.0 for a `rico:InstantiationExtent` with `rico:quantity` and `rico:hasUnitOfMeasurement`.

Check: rerun `conformance/probe.sh --profile=export-only` with pySHACL and rdflib installed.
