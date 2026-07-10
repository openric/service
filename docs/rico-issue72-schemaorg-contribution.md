# Draft comment — RiC-O issue #72 (Mapping RiC-O with Schema.org)

> Target: https://github.com/ICA-EGAD/RiC-O/issues/72
> Outward-facing — do not post without Johan's explicit go.

---

Adding an implementer's data point, since we run a **production schema.org
JSON-LD injector** over archival descriptions for web/search discoverability
(Heratio / OpenRiC, the open RiC-O reference implementation). What we actually
emit today:

- **Record / information object → `schema:ArchiveComponent`**, with `isPartOf`
  pointing at the parent (also an `ArchiveComponent`) to carry the hierarchy.
  Node kept deliberately thin: `name`, `identifier`, `description`, `url`.
- **Fonds / collection level → `schema:Dataset`** (with `DataDownload`
  distributions), specifically so Google Dataset Search / Bing pick the holdings up.
- **Agents:** corporate body → `Organization`, person and family → `Person`
  (there is no schema.org `Family`; `Person` is the pragmatic fit), else `Thing`.
- **Physical containers** → `Thing`.

On @williamsonrichard's point that a Record Resource is not really a
`CreativeWork` and should map no narrower than `Thing`: conceptually I agree —
archival material is documentation/by-product, not authored creative output. In
practice we still chose `ArchiveComponent` (a `CreativeWork` subtype) for the
record level, for one reason only: **discoverability**. Search tooling keys off
recognised types, and `ArchiveComponent` is schema.org's purpose-built type for
an "item that is part of a collection held by an archive." So the tension is real.

The way we reconcile it may be useful to the mapping: we treat the schema.org
type as a **lossy discovery *projection*, not an assertion of RiC identity**. That
lets two things coexist —

1. a **conceptual alignment** (rigorous: Record Resource ⊑ `Thing`, Organization ⊑
   `rico:Agent`, sub-property rather than equivalence where ranges differ — e.g.
   `rico:name` (rdf:Literal) vs schema `name` (Text)); and
2. a **discovery profile** (pragmatic, SEO-oriented: `ArchiveComponent` / `Dataset`),

which don't have to agree, because they answer different questions. Notably, keeping
the record node thin (no `encoding`/format on it) also respects the RiC
Record/Instantiation split @williamsonrichard flagged — those properties live on
the Instantiation.

Might it be worth RiC-O documenting these as **two separate mappings** rather than
one? Happy to share our injector logic and the Dataset descriptor, and to test any
proposed mapping against a live collection.

— Johan Pieterse (PhD), OpenRiC (ric.theahg.co.za) / Heratio
