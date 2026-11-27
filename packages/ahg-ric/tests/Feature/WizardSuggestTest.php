<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * POST /api/ric/v1/wizard/suggest, the AI-assisted wizard (AHG-AIIA-004, 27 Nov 2026): inert until configured,
 * contact details masked before the gateway call, an invalid model refused, a good one marked generated, and a
 * daily cap for the whole server.
 */

namespace Tests\Feature;

use AhgRic\Http\Controllers\WizardSuggestController;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class WizardSuggestTest extends TestCase
{
    private const URL = '/api/ric/v1/wizard/suggest';

    private const DESC = 'A parish baptism register, 1881-1899, kept by the Revd A. Smith (a.smith@example.org, 082 555 1234).';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        RateLimiter::clear('openric-wizard-suggest:'.now()->format('Y-m-d'));
    }

    protected function tearDown(): void
    {
        foreach (['OPENRIC_AI_URL', 'OPENRIC_AI_KEY', 'OPENRIC_AI_MODEL', 'OPENRIC_AI_DAILY_CAP'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        parent::tearDown();
    }

    private function configure(array $extra = []): void
    {
        foreach ($extra + ['OPENRIC_AI_URL' => 'https://gateway.test/ai/v1/chat/completions', 'OPENRIC_AI_KEY' => 'k', 'OPENRIC_AI_MODEL' => 'm'] as $k => $v) {
            putenv("$k=$v");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
    }

    private function scenario(string $entity = 'RiC-E04'): array
    {
        return ['choices' => [['message' => ['content' => json_encode([
            'id' => 'ai-suggestion', 'title' => 'Register', 'intro' => 'i', 'question' => 'q',
            'steps' => [['id' => 'a', 'prompt' => 'What is the register?', 'choices' => [
                ['label' => 'Record', 'entity' => $entity, 'correct' => true, 'why' => 'w'],
                ['label' => 'Place', 'entity' => 'RiC-E22', 'correct' => false, 'why' => 'w'],
            ]]],
            'outcome' => ['verdict' => 'v'],
        ])]]]];
    }

    public function test_it_is_inert_until_configured(): void
    {
        $this->configure(['OPENRIC_AI_KEY' => '']);
        Http::fake();
        $this->postJson(self::URL, ['description' => self::DESC])->assertStatus(503)->assertJsonPath('error', 'not_configured');
        Http::assertNothingSent();
    }

    public function test_a_valid_suggestion_is_marked_generated_and_contact_details_never_reach_the_gateway(): void
    {
        $this->configure();
        Http::fake(['gateway.test/*' => Http::response($this->scenario())]);

        $this->postJson(self::URL, ['description' => self::DESC])->assertOk()->assertJsonPath('generated', true);

        Http::assertSent(function (ClientRequest $r) {
            $prompt = $r['messages'][1]['content'];

            return str_contains($prompt, '1881-1899') && str_contains($prompt, '[email]') && str_contains($prompt, '[number]')
                && ! str_contains($prompt, 'a.smith@example.org') && ! str_contains($prompt, '555');
        });
    }

    public function test_mask_keeps_dates_and_masks_email_phone_and_id_numbers(): void
    {
        $this->assertSame('1881-1899, 12 May 1901, ref 2024/117', WizardSuggestController::mask('1881-1899, 12 May 1901, ref 2024/117'));
        $this->assertSame('[email] or [number] or [number]', WizardSuggestController::mask('x.y@z.co.za or +27 (82) 555-1234 or 8001015009087'));
    }

    public function test_an_invalid_model_is_refused(): void
    {
        $this->configure();
        Http::fake(['gateway.test/*' => Http::response($this->scenario('RiC-E99'))]);
        $this->postJson(self::URL, ['description' => self::DESC])->assertStatus(422)->assertJsonPath('error', 'invalid_model');
    }

    public function test_the_daily_cap_stops_model_calls(): void
    {
        $this->configure(['OPENRIC_AI_DAILY_CAP' => '2']);
        Http::fake(['gateway.test/*' => Http::response($this->scenario())]);

        $this->postJson(self::URL, ['description' => self::DESC])->assertOk();
        $this->postJson(self::URL, ['description' => self::DESC])->assertOk();
        $this->postJson(self::URL, ['description' => self::DESC])->assertStatus(429)->assertJsonPath('error', 'daily_cap');
        Http::assertSentCount(2);
    }
}
