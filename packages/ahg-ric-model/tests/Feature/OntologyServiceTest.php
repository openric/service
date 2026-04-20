<?php

declare(strict_types=1);

namespace AhgRicModel\Tests\Feature;

use AhgRicModel\Services\OntologyService;
use Tests\TestCase;

/**
 * Integration tests against a live Fuseki instance loaded with RiC-O v1.1.
 *
 * Skipped if Fuseki is unreachable at the configured FUSEKI_URL — so these
 * don't block CI in environments without the triplestore.
 */
class OntologyServiceTest extends TestCase
{
    private OntologyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfFusekiUnreachable();

        // Resolve directly — bypass the 'array' cache driver pinned in phpunit.xml
        // so we exercise real SPARQL, not a pre-warmed cache. Cache is still used
        // within the service (via `Cache::store()`), but the array store won't
        // persist between tests.
        $this->service = app(OntologyService::class);
    }

    public function test_lists_19_canonical_entities(): void
    {
        $entities = $this->service->listEntities();
        $this->assertCount(19, $entities, 'RiC-CM 1.0 declares 19 canonical entities');

        $ids = array_column($entities, 'id');
        $this->assertContains('RiC-E01', $ids);
        $this->assertContains('RiC-E04', $ids);
        $this->assertContains('RiC-E22', $ids, 'Place is E22, not E19 — hierarchy is non-sequential');
    }

    public function test_lists_42_canonical_attributes(): void
    {
        $attributes = $this->service->listAttributes();
        $this->assertCount(
            42,
            $attributes,
            'RiC-CM 1.0 declares 42 canonical attributes (the typo "Corrresponds to RiC-A28" must still match).'
        );
    }

    public function test_entity_detail_splits_declared_and_inherited(): void
    {
        $record = $this->service->getEntity('RiC-E04');
        $this->assertNotNull($record, 'RiC-E04 Record exists');
        $this->assertSame('RiC-E04', $record['entity']['id']);

        // Record inherits from RecordResource inherits from Thing.
        $ancestorIds = array_column($record['ancestors'], 'id');
        $this->assertContains('RiC-E02', $ancestorIds, 'RecordResource is an ancestor of Record');
        $this->assertContains('RiC-E01', $ancestorIds, 'Thing is the root ancestor');

        // Declared attributes don't have inheritedFrom; inherited ones do.
        foreach ($record['declaredAttributes'] as $a) {
            $this->assertArrayNotHasKey('inheritedFrom', $a, "Declared attribute {$a['id']} must not be tagged inherited");
        }
        foreach ($record['inheritedAttributes'] as $a) {
            $this->assertArrayHasKey('inheritedFrom', $a, "Inherited attribute {$a['id']} must carry provenance");
            $this->assertArrayHasKey('id', $a['inheritedFrom']);
            $this->assertArrayHasKey('name', $a['inheritedFrom']);
        }
    }

    public function test_relation_domain_and_range_are_single_entries_not_flattened(): void
    {
        $rel = $this->service->getRelation('RiC-R016');
        // NOTE: RiC-R016 may or may not exist in RiC-O v1.1 under that exact ID.
        // If it doesn't, we pick the first relation with a declared domain+range
        // and assert the invariant on that one instead.
        if ($rel === null) {
            $relations = $this->service->listRelations();
            foreach ($relations as $r) {
                if ($r['domain'] !== null && $r['range'] !== null) {
                    $rel = $this->service->getRelation($r['id']);
                    break;
                }
            }
        }
        $this->assertNotNull($rel, 'At least one relation must have declared domain+range');

        // Domain and range are single strings (or null) — never arrays, never flattened.
        $this->assertIsString($rel['domain']);
        $this->assertIsString($rel['range']);
        $this->assertDoesNotMatchRegularExpression('/,/', $rel['domain'], 'Domain is a single entity ID, not a CSV');
        $this->assertDoesNotMatchRegularExpression('/,/', $rel['range'],  'Range is a single entity ID, not a CSV');

        // Browsing aids are keyed separately — never merged into domain/range.
        $this->assertArrayHasKey('domainDescendants', $rel);
        $this->assertArrayHasKey('rangeDescendants',  $rel);
        $this->assertIsArray($rel['domainDescendants']);
        $this->assertIsArray($rel['rangeDescendants']);
    }

    public function test_hierarchy_starts_at_thing(): void
    {
        $tree = $this->service->getHierarchy();
        $this->assertNotEmpty($tree);
        $this->assertSame('RiC-E01', $tree[0]['id']);
        $this->assertNotEmpty($tree[0]['children'], 'Thing has at least one direct subclass');
    }

    public function test_attribute_detail_resolves_domain_entities(): void
    {
        $attrs = $this->service->listAttributes();
        $this->assertNotEmpty($attrs);
        $sampleId = $attrs[0]['id'];

        $attr = $this->service->getAttribute($sampleId);
        $this->assertNotNull($attr);
        $this->assertArrayHasKey('domainEntities', $attr, 'Detail view includes resolved domain entity refs');
    }

    public function test_available_versions_is_non_empty(): void
    {
        $this->assertNotEmpty($this->service->listAvailableVersions());
        $this->assertNotEmpty($this->service->latestVersion());
    }

    // ------------------------------------------------------------------

    private function skipIfFusekiUnreachable(): void
    {
        $url = config('ahg-ric-model.fuseki.url');
        $ds  = config('ahg-ric-model.fuseki.dataset');
        $ping = @file_get_contents(rtrim($url, '/') . '/' . $ds . '/sparql?query=ASK%20%7B%7D');
        if ($ping === false) {
            $this->markTestSkipped("Fuseki at {$url}/{$ds} unreachable; skipping integration tests.");
        }
    }
}
