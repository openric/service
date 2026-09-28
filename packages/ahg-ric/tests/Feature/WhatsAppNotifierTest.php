<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Coverage for the Ask-form WhatsApp notification.
 *
 * The parameter sanitiser is the reason this file exists. Meta rejects a
 * template SEND whose parameter value contains a newline, a tab, or four or
 * more consecutive spaces - the template itself stays valid, so the failure
 * appears on live traffic rather than at authoring time. The Ask form is a
 * public textarea, so unsanitised input will contain newlines.
 */

namespace Tests\Feature;

use AhgRic\Services\WhatsAppNotifier;
use Tests\TestCase;

class WhatsAppNotifierTest extends TestCase
{
    private string $spool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spool = sys_get_temp_dir() . '/openric-wa-test-' . bin2hex(random_bytes(4));
        mkdir($this->spool);

        config([
            'ahg-ric.whatsapp.enabled'  => true,
            'ahg-ric.whatsapp.to'       => '27999999999',
            'ahg-ric.whatsapp.spool'    => $this->spool,
            'ahg-ric.whatsapp.template' => 'openric_ask_received',
            'ahg-ric.whatsapp.language' => 'en',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->spool . '/{,.}*.json', GLOB_BRACE) ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->spool);

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function queuedPayload(): array
    {
        $files = glob($this->spool . '/*.json') ?: [];
        $this->assertCount(1, $files, 'exactly one payload should be queued');

        // The watcher skips dot-files, so a queued payload must NOT be one.
        $this->assertStringNotContainsString('/.', $files[0], 'payload was left dot-prefixed');

        return json_decode((string) file_get_contents($files[0]), true);
    }

    public function test_it_queues_a_template_payload_the_watcher_can_read(): void
    {
        $this->assertTrue(
            WhatsAppNotifier::askReceived(42, 'Does the API support OAI-PMH sets?', 'a@example.org', '/help/')
        );

        $payload = $this->queuedPayload();

        $this->assertSame('27999999999', $payload['to']);
        $this->assertSame('openric_ask_received', $payload['template']);
        $this->assertSame('en', $payload['language']);
        $this->assertSame('transactional', $payload['purpose']);

        $parameters = $payload['components'][0]['parameters'];
        $this->assertCount(4, $parameters, 'the template takes exactly four parameters');
        $this->assertSame('Does the API support OAI-PMH sets?', $parameters[0]['text']);
        $this->assertSame('/help/', $parameters[1]['text']);
        $this->assertSame('a@example.org', $parameters[2]['text']);

        // Positional, not named: the watcher sends no parameter_name, so a
        // named-variable template would fail every send.
        foreach ($parameters as $parameter) {
            $this->assertSame('text', $parameter['type']);
            $this->assertArrayNotHasKey('parameter_name', $parameter);
        }
    }

    public function test_no_parameter_contains_a_newline_tab_or_run_of_spaces(): void
    {
        $hostile = "Line one\nline two\r\nand\tthree    with    wide    gaps\n\n\nand trailing\n";

        WhatsAppNotifier::askReceived(1, $hostile, "  spaced\n@example.org  ", "/a\tpath\n");

        foreach ($this->queuedPayload()['components'][0]['parameters'] as $parameter) {
            $text = $parameter['text'];

            $this->assertStringNotContainsString("\n", $text);
            $this->assertStringNotContainsString("\r", $text);
            $this->assertStringNotContainsString("\t", $text);
            $this->assertDoesNotMatchRegularExpression('/ {4}/', $text);
            $this->assertNotSame('', $text, 'Meta rejects an empty parameter');
            $this->assertSame(trim($text), $text, 'leading or trailing space is wasted length');
        }
    }

    public function test_optional_fields_fall_back_rather_than_send_empty(): void
    {
        WhatsAppNotifier::askReceived(7, 'A question', null, null);

        $parameters = $this->queuedPayload()['components'][0]['parameters'];

        $this->assertSame('(unknown)', $parameters[1]['text']);
        $this->assertSame('(none supplied)', $parameters[2]['text']);
    }

    public function test_a_long_question_is_truncated_to_protect_the_body_limit(): void
    {
        // Meta measures the 1024-char body limit AFTER substitution, and the
        // question is the only unbounded field.
        WhatsAppNotifier::askReceived(9, str_repeat('long ', 400), null, null);

        $question = $this->queuedPayload()['components'][0]['parameters'][0]['text'];

        $this->assertLessThanOrEqual(300, mb_strlen($question));
    }

    public function test_it_is_off_unless_configured(): void
    {
        config(['ahg-ric.whatsapp.enabled' => false]);
        $this->assertFalse(WhatsAppNotifier::askReceived(1, 'x', null, null));

        config(['ahg-ric.whatsapp.enabled' => true, 'ahg-ric.whatsapp.to' => null]);
        $this->assertFalse(WhatsAppNotifier::askReceived(1, 'x', null, null));

        $this->assertSame([], glob($this->spool . '/*.json') ?: []);
    }

    public function test_a_failed_spool_write_does_not_throw(): void
    {
        // A notification channel must never be able to fail the request that
        // triggered it. The stored openric_question row is the durable record.
        config(['ahg-ric.whatsapp.spool' => '/nonexistent/openric-spool']);

        $this->assertFalse(WhatsAppNotifier::askReceived(1, 'A question', null, null));
    }
}
