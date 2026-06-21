<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * AskController — the public "Ask a question" contact form.
 *
 * POST /api/ric/v1/ask  { "body": "...", "email": "you@x.org" (optional), "page": "/help/" }
 *
 * Emails the question to the maintainer (OPENRIC_ASK_TO) via the system mailer
 * and stores it in openric_question so nothing is lost. The reply-to email is
 * OPTIONAL and volunteered by the asker. A honeypot field ("website") plus tight
 * throttling keep the bots out. Never throws on a mail failure — the question is
 * still stored.
 */

namespace AhgRic\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AskController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        // Honeypot — real users never fill a hidden "website" field. Pretend success.
        if (trim((string) $request->input('website', '')) !== '') {
            return response()->json(['ok' => true], 200);
        }

        $validated = $request->validate([
            'body'  => ['required', 'string', 'min:8', 'max:4000'],
            'email' => ['nullable', 'email', 'max:190'],
            'page'  => ['nullable', 'string', 'max:255'],
        ]);

        $body  = trim($validated['body']);
        $email = isset($validated['email']) ? trim($validated['email']) : null;
        $page  = isset($validated['page']) ? trim($validated['page']) : null;

        $id = DB::table('openric_question')->insertGetId([
            'body'          => $body,
            'contact_email' => $email,
            'page'          => $page,
            'emailed'       => false,
            'created_at'    => now(),
        ]);

        $emailed = $this->notify($id, $body, $email, $page);
        if ($emailed) {
            DB::table('openric_question')->where('id', $id)->update(['emailed' => true]);
        }

        return response()->json(['ok' => true], 200);
    }

    private function notify(int $id, string $body, ?string $email, ?string $page): bool
    {
        $to = (string) env('OPENRIC_ASK_TO', config('mail.from.address'));
        if ($to === '') {
            return false;
        }

        try {
            $text = "A question was submitted on openric.org\n\n"
                  . "Question:\n{$body}\n\n"
                  . 'Reply-to: ' . ($email ?: '(not supplied)') . "\n"
                  . 'From page: ' . ($page ?: '(unknown)') . "\n"
                  . "Ref: openric_question #{$id}\n";

            Mail::raw($text, function ($m) use ($to, $email) {
                $m->to($to)->subject('OpenRiC question from openric.org');
                if ($email) {
                    $m->replyTo($email);
                }
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('[openric/ask] mail send failed: ' . $e->getMessage());
            return false;
        }
    }
}
