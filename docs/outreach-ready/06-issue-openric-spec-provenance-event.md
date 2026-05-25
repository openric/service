---
target: https://github.com/openric/spec/issues/new
title: Refine `provenance-event` profile to allow `rico:Production` without `rico:hasOrHadParticipant` when the underlying record claims no creator
labels: spec, profile-refinement
status: send-ready as of 2026-05-25
related-task: openric/A4 + memory project_production_activity_gap
source-of-truth: openric-spec/docs/issues/provenance-event-creator-unknown.md
---

## Summary

The current `provenance-event` profile, read strictly, requires every `rico:Production` activity to carry `rico:hasOrHadParticipant` (or its `rico:isOrWasPerformedBy` sub-property). This makes the profile **unattainable for any archive holding pre-modern material**, because the historical performer of pre-modern Production activities is genuinely unknown to the archive in most cases.

Proposal: the profile prose should be refined to allow `rico:Production` activities to omit `rico:hasOrHadParticipant` **when the underlying record itself claims no creator**. The profile would distinguish:

- **Missing participant because data hasn't been curated** → still non-conformant.
- **Missing participant because the archive doesn't know** → conformant (with optional `openricx:participantUnknown true` or similar machine-readable flag).

## Evidence — reference-server audit (2026-05-25)

228 total `rico:Production` activities on the reference server. Coverage analysis:

| Bucket | Count | % | Notes |
|---|---|---|---|
| `performed_by` set directly | **58** | 25.4% | Includes 13 safely auto-derived in service v0.9.3 |
| Derivable via creator-event of related record | (8, included in 58) | | Creator-event path |
| Derivable via inverse `has_creator`/`has_accumulator` | (5, included in 58) | | Inverse-relation path |
| **Missing — no derivable signal** | **170** | 74.6% | Of which: 88 pre-1900, 49 from 1900–1949, 23 from 1950–1999, 8 no-date, 7 modern-but-signal-less |

**137 of the 170 unfilled activities are pre-1950.** The originals don't claim creators. No amount of code or curation will fill these in — they are archival ground truth as "unknown."

The v0.9.3 release of the reference service shipped an `openric:backfill-production-participants` Artisan command implementing the two safe auto-derivation paths (creator-event + inverse relation). Even at full deployment, coverage moved 45 → 58 / 228 = 25.4%.

## Why this matters

Without prose clarification, the profile is structurally unattainable for archives that hold the bulk of the world's records. The OpenRiC reference implementation **explicitly declines to claim `provenance-event`** in its `$openricConformance.profiles[]` list precisely because of this gap (see [`routes/api.php`](https://github.com/openric/service/blob/main/packages/ahg-ric/routes/api.php)). Any conformant archival service would face the same blocker. The profile, as written, optimises for born-digital and recent-administrative content and effectively excludes pre-1950 archival material from conformance.

This also blocks second-implementation outreach. The Damigos / Ionian and Sparna / Garance pitches both involve archives that would have the same gap — and would correctly decline to claim the profile under the strict reading.

## Proposed resolution

Add to `spec/profiles/provenance-event.md` (in the requirements / SHACL prose):

> A `rico:Production` activity MAY omit `rico:hasOrHadParticipant` when the underlying record (linked via `rico:resultsOrResultedIn`) claims no creator in its source description. Implementations SHOULD emit `openricx:participantUnknown true` (or equivalent machine-readable flag — exact predicate TBD in this issue) on such activities to distinguish "no participant claimable" from "participant not yet curated."

Implications:

1. **Reference service can immediately claim the profile** once the prose lands and `openricx:participantUnknown` is wired into `RicSerializationService::serializeActivity`. Probably a v0.11.0 / spec v0.39.0 cycle.
2. **SHACL shape needs adjustment** — current shape (if any) enforcing `rico:hasOrHadParticipant` on `rico:Production` needs an exception for the `openricx:participantUnknown true` case. Per the existing SPARQL Access SHACL precedent (spec v0.38.0 Wave B), the shape can be additive on top of the base `rico:Production` shape.
3. **Cross-reference with RiC-AG** (spec v0.38.1 §1.2 alignment) — RiC-AG v0.1 does not currently address the "creator unknown" archival case. This is a candidate for joint discussion with EGAD; the proposed predicate could be a v1.0+ openricx → rico upstream proposal if the pattern proves out across multiple implementations.

## Out of scope for this issue

- The `openric_audit_log.user_id` field (who edited the database row) MUST NOT be used to fill in `performed_by`. That's the archivist who typed, not the agent who performed. The reference service's backfill command (v0.9.3) explicitly excludes this path. See the [`production-activity-provenance-gap` memo](https://github.com/openric/service/blob/main/docs/sessions/2026-05-25-v0.9.3-production-participant-backfill.md) for the audit conclusion.

## Related

- Reference-server audit memo: [`docs/sessions/2026-05-25-v0.9.3-production-participant-backfill.md`](https://github.com/openric/service/blob/main/docs/sessions/2026-05-25-v0.9.3-production-participant-backfill.md)
- Backfill command: [`packages/ahg-ric/src/Console/Commands/BackfillProductionParticipants.php`](https://github.com/openric/service/blob/main/packages/ahg-ric/src/Console/Commands/BackfillProductionParticipants.php) (service v0.9.3)
- Profile under discussion: [`spec/profiles/provenance-event.md`](https://github.com/openric/spec/blob/main/spec/profiles/provenance-event.md)
- Source draft: [`docs/issues/provenance-event-creator-unknown.md`](https://github.com/openric/spec/blob/main/docs/issues/provenance-event-creator-unknown.md)
