---
to: contact@sparna.fr
subject: OpenRiC SPARQL Access (Draft) profile — sanity-check + Garance second-implementation question
attn: Thomas Francart
cc:
status: send-ready as of 2026-05-25
related-notification: workbench/B5
source-of-truth: openric-spec/docs/outreach/sparna-second-implementation.md
---

Hi Thomas,

I work on [OpenRiC](https://openric.org) — an open, implementation-neutral HTTP/API contract for serving RiC-aligned archival data. We're a smaller project than Garance: a spec + reference Laravel API + browser viewer + capture client + conformance probe, all CC-BY/AGPL. The aim is a contract any RiC-O 1.1-compliant server can implement.

Three things prompted this email:

**1. Garance is the cleanest RiC-O 1.1 publication architecture I've seen.** The Eleventy + JSON-LD framing + PageFind + QLever stack is a topology we cite in our [Related Implementations page](https://openric.org/related-implementations.html) and our [SPARQL Access (Draft) profile](https://openric.org/spec/profiles/sparql-access.html) §9 Q4 (the QLever vs Fuseki/Jena discussion). It is the working reference for "what mature RiC-O publication looks like."

**2. We just shipped the SPARQL Access (Draft) profile, end-to-end.** Spec v0.38.3 is on `origin/main`: an optional 7th profile (`sparql-access` v0.1.0) for OpenRiC servers that expose a SPARQL 1.1 query surface. Minimum viable obligations, an access-policy taxonomy (`public-read` / `authenticated-read` / `tenant-restricted`), rate-limit + max-query-time disclosure, and a `/sparql/info` `void:Dataset` description endpoint. SHACL shapes and two fixtures (`sparql-info`, `sparql-construct`) shipped in v0.38.0; the conformance probe (`openric-spec/conformance/probe.sh`) now covers the profile end-to-end.

The reference service at [`ric.theahg.co.za/api/ric/v1`](https://ric.theahg.co.za/api/ric/v1/) implements the profile as of OpenRiC v0.10.0 (today). Live endpoints:

- [`GET https://ric.theahg.co.za/api/ric/v1/sparql?query=SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o }`](https://ric.theahg.co.za/api/ric/v1/sparql?query=SELECT%20(COUNT(%2a)%20AS%20%3Fn)%20WHERE%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D)
- [`GET https://ric.theahg.co.za/api/ric/v1/sparql/info`](https://ric.theahg.co.za/api/ric/v1/sparql/info)
- [`GET https://ric.theahg.co.za/api/ric/v1/`](https://ric.theahg.co.za/api/ric/v1/) (the service description with all 7 claimed profiles)

I'd value your sanity-check on the profile draft as someone who has actually run a public SPARQL endpoint over RiC-O 1.1 at scale. Specifically:

- Is the access-policy taxonomy missing any kind of access posture you've seen at AnF or elsewhere?
- Is `/sparql/info` the right shape for a `void:Dataset` description, or have you seen a different convention emerge?
- Is the openricx-only `@context` requirement on JSON-LD CONSTRUCT/DESCRIBE results too strict / too loose?

**3. The bigger ask, posed gently.** OpenRiC's v1.0 freeze is gated on at least one non-reference implementation passing the conformance probe. Garance, by virtue of being an RDF-first publication of agents/places/concepts under RiC-O 1.1 with a SPARQL endpoint, is structurally close to a second implementation on the SPARQL Access profile — it would need to expose a `/sparql/info` endpoint matching our `void:Dataset` shape and respond to conformance-probe checks. **We are NOT asking for a commitment** — but if you'd be open to a future conversation about whether Garance v2 (the mid-June 2026 release per your roadmap) could opt into this profile, that would unblock something significant for us.

OpenRiC will not absorb Garance content, will preserve original AnF URIs and attribution per the [`Referentiels` repository](https://github.com/ArchivesNationalesFR/Referentiels) terms, and lists Garance on the [Related Implementations page](https://openric.org/related-implementations.html) as an external project (it already does).

If sanity-checking the draft is too much without context, I'm happy to walk through the profile's design decisions (especially Q1–Q5 in §9) over a call. Equally happy with terse line-by-line feedback on the draft itself via a [GitHub Discussion on `openric/spec`](https://github.com/openric/spec/discussions).

Thanks for everything Sparna does for the EGAD ecosystem.

— Johan Pieterse
[openric.org](https://openric.org) · [github.com/openric/spec](https://github.com/openric/spec) · [ric.theahg.co.za](https://ric.theahg.co.za/)
