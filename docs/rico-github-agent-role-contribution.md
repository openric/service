# Draft GitHub contribution — generic agent-role property (RiC-O 1.2)

> Intended as a comment on EGAD's forthcoming 1.2 issue for the generic
> agent-role-in-a-relation property (or the roadmap thread). Hold until that
> issue exists; do not open a competing issue.

---

We've implemented this exact property as an interim extension in **OpenRiC**, an
open (AGPL) reference implementation of RiC-O — so if it's useful, here is some
real-world experience to feed into the 1.2 design.

We defined a single generic property rather than one per relation class:

```turtle
openricx:relationHasAgentRole  rdfs:domain rico:Relation ; rdfs:range rico:RoleType .
rico:creationWithRole          rdfs:subPropertyOf openricx:relationHasAgentRole .
```

Two design points that worked well in practice, offered for consideration:

1. **Declare `rico:creationWithRole` a sub-property** of the new generic property.
   Existing RiC-O 1.1 creation-role data then infers through the generic path with
   zero migration, and the specific creation semantics are preserved.

2. **Range `rico:RoleType`, domain `rico:Relation`** covers every case we've hit —
   `EventRelation` (a "bride" at a wedding), `PerformanceRelation` (a "conductor"),
   `CreationRelation` — with one predicate. Emitted alongside the flat
   `hasOrHadParticipant` shortcut, mirroring RiC-O's shortcut + full-relation duality:

```turtle
my-data:APerfRel a rico:PerformanceRelation ;
    rico:relationHasTarget my-data:AnAgent ;
    openricx:relationHasAgentRole my-data:ConductorRole .
my-data:ConductorRole a rico:RoleType ; rdfs:label "conductor" .
```

When 1.2 publishes its property we'll bind ours with `owl:equivalentProperty`, so
this is purely interim. Happy to share the full extension (Turtle + SHACL) or to
help test the 1.2 property against real archival data. Thank you for the work on
1.2.

— Johan Pieterse (PhD), OpenRiC (ric.theahg.co.za)
