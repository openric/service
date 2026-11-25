<?php

// OpenRiC - a record's security classification and its access restrictions are both published, on its page and in its export.
// Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace AhgRic\Tests\Feature;

use AhgRic\Services\RicSerializationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IscapComplianceTest extends TestCase
{
    private const CLASSIFICATION = 'https://openric.org/vocab/rule-type/security-classification';

    private const RESTRICTION = 'https://openric.org/vocab/rule-type/access-restriction';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('security_level', function (Blueprint $t) {
            $t->integer('id');
            $t->string('classification');
            $t->integer('level_value');
        });
        Schema::create('security_level_i18n', function (Blueprint $t) {
            $t->integer('id');
            $t->string('culture');
            $t->string('name');
        });
        Schema::create('security_access_condition_link', function (Blueprint $t) {
            $t->integer('object_id');
            $t->integer('classification_id');
        });
        Schema::create('icip_access_restriction', function (Blueprint $t) {
            $t->integer('information_object_id');
            $t->string('restriction_type');
            $t->string('custom_restriction_text')->nullable();
            $t->string('start_date')->nullable();
            $t->string('end_date')->nullable();
            $t->boolean('applies_to_descendants')->default(false);
        });
        DB::table('security_level')->insert(['id' => 1, 'classification' => 'C', 'level_value' => 3]);
        DB::table('security_level_i18n')->insert(['id' => 1, 'culture' => app()->getLocale(), 'name' => 'Confidential']);
    }

    private function ruleTypes(array $entity): array
    {
        $rules = $entity['rico:isOrWasRegulatedBy'] ?? [];

        return array_map(fn ($r) => $r['rico:hasOrHadRuleType']['@id'], isset($rules['@type']) ? [$rules] : $rules);
    }

    public function test_a_record_with_a_classification_and_restrictions_keeps_them_all(): void
    {
        DB::table('security_access_condition_link')->insert(['object_id' => 10, 'classification_id' => 1]);
        DB::table('icip_access_restriction')->insert([
            ['information_object_id' => 10, 'restriction_type' => 'community'],
            ['information_object_id' => 10, 'restriction_type' => 'seasonal'],
        ]);

        $out = (new RicSerializationService)->addIscapCompliance(['@id' => 'r'], 10, 'information_object');

        $this->assertSame([self::CLASSIFICATION, self::RESTRICTION, self::RESTRICTION], $this->ruleTypes($out));
        $this->assertSame('C', $out['rico:isOrWasRegulatedBy'][0]['openricx:securityLevelCode']);
    }

    public function test_either_alone_is_published_as_before(): void
    {
        DB::table('security_access_condition_link')->insert(['object_id' => 11, 'classification_id' => 1]);
        DB::table('icip_access_restriction')->insert(['information_object_id' => 12, 'restriction_type' => 'community']);
        $service = new RicSerializationService;

        $this->assertSame('rico:Rule', $service->addIscapCompliance([], 11, 'information_object')['rico:isOrWasRegulatedBy']['@type']);
        $this->assertSame([self::RESTRICTION], $this->ruleTypes($service->addIscapCompliance([], 12, 'information_object')));
    }

    public function test_a_record_sets_export_carries_them_on_the_root_and_its_descendants(): void
    {
        // /records/{slug} always added them; /records/{slug}/export did not, so an export carried less.
        Schema::create('information_object', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('parent_id')->nullable();
            $t->integer('lft')->default(0);
        });
        DB::table('information_object')->insert([['id' => 20, 'parent_id' => null, 'lft' => 1], ['id' => 21, 'parent_id' => 20, 'lft' => 2]]);
        DB::table('security_access_condition_link')->insert(['object_id' => 20, 'classification_id' => 1]);
        DB::table('icip_access_restriction')->insert(['information_object_id' => 21, 'restriction_type' => 'community']);

        $serializer = new class extends RicSerializationService {
            public function __construct()
            {
            }

            public function serializeRecord(int $ioId, array $options = []): array
            {
                return ['@id' => "record/{$ioId}"];
            }
        };
        [$root, $child] = $serializer->exportRecordSet(20)['@graph'];

        $this->assertSame([self::CLASSIFICATION], $this->ruleTypes($root));
        $this->assertSame([self::RESTRICTION], $this->ruleTypes($child));
    }
}
