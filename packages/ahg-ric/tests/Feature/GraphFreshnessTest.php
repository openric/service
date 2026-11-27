<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * The service description says how current the public SPARQL graph is (27 November 2026). From 28 September the
 * scheduler never started, so the 03:00 rebuild never ran, and the graph stayed as built by hand on 26 October
 * with nothing to show it. A run that finds nothing changed still counts as a check.
 */

namespace Tests\Feature;

use AhgRic\Console\Commands\RebuildGraph;
use Tests\TestCase;

class GraphFreshnessTest extends TestCase
{
    private const DATASET = 'test-freshness';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ahg-ric.fuseki.dataset' => self::DATASET]);
        $this->dir = storage_path('app/openric-graph/'.self::DATASET);
        @mkdir($this->dir, 0775, true);
        array_map('unlink', glob($this->dir.'/*.json') ?: []);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*.json') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function write(string $file, array $data): void
    {
        file_put_contents($this->dir.'/'.$file, json_encode($data));
    }

    public function test_a_graph_never_built_is_stale(): void
    {
        $this->getJson('/api/ric/v1/')->assertOk()
            ->assertJsonPath('sparql_graph.dataset', self::DATASET)
            ->assertJsonPath('sparql_graph.built_at', null)
            ->assertJsonPath('sparql_graph.stale', true);
    }

    public function test_a_build_a_month_old_with_no_later_check_is_stale(): void
    {
        $this->write('last.json', ['built_at' => '2026-10-26T06:25:14+00:00', 'triples' => 26435]);
        $this->getJson('/api/ric/v1/')->assertJsonPath('sparql_graph.stale', true)
            ->assertJsonPath('sparql_graph.checked_at', '2026-10-26T06:25:14+00:00')
            ->assertJsonPath('sparql_graph.triples', 26435);
    }

    public function test_a_recent_no_change_run_keeps_an_old_build_current(): void
    {
        $this->write('last.json', ['built_at' => '2026-10-26T06:25:14+00:00', 'triples' => 26435]);
        $this->write('checked.json', ['checked_at' => date('c', time() - 3600), 'outcome' => 'no change']);
        $this->getJson('/api/ric/v1/')->assertJsonPath('sparql_graph.stale', false)
            ->assertJsonPath('sparql_graph.built_at', '2026-10-26T06:25:14+00:00');

        $this->write('checked.json', ['checked_at' => date('c', time() - (RebuildGraph::STALE_AFTER_HOURS + 1) * 3600)]);
        $this->assertTrue(RebuildGraph::freshness(self::DATASET)['stale']);
    }
}
