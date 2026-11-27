<?php

/**
 * RicSerializationServiceTest - Unit tests for RIC-O Serialization
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Unit;

use AhgRic\Services\RicSerializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RicSerializationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RicSerializationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // The empty AtoM tables these lookups read, so the not-found paths run on the in-memory
        // test database as they do in CI (27 Nov 2026; until then the file was left out of CI).
        $tables = [
            'information_object' => ['source_culture', 'level_of_description_id'],
            'actor' => ['entity_type_id'],
            'function_object' => ['source_culture'],
            'term' => [],
        ];
        foreach ($tables as $name => $columns) {
            Schema::create($name, function ($t) use ($columns) {
                $t->id();
                foreach ($columns as $c) {
                    $t->string($c)->nullable();
                }
            });
        }
        foreach (['information_object_i18n', 'actor_i18n', 'function_object_i18n', 'repository_i18n', 'term_i18n'] as $name) {
            Schema::create($name, function ($t) {
                $t->unsignedBigInteger('id');
                $t->string('culture');
                $t->string('name')->nullable();
            });
        }
        Schema::create('slug', function ($t) {
            $t->unsignedBigInteger('object_id');
            $t->string('slug');
        });
        $this->service = new RicSerializationService();
    }

    /**
     * Test that service can be instantiated.
     */
    public function test_can_instantiate(): void
    {
        $this->assertInstanceOf(RicSerializationService::class, $this->service);
    }

    /**
     * Test serialization returns array.
     */
    public function test_serialize_record_returns_array(): void
    {
        // Test with non-existent ID returns error array
        $result = $this->service->serializeRecord(999999);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test agent serialization returns array.
     */
    public function test_serialize_agent_returns_array(): void
    {
        // Test with non-existent ID returns error array
        $result = $this->service->serializeAgent(999999);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test function serialization returns array.
     */
    public function test_serialize_function_returns_array(): void
    {
        // Test with non-existent ID returns error array
        $result = $this->service->serializeFunction(999999);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test repository serialization returns array.
     */
    public function test_serialize_repository_returns_array(): void
    {
        // Test with non-existent ID returns error array
        $result = $this->service->serializeRepository(999999);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test ISCAP compliance addition.
     */
    public function test_add_iscap_compliance(): void
    {
        $entity = [
            '@id' => 'http://example.org/test',
            '@type' => 'rico:Record',
        ];

        $result = $this->service->addIscapCompliance($entity, 1, 'information_object');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('@id', $result);
    }

    /**
     * Test record set export returns array.
     */
    public function test_export_record_set_returns_array(): void
    {
        // Test with non-existent ID
        $result = $this->service->exportRecordSet(999999);
        
        $this->assertIsArray($result);
    }
}
