<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Anonymous demand-signal tracker — two tables, ZERO personal data harvested.
 *
 *  - openric_usage    : a daily ROLLUP counter keyed by (day, event_type, label).
 *                       There is no per-visitor row, no IP, no user-agent, no
 *                       cookie, no session id. A page view is just `count++` on
 *                       (today, 'page_view', '/help/sparql/'). Un-deanonymisable
 *                       by construction. Its only purpose is to surface what
 *                       content/demos are in demand so enhancements can be
 *                       pre-empted.
 *  - openric_question : the "Ask a question" contact form. The body is required;
 *                       the reply-to email is OPTIONAL and volunteered by the
 *                       asker so we can answer — a normal contact form, not
 *                       covert collection.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('openric_usage', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->date('day');
            $t->string('event_type', 24);          // page_view, search, wizard_started, wizard_completed, ai_suggest, api_action
            $t->string('label', 200);              // path / query text / scenario id / model / entity type
            $t->unsignedBigInteger('count')->default(0);
            $t->unique(['day', 'event_type', 'label'], 'openric_usage_unique');
            $t->index(['event_type', 'day'], 'openric_usage_lookup');
        });

        Schema::create('openric_question', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->text('body');
            $t->string('contact_email', 190)->nullable();  // optional, volunteered for a reply
            $t->string('page', 255)->nullable();           // which page they asked from (context, not identity)
            $t->boolean('emailed')->default(false);
            $t->timestamp('created_at')->nullable();
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openric_question');
        Schema::dropIfExists('openric_usage');
    }
};
