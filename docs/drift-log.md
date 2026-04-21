# Drift log — forked shared packages

OpenRiC vendors `ahg-core`, `ahg-api`, and `ahg-ric` into its own `packages/` dir. The upstream source of these packages remains Heratio (`/usr/share/nginx/heratio/packages/`) until Heratio switches to consuming RiC features via OpenRiC's HTTP API — at which point the Heratio copies become retired.

This log tracks every cherry-pick or drift event between the two trees, so we can reason about what lives where and roll back cleanly if needed. Every bug fix that touches a shared package lands an entry here at commit time (per `project_architecture_decisions.md` memory rule).

## How to use

- **Entry format** (one per line, newest at top under the current month):
  `YYYY-MM-DD | direction | files | heratio-SHA | openric-SHA | note`
  - `direction`: `heratio→openric` (pull a fix from heratio), `openric→heratio` (push a fix upstream), `divergence` (intentional split — document why).
- **When to log**:
  - Any `cp`/`patch` between the trees.
  - Any intentional divergence (OpenRiC strips a feature, OpenRiC fixes a bug not yet in Heratio).
  - Any namespace/interface change on either side (these will block the Heratio-via-API handover).

## Entries

### 2026-04

- **2026-04-21** | `divergence` | `packages/ahg-ric/src/Http/Controllers/LinkedDataApiController.php`, `packages/ahg-ric/src/Support/ProblemDetails.php` (new), `packages/ahg-ric/src/Support/OpenApiSpec.php` | heratio@unchanged | openric@v0.8.11 (pending) | **Intentional divergence to resolve spec Q6 (RFC 7807).** OpenRiC's `/api/ric/v1/*` surface migrates from `['error' => '…']` + HTTP status to `application/problem+json` with canonical RFC 7807 body shape, ahead of the openric-spec v0.3 freeze. Heratio's copy remains on the v0.2 `{error}` shape per the "RiC before Heratio cleanup" ordering rule (see `feedback_ric_before_heratio_split.md`). Direction for any future reconciliation on this surface: `openric→heratio`, not the default `heratio→openric`. Scope: ~52 error-return sites in `LinkedDataApiController.php`; legacy `RicController.php` admin endpoints (25× `{success:false}` sites) are NOT migrated — they are out of spec scope.
- **2026-04-20** | `fork` | `packages/ahg-core`, `packages/ahg-api`, `packages/ahg-ric` | heratio@`d5160da7e07be50ac0c4e3fa4b8097d5c0714ea8` (v0.116.3) | openric@`0c78637` (Phase 0 feature commit); tagged as `v0.2.0` (`ec1e0fc`) | Initial vendor fork. All three packages copied intact from Heratio — no pruning, no namespace changes. OpenRiC's copy becomes the authoritative source for RiC concerns from this point. Heratio's copy continues to evolve until Heratio transitions to API consumption.
