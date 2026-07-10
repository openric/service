# Draft comment — RiC-O issue #156 (Adopt a SHACL validator as a RiC-O resource)

> Target: https://github.com/ICA-EGAD/RiC-O/issues/156
> Outward-facing — do not post without Johan's explicit go.

---

A strong +1, with an implementer's angle that may be useful for the distinction
Florence draws between a straight OWL→SHACL transposition and richer rules.

In OpenRiC (the open AGPL reference implementation of RiC-O) we ship **two kinds
of SHACL**, and keeping them separate has paid off:

1. **OWL-transposition shapes** — structural, mechanically derivable from the
   ontology (cardinalities, domains/ranges). This is essentially what
   @nicholascar's generated graph gives you, and it's the right base layer.

2. **Archival-principle shapes** — hand-authored quality rules that go *beyond*
   the OWL, expressing exactly the descriptive principles Florence describes: a
   RecordSet must have a title (`sh:minCount 1`); it *should* have an identifier,
   scope/content, and an indication of holder/provenance (`sh:severity
   sh:Warning`); a Record should state its membership in a set; agents need an
   authorized name; and so on.

Two lessons worth passing on:

- **Use `sh:severity` so one shapes file serves both strict and advisory checks**
  — `sh:Violation` for the non-negotiables (title, identifier), `sh:Warning` for
  the recommended-but-not-mandatory (provenance, scope/content). Institutions then
  run the *same* file at different strictness levels instead of maintaining forks.
- **Layer generic + project shapes.** Ship the generic archival-principle shapes
  as a base — precisely the "starting point, to be completed and adjusted"
  Florence describes — and let a project add its own node shapes on top as an
  application profile. Keeping the base reusable has been the key.

And @tfrancart's shacl-play point matters here: severity-tiered shapes pay off
most when they're *readable* — running our archival-principle shapes through
shacl-play's HTML-ReSpec output gives an institution a plain-language "rules you
must meet / should meet" document straight from the SHACL, which is often more
useful to archivists than the raw Turtle. And the performing-arts.ch profile
Florence cites is a nice illustration of the layering above — a project-specific
application profile sitting on top of generic shapes.

Happy to share our shapes (`ric_shacl_shapes.ttl`, AGPL) as another worked example
alongside the Kurrawong graph. And tying to #157: describing each validator as a
`prof:ResourceDescriptor` with `role:validation` would make exactly this — *which*
SHACL, at *what* strictness — discoverable rather than folklore.

— Johan Pieterse (PhD), OpenRiC (ric.theahg.co.za)
