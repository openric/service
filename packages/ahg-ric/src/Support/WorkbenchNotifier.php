<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Raise a notification in the AHG Workbench bell.
 *
 * Any process on this host can surface a notification by dropping a JSON file
 * into /var/spool/workbench/notifications. A watcher sweeps every 15 seconds,
 * moving ingested files to archive/ and malformed ones to failed/.
 *
 * This is the canonical internal surface for "tell the maintainer something".
 * It is preferred over WhatsApp for anything that is not time-critical and not
 * needed away from a desk: no Meta template to get approved, no category
 * classifier to satisfy, no consent register, and no exposure for a sender
 * number shared with five other systems.
 *
 * Contrast AhgRic\Services\WhatsAppNotifier, which is the same spool-and-rename
 * shape pointed at a different watcher, for the one notification that does need
 * to reach a phone.
 */

namespace AhgRic\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class WorkbenchNotifier
{
    private const DEFAULT_INBOX = '/var/spool/workbench/notifications';

    /**
     * Drop a notification for one workbench user.
     *
     * Returns false when nothing was written. Never throws: a notification
     * channel must not be able to fail whatever triggered it.
     */
    public static function notify(
        string $title,
        string $message,
        ?string $webLink = null,
        string $eventType = 'info',
        ?string $username = null,
    ): bool {
        $payload = array_filter([
            'username'  => $username ?: (string) config('ahg-ric.workbench.notify_user', 'johan'),
            'title'     => $title,
            'message'   => $message,
            'eventType' => $eventType,
            'webLink'   => $webLink,
            'source'    => 'openric',
        ], static fn ($value) => $value !== null && $value !== '');

        return self::spool($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function spool(array $payload): bool
    {
        $dir = rtrim(
            (string) (env('WORKBENCH_NOTIFICATIONS_INBOX')
                ?: config('ahg-ric.workbench.inbox', self::DEFAULT_INBOX)),
            '/'
        );

        $name = 'openric-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
        $temporary = $dir . '/.' . $name;

        try {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json === false || file_put_contents($temporary, $json) === false) {
                throw new \RuntimeException('could not write to the notification inbox');
            }

            // Rename into place so the watcher never reads a half-written file.
            if (! rename($temporary, $dir . '/' . $name)) {
                @unlink($temporary);

                throw new \RuntimeException('could not move the payload into the notification inbox');
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('[openric] workbench notification not queued - ' . $e->getMessage());

            return false;
        }
    }
}
