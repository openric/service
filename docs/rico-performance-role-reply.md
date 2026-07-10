Dear Florence, Sylvain, all,

That's excellent news — thank you, Florence, for confirming a generic
role-of-agent-in-a-relation property is coming in RiC-O 1.2. It's exactly the
right shape: one property over any `rico:Relation` involving a `rico:Agent`,
rather than a `…WithRole` property minted per relation class. It resolves
Sylvain's PerformanceRelation gap and Ann Attwood's EventRelation ("bride") case
in the parallel thread with a single mechanism.

Since 1.2 is a few months out, one practical note for anyone who needs this now:
we've implemented an interim version in OpenRiC (the open reference
implementation) with precisely that signature, so it can be used today and then
retired cleanly when 1.2 lands —

    openricx:relationHasAgentRole  rdfs:domain rico:Relation ; rdfs:range rico:RoleType .
    rico:creationWithRole          rdfs:subPropertyOf openricx:relationHasAgentRole .

Declaring `rico:creationWithRole` a sub-property keeps existing RiC-O 1.1
creation-role data inferring through the generic path, with no migration.
Sylvain's case then reads:

    my-data:APerfRel a rico:PerformanceRelation ;
        rico:relationHasTarget my-data:AnAgent ;
        openricx:relationHasAgentRole my-data:ConductorRole .

    my-data:ConductorRole a rico:RoleType ; rdfs:label "conductor" .

When 1.2 ships its property we'll bind ours to it with `owl:equivalentProperty`,
so downstream data keeps working. Please do post the GitHub issue link when it's
open — we'd be glad to follow it and feed back real-world experience from
implementing the generic property (and the Carrier / kinship-of-care extensions
from the other threads) against actual archival data. Happy to share our
extension (Turtle + SHACL, AGPL) with anyone who wants a head start.

All the best,

Johan Pieterse (PhD)
http://heratio.theahg.co.za/  ·  https://ric.theahg.co.za/
