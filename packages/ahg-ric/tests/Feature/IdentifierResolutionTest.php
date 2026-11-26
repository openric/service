<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Canonical identifiers resolve for browsers and for data clients (viewing API
 * §3.1). Until 26 November 2026 a browser following /id/place/{id} was sent to
 * an admin page and /id/record/{slug} to a bare /{slug}, both 404 on the public
 * host; a browser now goes to the same record page as the short entity path.
 * An unpublished record answers 404 rather than a redirect naming its slug.
 */

namespace Tests\Feature;

use AhgRic\Support\Visibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdentifierResolutionTest extends TestCase
{
    use RefreshDatabase;

    private const HTML = ['Accept' => 'text/html,application/xhtml+xml'];

    private const LD = ['Accept' => 'application/ld+json'];

    /** Spec kind => [API collection, short entity path segment]. */
    private const KINDS = [
        'record' => ['records', 'informationobject'], 'record-set' => ['records', 'informationobject'],
        'record-part' => ['records', 'informationobject'], 'agent' => ['agents', 'actor'],
        'person' => ['agents', 'actor'], 'corporate-body' => ['agents', 'actor'], 'family' => ['agents', 'actor'],
        'mechanism' => ['agents', 'actor'], 'place' => ['places', 'place'], 'rule' => ['rules', 'rule'],
        'activity' => ['activities', 'activity'], 'instantiation' => ['instantiations', 'instantiation'],
        'function' => ['functions', 'function'],
    ];

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
        Schema::create('repository', fn ($t) => $t->integer('id')->primary());
        foreach ([10 => [160, 'open-fonds'], 11 => [159, 'draft-fonds']] as $id => [$status, $slug]) {
            DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1]);
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);
            DB::table('status')->insert(['object_id' => $id, 'type_id' => 158, 'status_id' => $status]);
        }
        DB::table('repository')->insert(['id' => 900]);
        DB::table('slug')->insert(['object_id' => 900, 'slug' => 'national-archives']);
        Visibility::forget();
    }

    private function viewer(string $segment, string $key): string
    {
        return 'https://viewer.openric.org/?start=/'.$segment.'/'.$key;
    }

    public function test_every_kind_sends_a_browser_to_the_record_page_and_a_data_client_to_the_api(): void
    {
        foreach (self::KINDS as $kind => [$collection, $segment]) {
            $key = $collection === 'records' ? 'open-fonds' : '912324';

            $browser = $this->get("/id/{$kind}/{$key}", self::HTML)->assertStatus(303);
            $this->assertSame($this->viewer($segment, $key), $browser->headers->get('Location'), $kind);
            $this->assertStringContainsString('Accept', (string) $browser->headers->get('Vary'), $kind);

            $data = $this->get("/id/{$kind}/{$key}", self::LD)->assertStatus(303);
            $this->assertStringEndsWith("/api/ric/v1/{$collection}/{$key}", (string) $data->headers->get('Location'), $kind);
        }
    }

    public function test_the_browser_page_is_the_one_the_short_entity_path_gives(): void
    {
        $short = $this->get('/place/912324', self::HTML)->headers->get('Location');

        $this->assertSame($short, $this->get('/id/place/912324', self::HTML)->headers->get('Location'));
    }

    public function test_a_numeric_record_id_resolves_to_its_slug_and_a_repository_to_the_repository_page(): void
    {
        $this->get('/id/record/10', self::HTML)->assertRedirect($this->viewer('informationobject', 'open-fonds'));
        $this->get('/id/corporate-body/national-archives', self::HTML)->assertRedirect($this->viewer('repository', 'national-archives'));
        $this->get('/openric/recordset/open-fonds', self::HTML)->assertRedirect($this->viewer('informationobject', 'open-fonds'));
    }

    public function test_an_unpublished_record_answers_404_and_names_nothing(): void
    {
        foreach (['/id/record/11', '/id/record/draft-fonds', '/openric/recordset/draft-fonds', '/openric/record/11'] as $url) {
            $this->get($url, self::HTML)->assertNotFound();
            $this->get($url, self::LD)->assertNotFound();
        }
    }
}
