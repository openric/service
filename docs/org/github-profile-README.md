<!--
  Org-level landing page for github.com/openric.

  This file is the source of truth. To deploy it, copy its contents to the
  ORG's `.github` repo at path `.github/profile/README.md`:

      1. Create (or use existing) repo:  github.com/openric/.github
      2. Put this content at:            .github/profile/README.md
      3. Commit + push.

  GitHub renders that file at the top of github.com/openric automatically.

  Any edits to this landing page should land HERE first (so they're versioned
  alongside OpenRiC's code + plans), then mirrored to the .github repo.
-->

# OpenRiC

**An open, Records-in-Contexts native platform for archives.**

OpenRiC is a clean-room, open-source implementation of the [**Records in Contexts-Ontology (RiC-O)**](https://github.com/ICA-EGAD/RiC-O) — the semantic standard for archival description published by the [ICA Expert Group on Archival Description (EGAD)](https://www.ica.org/ica-network/expert-groups/egad/). It treats RiC-O as the primary data model, not a bolt-on, and ships as a pluggable Laravel 12 service backed by an RDF triplestore.

---

## Why this exists

Archival software has been generation-hopping standards for twenty years: ISAD(G), ISAAR(CPF), EAD, MODS, Dublin Core — each a partial, flat picture of a record's context. **Records in Contexts** (RiC) is ICA's consolidated replacement: a graph-shaped model that describes records, agents, activities, places, rules and the relationships between them as first-class entities.

RiC-O, the OWL expression of RiC-CM, has existed since 2019 and reached v1.1 in 2025. But implementations have mostly been bolted into legacy systems whose internal data model is older and flatter than RiC itself — causing round-trip loss and limiting what the semantic layer can actually do.

**OpenRiC is RiC-native from line one.** Triples are the primary store. Classical archival views (ISAD(G), ISAAR, EAD) are rendered as *lenses* over the RiC graph, not the other way around.

---

## Status

> **v0.8** — early. Foundations and the RiC-CM reference browser are shipping. Public API surface and data model are not yet stable.

Track progress in the main repo's `CHANGELOG.md` and `docs/plans/`.

### Shipped

- **Genuine decoupling from Heratio** — OpenRiC runs standalone with its own vendored copies of `ahg-core`, `ahg-api`, `ahg-ric`. No shared on-disk dependencies. Release flow (`bin/release` + `version.json` + `CHANGELOG.md`) in place.
- **`ahg-ric-model` package — RiC-CM reference browser, feature-complete.** Browsable live at `/reference/ric-cm/` once deployed:
  - Live SPARQL against a dedicated Fuseki dataset loaded with RiC-O v1.1 (CC BY 4.0).
  - 19 entities, 42 attributes, 151 relations, 6 relation attributes.
  - **Clean declared-vs-inherited separation** on every entity, attribute, and relation page — the flagship differentiator, modelled on the CIDOC-CRM reference. Inherited rows are tagged with the ancestor they come from and anchor-link back to its declared section.
  - Versioned URLs (`/reference/ric-cm/1.0/…`) for stable citation; unversioned URLs redirect to the latest.
  - Expandable class hierarchy with `sessionStorage` persistence.
  - Alpine-driven client-side filter on every list, mobile-responsive, print stylesheet, WCAG keyboard focus + skip link + `aria-expanded` throughout.
  - Graceful degradation when the triplestore is unreachable.
- **47 tests, 198 assertions.** Pure-PHP `InheritanceResolver` kept Laravel-free for portability (pending upstream PR to `ric-cm-nav`).

### Next

- **Core RiC CRUD** — 10 entities editable via HTTP, content-negotiated entity IDs (`application/ld+json` vs. HTML), JSON-LD export, SHACL validation.
- **Traditional-view lenses** — ISAD(G), ISAAR-CPF rendered as projections over the RiC graph, with a per-record toggle.
- **Graph visualisation** — Cytoscape.js for both user holdings and the RiC-CM conceptual playground.
- **Semantic search** — Qdrant vector search over entity descriptions.
- **Workflow, ACL, federation** — depending on community priorities.

---

## What's in the stack

| Layer | Technology | Role |
|---|---|---|
| HTTP | Laravel 12 / PHP 8.3 | Application framework |
| Triplestore | Apache Jena Fuseki | Primary RDF store (instance data + loaded ontology) |
| Search | Elasticsearch | Full-text + faceted search over entities |
| Vector search | Qdrant | Embedding-based semantic search |
| Cache | Redis | Query + view cache |
| Frontend | Blade + Alpine.js + Bootstrap 5 | Server-rendered, progressive enhancement |
| Packaging | Composer path repos | Modular plugin architecture |

No heavy SPA. No proprietary extensions. Everything in the data path is RDF; everything rendered is progressively enhanced HTML.

---

## Key ideas

### RiC-native triplestore

User data is stored as RDF triples in Fuseki's `openric` dataset, using the `rico:` namespace directly. There is no parallel relational schema mirroring the triples — SPARQL is the query language.

### Reference browser done right

OpenRiC ships a browsable view of the **RiC-CM 1.0 conceptual model** itself — 19 entities, 42 attributes, 170+ relations, with a clean **declared vs. inherited** separation on every page. Inherited attributes and relations are tagged with the ancestor class they come from, in the style of the [CIDOC-CRM reference](https://cidoc-crm.org/html/cidoc_crm_v7.1.3.html). No silent flattening over subclass hierarchies.

This was a specific response to a real usability problem in other RiC browsers: when a relation declares its domain as `Agent`, they render it as *Agent + Person + Group + Mechanism + Family* — which reads as a semantic claim about the relation rather than a navigation aid.

### Content-negotiated entity IDs

Every entity has a stable URL. Machines asking for `application/ld+json` get JSON-LD; browsers get a human-readable page. IDs are opaque and persistent.

### Plugin architecture

Core OpenRiC runs with every plugin disabled. Each feature — compliance regimes, domain-specific workflows, jurisdiction-specific reporting — ships as its own Composer package implementing documented contracts. Upstream inherits nothing jurisdiction-specific.

---

## Get involved

| | Link |
|---|---|
| Main repository (Laravel service) | [github.com/openric/service](https://github.com/openric/service) |
| Plans, decisions, drafts | [`service/docs/`](https://github.com/openric/service/tree/main/docs) |
| Issue tracker | [`service/issues`](https://github.com/openric/service/issues) |
| Discussions | [`service/discussions`](https://github.com/openric/service/discussions) |
| Contact | `johan@theahg.co.za` |

### Contributing

OpenRiC is actively developed. If you work with archives, ontologies, or linked data and want to help shape a RiC-native implementation, read `docs/plans/` first — there are specific open questions in every phase plan where outside input is welcome. PRs must include tests; new features must be plumbed through the Composer plugin seam, not the core.

---

## Related projects

- **[RiC-O (ICA-EGAD)](https://github.com/ICA-EGAD/RiC-O)** — the ontology OpenRiC implements. Published under CC BY 4.0. OpenRiC bundles RiC-O v1.1 as the loaded model; all reference browser output credits ICA/EGAD per the license.
- **[RiC-CM NavTool (DLIB, Ionian University)](https://github.com/DLIB-Ionian-University/ric-cm-nav)** — independent Vue SPA browsing RiC-CM. Inspired OpenRiC's reference browser; an upstream contribution is pending.
- **[Heratio](https://github.com/ArchiveHeritageGroup/heratio)** — the broader GLAM platform from the same organisation. In the current architecture Heratio has its own RiC code; the roadmap migrates it to consuming OpenRiC's HTTP API, making OpenRiC the authoritative RiC-O endpoint across the stack.

---

## License

**AGPL-3.0-or-later.**

Bundled ontology data from ICA-EGAD retains its original **CC BY 4.0** licence, which is compatible with AGPL inputs. Any deployment or fork must preserve the ICA attribution rendered by `ahg-ric-model` — see [`RiC-O/ontology/Readme.md`](https://github.com/ICA-EGAD/RiC-O/blob/master/ontology/Readme.md) upstream.

---

## Maintainer

**The Archive and Heritage Group (Pty) Ltd**
Johan Pieterse — [`johan@theahg.co.za`](mailto:johan@theahg.co.za)
