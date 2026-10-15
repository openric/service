<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * A status row's id must be the id of its own `object` row (class QubitStatus),
 * as in Heratio #1470. The shared database gives status.id no default, so an
 * insert without one fails: creating a description or importing a CSV broke.
 */

namespace Tests\Feature;

use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatusRowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('object', function ($t) {
            $t->id();
            $t->string('class_name');
            $t->timestamps();
            $t->integer('serial_number')->default(0);
        });
        // As on the shared database: no AUTO_INCREMENT, no default.
        Schema::create('status', function ($t) {
            $t->unsignedBigInteger('id')->primary();
            $t->unsignedBigInteger('object_id');
            $t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('status_id');
            $t->integer('serial_number')->default(0);
        });
    }

    public function test_status_rows_get_their_own_object_id_and_replace_the_old_one(): void
    {
        $first = StatusRow::set(42, 158, 159);
        $this->assertSame('QubitStatus', DB::table('object')->where('id', $first)->value('class_name'));

        $second = StatusRow::set(42, 158, 160, ['serial_number' => 0]);
        $this->assertNotSame($first, $second);
        $this->assertSame(1, DB::table('status')->where('object_id', 42)->where('type_id', 158)->count());
        $this->assertSame(160, (int) DB::table('status')->where('id', $second)->value('status_id'));
    }

    public function test_no_code_inserts_status_rows_without_status_row(): void
    {
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('packages'))) as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'StatusRow.php' || str_contains($file->getPathname(), '/tests/')) {
                continue;
            }
            if (str_contains(file_get_contents($file->getPathname()), "table('status')->insert")) {
                $offenders[] = $file->getPathname();
            }
        }
        $this->assertSame([], $offenders, 'Write status rows through AhgCore\\Support\\StatusRow');
    }
}
