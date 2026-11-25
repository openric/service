<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * The graph viewer's /ric-api routes (and the /ricExplorer aliases of the same
 * handlers) apply the published-only rule, as /api/ric/v1 does: a request that
 * names an unpublished record answers 404, and answers lose every reference to one.
 * Before 25 November 2026 they had no filter and were safe only because the web
 * server redirects the prefix.
 */

namespace Tests\Feature;

use AhgRic\Http\Middleware\HideUnpublished;
use AhgRic\Support\Visibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GraphViewerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLISHED = 10;

    private const DRAFT = 11;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('information_object', function ($t) {
            $t->integer('id')->primary();
            $t->integer('parent_id')->nullable();
        });
        Schema::create('slug', function ($t) {
            $t->integer('object_id');
            $t->string('slug');
        });
        Schema::create('status', function ($t) {
            $t->integer('object_id');
            $t->integer('type_id');
            $t->integer('status_id');
        });
        foreach ([self::PUBLISHED => [160, 'open-fonds'], self::DRAFT => [159, 'draft-fonds']] as $id => [$status, $slug]) {
            DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1]);
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);
            DB::table('status')->insert(['object_id' => $id, 'type_id' => 158, 'status_id' => $status]);
        }
        Visibility::forget();
    }

    public function test_every_graph_viewer_route_carries_the_filter(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'ric-api/') || str_starts_with($r->uri(), 'ricExplorer/'));

        $this->assertGreaterThan(10, $routes->count());
        foreach ($routes as $route) {
            $this->assertContains(HideUnpublished::class.':inputs', $route->gatherMiddleware(), $route->uri());
        }
    }

    public function test_a_request_naming_a_draft_answers_404(): void
    {
        $iri = rtrim((string) config('app.url'), '/').'/openric/recordset/draft-fonds';

        foreach ([
            '/ric-api/relations/'.self::DRAFT,
            '/ric-api/graph-summary/'.self::DRAFT,
            '/ric-api/timeline/'.self::DRAFT,
            '/ric-api/explain/'.self::PUBLISHED.'/'.self::DRAFT,
            '/ric-api/graph-summary-by-uri?uri='.urlencode($iri),
            '/ric-api/expand?node='.urlencode($iri),
            '/ric-api/data?id='.self::DRAFT,
            '/ricExplorer/getData?id='.self::DRAFT,
        ] as $url) {
            $this->getJson($url)->assertNotFound();
        }
    }

    public function test_answers_lose_references_to_a_draft(): void
    {
        Route::get('/ric-api/test-nodes/{id}', fn () => response()->json([
            'nodes' => [['id' => self::PUBLISHED], ['id' => self::DRAFT]],
            'edges' => [['source' => self::PUBLISHED, 'target' => self::DRAFT]],
        ]))->middleware(HideUnpublished::class.':inputs');

        $this->getJson('/ric-api/test-nodes/'.self::PUBLISHED)
            ->assertOk()
            ->assertExactJson(['nodes' => [['id' => self::PUBLISHED]], 'edges' => []]);
    }
}
