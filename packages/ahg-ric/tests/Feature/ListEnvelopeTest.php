<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Core Discovery 0.4.0 §3.3: list responses carry openric:total, page, limit
 * and items as integers and an array, with next/prev links, and keep the
 * deprecated ric:* aliases. Functions and repositories had no total at all.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ListEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('function_object', function ($t) {
            $t->id();
            $t->string('source_culture')->default('en');
        });
        Schema::create('function_object_i18n', function ($t) {
            $t->unsignedBigInteger('id');
            $t->string('culture')->default('en');
            $t->string('authorized_form_of_name')->nullable();
            $t->text('description')->nullable();
        });
        Schema::create('repository', fn ($t) => $t->id());
        Schema::create('actor', function ($t) {
            $t->id();
            $t->string('source_culture')->default('en');
        });
        Schema::create('actor_i18n', function ($t) {
            $t->unsignedBigInteger('id');
            $t->string('culture')->default('en');
            $t->string('authorized_form_of_name')->nullable();
        });
        Schema::create('slug', function ($t) {
            $t->unsignedBigInteger('object_id');
            $t->string('slug');
        });
        foreach ([1, 2, 3] as $i) {
            DB::table('function_object')->insert(['id' => $i]);
            DB::table('function_object_i18n')->insert(['id' => $i, 'authorized_form_of_name' => "Function $i"]);
            DB::table('repository')->insert(['id' => 10 + $i]);
            DB::table('actor')->insert(['id' => 10 + $i]);
            DB::table('actor_i18n')->insert(['id' => 10 + $i, 'authorized_form_of_name' => "Repository $i"]);
            // A second translation must neither duplicate the row nor replace the source-culture name.
            DB::table('function_object_i18n')->insert(['id' => $i, 'culture' => 'af', 'authorized_form_of_name' => "Funksie $i"]);
            DB::table('actor_i18n')->insert(['id' => 10 + $i, 'culture' => 'af', 'authorized_form_of_name' => "Bewaarplek $i"]);
            DB::table('slug')->insert(['object_id' => 10 + $i, 'slug' => "repository-$i"]);
        }
    }

    public function test_lists_carry_the_normative_envelope_and_the_ric_aliases(): void
    {
        foreach (['functions', 'repositories'] as $list) {
            $r = $this->getJson("/api/ric/v1/{$list}?limit=2")->assertOk();
            $this->assertSame(3, $r->json('openric:total'), $list);
            $this->assertSame(1, $r->json('openric:page'), $list);
            $this->assertSame(2, $r->json('openric:limit'), $list);
            $this->assertCount(2, $r->json('openric:items'), $list);
            $this->assertStringContainsString('page=2', $r->json('openric:next'), $list);
            $this->assertNull($r->json('openric:prev'), $list);
            $this->assertSame($r->json('openric:items'), $r->json('ric:items'), $list);
            $this->assertStringNotContainsString('Funksie', $r->getContent(), $list);
            $this->assertStringNotContainsString('Bewaarplek', $r->getContent(), $list);
            $this->assertSame('https://openric.org/ns/v1#', $r->json('@context.ric'), $list);

            $last = $this->getJson("/api/ric/v1/{$list}?limit=2&page=2")->assertOk();
            $this->assertCount(1, $last->json('openric:items'), $list);
            $this->assertNull($last->json('openric:next'), $list);
            $this->assertNotNull($last->json('openric:prev'), $list);
        }
    }
}
