<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Hand a WhatsApp message to the estate's shared sender.
 *
 * OpenRiC holds no WhatsApp credential. It writes a JSON file into the spool
 * at /var/spool/ahg-whatsapp and ahg-whatsapp-watcher, running as root with
 * the token, does the sending. The file is written under a dot-name and
 * renamed into place, because the watcher skips dot-files and so never reads a
 * half-written payload.
 *
 * Deliberately smaller than CallHub's equivalent: OpenRiC has exactly one
 * recipient (the maintainer) and one notification, so there is no recipient
 * model, no per-type template map and no number normalisation beyond a digit
 * strip. Grow it when there is a second caller, not before.
 *
 * TEMPLATE ONLY. Free text sends only within 24 hours of an inbound message,
 * and a notification firing at 03:00 cannot assume the maintainer wrote to the
 * number that day.
 */

namespace AhgRic\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppNotifier
{
    /**
     * Longest question text passed as a template parameter.
     *
     * Meta measures the 1024-character body limit AFTER substitution, and this
     * is the only unbounded field, so it is the one that needs a ceiling.
     */
    private const MAX_QUESTION = 300;

    public static function enabled(): bool
    {
        return (bool) config('ahg-ric.whatsapp.enabled')
            && filled(config('ahg-ric.whatsapp.to'))
            && filled(config('ahg-ric.whatsapp.template'));
    }

    /**
     * Collapse a template parameter to something Meta will accept.
     *
     * A parameter value may not contain a newline, a tab, or four or more
     * consecutive spaces. Meta rejects the SEND, not the template, so an
     * unsanitised value passes authoring and then fails on live traffic. The
     * Ask form is a public textarea, so its content will contain newlines.
     */
    public static function param(?string $value, string $fallback, int $limit = 0): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        if ($clean === '') {
            return $fallback;
        }

        return $limit > 0 ? mb_strimwidth($clean, 0, $limit, '...') : $clean;
    }

    /**
     * Queue the "a question was asked" notification.
     *
     * Returns false when nothing was queued - the feature is off, or the write
     * failed. Never throws: a notification channel must not be able to fail
     * the request that triggered it.
     */
    public static function askReceived(int $id, string $body, ?string $email, ?string $page): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $payload = [
            'to'       => preg_replace('/\D+/', '', (string) config('ahg-ric.whatsapp.to')),
            'template' => (string) config('ahg-ric.whatsapp.template'),
            'language' => (string) config('ahg-ric.whatsapp.language', 'en'),
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => self::param($body, '(empty)', self::MAX_QUESTION)],
                        ['type' => 'text', 'text' => self::param($page, '(unknown)')],
                        ['type' => 'text', 'text' => self::param($email, '(none supplied)')],
                        ['type' => 'text', 'text' => now()->format('Y-m-d H:i T')],
                    ],
                ],
            ],
            // The watcher reads this. 'transactional' for every template send.
            'purpose' => 'transactional',
            'source'  => 'openric',
            'ref'     => ['system' => 'openric', 'id' => 'openric_question#' . $id],
        ];

        return self::spool($payload, $id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function spool(array $payload, int $id): bool
    {
        $dir = rtrim((string) config('ahg-ric.whatsapp.spool', '/var/spool/ahg-whatsapp'), '/');
        $name = 'openric-ask-' . $id . '-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';

        $temporary = $dir . '/.' . $name;

        try {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json === false || file_put_contents($temporary, $json) === false) {
                throw new \RuntimeException('could not write to the WhatsApp spool');
            }

            if (! rename($temporary, $dir . '/' . $name)) {
                @unlink($temporary);

                throw new \RuntimeException('could not move the payload into the WhatsApp spool');
            }

            return true;
        } catch (Throwable $e) {
            // Logged, not swallowed: a channel that quietly stops sending is
            // the failure this estate keeps finding. The recipient number is
            // left out - the log is not the place for it.
            Log::warning('[openric/ask] whatsapp not queued for question ' . $id . ' - ' . $e->getMessage());

            return false;
        }
    }
}
