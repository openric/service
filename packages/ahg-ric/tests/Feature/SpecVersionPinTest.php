<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Each release is pinned to the specification version it was verified against.
 * The service description declares that version (openric_conformance.spec_version,
 * hand-set in routes/api.php), and the newest CHANGELOG entry that says "verified
 * against spec vX.Y.Z" must name the same one, so the two cannot drift apart.
 */

namespace Tests\Feature;

use Tests\TestCase;

class SpecVersionPinTest extends TestCase
{
    public function test_the_declared_spec_version_is_the_one_the_changelog_says_was_verified(): void
    {
        $declared = $this->getJson('/api/ric/v1/')->assertOk()->json('openric_conformance.spec_version');

        $changelog = (string) file_get_contents(base_path('CHANGELOG.md'));
        $this->assertSame(1, preg_match('/verified against spec v(\d+\.\d+\.\d+)/i', $changelog, $m),
            'no CHANGELOG entry says which spec version it was verified against');

        $this->assertSame($declared, $m[1], 'the newest "verified against spec" entry and the declared spec_version differ');
    }
}
