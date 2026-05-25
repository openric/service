# Outreach-ready drafts

Each file in this directory is a **send-ready** draft for one outreach action:

| # | File | Action | Recipient | Workbench notification |
|---|---|---|---|---|
| 01 | `01-email-sparna-second-implementation.md` | Send email | Thomas Francart / Sparna | B5 |
| 02 | `02-email-damigos-second-implementation.md` | Send email | Matthew Damigos / Ionian University | B6 |
| 03 | `03-issue-egad-hasappraisalinformation.md` | File GitHub issue | `github.com/ICA-EGAD/RiC-O` | B7 |
| 04 | `04-issue-egad-containspersonaldata.md` | File GitHub issue | `github.com/ICA-EGAD/RiC-O` | B7 |
| 05 | `05-issue-egad-contactpoint.md` | File GitHub issue | `github.com/ICA-EGAD/RiC-O` | B7 |
| 06 | `06-issue-openric-spec-provenance-event.md` | File GitHub issue | `github.com/openric/spec/issues` | (drafted today; new) |

## Format

**Email drafts** (01, 02) carry YAML frontmatter with `to:`, `subject:`, and any `cc:` so they can be parsed by an email-creation tool:

```yaml
---
to: ...
subject: ...
cc: ...
status: send-ready as of YYYY-MM-DD
---

<email body — plain-text-ish markdown the recipient can read>
```

**Issue drafts** (03, 04, 05, 06) carry YAML frontmatter with `target:` and `title:`:

```yaml
---
target: github.com/<org>/<repo>/issues/new
title: <issue title>
status: send-ready as of YYYY-MM-DD
---

<issue body — markdown the GitHub renderer will accept>
```

## Status as of 2026-05-25 (Phase A close)

All six are **send-ready**. The prerequisites that previously gated each have all landed:

- ✅ openric-spec v0.38.0 (Wave B: SPARQL Access SHACL + fixtures, outreach drafts, extension proposals)
- ✅ openric-spec v0.38.1 (RiC-AG cross-reference + RiC-CM Nav reconciliation declared)
- ✅ openric-spec v0.38.2 (probe coverage 24 → 29)
- ✅ openric-spec v0.38.3 (SPARQL probe URL-encoding fix + canonical-shape assertion)
- ✅ OpenRiC service v0.9.1 → v0.10.0 (semantic /id/ URIs, detail-endpoint remediation, safe Production-participant backfill, **SPARQL Access profile claimed**)
- ✅ Conformance probe: 30/30 PASS against the live reference server

The original drafts in `openric-spec/docs/outreach/` and `openric-spec/docs/upstream-proposals/` remain the source of truth. The files in this directory are polished, send-ready copies adapted to the post-Phase-A state.

## Send order suggestion

Roughly in priority order, but they're independent:

1. **#03 / #04 / #05** (EGAD issues) — file these first. Filing on `ICA-EGAD/RiC-O` is the lowest-cost action and the issues become part of the public record EGAD's conversation builds on. Cite the live conformant service at `ric.theahg.co.za` as evidence the proposals are battle-tested.
2. **#06** (openric-spec issue) — file on your own repo. Makes the provenance-event profile-prose refinement a public conversation. No external dependency.
3. **#01** (Sparna email) — depends on a live SPARQL endpoint and a written sparql-access profile, both now shipped. Best window: send before mid-June 2026 when Garance v2 lands (workbench notification B2 will surface that).
4. **#02** (Damigos email) — depends on the RiC-CM Nav reconciliation being formally declared, now done in v0.38.1.

## What this directory is NOT

- **Not** the source of truth — the canonical drafts live in `openric-spec/docs/{outreach,upstream-proposals,issues}/`. Edits to those files do not automatically propagate here.
- **Not** automation — there's no robot that reads these files and sends them. The workbench email tool / GitHub UI is operated by a human.
- **Not** a public-facing publication — these documents are unsent drafts. Once sent, the public record of each lives at the recipient's inbox or GitHub issue, not here.

After sending, optionally annotate each file with the `sent:` field in the frontmatter (and the URL of the resulting GitHub issue or thread, if any).
