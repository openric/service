<?php

declare(strict_types=1);

namespace AhgRicModel\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * RiC-CM reference data, sourced from RiC-O via SPARQL against Fuseki.
 *
 * Public API returns normalised PHP arrays for list + detail views. Detail
 * results carry the declared/inherited split computed by InheritanceResolver —
 * no flattening over descendant classes.
 *
 * All reads are cached; bust via `artisan ric-model:rebuild-cache`.
 */
class OntologyService
{
    private Client $http;

    /**
     * @param array<int, string> $versions available model versions (e.g. ['1.0'])
     */
    public function __construct(
        private readonly string $fusekiUrl,
        private readonly string $dataset,
        private readonly string $ricoNamespace,
        private readonly ?string $user,
        private readonly ?string $password,
        private readonly int $timeout,
        private readonly ?string $cacheStore,
        private readonly int $cacheTtl,
        private readonly string $cachePrefix,
        private readonly array $versions,
        private readonly string $latestVersion,
        private readonly InheritanceResolver $resolver,
    ) {
        $auth = $this->user !== null && $this->user !== ''
            ? [$this->user, $this->password ?? '']
            : null;

        $this->http = new Client([
            'base_uri' => rtrim($this->fusekiUrl, '/') . '/',
            'timeout'  => $this->timeout,
            'auth'     => $auth,
        ]);
    }

    /** @return array<int, string> */
    public function listAvailableVersions(): array
    {
        return $this->versions;
    }

    public function latestVersion(): string
    {
        return $this->latestVersion;
    }

    /**
     * All 19 RiC-CM entities with id, uri, label, description, parents[].
     *
     * @return array<int, array{id: string, uri: string, name: string, definition: ?string, parents: array<int, string>}>
     */
    public function listEntities(?string $version = null): array
    {
        return $this->cached($this->key('entities', $version), function () {
            $rows = $this->sparqlSelect($this->entitiesQuery());
            foreach ($rows as &$row) {
                $row['parents']    = $this->splitList($row['parents'] ?? null);
                $row['scopeNotes'] = $this->splitMulti($row['scopeNotes'] ?? null);
                $row['examples']   = $this->splitMulti($row['examples']   ?? null);
            }
            return $rows;
        });
    }

    /**
     * Entity detail: core fields + declared/inherited split via resolver.
     *
     * @return array{
     *     entity: array<string, mixed>,
     *     declaredAttributes: array<int, array<string, mixed>>,
     *     inheritedAttributes: array<int, array<string, mixed>>,
     *     declaredRelations: array<int, array<string, mixed>>,
     *     inheritedRelations: array<int, array<string, mixed>>,
     *     ancestors: array<int, array{id: string, name: string}>,
     *     descendants: array<int, array{id: string, name: string}>
     * }|null
     */
    public function getEntity(string $id, ?string $version = null): ?array
    {
        $entities   = $this->listEntities($version);
        $entity     = $this->findById($entities, $id);
        if ($entity === null) {
            return null;
        }

        $attributes = $this->listAttributes($version);
        $relations  = $this->listRelations($version);

        $resolved = $this->resolver->resolve($id, $entities, $attributes, $relations);

        return [
            'entity'              => $entity,
            'ancestors'           => $resolved['ancestors'],
            'descendants'         => $this->resolver->descendants($id, $entities),
            'declaredAttributes'  => $resolved['declaredAttributes'],
            'inheritedAttributes' => $resolved['inheritedAttributes'],
            'declaredRelations'   => $resolved['declaredRelations'],
            'inheritedRelations'  => $resolved['inheritedRelations'],
        ];
    }

    /**
     * All 42 RiC-CM attributes with id, uri, label, definition, domain[].
     *
     * @return array<int, array{id: string, uri: string, name: string, definition: ?string, domain: array<int, string>}>
     */
    public function listAttributes(?string $version = null): array
    {
        return $this->cached($this->key('attributes', $version), function () {
            $rows = $this->sparqlSelect($this->attributesQuery());
            foreach ($rows as &$row) {
                $row['domain']     = $this->splitList($row['domain'] ?? null);
                $row['scopeNotes'] = $this->splitMulti($row['scopeNotes'] ?? null);
                $row['examples']   = $this->splitMulti($row['examples']   ?? null);
            }
            return $rows;
        });
    }

