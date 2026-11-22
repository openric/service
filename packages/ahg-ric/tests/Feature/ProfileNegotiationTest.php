<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace AhgRic\Tests\Feature;

use AhgRic\Http\Middleware\ProfileNegotiation;
use Tests\TestCase;

/** Content negotiation by profile (openric/service#13). */
class ProfileNegotiationTest extends TestCase
{
    public function test_default_answer_names_the_openric_profile(): void
    {
        $r = $this->get('/api/ric/v1/health');
        $r->assertStatus(200);
        $r->assertHeader('Content-Profile', '<' . ProfileNegotiation::OPENRIC . '>');
        $this->assertStringContainsString('<' . ProfileNegotiation::OPENRIC . '>; rel="profile"', $r->headers->get('Link'));
        $this->assertStringContainsString('_profile=rico', $r->headers->get('Link'));
        $this->assertStringContainsString('Accept-Profile', $r->headers->get('Vary'));
    }

    public function test_accept_profile_selects_plain_rico_by_q(): void
    {
        $r = $this->get('/api/ric/v1/health', ['Accept-Profile' => '<' . ProfileNegotiation::OPENRIC . '>;q=0.5, <' . ProfileNegotiation::RICO . '>;q=0.9']);
        $r->assertHeader('Content-Profile', '<' . ProfileNegotiation::RICO . '>');
    }

    public function test_unknown_profile_falls_back_to_the_default(): void
    {
        $r = $this->get('/api/ric/v1/health', ['Accept-Profile' => '<https://example.org/nope>']);
        $r->assertHeader('Content-Profile', '<' . ProfileNegotiation::OPENRIC . '>');
    }

    public function test_alt_lists_profiles_with_their_shapes_and_guidance(): void
    {
        $r = $this->getJson('/api/ric/v1/health?_profile=alt');
        $r->assertStatus(200);
        $tokens = array_column($r->json('profiles'), 'token');
        $this->assertSame(['openric', 'rico'], $tokens);
        $this->assertSame('https://openric.org/shapes/openric.shacl.ttl', $r->json('profiles.0.shapes'));
        $this->assertTrue($r->json('profiles.0.default'));
    }

    public function test_qsa_accepts_a_token_or_a_uri(): void
    {
        $m = new ProfileNegotiation();
        $this->assertSame('rico', $m->choose('rico', ''));
        $this->assertSame('rico', $m->choose(ProfileNegotiation::RICO . '#', ''));
        $this->assertSame('openric', $m->choose('', ''));
        $this->assertSame('rico', $m->choose('rico', '<' . ProfileNegotiation::OPENRIC . '>'), 'the query parameter wins over the header');
    }

    public function test_plain_rico_drops_every_extension_term_and_nothing_else(): void
    {
        $in = [
            '@context' => ['rico' => 'https://www.ica.org/standards/RiC/ontology#', 'openricx' => 'https://openric.org/ns/ext/v1#'],
            '@type' => 'openricx:RecordList',
            'items' => [[
                '@id' => 'r1', '@type' => ['rico:Record', 'openricx:Something'],
                'rico:title' => 'Minutes', 'openricx:relationHasLanguage' => ['@id' => 'zul'],
                'rico:isOrWasIncludedIn' => ['@id' => 'f1', 'openricx:hasCarrier' => 'x'],
            ]],
        ];
        $out = ProfileNegotiation::stripExtension($in);
        $this->assertSame(['rico' => 'https://www.ica.org/standards/RiC/ontology#'], $out['@context']);
        $this->assertArrayNotHasKey('@type', $out, 'a node typed only with an extension class loses the @type');
        $this->assertSame(['rico:Record'], $out['items'][0]['@type']);
        $this->assertSame('Minutes', $out['items'][0]['rico:title']);
        $this->assertArrayNotHasKey('openricx:relationHasLanguage', $out['items'][0]);
        $this->assertSame(['@id' => 'f1'], $out['items'][0]['rico:isOrWasIncludedIn']);
        $this->assertStringNotContainsString('openricx', json_encode($out));
    }
}
