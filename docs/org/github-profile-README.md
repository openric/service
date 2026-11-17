<!--
  The landing page of github.com/openric, rendered from openric/.github at profile/README.md.
  This copy, in the OpenRiC service repository at docs/org/github-profile-README.md, is the one to edit;
  then copy it there. Release numbers come from live badges, so they need no editing.
-->

# OpenRiC

**Records in Contexts, open: a specification, and open-source software that implements it.**

[Records in Contexts](https://www.ica.org/standards/RiC/ontology) (RiC) is the International Council on Archives' model for describing records: records, agents, activities, places, rules and dates as entities of their own, and the relations between them as statements in their own right, where ISAD(G), ISAAR(CPF) and EAD give a flatter, partial picture. OpenRiC is an open specification of how RiC-CM and RiC-O data is described, served over HTTP, read, written and checked, with software that implements it and tests itself against it.

Everything here is maintained by Dr Johan Pieterse, The Archive and Heritage Digital Commons Group, at [openric.org](https://openric.org).

## The repositories

| Repository | What it is | Release |
|---|---|---|
| **[spec](https://github.com/openric/spec)** | The OpenRiC specification: the mapping to RiC-CM 1.0.1 and RiC-O 1.1, the HTTP API, twelve conformance profiles, JSON schemas, SHACL shapes, fixtures, a conformance probe and an API explorer. Published at [openric.org](https://openric.org). | ![spec](https://img.shields.io/github/v/tag/openric/spec?label=&sort=semver) |
| **[workspace](https://github.com/openric/workspace)** | The OpenRiC Workspace: an archival management application on RiC, for an archive starting fresh. Describing, accessions, rights and embargoes, preservation (OCFL, PREMIS, fixity), search, a public catalogue, OAI-PMH, IIIF, EAD, EAC-CPF and RiC-O in and out, an audit log that is chained and signed. Demonstration at [workspace.openric.org](https://workspace.openric.org). | ![workspace](https://img.shields.io/github/v/tag/openric/workspace?label=&sort=semver) |
| **[service](https://github.com/openric/service)** | The reference API service: the OpenRiC HTTP API over an RDF triplestore (Apache Jena Fuseki), with SPARQL and SHACL, and a browser of RiC-CM itself that keeps declared and inherited properties apart. Running at [ric.theahg.co.za](https://ric.theahg.co.za). | ![service](https://img.shields.io/github/v/tag/openric/service?label=&sort=semver) |
| **[viewer](https://github.com/openric/viewer)** | `@openric/viewer`: a 2D and 3D graph viewer for any server that implements the OpenRiC Viewing API. | ![viewer](https://img.shields.io/github/v/tag/openric/viewer?label=&sort=semver) |
| **[capture](https://github.com/openric/capture)** | `@openric/capture`: a data-entry tool that runs in the browser alone and writes records, agents, places, rules, activities, instantiations and relations to any server that implements the Write API. Live at [capture.openric.org](https://capture.openric.org). | |

[Heratio](https://github.com/ArchiveHeritageGroup/heratio), the GLAM platform of the same group, implements the specification too; the Workspace takes much of its code from Heratio, rewritten onto RiC, and stands on its own.

## Where things stand

- **The specification** is at version 0.58: seven profiles normative, five in draft. Its changes are in [CHANGELOG.md](https://github.com/openric/spec/blob/main/CHANGELOG.md).
- **The Workspace** is in development and not yet released for use: install it to try it, not to hold an archive's only copy of anything. RiC-CM 1.0.1 and RiC-O 1.1 are its model; ISAD(G), ISAAR(CPF), ISDIAH, ISDF, EAD and EAC-CPF are views of it and formats in and out. Its OpenRiC profiles are tested in every release. Two outside assessments in November 2026 found it ready for demonstrations and pilot preparation, not yet for an institution's authoritative records. What it still needs (a tested restore, the staff side at 100,000 records, adversarial security tests, an outside installer, security tester and archivist) is on its [roadmap](https://github.com/openric/workspace/blob/main/ROADMAP.md), and every decision is on [openric.org](https://openric.org/application/decisions/).
- **The service** is the reference implementation of the API, with its own [plans](https://github.com/openric/service/tree/main/docs/plans).

## How it is built

| | Workspace | Service |
|---|---|---|
| Application | Laravel 13, PHP 8.3 | Laravel 12, PHP 8.3 |
| Store | PostgreSQL 16: the RiC entities and their relations as tables, every change audited and signed | Apache Jena Fuseki: RDF triples |
| Search | PostgreSQL full text, close matches (pg_trgm) and search by meaning (pgvector) | SPARQL |
| Files | An OCFL storage root, PREMIS events, scheduled fixity | |
| RDF out | RiC-O as JSON-LD, Turtle and RDF/XML; an optional read-only SPARQL endpoint (Fuseki) | Native |
| Install | `bin/install` on Ubuntu 24.04, or Docker | Composer, as its README describes |

No single-page application and no proprietary dependency: server-rendered pages, progressively enhanced; the Workspace's are tested for WCAG 2.2 AA in every release.

## Taking part

| | |
|---|---|
| The specification and its open questions | [openric.org](https://openric.org) and [spec issues](https://github.com/openric/spec/issues) |
| The Workspace | [workspace issues](https://github.com/openric/workspace/issues), [INSTALL.md](https://github.com/openric/workspace/blob/main/INSTALL.md) |
| The service | [service issues](https://github.com/openric/service/issues) |
| Contact | johan@theahg.co.za |

If you work with archives, RiC or linked data, the specification's draft profiles and the Workspace's roadmap both say where outside review is wanted. Contributions come with tests.

## Licences

The software is under the **GNU Affero General Public License 3.0 or later**; the specification's text under **CC BY 4.0**. RiC-O, which the specification and the software bundle, is ICA-EGAD's under CC BY 4.0, and every deployment keeps its attribution ([RiC-O](https://github.com/ICA-EGAD/RiC-O)).