    /** @return array<string, mixed>|null */
    public function getAttribute(string $id, ?string $version = null): ?array
    {
        $all = $this->listAttributes($version);
        $row = $this->findById($all, $id);
        if ($row === null) {
            return null;
        }
        $entities = $this->listEntities($version);

        $domainEntities = [];
        $inheritedBy    = [];
        $seenDescendant = [];

        foreach ($row['domain'] as $eid) {
            $entity = $this->findById($entities, $eid);
            if ($entity === null) {
                continue;
            }
            $domainEntities[] = ['id' => $entity['id'], 'name' => $entity['name']];
            // Each descendant of this declared-on entity inherits the attribute.
            // Dedupe across multiple declared-on entities.
            foreach ($this->resolver->descendants($eid, $entities) as $desc) {
                if (!isset($seenDescendant[$desc['id']])) {
                    $seenDescendant[$desc['id']] = true;
                    $inheritedBy[] = [
                        'id'            => $desc['id'],
                        'name'          => $desc['name'],
                        'inheritedFrom' => ['id' => $entity['id'], 'name' => $entity['name']],
                    ];
                }
            }
        }

        $row['domainEntities'] = $domainEntities;
        $row['inheritedBy']    = $inheritedBy;
        return $row;
    }

    /**
     * All RiC-CM relations (canonical + inverses) with id, uri, label, domain, range.
     * Domain/range are SINGLE entity IDs — never flattened over descendants.
     *
     * @return array<int, array{id: string, uri: string, name: string, definition: ?string, domain: ?string, range: ?string, inverseOf: ?string}>
     */
    public function listRelations(?string $version = null): array
    {
        return $this->cached($this->key('relations', $version), function () {
            $rows = $this->sparqlSelect($this->relationsQuery());
            foreach ($rows as &$row) {
                // domain/range are single-valued — do not split.
                $row['scopeNotes'] = $this->splitMulti($row['scopeNotes'] ?? null);
                $row['examples']   = $this->splitMulti($row['examples']   ?? null);
            }
            return $rows;
        });
    }

    /** @return array<string, mixed>|null */
    public function getRelation(string $id, ?string $version = null): ?array
    {
        $all = $this->listRelations($version);
        $row = $this->findById($all, $id);
        if ($row === null) {
            return null;
        }
        $entities = $this->listEntities($version);

        // Resolve domain + range to {id, name}. Single entries — no expansion.
        if ($row['domain'] !== null) {
            $entity = $this->findById($entities, $row['domain']);
            $row['domainEntity'] = $entity !== null ? ['id' => $entity['id'], 'name' => $entity['name']] : null;
        }
        if ($row['range'] !== null) {
            $entity = $this->findById($entities, $row['range']);
            $row['rangeEntity'] = $entity !== null ? ['id' => $entity['id'], 'name' => $entity['name']] : null;
        }

        // Browsing aids — descendants of the declared domain/range.
        $row['domainDescendants'] = $row['domain'] !== null ? $this->resolver->descendants($row['domain'], $entities) : [];
        $row['rangeDescendants']  = $row['range']  !== null ? $this->resolver->descendants($row['range'],  $entities) : [];

        // Inverse relation, if any, resolved to a link-ready struct.
        if (!empty($row['inverseOf'])) {
            $inv = $this->findById($all, $row['inverseOf']);
            $row['inverseRelation'] = $inv !== null
                ? ['id' => $inv['id'], 'name' => $inv['name'] ?? $inv['id']]
                : ['id' => $row['inverseOf'], 'name' => $row['inverseOf']];
        } else {
            $row['inverseRelation'] = null;
        }

        // Broader (rdfs:subPropertyOf) / narrower (reverse) — scoped to canonical
        // RiC-CM relations only (other properties with RiC-R markers).
        $row['broader']  = $this->fetchSubPropertyGraph($row['uri'] ?? '', 'broader');
        $row['narrower'] = $this->fetchSubPropertyGraph($row['uri'] ?? '', 'narrower');

        return $row;
    }

