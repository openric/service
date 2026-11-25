<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Two defects found by the conformance probe of spec v0.61.0:
 * - openric/service#19: a record set's export read each child's id back from its
 *   URI, which ends in the slug, so every child came out as "not found" (or, for a
 *   slug starting with digits, as another record).
 * - openric/service#20: AtoM's root repository, which has no name, was listed and
 *   served as a bare @id and @type.
 */

namespace Tests\Feature;

use AhgRic\Services\RicSerializationService;
use AhgRic\Support\Visibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExportTreeAndRootRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('object', function ($t) {
            $t->integer('id')->primary();
            $t->string('class_name');
        });
        Schema::create('slug', function ($t) {
            $t->integer('object_id');
            $t->string('slug');
        });
        Schema::create('information_object', function ($t) {
            $t->integer('id')->primary();
            $t->integer('parent_id')->nullable();
            $t->integer('lft')->default(0);
        });
        Schema::create('status', function ($t) {
            $t->integer('id')->nullable();
            $t->integer('object_id');
            $t->integer('type_id');
            $t->integer('status_id');
        });
        Schema::create('repository', fn ($t) => $t->integer('id')->primary());
        Schema::create('actor', function ($t) {
            $t->integer('id')->primary();
            $t->string('source_culture')->default('en');
        });
        Schema::create('actor_i18n', function ($t) {
            $t->integer('id');
            $t->string('culture')->default('en');
            $t->string('authorized_form_of_name')->nullable();
        });
        Visibility::forget();
    }

    public function test_a_record_sets_export_holds_its_descendants_found_by_id(): void
    {
        // A fonds with a child whose slug starts with digits and a grandchild; 3 is another record.
        foreach ([[3, null, 1], [10, null, 2], [11, 10, 3], [12, 11, 4]] as [$id, $parent, $lft]) {
            DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent, 'lft' => $lft]);
        }
        DB::table('slug')->insert([['object_id' => 11, 'slug' => '3-dimensional-object'], ['object_id' => 12, 'slug' => 'page-one']]);

        $serializer = new class extends RicSerializationService {
            public function __construct()
            {
            }

            public function serializeRecord(int $ioId, array $options = []): array
            {
                return $ioId === 12 ? ['error' => 'Information Object not found'] : ['@id' => "record/{$ioId}"];
            }
        };
        $graph = \Closure::bind(fn () => $this->getAllDescendants(10), $serializer, RicSerializationService::class)();

        $this->assertSame([['@id' => 'record/11']], $graph, 'the child by its id, not 3 from its slug; no error node');
    }

    public function test_the_root_repository_is_not_listed_and_answers_404(): void
    {
        $repositories = [Visibility::REPOSITORY_ROOT_ID => ['d6mh-ktzy-h6qz', null], 900 => ['national-archives', 'National Archives']];
        foreach ($repositories as $id => [$slug, $name]) {
            DB::table('object')->insert(['id' => $id, 'class_name' => 'QubitRepository']);
            DB::table('repository')->insert(['id' => $id]);
            DB::table('actor')->insert(['id' => $id]);
            DB::table('actor_i18n')->insert(['id' => $id, 'authorized_form_of_name' => $name]);
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);
        }

        $list = $this->getJson('/api/ric/v1/repositories')->assertOk();
        $this->assertSame(1, $list->json('openric:total'));
        $this->assertSame(['National Archives'], array_column($list->json('openric:items'), 'rico:name'));
        $this->getJson('/api/ric/v1/repositories/d6mh-ktzy-h6qz')->assertNotFound();
        $this->getJson('/api/ric/v1/repositories/' . Visibility::REPOSITORY_ROOT_ID)->assertNotFound();
    }
}
