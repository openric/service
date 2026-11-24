# Issue draft for openric/service (not filed; awaiting Dr Johan Pieterse)

Title: A repository with no name is listed and served as a bare @id and @type, failing Core Discovery SHACL

Found on 24 November 2026 by the conformance probe of spec v0.61.0 (Core Discovery SHACL), run read-only against ric.theahg.co.za (service v0.18.2).

The repository list includes `https://ric.theahg.co.za/repository/d6mh-ktzy-h6qz` with `name: null`, and `GET /api/ric/v1/repositories/d6mh-ktzy-h6qz` answers 200 with only:

```json
{"@id":"https://ric.theahg.co.za/repository/d6mh-ktzy-h6qz","@type":"rico:CorporateBody"}
```

`core-discovery.shacl.ttl` requires a repository to have `rico:name` or `rico:hasOrHadAgentName`, so this is a violation. It is the first repository the list returns, so every probe run meets it.

Either the repository has no name in Heratio's data, in which case the service should leave it out of the public list and answer 404 (as for an unpublished entity), or the name exists under another culture and the service is not falling back to it. Which one should be established before the fix.
