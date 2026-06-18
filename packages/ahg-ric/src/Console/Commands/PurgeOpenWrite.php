<?php

/**
 * PurgeOpenWrite — tear down entities created through the OPENRIC_OPEN_WRITE window.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL 3.0.
 *
 * Reads the openric_open_write inventory (populated by the open-write bypass in
 * ApiAuthenticate) and deletes each logged entity, then clears its inventory row.
 *
 * Usage:
 *   php artisan openric:purge-open-write                 # purge everything
 *   php artisan openric:purge-open-write --older-than=7  # only entries > 7 days old
 *   php artisan openric:purge-open-write --dry-run       # show what would be deleted
 */

namespace AhgRic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AhgRic\Services\RicEntityService;

class PurgeOpenWrite extends Command
{
    protected $signature = 'openric:purge-open-write {--older-than=0 : Only purge entries older than N days (0 = all)} {--dry-run : List what would be deleted without deleting}';
    protected $description = 'Delete entities created through the OPENRIC_OPEN_WRITE window (the openric_open_write inventory).';

    public function handle(): int
    {
        if (!DB::getSchemaBuilder()->hasTable('openric_open_write')) {
            $this->error('openric_open_write table not found — run `php artisan migrate` first.');
            return self::FAILURE;
        }

        $days = (int) $this->option('older-than');
        $dry  = (bool) $this->option('dry-run');

        $query = DB::table('openric_open_write')->orderBy('id');
        if ($days > 0) {
            $query->where('created_at', '<', now()->subDays($days));
        }
        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to purge.');
            return self::SUCCESS;
        }

        $this->warn(count($rows) . ' open-write entit' . (count($rows) === 1 ? 'y' : 'ies') . ($dry ? ' would be deleted (dry run):' : ' queued for deletion:'));

        $svc = new RicEntityService('en');
        $ok = 0; $fail = 0;

        foreach ($rows as $r) {
            if ($dry) {
                $this->line("  would delete {$r->entity_type} #{$r->entity_id} (ip {$r->ip}, {$r->created_at})");
                continue;
            }
            try {
                $this->deleteByType($svc, $r->entity_type, (int) $r->entity_id);
                DB::table('openric_open_write')->where('id', $r->id)->delete();
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $this->error("  #{$r->entity_id} ({$r->entity_type}): " . $e->getMessage());
            }
        }

        if (!$dry) {
            $this->info("Deleted {$ok}; failed {$fail}.");
        }
        return self::SUCCESS;
    }

    private function deleteByType(RicEntityService $svc, string $type, int $id): void
    {
        switch ($type) {
            case 'records':
            case 'record-parts':
            case 'record-sets':   $svc->deleteRecord($id); break;
            case 'agents':        $svc->deleteAgent($id); break;
            case 'repositories':  $svc->deleteRepository($id); break;
            case 'functions':     $svc->deleteFunction($id); break;
            case 'relations':     $svc->deleteRelation($id); break;
            case 'places':        $svc->deletePlace($id); break;
            case 'rules':         $svc->deleteRule($id); break;
            case 'activities':    $svc->deleteActivity($id); break;
            case 'instantiations':$svc->deleteInstantiation($id); break;
            default: throw new \RuntimeException("unknown entity_type '{$type}' — delete manually");
        }
    }
}