    /**
     * Find canonical RiC-CM relations that are directly broader than or
     * narrower than the given one via rdfs:subPropertyOf. "Broader" = this
     * URI's super-properties; "narrower" = properties that declare this URI
     * as their super-property. Filtered to RiC-R-marked properties only so
     * internal helper properties don't leak into the UI.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function fetchSubPropertyGraph(string $uri, string $direction): array
    {
        if ($uri === '') {
            return [];
        }

        $ns = $this->ricoNamespace;
        $sparql = $direction === 'broader'
            ? <<<SPARQL
PREFIX rico: <{$ns}>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT DISTINCT ?id ?name WHERE {
    <{$uri}> rdfs:subPropertyOf ?other .
    ?other rico:RiCCMCorrespondingComponent ?marker ;
           rdfs:label ?name .
    FILTER(REGEX(STR(?marker), "^RiC-R[0-9]+", "s"))
    FILTER(lang(?name) = "en")
    BIND(REPLACE(STR(?marker), "^(RiC-R[0-9]+i?).*", "\$1", "s") AS ?id)
}
ORDER BY ?id
SPARQL
            : <<<SPARQL
PREFIX rico: <{$ns}>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT DISTINCT ?id ?name WHERE {
    ?other rdfs:subPropertyOf <{$uri}> ;
           rico:RiCCMCorrespondingComponent ?marker ;
           rdfs:label ?name .
    FILTER(REGEX(STR(?marker), "^RiC-R[0-9]+", "s"))
    FILTER(lang(?name) = "en")
    BIND(REPLACE(STR(?marker), "^(RiC-R[0-9]+i?).*", "\$1", "s") AS ?id)
}
ORDER BY ?id
SPARQL;

        $rows = $this->sparqlSelect($sparql);
        return array_map(static fn (array $r): array => [
            'id'   => $r['id']   ?? '',
            'name' => $r['name'] ?? ($r['id'] ?? ''),
        ], $rows);
    }

    /**
     * RiC-CM relation attributes (NavTool's RiC-RA##). RiC-O v1.1 does not
     * expose these as a distinct annotation category, so for now this returns
     * a bundled list derived from the RiC-CM 1.0 document. When RiC-O adds
     * first-class support, swap to SPARQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRelationAttributes(?string $version = null): array
    {
        return $this->cached($this->key('relation-attributes', $version), function () {
            return $this->staticRelationAttributes();
        });
    }

    /** @return array<string, mixed>|null */
    public function getRelationAttribute(string $id, ?string $version = null): ?array
    {
        return $this->findById($this->listRelationAttributes($version), $id);
    }

    /**
     * Hierarchy tree from Thing (RiC-E01) down, breadth-first.
     *
     * @return array<int, array{id: string, name: string, children: array<int, mixed>}>
     */
    public function getHierarchy(?string $version = null): array
    {
        return $this->cached($this->key('hierarchy', $version), function () use ($version) {
            $entities = $this->listEntities($version);
            return $this->buildTree('RiC-E01', $entities);
        });
    }

    // ------------------------------------------------------------------
    // SPARQL query construction
    // ------------------------------------------------------------------

    private function entitiesQuery(): string
    {
        $ns = $this->ricoNamespace;
        return <<<SPARQL
PREFIX rico: <{$ns}>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX owl:  <http://www.w3.org/2002/07/owl#>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
SELECT DISTINCT ?id ?uri ?name ?definition
       (GROUP_CONCAT(DISTINCT ?parentId; separator=",")   AS ?parents)
       (GROUP_CONCAT(DISTINCT ?scopeNote; separator="|||") AS ?scopeNotes)
       (GROUP_CONCAT(DISTINCT ?example;   separator="|||") AS ?examples)
WHERE {
    ?uri a owl:Class ;
         rico:RiCCMCorrespondingComponent ?marker .
    FILTER(REGEX(STR(?marker), "[Cc]or+esponds to RiC-E[0-9]+", "s"))
    BIND(REPLACE(STR(?marker), ".*?(RiC-E[0-9]+).*", "\$1", "s") AS ?id)

    OPTIONAL { ?uri rdfs:label    ?name       . FILTER(lang(?name)       = "en") }
    OPTIONAL { ?uri rdfs:comment  ?definition . FILTER(lang(?definition) = "en") }
    OPTIONAL { ?uri skos:scopeNote ?scopeNote . FILTER(lang(?scopeNote)  = "en") }
    OPTIONAL { ?uri skos:example   ?example   . FILTER(lang(?example)    = "en") }

    OPTIONAL {
        ?uri rdfs:subClassOf ?parent .
        ?parent a owl:Class ;
                rico:RiCCMCorrespondingComponent ?parentMarker .
        FILTER(REGEX(STR(?parentMarker), "[Cc]or+esponds to RiC-E[0-9]+", "s"))
        BIND(REPLACE(STR(?parentMarker), ".*?(RiC-E[0-9]+).*", "\$1", "s") AS ?parentId)
    }
}
GROUP BY ?id ?uri ?name ?definition
ORDER BY ?id
SPARQL;
    }

