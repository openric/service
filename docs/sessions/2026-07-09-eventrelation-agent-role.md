# OpenRiC v0.17.0 — agent-role-in-relation + kinship-of-care + Carrier extension

**Date:** 2026-07-09
**Trigger:** Two Records_in_Contexts_users list threads with Florence Clavaud (ICA/EGAD): (1) Ann Attwood, 2026-06 — the role an agent plays in an event (e.g. "bride" at a wedding), plus adoption/fostering/guardianship; (2) Valentín Mansilla magnetic-tape thread, Florence's 2026-07-09 reply — tape = carrier, track = Record, inscription = Instantiation, Carrier class coming in RiC-O 2.0.

## What shipped
- **Extension ontology** `packages/ahg-ric/tools/openric_ext.ttl` (namespace `openricx:` = `https://openric.org/ns/ext/v1#`):
  - `openricx:relationHasAgentRole` — generic (domain `rico:Relation`, range `rico:RoleType`); `rico:creationWithRole` declared as a sub-property so RiC-O 1.1 creation-role data flows through the generic path. Designed to bind to the intended RiC-O 1.2 generic property via `owl:equivalentProperty` when it lands.
  - Kinship of care: `hasAdoptedChild ⊑ rico:hasChild`, `hasFosterChild ⊑ rico:hasFamilyAssociationWith`, `isOrWasGuardianOf`/`isOrWasUnderGuardianshipOf ⊑ rico:hasFamilyAssociationWith` (+ inverses). Care-home stays modelled via the role pattern, not a kinship property.
- **SHACL** `packages/ahg-ric/tools/openric_ext_shapes.ttl` — RoleType must be named; relation carries ≤1 role of class RoleType. Both TTLs validated with rdflib (49 + 27 triples).
- **Storage** `packages/ahg-ric/database/install_relation_roletype.sql` — adds nullable `role_term_id` FK on `ric_relation_meta` (idempotent, information_schema-guarded). A RoleType is an ordinary `term`.
- **Serializer** `RicSerializationService::serializeActivity` — when a `performed_by` relation has `role_term_id`, emits a reified `rico:EventRelation` (`rico:relationHasTarget` + `openricx:relationHasAgentRole → rico:RoleType`) alongside the existing `rico:hasOrHadParticipant` shortcut. PHP lints clean; vendor copy synced.
- **Carrier extension** (same release, from the tape thread):
  - `openric_ext.ttl` §3 — `openricx:Carrier ⊑ rico:Thing` (the individual physical medium, distinct from `rico:CarrierType` the kind); `openricx:hasCarrier` (Instantiation → Carrier, many-to-one) + inverse `isCarrierOf`. Binds to RiC-O 2.0's Carrier class via `owl:equivalentClass` when it ships.
  - `openric_ext_shapes.ttl` — Carrier should carry `rico:identifier`; `hasCarrier` value must be a Carrier.
  - `database/install_instantiation_carrier.sql` — nullable `carrier_identifier` on `ric_instantiation` (idempotent).
  - `serializeInstantiation` — emits `openricx:hasCarrier → openricx:Carrier` (with `rico:identifier` + `rico:hasCarrierType`) when `carrier_identifier` is set, keeping the existing `rico:hasCarrierType` on the Instantiation.
- **Replies** `docs/rico-role-modelling-reply.md` (role thread) and `docs/rico-carrier-modelling-reply.md` (tape thread) — mailing-list answers positioning OpenRiC as the reference implementation.
- Version 0.16.4 → **0.17.0**. TTLs revalidated with rdflib (67 + 45 triples); Carrier axioms verified.

## Deploy notes
- Run `install_relation_roletype.sql` against the reference DB before the serializer path can carry data.
- `vendor/ahg/ric` is a gitignored copy — already synced this session; a proper deploy re-copies from `packages/ahg-ric`.
- Not yet committed (project rule: stage only). Re-run `openric-spec/conformance/probe.sh` after release per the re-probe rule.

## Follow-ups
- Mirror `openric_ext.ttl` into the openric-spec repo for publication at `https://openric.org/ns/ext/v1`.
- Wizard/editor UI to set a participant's RoleType.
- Watch RiC-O 1.2 for the official generic agent-role property; add the `owl:equivalentProperty` binding when named.
