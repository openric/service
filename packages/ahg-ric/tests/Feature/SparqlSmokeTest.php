<?php

/**
 * SparqlSmokeTest - lightweight smoke test for SPARQL endpoint
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 */

namespace Tests\Feature;

use Tests\TestCase;
use AhgRic\Services\SparqlQueryService;
use PHPUnit\Framework\Attributes\Group;

/**
 * PHPUnit 11+ no longer reads the `@group` doc-comment annotation — the group
 * must be declared with the #[Group] attribute or `--group smoke` matches nothing
 * ("No tests executed!", exit 1). The attribute below is what CI relies on.
 *
 * @group smoke
 */
#[Group('smoke')]
class SparqlSmokeTest extends TestCase
{
    public function test_sparql_endpoint_responds(): void
    {
        $sparql = new SparqlQueryService();

        // Use a safe ASK query that succeeds on an empty dataset
        $result = $sparql->executeQuery('ASK { ?s ?p ?o }');

        $this->assertIsArray($result, 'SPARQL result should be an array');
        $this->assertArrayNotHasKey('error', $result, 'SPARQL backend returned an error');
        $this->assertArrayHasKey('head', $result);
        $this->assertArrayHasKey('bindings', $result);
    }
}
