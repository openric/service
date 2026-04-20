# ahg/ric-model

RiC-CM reference browser for OpenRiC — SPARQL-driven view of the Records in Contexts conceptual model, sourced from the RiC-O ontology.

Provides `OntologyService` and `InheritanceResolver` services that render the 19 entities, 42 attributes, and 84 non-inverse relations of RiC-CM 1.0 with a clean **declared vs. inherited** separation on each entity, modelled on the CIDOC-CRM reference pattern.

## Scope (Phase 1)

- Fuseki-backed `OntologyService` querying the `FUSEKI_DATASET_MODEL` dataset.
- Pure-PHP `InheritanceResolver` (zero Laravel imports — portable).
- Bundled RiC-O v1.1 OWL + CSV component lists under `resources/data/ric-o/`.

HTTP routes, Blade views, and console commands land in Phase 2+ (see `docs/plans/ric-cm-reference-browser.md` in the parent OpenRiC repo).

## Install

This package is a path-repository dependency of OpenRiC. If you're adding it to another Laravel 12 app:

```bash
composer require ahg/ric-model
php artisan vendor:publish --tag=ahg-ric-model-config
```

Configure `.env`:

```
FUSEKI_URL=http://localhost:3030
FUSEKI_DATASET_MODEL=openric-model
FUSEKI_USER=admin
FUSEKI_PASSWORD=admin123
```

Load the ontology (one-off, or after RiC-O updates):

```bash
curl -u "$FUSEKI_USER:$FUSEKI_PASSWORD" -X PUT \
  "$FUSEKI_URL/$FUSEKI_DATASET_MODEL/data?default" \
  -H "Content-Type: application/rdf+xml" \
  --data-binary @vendor/ahg/ric-model/resources/data/ric-o/RiC-O_1-1.rdf
```

See `resources/data/ric-o/loaded-versions.md` for the exact source tag and verification details.

## Commands

```bash
# Clear + warm the SPARQL cache after loading a new ontology.
php artisan ric-model:rebuild-cache
php artisan ric-model:rebuild-cache --model-version=1.0

# Load a new ontology file (default: POST; --replace for PUT).
php artisan ric-model:load-ontology path/to/RiC-O_x-y.rdf \
    --dataset=openric-model \
    --format=rdf+xml
```

## Dev-time workflow gotcha

Composer path repositories with `symlink: false` **mirror** packages into `vendor/`. `composer update ahg/ric-model` does not always re-mirror if it thinks nothing changed. When iterating on this package's source, the reliable resync is:

```bash
rm -rf vendor/ahg/ric-model && composer install
```

## Attribution

This package bundles and queries **Records in Contexts-Ontology (RiC-O) v1.1**, published by the **International Council on Archives, Expert Group on Archival Description (ICA EGAD)** under **[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)**. Upstream: <https://github.com/ICA-EGAD/RiC-O>.

Any application rendering data from this package must credit the ontology accordingly — the config key `ahg-ric-model.attribution.ontology_credit` provides pre-formatted credit text.

## License

AGPL-3.0-or-later (matching the rest of the AHG stack). The bundled RiC-O artifacts retain their original CC BY 4.0 license, compatible with AGPL inputs.
