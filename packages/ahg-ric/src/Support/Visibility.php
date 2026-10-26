<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * What a visitor may see through the public read API. A description is visible
 * only when its publication status is Published, as on AtoM and Heratio: a
 * draft, or a description with no status at all, is hidden. Hidden with it are
 * its digital objects and instantiations, and every open-write (sandbox)
 * entity (OpenWriteFilter).
 *
 * All entities share AtoM's one `object` id space, so one id set covers every
 * entity type, and a relation, edge or row that names a hidden id is hidden too.
 *
 * Two layers use it: queries exclude hidden ids (hide), and every JSON response
 * of the read API is scrubbed of nested references to them (scrub, applied by
 * Http\Middleware\HideUnpublished and by the graph rebuild), so a serializer
 * that embeds a related record cannot leak a draft. A hidden entity answers 404,
 * the same as a missing one.
 */

namespace AhgRic\Support;

use AhgCore\Constants\TermId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class Visibility
{
    /** AtoM's root description: the tree root, not a description anyone reads. */
    private const ROOT_ID = 1;

    /** Keys whose value names the entity a node is about, or an end of an edge. */
    private const REFERENCE_KEYS = [
        '@id', 'id', 'atomId', 'entity_id', 'openric:root',
        'subject_id', 'object_id', 'target_id', 'record_id', 'source', 'target', 'target_uri',
    ];

    private static ?array $ids = null;
    private static ?array $keys = null;

    /** Ids a visitor may not see, as a set (id => true). */
    public static function hiddenIds(): array
    {
        if (self::$ids !== null) {
            return self::$ids;
        }

        // Fail closed: a description that is not marked Published, or that also
        // carries any other publication status, is hidden. With no status table
        // every description is hidden. (Missing tables occur only in tests.)
        $records = [];
        if (Schema::hasTable('information_object')) {
            $query = DB::table('information_object')->where('id', '!=', self::ROOT_ID);
            if (Schema::hasTable('status')) {
                $status = fn (bool $published) => DB::table('status')
                    ->where('type_id', TermId::STATUS_TYPE_PUBLICATION)
                    ->where('status_id', $published ? '=' : '!=', TermId::PUBLICATION_STATUS_PUBLISHED)
                    ->select('object_id');
                $query->where(fn ($w) => $w->whereNotIn('id', $status(true))->orWhereIn('id', $status(false)));
            }
            $records = $query->pluck('id')->all();
        }

        $ids = $records;
        foreach (['digital_object' => 'object_id', 'ric_instantiation' => 'record_id'] as $table => $column) {
            if ($records && Schema::hasTable($table)) {
                $ids = array_merge($ids, DB::table($table)->whereIn($column, $records)->pluck('id')->all());
            }
        }
        if (OpenWriteFilter::enabled()) {
            $ids = array_merge($ids, DB::table('openric_open_write')->pluck('entity_id')->all());
        }

        return self::$ids = array_fill_keys(array_map('intval', $ids), true);
    }

    public static function isHidden(int $id): bool
    {
        return isset(self::hiddenIds()[$id]);
    }

    /** A slug or numeric id, as the last segment of a URI, of a hidden entity. */
    public static function isHiddenKey(string $key): bool
    {
        return isset(self::hiddenKeys()[$key]);
    }

    /** Exclude hidden ids from a query on the given id column. */
    public static function hide($query, string $idColumn)
    {
        $ids = array_keys(self::hiddenIds());
        return $ids ? $query->whereNotIn($idColumn, $ids) : $query;
    }

    /**
     * Remove every node that names a hidden entity, and every bare IRI of one.
     * Returns null when the document itself is about a hidden entity.
     */
    public static function scrub(array $doc): ?array
    {
        if (self::names($doc)) {
            return null;
        }
        return self::clean($doc);
    }

    /** Forget the cached sets: once per request, and in tests. */
    public static function forget(): void
    {
        self::$ids = self::$keys = null;
    }

    private static function clean(array $node): array
    {
        $list = array_is_list($node);
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if (self::names($value)) {
                    unset($node[$key]);
                } else {
                    $node[$key] = self::clean($value);
                }
            } elseif (is_string($value) && self::isHiddenIri($value)) {
                unset($node[$key]);
            }
        }
        return $list ? array_values($node) : $node;
    }

    /** Does this node (an object, not a list) name a hidden entity? */
    private static function names(array $node): bool
    {
        if (array_is_list($node)) {
            return false;
        }
        foreach (self::REFERENCE_KEYS as $key) {
            $value = $node[$key] ?? null;
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                if (self::isHidden((int) $value)) {
                    return true;
                }
            } elseif (is_string($value) && self::isHiddenIri($value)) {
                return true;
            }
        }
        // A revision list or similar names its entity in a nested "entity".
        return isset($node['entity']['id']) && is_numeric($node['entity']['id'])
            && self::isHidden((int) $node['entity']['id']);
    }

    /**
     * An IRI or path whose last segment is a hidden entity's id or slug. Ids
     * are unique across entity types and slugs are unique, so the last segment
     * alone identifies the entity, whatever URI shape names it (/informationobject/,
     * /api/ric/v1/records/, the graph's /<instance>/recordset/, a thumbnail).
     */
    private static function isHiddenIri(string $value): bool
    {
        // Only this service's own IRIs and paths: an external link that happens
        // to end in a hidden slug is someone else's resource.
        if (!str_starts_with($value, '/')
            && parse_url($value, PHP_URL_HOST) !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return false;
        }
        $path = rtrim((string) parse_url($value, PHP_URL_PATH), '/');
        $last = substr($path, strrpos($path, '/') + 1);
        return $last !== '' && isset(self::hiddenKeys()[$last]);
    }

    /** Hidden entities' ids and slugs, as a set of strings. */
    private static function hiddenKeys(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }
        $ids = array_keys(self::hiddenIds());
        $slugs = $ids ? DB::table('slug')->whereIn('object_id', $ids)->pluck('slug')->all() : [];
        return self::$keys = array_fill_keys(array_merge(array_map('strval', $ids), $slugs), true);
    }
}
