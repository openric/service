<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * List API keys, or revoke one. Revocation sets is_active = 0, which
 * ApiAuthenticate already refuses on every request. Key material is never
 * shown: only the id, name, prefix, scopes and dates.
 *
 *   php artisan openric:revoke-key --list
 *   php artisan openric:revoke-key <key-id> --reason="exposed in a browser"
 */

namespace AhgRic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevokeKey extends Command
{
    protected $signature = 'openric:revoke-key
        {id? : The ahg_api_key id to revoke}
        {--list : List keys (no key material) instead of revoking}
        {--reason= : Why the key is being revoked; logged}';

    protected $description = 'List API keys, or revoke one by id.';

    public function handle(): int
    {
        if ($this->option('list') || $this->argument('id') === null) {
            $rows = DB::table('ahg_api_key')
                ->orderBy('id')
                ->get(['id', 'name', 'api_key_prefix', 'scopes', 'is_active', 'expires_at', 'last_used_at']);
            $this->table(
                ['id', 'name', 'prefix', 'scopes', 'active', 'expires', 'last used'],
                $rows->map(fn ($r) => [
                    $r->id, $r->name, $r->api_key_prefix,
                    implode(',', json_decode($r->scopes ?? '[]', true) ?: []),
                    $r->is_active ? 'yes' : 'no', $r->expires_at ?? '-', $r->last_used_at ?? 'never',
                ])->all()
            );
            return self::SUCCESS;
        }

        $id = (int) $this->argument('id');
        $key = DB::table('ahg_api_key')->where('id', $id)->first(['id', 'name', 'is_active']);
        if (!$key) {
            $this->error("No API key with id {$id}.");
            return self::FAILURE;
        }
        if (!$key->is_active) {
            $this->info("Key {$id} ({$key->name}) is already revoked.");
            return self::SUCCESS;
        }

        DB::table('ahg_api_key')->where('id', $id)->update(['is_active' => 0]);
        $reason = (string) $this->option('reason');
        Log::warning('openric: API key revoked', ['key_id' => $id, 'name' => $key->name, 'reason' => $reason]);
        $this->info("Revoked key {$id} ({$key->name})." . ($reason !== '' ? " Reason: {$reason}" : ''));
        return self::SUCCESS;
    }
}
