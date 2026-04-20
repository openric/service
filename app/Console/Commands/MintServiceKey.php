<?php

/**
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mint a service-to-service API key for Heratio (or any other client)
 * to call this RiC service. Prints the raw key ONCE — not recoverable
 * after that, so copy it into Heratio's .env immediately.
 *
 * Usage:
 *   php artisan ric:mint-service-key --owner=<user_id> [--name="..."]
 *                                     [--scopes=read,write,delete]
 */
class MintServiceKey extends Command
{
    protected $signature = 'ric:mint-service-key
                            {--owner= : user.id of the row to attribute the key to (required)}
                            {--name=heratio → openric-service : human-readable label}
                            {--scopes=read,write,delete : comma-separated scope list}
                            {--expires= : YYYY-MM-DD expiry date, or empty for none}';

    protected $description = 'Mint a service-to-service API key and print the raw secret once.';

    public function handle(): int
    {
        $ownerId = (int) $this->option('owner');
        if (!$ownerId) {
            $this->error('--owner=<user_id> is required. Pick any admin user: `mysql heratio -sNe "SELECT id,username FROM user WHERE username=\'johanpiet\'"`');
            return self::FAILURE;
        }

        $ownerExists = DB::table('user')->where('id', $ownerId)->exists();
        if (!$ownerExists) {
            $this->error("user.id={$ownerId} does not exist.");
            return self::FAILURE;
        }

        $name = (string) $this->option('name');
        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('scopes')))));
        if (!$scopes) {
            $this->error('Need at least one scope (read/write/delete).');
            return self::FAILURE;
        }

        $expires = $this->option('expires');
        $expiresAt = $expires ? date('Y-m-d 00:00:00', strtotime($expires)) : null;

        $rawKey = bin2hex(random_bytes(32));               // 64-char hex
        $hashed = hash('sha256', $rawKey);
        $prefix = substr($rawKey, 0, 8);

        $id = DB::table('ahg_api_key')->insertGetId([
            'user_id' => $ownerId,
            'name' => $name,
            'api_key' => $hashed,
            'api_key_prefix' => $prefix,
            'scopes' => json_encode($scopes),
            'rate_limit' => 10000,
            'expires_at' => $expiresAt,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("Key minted (row id={$id}, prefix={$prefix}, scopes=" . implode(',', $scopes) . ").");
        $this->line('');
        $this->line('Copy the following into Heratio\'s .env (and any other client):');
        $this->line('');
        $this->line('    RIC_SERVICE_API_KEY=' . $rawKey);
        $this->line('');
        $this->warn('This is the LAST time the raw key is shown. Store it somewhere safe.');
        $this->warn('To revoke later: UPDATE ahg_api_key SET is_active=0 WHERE id=' . $id . ';');
        return self::SUCCESS;
    }
}
