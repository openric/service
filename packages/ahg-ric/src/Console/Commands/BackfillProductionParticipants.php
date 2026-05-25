<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Backfill rico:isOrWasPerformedBy (dropdown_code='performed_by') on
 * rico:Production activities that have an archivally-grounded performer
 * signal in the existing data — i.e., the database already claims an actor
 * as the performer via either:
 *
 *   (A) The activity has a results_from link to a record, AND that record
 *       has an event row with type_id = EVENT_TYPE_CREATION (111) and a
 *       non-null actor_id. We promote that creator to performed_by.
 *
 *   (B) An inverse relation exists pointing AT the activity from an actor
 *       (relation.object_id = activity.id, dropdown_code IN
 *       ('has_creator','has_accumulator')). We emit the forward direction.
 *
 * These are the ONLY two safe paths identified by the 2026-05-25 audit
 * (memory: project_production_activity_gap). Per that audit, NEVER
 * backfill from openric_audit_log.user_id — that names the archivist who
 * created the row in 2026, not the historical performer in e.g. 1850.
 *
 * Usage:
 *   php artisan openric:backfill-production-participants --dry-run
 *   php artisan openric:backfill-production-participants
 */

namespace AhgRic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillProductionParticipants extends Command
{
    protected $signature = 'openric:backfill-production-participants
        {--dry-run : List what would be inserted; do not write to the database.}';

    protected $description = 'Backfill performed_by on Production activities from creator events and inverse has_creator/has_accumulator relations';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->line($dryRun ? '<comment>DRY RUN — no rows will be written.</comment>' : '<info>LIVE RUN — relation rows will be inserted.</info>');
        $this->newLine();

        // Discover backfill candidates. Both queries return rows shaped
        // (activity_id, actor_id, source) where source ∈ {creator-event, inverse-relation}.
        $candidates = collect();

        // (A) creator-event path
        $creatorRows = DB::select(<<<SQL
            SELECT DISTINCT a.id AS activity_id, ev.actor_id AS actor_id, 'creator-event' AS source
            FROM ric_activity a
            JOIN relation rel_rf ON rel_rf.subject_id = a.id
            JOIN ric_relation_meta rm_rf ON rm_rf.relation_id = rel_rf.id AND rm_rf.dropdown_code = 'results_from'
            JOIN information_object io ON io.id = rel_rf.object_id
            JOIN event ev ON ev.object_id = io.id AND ev.type_id = 111 AND ev.actor_id IS NOT NULL
            WHERE LOWER(a.type_id) = 'production'
              AND NOT EXISTS (
                SELECT 1 FROM relation r2
                JOIN ric_relation_meta rm2 ON rm2.relation_id = r2.id
                WHERE r2.subject_id = a.id AND rm2.dropdown_code = 'performed_by'
              )
        SQL);
        foreach ($creatorRows as $r) {
            $candidates->push($r);
        }

        // (B) inverse-relation path
        $inverseRows = DB::select(<<<SQL
            SELECT DISTINCT r.object_id AS activity_id, r.subject_id AS actor_id, 'inverse-relation' AS source
            FROM ric_activity a
            JOIN relation r ON r.object_id = a.id
            JOIN ric_relation_meta rm ON rm.relation_id = r.id AND rm.dropdown_code IN ('has_creator','has_accumulator')
            WHERE LOWER(a.type_id) = 'production'
              AND NOT EXISTS (
                SELECT 1 FROM relation r2
                JOIN ric_relation_meta rm2 ON rm2.relation_id = r2.id
                WHERE r2.subject_id = a.id AND rm2.dropdown_code = 'performed_by'
              )
        SQL);
        foreach ($inverseRows as $r) {
            $candidates->push($r);
        }

        // Deduplicate on (activity_id, actor_id) pairs — same actor could be
        // claimed via both paths; we only want one performed_by row per pair.
        $candidates = $candidates->unique(fn($r) => $r->activity_id . ':' . $r->actor_id)->values();

        if ($candidates->isEmpty()) {
            $this->info('No backfill candidates — nothing to do.');
            return self::SUCCESS;
        }

        $this->table(
            ['activity_id', 'actor_id', 'source'],
            $candidates->map(fn($r) => [$r->activity_id, $r->actor_id, $r->source])->toArray()
        );
        $this->info("Total: {$candidates->count()} relation rows to insert.");

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run complete. Re-run without --dry-run to write.');
            return self::SUCCESS;
        }

        // Live insert. Wrap in a single transaction so a mid-loop failure
        // rolls everything back.
        $inserted = 0;
        DB::transaction(function () use ($candidates, &$inserted) {
            foreach ($candidates as $r) {
                // Idempotency guard inside the transaction — re-check that
                // no performed_by relation already exists for this exact
                // (activity, actor) pair. Defends against a concurrent
                // archivist-driven insert between dry-run and live-run.
                $exists = DB::table('relation as rel')
                    ->join('ric_relation_meta as rm', 'rel.id', '=', 'rm.relation_id')
                    ->where('rel.subject_id', $r->activity_id)
                    ->where('rel.object_id', $r->actor_id)
                    ->where('rm.dropdown_code', 'performed_by')
                    ->exists();
                if ($exists) {
                    continue;
                }

                // Insert into the CTI parent table (object) first to mint
                // the shared id. AtoM convention: every relation is a
                // QubitRelation object.
                $objectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitRelation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Child rows reuse the same id.
                DB::table('relation')->insert([
                    'id'             => $objectId,
                    'subject_id'     => $r->activity_id,
                    'object_id'      => $r->actor_id,
                    'type_id'        => null,
                    'start_date'     => null,
                    'end_date'       => null,
                    'source_culture' => 'en',
                ]);

                DB::table('ric_relation_meta')->insert([
                    'relation_id'       => $objectId,
                    'rico_predicate'    => 'rico:isOrWasPerformedBy',
                    'inverse_predicate' => 'rico:performsOrPerformed',
                    'domain_class'      => 'Activity',
                    'range_class'       => 'Agent',
                    'dropdown_code'     => 'performed_by',
                    'certainty'         => 'derived',
                    'evidence'          => 'Backfilled 2026-05-25 from ' . $r->source
                        . '; see project_production_activity_gap memory for the audit.',
                ]);

                $inserted++;
            }
        });

        $this->newLine();
        $this->info("Inserted {$inserted} performed_by relation row(s).");
        $this->line('Each new row has certainty=derived + an evidence note pointing to the 2026-05-25 audit.');

        return self::SUCCESS;
    }
}