    private function attributesQuery(): string
    {
        $ns = $this->ricoNamespace;
        return <<<SPARQL
PREFIX rico: <{$ns}>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX owl:  <http://www.w3.org/2002/07/owl#>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
SELECT DISTINCT ?id ?uri ?name ?definition
       (GROUP_CONCAT(DISTINCT ?domainId;  separator=",")   AS ?domain)
       (GROUP_CONCAT(DISTINCT ?scopeNote; separator="|||") AS ?scopeNotes)
       (GROUP_CONCAT(DISTINCT ?example;   separator="|||") AS ?examples)
WHERE {
    ?uri rico:RiCCMCorrespondingComponent ?marker .
    FILTER(REGEX(STR(?marker), "[Cc]or+esponds to RiC-A[0-9]+", "s"))
    BIND(REPLACE(STR(?marker), ".*?(RiC-A[0-9]+).*", "\$1", "s") AS ?id)

    OPTIONAL { ?uri rdfs:label     ?name       . FILTER(lang(?name)       = "en") }
    OPTIONAL { ?uri rdfs:comment   ?definition . FILTER(lang(?definition) = "en") }
    OPTIONAL { ?uri skos:scopeNote ?scopeNote  . FILTER(lang(?scopeNote)  = "en") }
    OPTIONAL { ?uri skos:example   ?example    . FILTER(lang(?example)    = "en") }

    OPTIONAL {
        ?uri rdfs:domain ?d .
        ?d rico:RiCCMCorrespondingComponent ?dMarker .
        FILTER(REGEX(STR(?dMarker), "[Cc]or+esponds to RiC-E[0-9]+", "s"))
        BIND(REPLACE(STR(?dMarker), ".*?(RiC-E[0-9]+).*", "\$1", "s") AS ?domainId)
    }
}
GROUP BY ?id ?uri ?name ?definition
ORDER BY ?id
SPARQL;
    }

    private function relationsQuery(): string
    {
        $ns = $this->ricoNamespace;
        return <<<SPARQL
PREFIX rico: <{$ns}>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX owl:  <http://www.w3.org/2002/07/owl#>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
SELECT DISTINCT ?id ?uri ?name ?definition ?domain ?range ?inverseOf
       (GROUP_CONCAT(DISTINCT ?scopeNote; separator="|||") AS ?scopeNotes)
       (GROUP_CONCAT(DISTINCT ?example;   separator="|||") AS ?examples)
WHERE {
    ?uri a owl:ObjectProperty ;
         rico:RiCCMCorrespondingComponent ?marker .
    FILTER(REGEX(STR(?marker), "^RiC-R[0-9]+", "s"))
    BIND(REPLACE(STR(?marker), "^(RiC-R[0-9]+i?).*", "\$1", "s") AS ?id)

    OPTIONAL { ?uri rdfs:label     ?name       . FILTER(lang(?name)       = "en") }
    OPTIONAL { ?uri rdfs:comment   ?definition . FILTER(lang(?definition) = "en") }
    OPTIONAL { ?uri skos:scopeNote ?scopeNote  . FILTER(lang(?scopeNote)  = "en") }
    OPTIONAL { ?uri skos:example   ?example    . FILTER(lang(?example)    = "en") }

    OPTIONAL {
        ?uri rdfs:domain ?d .
        ?d rico:RiCCMCorrespondingComponent ?dMarker .
        FILTER(REGEX(STR(?dMarker), "[Cc]or+esponds to RiC-E[0-9]+", "s"))
        BIND(REPLACE(STR(?dMarker), ".*?(RiC-E[0-9]+).*", "\$1", "s") AS ?domain)
    }
    OPTIONAL {
        ?uri rdfs:range ?r .
        ?r rico:RiCCMCorrespondingComponent ?rMarker .
        FILTER(REGEX(STR(?rMarker), "[Cc]or+esponds to RiC-E[0-9]+", "s"))
        BIND(REPLACE(STR(?rMarker), ".*?(RiC-E[0-9]+).*", "\$1", "s") AS ?range)
    }
    OPTIONAL {
        ?uri owl:inverseOf ?inv .
        ?inv rico:RiCCMCorrespondingComponent ?invMarker .
        BIND(REPLACE(STR(?invMarker), "^(RiC-R[0-9]+i?).*", "\$1", "s") AS ?inverseOf)
    }
}
GROUP BY ?id ?uri ?name ?definition ?domain ?range ?inverseOf
ORDER BY ?id
SPARQL;
    }

    // ------------------------------------------------------------------
    // HTTP + binding parsing
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sparqlSelect(string $query): array
    {
        try {
            $response = $this->http->post($this->dataset . '/sparql', [
                'form_params' => ['query' => $query],
                'headers'     => ['Accept' => 'application/sparql-results+json'],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                'SPARQL query failed against Fuseki dataset ' . $this->dataset . ': ' . $e->getMessage(),
                previous: $e,
            );
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['results']['bindings'])) {
            throw new RuntimeException('Malformed SPARQL response: ' . substr($body, 0, 200));
        }

        return $this->normaliseBindings($decoded['results']['bindings']);
    }

