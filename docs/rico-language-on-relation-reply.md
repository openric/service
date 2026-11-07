Dear Sylvain,

I checked the current definitions: `rico:hasOrHadLanguage` has domain Agent or
Record or RecordPart and range `rico:Language`, and `PerformanceRelation` connects
an Activity (the source) to the Agent(s) that performed it (the target).

I'd favour your second option, and I'd push it one step further.

Widening `hasOrHadLanguage` to include Activity would, as you note, blur things:
a language is not inherent to an activity the way it is to a record. A qualifier
on the relation is the more faithful model - the language belongs to the
*performance* (this agent, performing this activity), not to the activity type
itself.

The refinement: rather than a PerformanceRelation-specific property
(`useOrUsedLanguage` / `withLanguage`), consider a *generic* one on any relation -
say `relationHasLanguage`, with domain `rico:Relation` and range `rico:Language`.
This is the same design question the group is already working through for roles:
the RiC-O 1.2 plan is a single generic "role played by an agent in a relation"
property rather than per-class ones (creationWithRole, an event one, and so on).
Language-on-a-relation is the same shape - a contextual qualifier on a reified
relation - so it would be consistent to treat it the same way instead of minting
a PerformanceRelation-only property. It would also then cover, for instance, the
language of a creation or an event, not just a performance.

For what it's worth we implemented the role version of exactly this pattern in
OpenRiC (a generic `relationHasAgentRole`, Relation -> RoleType) as an interim
extension; a sibling `relationHasLanguage` (Relation -> Language) drops in cleanly
alongside it.

And yes - GitHub is the right place. The RiC-O team have said that's where they
prefer to track this kind of thing, and an issue there can be linked to the
related role / relation-qualifier work. Happy to comment if you open one.

Best regards,

Johan Pieterse (PhD)
OpenRiC (ric.theahg.co.za)
