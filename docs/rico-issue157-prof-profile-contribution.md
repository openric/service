# Draft comment — RiC-O issue #157 (Describe RiC-O resources as a PROF Profile)

> Target: https://github.com/ICA-EGAD/RiC-O/issues/157
> Outward-facing — do not post without Johan's explicit go.

---

+1 — and a data point from having built roughly the ecosystem this proposes.

OpenRiC (the open AGPL reference implementation of RiC-O) already publishes its
material as a set of distinct, versioned artefacts that map almost one-to-one onto
PROF resource roles: a normative **specification** with several **conformance
profiles**, **SHACL** validators, a machine-readable **conformance probe**, a
JSON-LD **@context**, **examples**, and **mappings**. Splitting these out rather
than shipping one monolith has been the single biggest help for versioning, and
for letting implementers target a specific conformance level — which is exactly
the payoff PROF is meant to formalise.

Two things that might make the profile especially useful in practice:

- **`prof:hasRole` labels each RiC-O artefact cleanly** — `role:specification`,
  `role:guidance`, `role:vocabulary` (the SKOS schemes you're moving the RiC-O 1.1
  individuals into for 2.0), `role:validation` (the SHACL from #156),
  `role:example`, `role:mapping`, and `role:schema`. The modularized version you
  mention for 2.0 is a natural fit: each module becomes a `prof:ResourceDescriptor`.

- **Profile negotiation is where it pays off for consumers.** With a PROF
  description a client can content-negotiate a specific resource — "give me the
  SHACL", "the guidance", "module X" — via `Accept-Profile` or a query parameter.
  Reference implementations like ours already do *format* negotiation (JSON-LD /
  Turtle / RDF-XML); *profile* negotiation is the natural next layer, and PROF is
  what makes it declarative rather than bespoke.

Happy to contribute a draft profile descriptor, or to share how OpenRiC has
structured its conformance profiles, if that would help a lightweight draft here.

— Johan Pieterse (PhD), OpenRiC (ric.theahg.co.za)
