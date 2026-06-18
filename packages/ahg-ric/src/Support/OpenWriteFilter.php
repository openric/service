<?php

/**
 * OpenWriteFilter — hide open-write (sandbox) entities from public read surfaces.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL 3.0.
 *
 * Entities created through the temporary OPENRIC_OPEN_WRITE window are logged in
 * `openric_open_write`. This helper excludes them from public list / search /
 * harvest queries so wizard- and public-created entities never pollute the live
 * catalogue, while remaining fetchable by id (so the wizard can still show what
 * it created). All RiC entities share one global `object` id space, so excluding
 * by id alone is unambiguous across entity types.
 *
 * No-ops when OPENRIC_HIDE_OPEN_WRITE is false or the inventory table is absent,
 * so detail/by-id reads and pre-migration deployments are unaffected. Set
 * OPENRIC_HIDE_OPEN_WRITE=false to make open-write entities publicly visible.
 */

namespace AhgRic\Support;

use Illuminate\Support\Facades\Schema;

class OpenWriteFilter
{
    public static function enabled(): bool
    {
        return filter_var(env('OPENRIC_HIDE_OPEN_WRITE', true), FILTER_VALIDATE_BOOLEAN)
            && Schema::hasTable('openric_open_write');
    }

    /**
     * Add `WHERE {idColumn} NOT IN (open-write inventory)` to a query builder.
     * Returns the builder for fluent chaining.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    public static function hide($query, string $idColumn)
    {
        if (!self::enabled()) {
            return $query;
        }

        return $query->whereNotIn($idColumn, function ($q) {
            $q->select('entity_id')->from('openric_open_write');
        });
    }
}
