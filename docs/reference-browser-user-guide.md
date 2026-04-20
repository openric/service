# RiC-CM reference browser — user guide

OpenRiC ships a live, SPARQL-driven browser for the **Records in Contexts Conceptual Model** at `/reference/ric-cm/`. It lets archivists, cataloguers, and developers look up the definition, attributes, relations, and inheritance structure of any RiC-CM entity without leaving the application.

This page explains how to use it.

---

## Where to find it

- **Latest version**: `/reference/ric-cm/` (currently redirects to `/reference/ric-cm/1.0/`).
- **Specific version** (stable for citation): `/reference/ric-cm/{version}/` — e.g. `/reference/ric-cm/1.0/entities/RiC-E04`.

Four top-level sections are exposed in the nav bar:

| Section | URL | Count |
|---|---|---|
| Entities | `/reference/ric-cm/{version}/entities` | 19 |
| Attributes | `/reference/ric-cm/{version}/attributes` | 42 |
| Relations | `/reference/ric-cm/{version}/relations` | 151 (incl. inverses) |
| Relation attributes | `/reference/ric-cm/{version}/relation-attributes` | 6 |

Every list has a client-side filter (name, ID, definition, domain/range) that narrows the rows on keystroke — 150 ms debounce, no server round-trip.

---

## Declared vs. inherited — the distinction that matters

This is the single most important feature of the browser, and where it differs from other RiC-CM navigators.

On any **entity page** — say [Record (RiC-E04)](#) — you'll see **four separate sections**:

1. **Declared attributes** — attributes whose `rdfs:domain` is *this specific class*.
2. **Declared relations** — relations whose declared `rdfs:domain` is *this specific class*.
3. **Inherited attributes** (collapsed) — attributes declared on an ancestor class. Each row is tagged `(from RecordResource)` with a link that jumps to the `#declared-attributes` anchor on the ancestor's page, so you can see exactly where the attribute was introduced.
4. **Inherited relations** (collapsed) — same pattern for relations.

### Why this matters

Earlier RiC-CM browsers conflate these. For example, when a relation declares its domain as `Agent`, some tools render the domain as *Agent + Person + Group + Mechanism + Family* — which reads as a semantic claim about the relation's domain rather than a navigation aid. **OpenRiC never flattens like this.** A relation's declared domain is shown as the single class the ontology actually specifies, and subclasses are surfaced under a clearly-labelled "Subclasses (browsing aid)" panel that reinforces the distinction.

### Quick mental model

> "Declared" = *what the RiC-O ontology explicitly says about this class.*
> "Inherited" = *what this class gets because it's a subclass of something else, with an explicit pointer back to the source.*
> "Subclasses covered" = *a navigation convenience — not part of the semantic definition.*

---

## Relation pages

On any relation page (e.g. `/reference/ric-cm/1.0/relations/RiC-R016`) you get:

- Declared **Domain** and **Range** as single linked entities. No flattening.
- **Inverse** pairing (RiC-O uses `owl:inverseOf`), linked.
- **Hierarchy** (Broader/Narrower) via `rdfs:subPropertyOf`, when present.
- A "Subclasses covered" expander that shows descendants of the declared domain/range — as a **navigation aid only**, labelled as such in the UI.

---

## Attribute pages

On any attribute page (e.g. `/reference/ric-cm/1.0/attributes/RiC-A22`) you get:

- The list of entities the attribute is **declared on**.
- A "Also applies to (inherited by)" expander listing all entity **subclasses** that inherit the attribute, each tagged with the class it inherits from. This is the mirror image of the entity page's inherited-attributes section — the same relationship viewed from the attribute's side.

---

## Accessibility

- Every expand/collapse toggle exposes `aria-expanded`.
- Filter input is wrapped in `role="search"` with `aria-label`.
- Visible keyboard focus ring on every interactive element (WCAG 2.4.7).
- `Skip to content` link appears on first Tab.
- The hierarchy tree uses native `<details>` / `<summary>`, so it works with screen readers and keyboard navigation out of the box; open state persists to `sessionStorage`.
- Print stylesheet hides filters and navbar, expands collapsed sections, and appends link URLs so printed pages stand alone.

---

## Versioning

URLs under `/reference/ric-cm/{version}/` are **stable**. A citation like `/reference/ric-cm/1.0/entities/RiC-E04` will continue to resolve correctly as long as v1.0 remains in the shipped version list, even when newer versions arrive. Unversioned URLs (`/reference/ric-cm/entities/RiC-E04`) always redirect to the latest version.

When RiC-CM 2.x ships, it will coexist — v1.0 links stay; v2.0 becomes the new unversioned-URL target.

---

## Data source and attribution

Content is queried live from a local Apache Jena Fuseki triplestore loaded with **RiC-O v1.1** (released 2025-05-27 by ICA EGAD). The exact SHA of the loaded ontology is recorded in `packages/ahg-ric-model/resources/data/ric-o/loaded-versions.md`.

**RiC-O is licensed under CC BY 4.0.** Every page in the browser renders an attribution footer crediting:

> Records in Contexts-Ontology (RiC-O) v1.1 is published by the International Council on Archives, Expert Group on Archival Description (ICA EGAD), under CC BY 4.0.

If you reuse screenshots or excerpts, preserve this attribution per the licence.

---

## Administration

### Cache

Reference data is cached for 24 hours. To clear and warm:

```bash
php artisan ric-model:rebuild-cache                    # all versions
php artisan ric-model:rebuild-cache --model-version=1.0  # one version
```

### Loading a newer RiC-O release

When ICA publishes a new RiC-O version:

```bash
curl -L -o RiC-O-new.rdf \
    https://raw.githubusercontent.com/ICA-EGAD/RiC-O/master/ontology/current-version/RiC-O_1-2.rdf

php artisan ric-model:load-ontology \
    RiC-O-new.rdf \
    --dataset=openric-model \
    --replace
```

Then update `packages/ahg-ric-model/resources/data/ric-o/loaded-versions.md` with the new tag/SHA and run `ric-model:rebuild-cache`.

### When Fuseki is unreachable

Every route degrades gracefully to a `503 Service Unavailable` page that describes the configuration and suggests diagnostic steps. It never 500s or shows a blank page.

---

## Known limitations

- **Relation counts are lower than some older RiC-CM tools report** (151 incl. inverses in RiC-O v1.1, vs. 218 in older NavTool data). This reflects the RiC-O OWL's consolidated relations, not an indexing gap — we surface what the ontology actually provides.
- **Lighthouse accessibility score** has not been audited automatically in this environment. Manual review suggests good conformance, but a local audit on your deployment is recommended before production use.
- **Relation attributes** (RiC-RA01–RA06) are currently sourced from the RiC-CM 1.0 document text, not from RiC-O OWL, because v1.1 does not expose them as a distinct annotation category. This switches to SPARQL automatically when upstream adds support.
