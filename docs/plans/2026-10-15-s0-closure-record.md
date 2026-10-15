# OpenRiC S0 closure record

Programme plan V4 (14 October 2026), Phase S0: secure, preserve, and verify. Incident: anonymous writes to production through `OPENRIC_OPEN_WRITE` (CallHub CH-000273).

| Field | Value |
|---|---|
| Record date | 15 October 2026 |
| Prepared by | OpenRiC session, for Dr Johan Pieterse |
| Approver | Dr Johan Pieterse (Standard owner; interim security and operations owner) |
| Systems | Reference service ric.theahg.co.za (openric/service); shared `heratio` database; Heratio (heratio.org); openric.org, capture.openric.org |
| Status | Closed. Signed off 15 October 2026; all four conditions met the same day |

## Acceptance evidence by item

### S0.1 - Open write is off

- `OPENRIC_OPEN_WRITE=false` in the service `.env` since 14 October; the flag is read from config since service v0.17.1.
- Observed externally after every service release on 15 October (v0.17.3 to v0.17.6): a `POST /api/ric/v1/places` without a key returns 401.
- Build at closure: service v0.17.6 (tag `ddc0f3c`).
- Not recorded: table counts before and after. The purge on 15 October (S0.3) changed them deliberately, so a before-and-after comparison no longer applies.

### S0.2 - Evidence preserved

- Incident folder `/var/lib/openric-incidents/2026-10-14-open-write/`, outside the web roots and not readable by the developer account.
- Database backup `/var/backups/openric-s0/heratio-2026-10-14.sql.gz`, SHA-256 `4d855a9d...41387` (full hash in the evidence register), restore-tested on 14 October: the restore matched.
- Custodian: Dr Johan Pieterse.
- Gap: no retention period has been set for the folder or the backup.

### S0.3 - The 37 tracking entries

- All 37 reconciled on 14 October: 36 synthetic entities from the public wizard, and one record, "Audio_001".
- Propagation: five agents had been published; they were unpublished and reindexed on 14 October.
- Notification assessment: no privacy or contractual notification. Decision by Dr Johan Pieterse, 14 October; the assessment is recorded in KM.
- Data-owner decision: removal approved by Dr Johan Pieterse, 15 October. It was blocked until heratio-dev repaired a Heratio term-id collision (terms 914228 and 914229).
- Removal: `php artisan openric:purge-open-write`, run by Dr Johan Pieterse on 15 October. The first run deleted 35. Records 914142 and 914231 had descendants; once those were removed, a second run deleted both. Final check: `openric_open_write` = 0, and objects 914142, 914231, 914228 and 914229 = 0.
- The demo snapshot was retaken after the purge, so the nightly 02:20 restore of the whole `heratio` database keeps the clean state.
- Independent check: `GET /api/ric/v1/entities/{id}/info` returned 404 for all 37 ids, and 200 for a control entity.
- Gap in the source-to-projection matrix: the Heratio catalogue and the public API are verified; the Fuseki graph and OAI-PMH were not checked separately. Re-check due after the 16 October 02:20 reset.

### S0.4 - Authorization on destructive operations

- Reference service: v0.17.1 and v0.17.2 require the `delete` scope on every RiC DELETE route, enforced per route so that users can still revoke their own keys. The legacy write routes now need a key.
- Tests: `ApiSecurityTest` (11 tests). Keyless, wrong-scope, revoked and expired keys are refused; a write-only key cannot delete.
- Staging: the Round-Trip Editing write probe ran against a disposable copy of the database on 15 October, 17 of 17 passing, including 401 without a key and 403 for a delete with a write-only key. The staging database was dropped afterwards.
- Heratio's separate release line: v1.159.29, all seven RiC DELETE routes require `api.auth:delete`, covered by `ApiScopeGatesTest`. Reported by heratio-dev.
- Rollback proof: a `vendor/` rollback archive was taken before each service release. They sit in a session scratch directory, which is not durable storage (see the conditions).
- Gap: cross-institution and restricted-record attempts were not tested. The reference service has no multi-institution model and no restricted records, so there is nothing to attempt. This becomes a requirement for the Workspace (Part II, multi-tenancy as a security architecture).

