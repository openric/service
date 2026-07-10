Dear Ann,

Florence's EventRelation answer is exactly the pattern I would use too, and I
wanted to add a practical note: we've now implemented it in OpenRiC (the open
reference implementation at ric.theahg.co.za), so you can see the shape working
end to end and reuse the extension rather than defining it from scratch.

**On the role in an event ("bride" at a wedding)**

Florence is right that the `_role` object properties (`rico:eventRelation_role`
etc.) are not what you want — those are the reflexive relation-to-relation
properties from the RiC-O 1.0 relation-system rewrite, not "the role an agent
played." The clean pattern is the reified one she drew:

- keep the simple shortcut `Event —rico:hasOrHadParticipant→ Person` for easy
  querying, and, in addition,
- reify: `Event —rico:thingIsSourceOfRelation→ EventRelation`, with the
  EventRelation carrying `rico:relationHasTarget → Person` and the role.

The only missing piece in RiC-O 1.1 is a generic "role played by the agent in
this relation" property — today there is only `rico:creationWithRole`, scoped to
CreationRelation. Florence has confirmed a generic version is intended for
RiC-O 1.2. In the meantime we've defined one in our extension namespace:

    openricx:relationHasAgentRole   (domain rico:Relation, range rico:RoleType)

deliberately generic so it works on EventRelation, PerformanceRelation, etc. —
not just events — and we declare `rico:creationWithRole` as a sub-property of it
so existing creation-role data flows through the same path. When RiC-O 1.2 ships
its own property we'll bind ours to it with `owl:equivalentProperty`, so nothing
downstream breaks. Turtle for your wedding example:

    my-data:JaneDoeWedding a rico:Event ;
        rico:hasOrHadParticipant my-data:JaneDoe ;                 # shortcut
        rico:thingIsSourceOfRelation my-data:JDWeddingBrideRel .

    my-data:JDWeddingBrideRel a rico:EventRelation ;
        rico:relationHasTarget my-data:JaneDoe ;
        openricx:relationHasAgentRole my-data:BrideRole .

    my-data:BrideRole a rico:RoleType ; rico:name "bride" .

On your instinct to make Role a first-class entity like Position: I'd gently push
back. `rico:RoleType` already *is* the reusable, referenceable node (you define
"bride" once and point many relations at it) — so you get the benefit of a shared
vocabulary without a new entity. And Position is genuinely a different thing:
it connects an Agent to a *Group* (an office/post held within an organisation),
not an agent to an event, which is why it doesn't fit here.

**On adoption / fostering / guardianship, and care homes**

RiC-O 1.1 gives you `rico:hasChild`, `rico:hasSibling`, `rico:hasOrHadSpouse`
under `rico:hasFamilyAssociationWith`, but nothing for care relationships, so an
extension is the way (Florence suggested the same). We've added, in the same
extension:

- `openricx:hasAdoptedChild`  — sub-property of `rico:hasChild` (an adopted child
  is a legal child), with inverse `openricx:isOrWasAdoptedChildOf`;
- `openricx:hasFosterChild`    — under `rico:hasFamilyAssociationWith` rather than
  `hasChild`, since a foster child is not legally one's child;
- `openricx:isOrWasGuardianOf` / `openricx:isOrWasUnderGuardianshipOf` — for legal
  guardianship. Where the guardianship rests on a legal instrument, also relate
  the two people through a `rico:Mandate` (this is where Mandate earns its keep —
  the instrument, not the relationship itself).

Where you need the *dates* a relationship ran (fostering 1962–1968, say), reify
it as a `rico:FamilyRelation` between the two persons and put the start/end dates
on that relation node — same shortcut-plus-reified idea as the role case.

For time spent **in a care-home setting**, I'd actually reach for the role
pattern above rather than a kinship property: model the residency (or the episode
of care) as an Event/Activity, with the person as a participant playing the role
"resident" and the carer playing "carer", each qualified with
`openricx:relationHasAgentRole`. Dates then sit naturally on the Event. That
keeps a professional/institutional relationship out of the *family*-association
hierarchy, where it doesn't really belong, and it ties your two questions
together with one mechanism.

Like Position and the guardianship properties, none of this is Position's job —
Position is Agent-to-Group only.

If it's useful, the extension ontology (Turtle) and its SHACL shapes are small
and open (AGPL); happy to share the files or a link so you can drop them straight
into your own graph. And of course the RiC-AG "how to extend RiC" FAQ Florence
linked is the right home base for doing this cleanly.

Groete / Regards,

Johan Pieterse (PhD)
http://heratio.theahg.co.za/  ·  https://ric.theahg.co.za/
