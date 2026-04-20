<?php

declare(strict_types=1);

namespace AhgRicModel\Tests\Unit;

use AhgRicModel\Services\InheritanceResolver;
use Tests\TestCase;

class InheritanceResolverTest extends TestCase
{
    private InheritanceResolver $resolver;

    /** @var array<int, array{id: string, name: string, parents: array<int, string>}> */
    private array $entities;

    /** @var array<int, array<string, mixed>> */
    private array $attributes;

    /** @var array<int, array<string, mixed>> */
    private array $relations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new InheritanceResolver();

        // Synthetic hierarchy modelling RiC-CM:
        //   Thing -> RecordResource -> Record
        //   Thing -> Agent -> Person
        $this->entities = [
            ['id' => 'RiC-E01', 'name' => 'Thing',           'parents' => []],
            ['id' => 'RiC-E02', 'name' => 'Record Resource', 'parents' => ['RiC-E01']],
            ['id' => 'RiC-E04', 'name' => 'Record',          'parents' => ['RiC-E02']],
            ['id' => 'RiC-E07', 'name' => 'Agent',           'parents' => ['RiC-E01']],
            ['id' => 'RiC-E08', 'name' => 'Person',          'parents' => ['RiC-E07']],
        ];

        $this->attributes = [
            ['id' => 'RiC-A28', 'name' => 'Name',     'domain' => ['RiC-E01']],               // Thing → inherited by all
            ['id' => 'RiC-A10', 'name' => 'Content',  'domain' => ['RiC-E02']],               // RecordResource → Record inherits
            ['id' => 'RiC-A22', 'name' => 'Identifier', 'domain' => ['RiC-E04']],             // Record → declared on Record
        ];

        $this->relations = [
            ['id' => 'RiC-R001',  'name' => 'is related to', 'domain' => 'RiC-E01', 'range' => 'RiC-E01'],
            ['id' => 'RiC-R016',  'name' => 'has successor', 'domain' => 'RiC-E04', 'range' => 'RiC-E04'],  // declared on Record
            ['id' => 'RiC-R017',  'name' => 'created by',    'domain' => 'RiC-E02', 'range' => 'RiC-E07'],  // inherited by Record
        ];
    }

    public function test_ancestors_are_nearest_to_root(): void
    {
        $index = [];
        foreach ($this->entities as $e) {
            $index[$e['id']] = $e;
        }
        $ancestors = $this->resolver->ancestors('RiC-E04', $index);

        $this->assertSame([
            ['id' => 'RiC-E02', 'name' => 'Record Resource'],
            ['id' => 'RiC-E01', 'name' => 'Thing'],
        ], $ancestors);
    }

    public function test_ancestors_of_root_entity_is_empty(): void
    {
        $index = ['RiC-E01' => $this->entities[0]];
        $this->assertSame([], $this->resolver->ancestors('RiC-E01', $index));
    }

    public function test_declared_attribute_appears_only_in_declared_bucket(): void
    {
        $result = $this->resolver->resolve('RiC-E04', $this->entities, $this->attributes, $this->relations);

        $declaredIds = array_column($result['declaredAttributes'], 'id');
        $this->assertContains('RiC-A22', $declaredIds, 'Record declares A22 Identifier');

        $inheritedIds = array_column($result['inheritedAttributes'], 'id');
        $this->assertNotContains('RiC-A22', $inheritedIds, 'A22 must NOT appear as inherited on Record');
    }

    public function test_inherited_attributes_carry_ancestor_provenance(): void
    {
        $result = $this->resolver->resolve('RiC-E04', $this->entities, $this->attributes, $this->relations);

        $byId = [];
        foreach ($result['inheritedAttributes'] as $a) {
            $byId[$a['id']] = $a;
        }

        $this->assertArrayHasKey('RiC-A10', $byId, 'Content inherited from RecordResource');
        $this->assertSame(
            ['id' => 'RiC-E02', 'name' => 'Record Resource'],
            $byId['RiC-A10']['inheritedFrom']
        );

        $this->assertArrayHasKey('RiC-A28', $byId, 'Name inherited from Thing');
        $this->assertSame(
            ['id' => 'RiC-E01', 'name' => 'Thing'],
            $byId['RiC-A28']['inheritedFrom']
        );
    }

    public function test_relation_domain_and_range_are_never_flattened(): void
    {
        $result = $this->resolver->resolve('RiC-E04', $this->entities, $this->attributes, $this->relations);

        // R016 is declared on Record — domain stays 'RiC-E04', not expanded.
        $r016 = null;
        foreach ($result['declaredRelations'] as $r) {
            if ($r['id'] === 'RiC-R016') { $r016 = $r; break; }
        }
        $this->assertNotNull($r016);
        $this->assertSame('RiC-E04', $r016['domain']);
        $this->assertSame('RiC-E04', $r016['range']);
        $this->assertArrayNotHasKey('inheritedFrom', $r016);

        // R017 is declared on RecordResource → Record sees it as inherited.
        $r017 = null;
        foreach ($result['inheritedRelations'] as $r) {
            if ($r['id'] === 'RiC-R017') { $r017 = $r; break; }
        }
        $this->assertNotNull($r017);
        $this->assertSame('RiC-E02', $r017['domain'], 'Inherited relation keeps its declared-entity domain');
        $this->assertSame('RiC-E07', $r017['range'], 'Inherited relation keeps its declared range');
        $this->assertSame(
            ['id' => 'RiC-E02', 'name' => 'Record Resource'],
            $r017['inheritedFrom']
        );
    }

    public function test_descendants_breadth_first_and_deduped(): void
    {
        $desc = $this->resolver->descendants('RiC-E01', $this->entities);
        $ids  = array_column($desc, 'id');

        $this->assertContains('RiC-E02', $ids);
        $this->assertContains('RiC-E04', $ids);
        $this->assertContains('RiC-E07', $ids);
        $this->assertContains('RiC-E08', $ids);
        $this->assertCount(4, $ids, 'Four descendants of Thing');
        $this->assertCount(count(array_unique($ids)), $ids, 'No duplicates');
    }

    public function test_resolve_unknown_entity_returns_empty_buckets(): void
    {
        $result = $this->resolver->resolve('RiC-E99', $this->entities, $this->attributes, $this->relations);
        $this->assertSame([], $result['ancestors']);
        $this->assertSame([], $result['declaredAttributes']);
        $this->assertSame([], $result['inheritedAttributes']);
        $this->assertSame([], $result['declaredRelations']);
        $this->assertSame([], $result['inheritedRelations']);
    }

    public function test_resolver_is_free_of_laravel_imports(): void
    {
        $src = file_get_contents(__DIR__ . '/../../src/Services/InheritanceResolver.php');
        $this->assertIsString($src);
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*use\s+(Illuminate|Laravel)\\\\/m',
            $src,
            'InheritanceResolver MUST remain free of Laravel imports (portability contract).',
        );
    }
}
