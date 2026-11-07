# Draft comment - RiC-O issue #146 (LanguageRelation / language on a relation)

> Target: https://github.com/ICA-EGAD/RiC-O/issues/146
> Supersedes the earlier list-reply draft, since Florence redirected this to #146
> and confirmed performanceRelationWithLanguage for RiC-O 1.2.
> Outward-facing - do not post without Johan's explicit go.

---

Dear Florence, Sylvain, all,

+1 on adding `performanceRelationWithLanguage`. #146 has a structure worth making
explicit, because it maps almost exactly onto the agent-role pattern the group is
already building for 1.2.

There seem to be two distinct qualifiers on the reified relation, and it helps to
keep them apart:

1. **Which language** was used - a property from the relation to a `rico:Language`.
   That is `performanceRelationWithLanguage` (and, if it were ever wanted beyond
   performances, a generic `relationHasLanguage` over any `rico:Relation`).

2. **How** the language was used - Sylvain's `languageWithUsage` -> `LanguageUseType`
   idea (primary text, commentary, gloss, transcription, translation, code-switching,
   and so on). This is precisely parallel to `creationWithRole` -> `RoleType`: a
   reusable, per-archive taxonomy attached to the relation.

So the language question is structurally the same as the role question: (which agent
/ which language) plus (an optional typed qualifier: RoleType / LanguageUseType).
Treating the two the same way, rather than as unrelated mechanisms, gives implementers
one consistent reified-relation-qualifier pattern instead of a growing set of
per-class one-offs.

For what it is worth, we have built this pattern in OpenRiC (the open reference
implementation): a generic `relationHasAgentRole` (Relation -> RoleType), and now a
sibling `relationHasLanguage` (Relation -> Language). A `performanceRelationWithLanguage`
slots in cleanly as a sub-property of the generic one, exactly as `creationWithRole`
specialises the generic role property. A `LanguageUseType` parallel to `RoleType`
drops in the same way - happy to prototype it and share.

On Richard's outcome-vs-performance point (is a translation "performed in" a language,
or is its resulting Record Part "expressed in" one?): in practice both are true and
useful, and the reified-relation approach lets you state each without forcing a choice
- the language of the Instantiation / Record Part stays on `hasOrHadLanguage`, while
the language of the performing or creating activity sits on the relation. Keeping them
as separate statements also avoids the "two events?" duplication Sylvain raised.

Best regards,

Johan Pieterse (PhD)
OpenRiC (ric.theahg.co.za)