### S0.5 - Keys, CORS, CSP, browser tokens

- Revocation: `php artisan openric:revoke-key` (v0.17.1).
- CORS: reads open to every origin; writes answer only allow-listed origins, with `Vary: Origin` (v0.17.1).
- CSP: report-only from 14 October, enforcing from 15 October (condition 1). Before the switch, both HTML pages the service serves (`/` and `/api/ric/v1/docs`) showed no violations in a browser; after it, both loaded with nothing blocked.
- Browser keys: on 15 October Browse, the API explorer, Capture and the service's docs page all moved to keeping the API key for the browser session only, removing any copy an earlier version left in `localStorage` (conditions 2 to 4). Only the server URL and view settings stay in `localStorage`.
- Security review: see the exception below.

### S0.6 - Public wizard

- Since spec v0.43.14 the wizard no longer saves to the public reference service, and says so. It can copy the modelled requests to the clipboard.

## Exception: no second-person review

The plan requires the S0 changes to be reviewed by a second person. None is available: Dr Johan Pieterse holds the Standard owner, reviewer, and security and operations owner roles for now (plan V4 §II.10).

- **Exception granted** by Dr Johan Pieterse, 15 October 2026.
- **Compensating controls:**
  - every service change went through tests and a staging probe, with read-only verification on the live service after each deploy;
  - each release was approved individually;
  - heratio-dev, a separate session working on the Heratio line, independently checked the Heratio side and the term repair.
- **Retrospective review required:** the S2.5 external technical review must also cover the S0 security changes (service v0.17.1 to v0.17.7, Heratio v1.159.29, the CSP, and the key storage). That is due before the S-GATE.

## Conditions of sign-off

| # | Condition | Status |
|---|---|---|
| 1 | CSP switched from report-only to enforcing on ric.theahg.co.za | Met 15 October: `Content-Security-Policy` header live; `/` and `/api/ric/v1/docs` load with nothing blocked. The report-only file is kept for rollback |
| 2 | Service docs page (`/api/ric/v1/docs`): key held for the session only; Swagger UI pinned with integrity hashes (service v0.17.7) | Met 15 October: v0.17.7 (tag `f7e7f81`) live; three integrity hashes, no unpkg or localStorage reads |
| 3 | openric.org Browse and API explorer: session-only keys and pinned Swagger UI, released ahead of the held stack as a security-only change | Met 15 October: openric/spec #28 merged (`b165793`) after CI passed; verified live |
| 4 | capture.openric.org: session-only key (openric/capture #1) merged | Met 15 October: merged and verified live |

## Before the S-GATE (not conditions of S0)

| Item | Status |
|---|---|
| Fuseki graph and OAI-PMH no longer carry the 37 | Done 15 October: a SPARQL query over the public graph (7,192 triples, 2,197 entities) for all 37 ids as subject or object found none; a full OAI-PMH harvest (420 identifiers) found none. |
| Release rollback archives moved into the incident folder | Done 15 October, run by Dr Johan Pieterse: the ten archives for v0.17.1 to v0.17.9 and `rollback-SHA256SUMS` copied to `rollbacks/` in the incident folder, the copy checked with `sha256sum -c`. Archives for later releases are added the same way. |
| Retention period for the evidence | Proposed, awaiting Dr Johan Pieterse: keep this record, the evidence register, hashes and rollback archives for five years, until 15 October 2031, then review. Delete the full database backup (`heratio-2026-10-14.sql.gz`) 90 days after the S-GATE, because it is a complete copy of the shared database and is needed only for rollback. |

## Sign-off

S0 accepted by Dr Johan Pieterse, 15 October 2026. All four conditions were met on 15 October 2026, so S0 is closed.
