<?php

declare(strict_types=1);

namespace AhgRicModel\Tests\Feature;

use AhgRicModel\Services\OntologyService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Graceful-degradation behaviour: when OntologyService throws a
 * RuntimeException (Fuseki unreachable, malformed response, etc.) every
 * public route renders the not-configured partial with HTTP 503 instead
 * of crashing with HTTP 500.
 */
class FusekiFailureTest extends TestCase
{
    /** @var OntologyService&MockInterface */
    private $ontology;

    protected function setUp(): void
    {
        parent::setUp();

        // Replace the bound OntologyService with one that throws on every call.
        $this->ontology = Mockery::mock(OntologyService::class);
        $this->ontology->shouldReceive('listAvailableVersions')->andReturn(['1.0']);
        $this->ontology->shouldReceive('latestVersion')->andReturn('1.0');
        // All data methods throw.
        foreach (['listEntities', 'listAttributes', 'listRelations', 'listRelationAttributes',
                  'getEntity', 'getAttribute', 'getRelation', 'getRelationAttribute',
                  'getHierarchy'] as $method) {
            $this->ontology->shouldReceive($method)
                ->andThrow(new RuntimeException('simulated fuseki failure'));
        }
        $this->app->instance(OntologyService::class, $this->ontology);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[DataProvider('urls')]
    public function test_fuseki_failure_renders_not_configured_partial_with_503(string $url): void
    {
        $this->get($url)
            ->assertStatus(503)
            ->assertSee('RiC-CM reference is temporarily unavailable')
            ->assertSee('simulated fuseki failure');
    }

    /** @return array<string, array{string}> */
    public static function urls(): array
    {
        return [
            'reference index'       => ['/reference/ric-cm/1.0'],
            'entities list'         => ['/reference/ric-cm/1.0/entities'],
            'entity detail'         => ['/reference/ric-cm/1.0/entities/RiC-E04'],
            'attributes list'       => ['/reference/ric-cm/1.0/attributes'],
            'relations list'        => ['/reference/ric-cm/1.0/relations'],
            'relation-attrs list'   => ['/reference/ric-cm/1.0/relation-attributes'],
        ];
    }
}
