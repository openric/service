<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * A visitor sees published descriptions only (Support\Visibility). A draft, or
 * a description with no publication status, is left out of lists and answers
 * 404 exactly as a missing one does, and nested references to it are removed.
 * On 26 October 2026 a draft was readable anonymously at /api/ric/v1/records/{slug}.
 */

namespace Tests\Feature;

use AhgRic\Support\Visibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublishedOnlyTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLISHED = 101;
    private const DRAFT = 102;
    private const NO_STATUS = 103;
    private const DRAFT_IMAGE = 201;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('object', function ($t) {
            $t->id();
            $t->string('class_name');
        });
        Schema::create('slug', function ($t) {
            $t->unsignedBigInteger('object_id');
            $t->string('slug');
        });
        Schema::create('information_object', function ($t) {
            $t->id();
            $t->string('identifier')->nullable();
            $t->unsignedBigInteger('level_of_description_id')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
        });
        Schema::create('information_object_i18n', function ($t) {
            $t->unsignedBigInteger('id');
            $t->string('culture')->default('en');
            $t->string('title')->nullable();
        });
        Schema::create('term', fn ($t) => $t->id());
        Schema::create('term_i18n', function ($t) {
            $t->unsignedBigInteger('id');
            $t->string('culture');
            $t->string('name');
        });
        Schema::create('status', function ($t) {
            $t->id();
            $t->unsignedBigInteger('object_id');
            $t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('status_id');
        });
        Schema::create('digital_object', function ($t) {
            $t->id();
            $t->unsignedBigInteger('object_id');
            $t->string('path')->nullable();
            $t->string('mime_type')->nullable();
        });

        foreach ([self::PUBLISHED => 'published-fonds', self::DRAFT => 'draft-file', self::NO_STATUS => 'unmarked-item'] as $id => $slug) {
            DB::table('object')->insert(['id' => $id, 'class_name' => 'QubitInformationObject']);
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);
            DB::table('information_object')->insert(['id' => $id]);
            DB::table('information_object_i18n')->insert(['id' => $id, 'title' => ucfirst(str_replace('-', ' ', $slug))]);
        }
        DB::table('status')->insert(['object_id' => self::PUBLISHED, 'type_id' => 158, 'status_id' => 160]);
        DB::table('status')->insert(['object_id' => self::DRAFT, 'type_id' => 158, 'status_id' => 159]);
        DB::table('object')->insert(['id' => self::DRAFT_IMAGE, 'class_name' => 'QubitDigitalObject']);
        DB::table('digital_object')->insert(['id' => self::DRAFT_IMAGE, 'object_id' => self::DRAFT, 'path' => 'a.jpg', 'mime_type' => 'image/jpeg']);
        Visibility::forget();
    }

    public function test_lists_hold_published_descriptions_only(): void
    {
        $r = $this->getJson('/api/ric/v1/records')->assertOk();
        $this->assertSame(1, $r->json('openric:total'));
        $this->assertSame(['Published fonds'], array_column($r->json('openric:items'), 'rico:title'));
    }

    public function test_hidden_descriptions_answer_404_as_missing_ones_do(): void
    {
        $missing = $this->getJson('/api/ric/v1/records/no-such-record')->assertNotFound()->json();
        foreach (['draft-file', 'unmarked-item', (string) self::DRAFT] as $key) {
            $hidden = $this->getJson("/api/ric/v1/records/{$key}")->assertNotFound()->json();
            $this->assertSame(array_diff_key($missing, ['instance' => 1]), array_diff_key($hidden, ['instance' => 1]), $key);
        }
        $this->getJson('/api/ric/v1/records/draft-file/export')->assertNotFound();
        $this->getJson('/api/ric/v1/thumbnail/' . self::DRAFT_IMAGE)->assertNotFound();
        foreach (['relations-for', 'hierarchy', 'entities'] as $endpoint) {
            $path = $endpoint === 'entities' ? 'records/%d/entities' : "{$endpoint}/%d";
            $this->assertSame(
                array_diff_key($this->getJson('/api/ric/v1/' . sprintf($path, 999))->assertNotFound()->json(), ['instance' => 1]),
                array_diff_key($this->getJson('/api/ric/v1/' . sprintf($path, self::DRAFT))->assertNotFound()->json(), ['instance' => 1]),
                $endpoint
            );
        }
    }

    public function test_scrub_removes_nested_references_and_keeps_external_links(): void
    {
        $base = config('app.url');
        $doc = [
            '@id' => "{$base}/informationobject/published-fonds",
            'rico:hasOrHadPart' => [
                ['@id' => "{$base}/informationobject/draft-file", 'rico:title' => 'Draft file'],
                ['@id' => "{$base}/informationobject/unmarked-item"],
                ['@id' => "{$base}/informationobject/" . self::PUBLISHED],
            ],
            'rico:isRelatedTo' => "{$base}/informationobject/draft-file",
            'openric:edges' => [['source' => "{$base}/x/recordset/" . self::DRAFT, 'target' => "{$base}/informationobject/published-fonds"]],
            'rows' => [['id' => 5, 'subject_id' => self::DRAFT], ['id' => self::DRAFT_IMAGE]],
            'rdfs:seeAlso' => 'https://example.org/archive/draft-file',
        ];

        $clean = Visibility::scrub($doc);

        $this->assertSame([['@id' => "{$base}/informationobject/" . self::PUBLISHED]], $clean['rico:hasOrHadPart']);
        $this->assertArrayNotHasKey('rico:isRelatedTo', $clean);
        $this->assertSame([], $clean['openric:edges']);
        $this->assertSame([], $clean['rows']);
        $this->assertSame('https://example.org/archive/draft-file', $clean['rdfs:seeAlso']);
        $this->assertNull(Visibility::scrub(['@id' => "{$base}/informationobject/draft-file"]));
        $this->assertNull(Visibility::scrub(['entity_id' => self::NO_STATUS, 'children' => []]));
    }
}