    /**
     * Convert SPARQL JSON bindings to flat associative rows. All values are
     * left as strings (or null). Per-query splitting of GROUP_CONCAT
     * results into arrays is handled by each list-*() method so that
     * fields with list vs. scalar semantics are disambiguated at the
     * call site (attributes.domain = list; relations.domain = scalar).
     *
     * @param array<int, array<string, array<string, string>>> $bindings
     * @return array<int, array<string, mixed>>
     */
    private function normaliseBindings(array $bindings): array
    {
        $out = [];
        foreach ($bindings as $row) {
            $flat = [];
            foreach ($row as $key => $cell) {
                $flat[$key] = $cell['value'] ?? null;
            }
            $out[] = $flat;
        }
        return $out;
    }

    /**
     * Split a comma-separated GROUP_CONCAT value into a de-duplicated array.
     * Returns empty array for null/empty.
     *
     * @return array<int, string>
     */
    private function splitList(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    /**
     * Split on the `|||` separator used for multi-valued literal fields
     * (scope notes, examples) whose values may themselves contain commas
     * or newlines. Normalises whitespace in each element.
     *
     * @return array<int, string>
     */
    private function splitMulti(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $parts = array_map(
            static fn (string $s): string => trim(preg_replace('/\s+/', ' ', $s) ?? ''),
            explode('|||', $value),
        );
        return array_values(array_unique(array_filter($parts)));
    }

    // ------------------------------------------------------------------
    // Cache + lookup helpers
    // ------------------------------------------------------------------

    private function key(string $scope, ?string $version): string
    {
        $v = $version ?? $this->latestVersion;
        return "{$this->cachePrefix}.{$scope}.{$v}";
    }

    /**
     * @template T of mixed
     * @param callable(): T $compute
     * @return T
     */
    private function cached(string $key, callable $compute): mixed
    {
        $store = $this->cacheStore !== null && $this->cacheStore !== ''
            ? Cache::store($this->cacheStore)
            : Cache::store();
        return $store->remember($key, $this->cacheTtl, $compute);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function findById(array $rows, string $id): ?array
    {
        foreach ($rows as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param array<int, array{id: string, name: string, parents: array<int, string>}> $entities
     * @return array<int, array{id: string, name: string, children: array<int, mixed>}>
     */
    private function buildTree(string $rootId, array $entities): array
    {
        $root = $this->findById($entities, $rootId);
        if ($root === null) {
            return [];
        }
        return [$this->buildNode($root, $entities)];
    }

    /**
     * @param array<string, mixed>                                                           $node
     * @param array<int, array{id: string, name: string, parents: array<int, string>}>       $entities
     * @return array{id: string, name: string, children: array<int, mixed>}
     */
    private function buildNode(array $node, array $entities): array
    {
        $children = [];
        foreach ($entities as $e) {
            if (in_array($node['id'], $e['parents'] ?? [], true)) {
                $children[] = $this->buildNode($e, $entities);
            }
        }
        return [
            'id'       => $node['id'],
            'name'     => $node['name'] ?? $node['id'],
            'children' => $children,
        ];
    }

    /**
     * Relation attributes from RiC-CM 1.0 document. RiC-O v1.1 does not
     * annotate these as a distinct category; when it does, this returns
     * via SPARQL instead.
     *
     * @return array<int, array{id: string, name: string, definition: string}>
     */
    private function staticRelationAttributes(): array
    {
        return [
            ['id' => 'RiC-RA01', 'name' => 'Certainty of Relation',    'definition' => 'Qualifies the level of certainty of the accuracy of the relation.'],
            ['id' => 'RiC-RA02', 'name' => 'Date of Relation',         'definition' => 'Qualifies the date (or date range) at which the relation was effective.'],
            ['id' => 'RiC-RA03', 'name' => 'Role of Relation',         'definition' => 'Qualifies the role played in the relation by one of the related entities.'],
            ['id' => 'RiC-RA04', 'name' => 'Source of Relation',       'definition' => 'Qualifies the source(s) of the information that describes the relation.'],
            ['id' => 'RiC-RA05', 'name' => 'Type of Relation',         'definition' => 'Qualifies the relation by typifying it according to a specific vocabulary.'],
            ['id' => 'RiC-RA06', 'name' => 'Description of Relation',  'definition' => 'General description of the relation itself.'],
        ];
    }
}
