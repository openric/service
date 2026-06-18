<?php

/**
 * Inventory of entities created through the temporary OPENRIC_OPEN_WRITE window.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL 3.0.
 *
 * Every anonymous create that passes the open-write bypass is logged here so the
 * window can be (a) per-IP rate-capped and (b) fully torn down with
 * `php artisan openric:purge-open-write`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('openric_open_write', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('entity_id');
            $t->string('entity_type', 32);          // path segment: records, record-parts, agents, relations, …
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openric_open_write');
    }
};
