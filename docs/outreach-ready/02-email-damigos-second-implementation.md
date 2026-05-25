---
to: damigos@ionio.gr
subject: OpenRiC conformance probe + the two RiC-CM navigators question
attn: Matthew Damigos (Laboratory on Digital Libraries and Electronic Publishing, Ionian University)
cc:
status: send-ready as of 2026-05-25
related-notification: workbench/B6
source-of-truth: openric-spec/docs/outreach/damigos-second-implementation.md
---

Hi Matthew,

I work on [OpenRiC](https://openric.org) — an open, implementation-neutral HTTP/API contract for RiC-aligned archival data, with a spec + reference Laravel API + viewer + capture client + conformance probe, all CC-BY/AGPL. We just completed full RiC-O 1.1 conformance remediation (spec v0.37.x, service v0.10.0 — zero non-canonical `rico:*` emissions remain, 30/30 conformance probe).

Two reasons I'm writing.

**1. The Corfu Criminal Court Archives application is the closest production RiC-CM deployment I've found to a candidate second implementation of OpenRiC.** Our v1.0 freeze is gated on at least one non-reference implementation passing the conformance probe. Looking at your published work (the [2025-09 Corfu RiC-CM deployment](https://dlib-ionian-university.github.io/), the [2023 ICADL paper on RiC-CM ↔ CIDOC-CRM mapping](https://link.springer.com/), and the [2026 RiC-CM Nav](https://dlib-ionian-university.github.io/ric-cm-nav/)), your group is one of the few outside the EGAD core teams that's built sustained RiC-CM tooling at production scale.

The OpenRiC conformance probe is pure shell + jq (no install). The minimum bar is the [`core-discovery` profile](https://openric.org/spec/profiles/core-discovery.html) — read-only access to records, agents, repositories, vocabulary, autocomplete, plus a few SHACL shapes. **I'm not asking for a commitment** — but if your Corfu archives system already exposes records, agents, and a vocabulary endpoint over HTTP (in any shape), we could probably probe it today and tell you whether the gap to OpenRiC `core-discovery` is small or significant. If small, adopting the profile is a low-effort visibility win for both of us.

A quick demo of the probe in action against our reference server:

```
git clone https://github.com/openric/spec && cd spec/conformance
BASE=https://ric.theahg.co.za/api/ric/v1 ./probe.sh
# → ✓ Conformant. 30/30.
```

The same `BASE=` pointed at your Corfu service would tell us where the gaps are in a few seconds.

**2. The two RiC-CM navigators — relationship now declared.** Your group ships [RiC-CM Nav](https://dlib-ionian-university.github.io/ric-cm-nav/), listed on the official ICA RiC ResourceList. We ship our own RiC-CM browser at [`ric.theahg.co.za/reference/ric-cm/`](https://ric.theahg.co.za/reference/ric-cm/) (SPARQL-backed against the canonical RiC-O 1.1 ontology, with declared-vs-inherited separation per CIDOC-CRM-style page browsers).

In our spec v0.38.1 (this week) we formally declared the relationship as **complementary, not competing** — different reader needs (spec-version reference vs conceptual graph traversal), no deprecation, no fork, either tool can cite the other. See the [drift-log entry](https://openric.org/drift-log.html#closed-recent) and the [Related Implementations page](https://openric.org/related-implementations.html#damigos--ionian-university--ric-cm-nav-and-the-corfu-archives). I wanted to flag this to you rather than just publish it — your reaction is what matters here, and if the framing is wrong from your side I'd like to know.

A separate but related question: your 2023 ICADL paper on the RiC-CM ↔ CIDOC-CRM mapping is the basis for what OpenRiC is calling [`OpenRiC-CIDOC-Bridge`](https://github.com/openric/spec/blob/main/docs/extensions/openric-cidoc-bridge.md) — a post-v1.0 extension proposal to express RiC-O graphs in CIDOC-CRM-equivalent form for museum interoperability. **We have NOT done the mapping work** — it would be straightforwardly built on yours, with attribution. If you'd be open to that being framed as a joint Ionian / OpenRiC extension, I think it would land more credibly with both the archive and the museum communities.

Happy to start with whichever of the three threads feels easiest. A 20-minute call works for me; equally happy with line-by-line over GitHub on `openric/spec`.

— Johan Pieterse
[openric.org](https://openric.org) · [github.com/openric/spec](https://github.com/openric/spec) · [ric.theahg.co.za](https://ric.theahg.co.za/)
